# Lockstation_AdminReindex

Run Magento indexers from the **admin panel** instead of the command line. Replicates the behaviour of MagePal's "Reindex for Magento 2" extension.

Adds two things to **System → Tools → Index Management**:

1. **A "Reindex" mass-action option** in the existing dropdown — tick any indexers, click *Submit*, and they rebuild.
2. **A "Reindex All" button** in the top action bar — one click rebuilds every indexer in sequence.

## Features

| | |
|---|---|
| Reindex selected indexers from the admin grid | ✓ |
| Reindex every indexer with one button click | ✓ |
| Per-indexer success/failure messages with runtime in seconds | ✓ |
| Form-key + ACL protected (no CSRF, granular role permissions) | ✓ |
| No SSH / no CLI / no cron knowledge needed | ✓ |
| Works on any Magento install — no DB changes, no frontend assets | ✓ |

## Compatibility

| Platform | Status |
|---|---|
| Magento Open Source 2.4.4 – 2.4.8 | ✓ |
| Adobe Commerce 2.4.4 – 2.4.8 | ✓ |
| Hyvä-themed storefronts | ✓ (admin-only module — Hyvä doesn't touch admin) |
| PHP 8.1 / 8.2 / 8.3 / 8.4 | ✓ |

## Installation

```bash
# Option A — Composer
composer require etchflow/module-admin-reindex:^1.0
bin/magento module:enable Lockstation_AdminReindex
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush

# Option B — Manual drop-in
cp -r Lockstation_AdminReindex app/code/Lockstation/AdminReindex
bin/magento module:enable Lockstation_AdminReindex
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

No database tables, no config rows — pure admin overlay.

## Permissions

A new ACL resource appears under **Stores → Permissions → User Roles → System → Tools → Index Management**:

- `Lockstation_AdminReindex::run` — required to see the *Reindex* mass action and *Reindex All* button, and to POST to the run endpoint.

Both the visible UI and the controller enforce this resource server-side. A limited user without it sees the original stock Index Management page exactly as before.

## Usage

### Reindex selected indexers

1. **Admin → System → Tools → Index Management**
2. Tick the indexer rows you want to rebuild
3. From the **Actions** dropdown above the grid, choose **Reindex**
4. Click **Submit** → confirm the dialog
5. After ~a few seconds (or longer for big catalogs), the page reloads with green per-indexer success messages including the runtime in seconds, plus any per-indexer errors as red messages

### Reindex everything

1. Same page
2. Click the **Reindex All** button in the top action bar
3. Confirm the dialog (recommended only when you can afford the time hit — full reindex takes minutes on large catalogs)
4. Each indexer runs in sequence; the page reloads with the same per-indexer report

## How it works

1. A plugin (`Plugin/IndexerGridPlugin.php`) on `Magento\Indexer\Block\Backend\Grid::beforeToHtml()` injects the **Reindex** item into the grid's mass-action block.
2. A layout override on `indexer_indexer_list.xml` adds the **Reindex All** button into the page's action toolbar.
3. Both POST to `/admin/lockstation_admin_reindex/indexer/massReindex` — the **Reindex All** button passes `indexer_ids=__all__` as a shortcut sentinel; the mass action posts the actual selected IDs.
4. `Controller/Adminhtml/Indexer/MassReindex.php` validates the form-key + ACL, resolves each ID through `Magento\Framework\Indexer\IndexerRegistry`, and runs `$indexer->reindexAll()` for each one.
5. Success messages are surfaced through Magento's standard `MessageManager`, so admin sees the same green/red banner pattern they're used to.

The module **does not** override the Index Management grid itself, so any changes Magento ships in future updates to that grid (new columns, new default actions) carry through unchanged.

## Why Hyvä-safe

- Module has no `view/frontend/` directory at all
- Adminhtml templates only — Hyvä only re-skins the storefront
- No RequireJS, no Knockout, no jQuery added — just an admin layout XML + one PHP plugin + one PHP controller
- Uses only stable Magento core APIs (`IndexerRegistry`, `Backend\App\Action`, `MessageManager`)

## Uninstall

```bash
bin/magento module:disable Lockstation_AdminReindex
bin/magento cache:flush

# Composer:
composer remove etchflow/module-admin-reindex
# Manual:
rm -rf app/code/Lockstation/AdminReindex
bin/magento setup:upgrade
bin/magento cache:flush
```

Nothing to clean from the database — the module doesn't add tables or persist config.

## File layout

```
Lockstation_AdminReindex/
├── registration.php
├── composer.json
├── LICENSE
├── README.md
├── etc/
│   ├── module.xml
│   ├── acl.xml
│   └── adminhtml/
│       ├── routes.xml
│       └── di.xml                    # plugin registration
├── Controller/
│   └── Adminhtml/
│       └── Indexer/
│           └── MassReindex.php       # POST handler
├── Plugin/
│   └── IndexerGridPlugin.php         # adds the "Reindex" mass action
├── view/
│   └── adminhtml/
│       ├── layout/
│       │   └── indexer_indexer_list.xml      # adds the "Reindex All" button
│       └── templates/
│           └── reindex_all_button.phtml      # the button form markup
└── i18n/
    └── en_GB.csv
```

## License

MIT — see `LICENSE`.
