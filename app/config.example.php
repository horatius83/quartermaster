<?php
// Copy this file to config.php and fill in your database details.
// config.php is ignored by git so your password never lands in the repo.

return [
    // 'mysql' for Hostinger (or any MySQL/MariaDB), 'sqlite' for quick local development.
    'driver' => 'mysql',

    'mysql' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'u123456789_quartermaster',
        'username' => 'u123456789_qm',
        'password' => 'change-me',
    ],

    // Only used when driver = 'sqlite'. The file is created on first run.
    'sqlite' => [
        'path' => __DIR__ . '/../storage/quartermaster.sqlite',
    ],

    // Leave null to detect automatically. Only set this if the app lives in a sub-folder
    // and links come out wrong, e.g. '/quartermaster'.
    'base_path' => null,

    // Only behind a proxy or CDN (such as Cloudflare): the $_SERVER key holding the visitor's real address,
    // e.g. 'HTTP_CF_CONNECTING_IP' or 'HTTP_X_FORWARDED_FOR'. Wrong-password limits count per address, so
    // without this every visitor looks like the proxy. Leave null otherwise: visitors could fake the header.
    'client_ip_header' => null,

    // Set to true while developing to see PHP errors in the browser.
    'debug' => false,
];
