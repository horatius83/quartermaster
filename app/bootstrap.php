<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);

// QM_CONFIG points at another config file; the end-to-end tests use it to run against a throwaway database.
$configFile = getenv('QM_CONFIG') ?: APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing app/config.php. Copy app/config.example.php to app/config.php and fill in your database details.');
}
$GLOBALS['config'] = require $configFile;

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('UTC');

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/money.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/router.php';
require APP_DIR . '/lib/repo.php';

foreach (glob(APP_DIR . '/handlers/*.php') as $handlerFile) {
    require $handlerFile;
}

start_session();
