<?php
// Config for the end-to-end tests (selected with QM_CONFIG). Uses its own SQLite file in memory-backed
// storage, so your app/config.php and real database are never touched.

return [
    'driver' => 'sqlite',
    'sqlite' => [
        'path' => (require __DIR__ . '/db-dir.php') . '/quartermaster-e2e.sqlite',
    ],
    'base_path' => null,
    'debug' => true,
    // Lets tests act as visitors from different addresses (see the wrong-password tests).
    'client_ip_header' => 'HTTP_X_TEST_CLIENT_IP',
];
