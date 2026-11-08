<?php
// Makes a seeded campaign look like one from before systems, whose system can still be set once.
// Run with QM_CONFIG pointing at tests/e2e/config.php (the Playwright helpers do this).
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

q('UPDATE campaigns SET ruleset_open = 1 WHERE name = ?', [$argv[1]]);
