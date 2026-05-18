<?php
declare(strict_types=1);

namespace Etechflow\AdminReindex\Plugin;

use Magento\Framework\AuthorizationInterface;
use Magento\Indexer\Block\Backend\Grid;

/**
 * Adds:
 *   - a "Reindex" mass-action option to the Index Management grid dropdown
 *   - (top-of-grid "Reindex All" button is handled via Controller route,
 *      surfaced as a quick link from the same mass action with id=__all__)
 *
 * Both options are hidden for admin users without the
 * Etechflow_AdminReindex::run ACL resource.
 */
class IndexerGridPlugin
{
    public function __construct(
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function beforeToHtml(Grid $subject): void
    {
        if (! $this->authorization->isAllowed('Etechflow_AdminReindex::run')) {
            return;
        }

        $massaction = $subject->getMassactionBlock();
        if (! $massaction) {
            return;
        }

        // If we've already added the item (block may render twice in some
        // admin flows), skip the second pass.
        try {
            if ($massaction->getItem('etechflow_reindex')) {
                return;
            }
        } catch (\Throwable $e) {
            // Some Magento versions throw when an item doesn't exist; ignore.
        }

        $massaction->addItem('etechflow_reindex', [
            'label'   => __('Reindex'),
            'url'     => $subject->getUrl('etechflow_admin_reindex/indexer/massReindex'),
            'confirm' => __('Are you sure you want to reindex the selected items? This can take a few minutes on large catalogs.'),
        ]);
    }
}
