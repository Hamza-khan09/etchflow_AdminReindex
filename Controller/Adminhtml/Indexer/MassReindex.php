<?php
declare(strict_types=1);

namespace Lockstation\AdminReindex\Controller\Adminhtml\Indexer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * POST: /admin/lockstation_admin_reindex/indexer/massReindex
 *
 * Receives the indexer IDs the admin selected on the Index Management grid
 * (or `indexer_ids=__all__` for the Reindex All shortcut), rebuilds each one,
 * surfaces success/failure via admin messages, and redirects back to the
 * Index Management list.
 */
class MassReindex extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Lockstation_AdminReindex::run';

    public function __construct(
        Context $context,
        private readonly IndexerRegistry          $indexerRegistry,
        private readonly IndexerCollectionFactory $indexerCollectionFactory,
        private readonly LoggerInterface          $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        if (! $this->_formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid security token. Please reload the page and try again.'));
            return $redirect->setPath('indexer/indexer/list');
        }

        $rawIds = $this->getRequest()->getParam('indexer_ids', []);
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
        }
        $rawIds = array_filter((array) $rawIds);

        if (! $rawIds) {
            $this->messageManager->addWarningMessage(__('Please select at least one indexer to reindex.'));
            return $redirect->setPath('indexer/indexer/list');
        }

        // "__all__" sentinel triggers a full reindex of every indexer.
        if (in_array('__all__', $rawIds, true)) {
            $rawIds = array_keys($this->indexerCollectionFactory->create()->getItems());
        }

        $succeeded = [];
        $failures  = [];

        foreach ($rawIds as $indexerId) {
            $indexerId = (string) $indexerId;
            try {
                $indexer = $this->indexerRegistry->get($indexerId);
                $start = microtime(true);
                $indexer->reindexAll();
                $duration = round(microtime(true) - $start, 2);
                $succeeded[] = sprintf('%s (%.2fs)', $indexer->getTitle() ?: $indexerId, $duration);
            } catch (\Throwable $e) {
                $failures[$indexerId] = $e->getMessage();
                $this->logger->error('[lockstation_admin_reindex] ' . $indexerId . ': ' . $e->getMessage());
            }
        }

        if ($succeeded) {
            $this->messageManager->addSuccessMessage(__(
                '%1 indexer(s) rebuilt successfully: %2',
                count($succeeded),
                implode(', ', $succeeded)
            ));
        }
        foreach ($failures as $id => $msg) {
            $this->messageManager->addErrorMessage(__('Failed to reindex %1: %2', $id, $msg));
        }

        return $redirect->setPath('indexer/indexer/list');
    }
}
