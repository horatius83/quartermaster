<?php
// Router for the end-to-end tests' server: scripts/dev-router.php, plus POST /__test/reset, which puts the
// caller's worker database (see test-db.php) back to the demo seed by copying the template that
// global-setup.ts seeds once per run. ?empty=1 deletes it instead, so the next request starts a fresh install.
// Copying a file here takes milliseconds; reseeding in a new PHP process took seconds per test.

$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($route === '/__test/ping') {
    // global-setup.ts checks for this, so a server reused from an older checkout is caught before any test runs.
    http_response_code(204);
    exit;
}
if ($route !== '/__test/reset') {
    return require __DIR__ . '/../../scripts/dev-router.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$path = (require __DIR__ . '/config.php')['sqlite']['path'];
$template = (require __DIR__ . '/test-db.php')('quartermaster-e2e', 'template');
foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
    if (is_file($path . $suffix) && !unlink($path . $suffix)) {
        http_response_code(500);
        exit("Could not delete {$path}{$suffix}.");
    }
}
if (empty($_GET['empty'])) {
    if (!is_file($template)) {
        http_response_code(500);
        exit("No seeded template at {$template}: tests/e2e/global-setup.ts makes it.");
    }
    if (!copy($template, "{$path}.tmp") || !rename("{$path}.tmp", $path)) {
        http_response_code(500);
        exit("Could not copy {$template} to {$path}.");
    }
}
http_response_code(204);
