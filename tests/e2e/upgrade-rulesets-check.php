<?php
// Builds a database as upgrade 005 left it, with an item that packs and tags point at, boots the app so upgrade 006
// rebuilds the items table, and prints what came out as JSON for rulesets.spec.ts to check.
declare(strict_types=1);

$configFile = __DIR__ . '/upgrade-config.php';
$path = (require $configFile)['sqlite']['path'];
if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, "Could not delete {$path}.\n");
    exit(1);
}

$sql = __DIR__ . '/../../app/sql/';
$old = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$old->exec(file_get_contents(__DIR__ . '/fixtures/schema-before-campaigns.sqlite.sql'));
foreach (['001-inventory-log', '002-campaigns', '003-admin', '004-sign-in', '005-item-tags'] as $upgrade) {
    $old->exec(file_get_contents("{$sql}upgrade-{$upgrade}.sqlite.sql"));
}
$old->exec(
    "INSERT INTO characters (campaign_id, name, created_at) VALUES (1, 'Bryn', '2026-01-01 00:00:00');
     INSERT INTO items (id, name, category_id, description) VALUES (7, 'Old sword', 1, 'Well used.');
     INSERT INTO items (id, name, category_id) VALUES (9, 'Gone sword', 1);
     DELETE FROM items WHERE id = 9;
     INSERT INTO item_tags (item_id, tag) VALUES (7, 'martial'), (7, 'melee');
     INSERT INTO inventory (character_id, item_id) VALUES (1, 7);"
);
$old = null;

putenv('QM_CONFIG=' . $configFile);
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$categoryId = (int)categories()[0]['id'];
$items = array_map(
    fn($i) => ['id' => (int)$i['id'], 'name' => $i['name'], 'ruleset' => $i['ruleset'], 'description' => $i['description']],
    q_all('SELECT * FROM items')
);
$foreignKeyProblems = count(q_all('PRAGMA foreign_key_check'));
$tags = array_column(q_all('SELECT tag FROM item_tags WHERE item_id = 7 ORDER BY tag'), 'tag');
$inventoryItems = array_map('intval', array_column(q_all('SELECT item_id FROM inventory'), 'item_id'));
$campaignRulesets = array_column(q_all('SELECT ruleset FROM campaigns'), 'ruleset');
$campaignsOpen = array_map('intval', array_column(q_all('SELECT ruleset_open FROM campaigns'), 'ruleset_open'));
$newCampaignOpen = (int)q_val('SELECT ruleset_open FROM campaigns WHERE id = ?', [create_campaign('New', create_login('newdm', 'a-long-enough-password', 'dm', 'Test'), '3.5e')]);

$other = create_item('Old sword', $categoryId, 1, 100, '', '3.5e');
$otherEditionAllowed = (bool)$other;
$nextItemId = (int)$other['id'];
try {
    create_item('OLD SWORD', $categoryId, 1, 100, '', '3.5e');
    $sameEditionRejected = false;
} catch (PDOException) {
    $sameEditionRejected = true;
}

echo json_encode(compact('campaignRulesets', 'campaignsOpen', 'newCampaignOpen', 'items', 'tags', 'inventoryItems', 'foreignKeyProblems', 'nextItemId', 'otherEditionAllowed', 'sameEditionRejected'));
