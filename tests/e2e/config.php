<?php
// Config for the end-to-end tests (selected with QM_CONFIG). Uses its own SQLite file in the
// system temp folder, so your app/config.php and real database are never touched.

return [
    'driver' => 'sqlite',
    'sqlite' => [
        'path' => sys_get_temp_dir() . '/quartermaster-e2e.sqlite',
    ],
    'base_path' => null,
    'debug' => true,
];
