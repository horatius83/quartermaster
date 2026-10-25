<?php
// Config for the upgrade test (tests/e2e/upgrade-check.php): a throwaway SQLite file built from an old schema.

return [
    'driver' => 'sqlite',
    'sqlite' => [
        'path' => sys_get_temp_dir() . '/quartermaster-e2e-upgrade.sqlite',
    ],
    'campaign_name' => 'The Old Campaign',
    'base_path' => null,
    'debug' => true,
];
