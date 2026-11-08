<?php
// An upgraded database with a campaign but no DM, so /setup opens and the new DM takes it over. Its character holds a
// 5e item, as upgrade 006 labels everything. Run with QM_CONFIG pointing at tests/e2e/config.php, after an empty reset.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$campaign = insert("INSERT INTO campaigns (name, ruleset, ruleset_open, created_at) VALUES ('Unnamed', '5e', 1, ?)", [now()]);
$character = insert('INSERT INTO characters (campaign_id, name, created_at) VALUES (?, ?, ?)', [$campaign, 'Bryn', now()]);
$item = create_item('Old sword', (int)categories()[0]['id'], 3, 700, 'Well used.', '5e');
q('INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, ?, 1)', [$character, $item['id']]);
