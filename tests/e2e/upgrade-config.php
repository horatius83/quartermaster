<?php
// Config for the upgrade test (tests/e2e/upgrade-check.php): a throwaway SQLite file built from an old schema.

return [
    'driver' => 'sqlite',
    'sqlite' => [
        'path' => (require __DIR__ . '/test-db.php')('quartermaster-e2e-upgrade'),
    ],
    'campaign_name' => 'The Old Campaign',
    'base_path' => null,
    'debug' => true,
];
