<?php
// Wipe the end-to-end test database and fill it with the demo seed.
// Run with QM_CONFIG pointing at tests/e2e/config.php (the Playwright helpers do this).
declare(strict_types=1);

$configFile = getenv('QM_CONFIG');
if (!$configFile || !str_ends_with(str_replace('\\', '/', $configFile), 'tests/e2e/config.php')) {
    fwrite(STDERR, "Refusing to reset: QM_CONFIG must point at tests/e2e/config.php.\n");
    exit(1);
}
$path = (require $configFile)['sqlite']['path'];
if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, "Could not delete {$path}.\n");
    exit(1);
}
ob_start();
require __DIR__ . '/../../scripts/seed-demo.php';
ob_end_clean();
