<?php
// Builds a database the way an install from before campaigns left it, boots the app so it upgrades
// itself, and prints what came out as JSON for upgrade.spec.ts to check.
// Usage: php tests/e2e/upgrade-check.php [statements]
//   statements = how many statements of upgrade-002 to run by hand first, as if an earlier upgrade stopped partway.
declare(strict_types=1);

$configFile = __DIR__ . '/upgrade-config.php';
$path = (require $configFile)['sqlite']['path'];
if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, "Could not delete {$path}.\n");
    exit(1);
}

$old = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$old->exec(file_get_contents(__DIR__ . '/fixtures/schema-before-campaigns.sqlite.sql'));
$old->exec(file_get_contents(__DIR__ . '/../../app/sql/upgrade-001-inventory-log.sqlite.sql'));
$old->exec(
    "INSERT INTO users (username, password_hash, role, created_at) VALUES ('dm', 'x', 'dm', '2026-01-01 00:00:00'), ('bryn', 'x', 'player', '2026-01-01 00:00:00');
     INSERT INTO characters (user_id, name, coin_cp, created_at) VALUES (2, 'Bryn', 100, '2026-01-01 00:00:00'), (NULL, 'Hireling', 0, '2026-01-01 00:00:00');
     INSERT INTO stores (name, is_open, created_at) VALUES ('Anvil', 1, '2026-01-01 00:00:00');
     INSERT INTO inventory_log (character_id, character_name, action, quantity, created_at) VALUES (1, 'Bryn', 'give', 1, '2026-01-01 00:00:00'), (NULL, 'Gone', 'give', 1, '2026-01-01 00:00:00');"
);
$upgrade = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../app/sql/upgrade-002-campaigns.sqlite.sql'));
foreach (array_slice(array_values(array_filter(array_map('trim', explode(';', $upgrade)))), 0, (int)($argv[1] ?? 0)) as $statement) {
    $old->exec($statement);
}
$old = null;

putenv('QM_CONFIG=' . $configFile);
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$campaignOf = fn(string $table) => array_map('intval', array_column(q_all("SELECT campaign_id FROM {$table} ORDER BY id"), 'campaign_id'));
echo json_encode([
    'campaigns'  => q_all('SELECT id, name FROM campaigns'),
    'characters' => $campaignOf('characters'),
    'stores'     => $campaignOf('stores'),
    'log'        => $campaignOf('inventory_log'),
]);
