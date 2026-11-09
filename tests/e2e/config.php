<?php
// Config for the end-to-end tests (selected with QM_CONFIG). Uses its own SQLite files in memory-backed
// storage, so your app/config.php and real database are never touched.

return [
    'driver' => 'sqlite',
    'sqlite' => [
        // One database per Playwright worker, so workers run side by side (see test-db.php).
        'path' => (require __DIR__ . '/test-db.php')('quartermaster-e2e'),
    ],
    'base_path' => null,
    'debug' => true,
    // Lets tests act as visitors from different addresses (see the wrong-password tests).
    'client_ip_header' => 'HTTP_X_TEST_CLIENT_IP',
    // bcrypt's lowest cost: PHP's default takes ~0.2 s per hash, and every test seeds logins and signs in.
    'password_cost' => 4,
    // Browse lists this many items at a time, so the demo's categories are long enough to show "Show more".
    'suggest_browse_max' => 4,
];
