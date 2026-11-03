<?php
// Plays one request with a hand-made session for the seeded DM (user 2), and prints as JSON who
// current_user() sees and which session keys are left, for password.spec.ts to check.
// Usage: php tests/e2e/session-check.php legacy|current|deleted
//   legacy  = a session from before session versions existed (no session_version key)
//   current = a session signed in under the DM's current session_version
//   deleted = a session whose login no longer exists
// Run with QM_CONFIG pointing at tests/e2e/config.php, on a freshly seeded database.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$_SESSION = ['user_id' => 2, 'old_input' => ['path' => '/dm/party', 'fields' => ['name' => 'Left behind'], 'at' => time()]];
if (($argv[1] ?? '') === 'current') {
    $_SESSION['session_version'] = (int)q_val('SELECT session_version FROM users WHERE id = 2');
} elseif (($argv[1] ?? '') === 'deleted') {
    $_SESSION['user_id'] = 999;
    $_SESSION['session_version'] = 0;
}
echo json_encode([
    'user'        => current_user()['username'] ?? null,
    'sessionKeys' => array_keys($_SESSION),
]);
