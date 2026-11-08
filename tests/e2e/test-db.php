<?php
// Which test database a request or script uses: one per Playwright worker, so workers never share one.
// Browsers say which in the X-QM-Test-Db header (fixtures.ts adds it); PHP scripts get QM_TEST_DB (php.ts
// sets it). Both hold the worker's TEST_PARALLEL_INDEX, or "template" for the seeded copy that resets
// start from. A request or script that doesn't say is refused rather than given some other worker's database.
// File names include the test server's port, so runs on different ports (QM_E2E_PORT) never share files.
// Returns a function from a file prefix (and optionally which copy) to that file's path.

return static function (string $prefix, ?string $which = null): string {
    $server = PHP_SAPI === 'cli-server';
    $which ??= $server ? ($_SERVER['HTTP_X_QM_TEST_DB'] ?? '') : (string)getenv('QM_TEST_DB');
    $port = $server ? (string)$_SERVER['SERVER_PORT'] : (string)getenv('QM_E2E_PORT');
    if (!preg_match('/^(\d{1,3}|template)$/', $which) || !preg_match('/^\d{1,5}$/', $port)) {
        $problem = $server
            ? 'Test requests need an X-QM-Test-Db header naming their worker (tests/e2e/fixtures.ts adds it).'
            : 'Test scripts need QM_TEST_DB and QM_E2E_PORT set (tests/e2e/php.ts sets them).';
        if ($server) {
            http_response_code(400);
            exit($problem);
        }
        fwrite(STDERR, $problem . "\n");
        exit(1);
    }
    return (require __DIR__ . '/db-dir.php') . "/{$prefix}-{$port}-{$which}.sqlite";
};
