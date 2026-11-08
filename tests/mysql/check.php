<?php
// Checks item tags and rulesets against a real MySQL or MariaDB, which the SQLite end-to-end tests can't: that upgrades
// 005 and 006 run, that 006 finishes if interrupted, and that the tag column stores, sorts and cascades the way the app
// relies on. Run by tests/mysql/run.mjs.
declare(strict_types=1);

putenv('QM_CONFIG=' . __DIR__ . '/config.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$cfg = (require __DIR__ . '/config.php')['mysql'];

// Build the current schema, then undo upgrades 006 and 005: a database from just before them.
$raw = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../app/sql/schema.mysql.sql'));
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $raw->exec($statement);
}
$raw->exec('DROP TABLE item_tags');
$raw->exec('ALTER TABLE campaigns DROP COLUMN ruleset_open, DROP COLUMN ruleset');
$raw->exec("INSERT INTO campaigns (name, created_at) VALUES ('Older campaign', '2026-01-01 00:00:00')");
$raw->exec('ALTER TABLE items ADD UNIQUE KEY uq_items_name (name), DROP INDEX uq_items_ruleset_name, DROP COLUMN ruleset');

require __DIR__ . '/../../app/bootstrap.php'; // connecting below runs the missing upgrade

$failures = 0;
$check = function (string $what, mixed $expected, mixed $actual) use (&$failures): void {
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . ($ok ? '' : "\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual)) . "\n";
};
$tagsOf = fn(int $id) => with_item_tags([['id' => $id]])[0]['tags'];

$check('upgrade 005 created the table', 0, (int)q_val('SELECT COUNT(*) FROM item_tags'));
$indexes = fn() => array_column(q_all("SHOW INDEX FROM items WHERE Key_name LIKE 'uq_items%'"), 'Key_name');
$check('upgrade 006 made names unique per ruleset', ['uq_items_ruleset_name', 'uq_items_ruleset_name'], $indexes());
$check('upgrade 006 gave campaigns a ruleset', 'ruleset', q_one('SHOW COLUMNS FROM campaigns WHERE Field = ?', ['ruleset'])['Field'] ?? null);

// An upgrade that stopped just before its last statement: the probe fails, and the next run has to skip every
// change that's already there, including the index it already dropped, then finish.
$raw->exec('ALTER TABLE campaigns DROP COLUMN ruleset_open, DROP COLUMN ruleset');
apply_upgrades(db());
$check('an interrupted upgrade 006 is finished by the next run', 'ruleset', q_one('SHOW COLUMNS FROM campaigns WHERE Field = ?', ['ruleset'])['Field'] ?? null);
$check('finishing upgrade 006 leaves the right index', ['uq_items_ruleset_name', 'uq_items_ruleset_name'], $indexes());

$owner = create_login('checkdm', 'a-long-enough-password', 'dm', 'Test');
$check('upgrade 007 leaves an existing campaign able to set its system', 1, (int)q_val("SELECT ruleset_open FROM campaigns WHERE name = 'Older campaign'"));
$check('a new campaign can’t', 0, (int)q_val('SELECT ruleset_open FROM campaigns WHERE id = ?', [create_campaign('Newer campaign', $owner, '3.5e')]));

$itemId = (int)create_item('Test sword', (int)categories()[0]['id'], 1, 100)['id'];
$save = function (string $text) use ($itemId): array {
    $tags = parse_tags($text);
    transaction(fn() => set_item_tags($itemId, $tags));
    return $tags;
};

// utf8mb4_unicode_ci would call these the same tag, and refuse to save them together.
$tags = $save('café, cafe, straße, strasse');
$check('accented tags stay separate tags', $tags, $tagsOf($itemId));
$save('cafe');
$check('filtering by a tag doesn’t match its accented twin', [], catalog_rows('', '', ['café']));
$check('filtering by a tag finds the item', [$itemId], array_map('intval', array_column(catalog_rows('', '', ['cafe']), 'id')));
$check('filtering by two tags needs both', [], catalog_rows('', '', ['cafe', 'other']));

// The README says upgrade 005 is safe to import again by hand.
$save('kept');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../app/sql/upgrade-005-item-tags.mysql.sql'))))) as $statement) {
    $raw->exec($statement);
}
$check('importing the upgrade again keeps existing tags', ['kept'], $tagsOf($itemId));

// Saving a row compares the tags it parsed with those it read back, so PHP and the database must sort alike.
$tags = $save('9, 10, zebra, Éclair, apple, 1e1, 010');
$check('the database sorts tags the way PHP does', $tags, $tagsOf($itemId));
$check('the catalog-wide list sorts the same way', $tags, all_tags());

$longest = str_repeat('é', MAX_TAG_LENGTH);
$check('the longest allowed tag fits', [$longest], (function () use ($save, $longest, $itemId, $tagsOf) {
    $save($longest);
    return $tagsOf($itemId);
})());

$save('doomed');
q('DELETE FROM items WHERE id = ?', [$itemId]);
$check('deleting an item deletes its tags', 0, (int)q_val('SELECT COUNT(*) FROM item_tags'));

try {
    create_item('Test sword', (int)categories()[0]['id'], 1, 100);
    create_item('TEST SWORD', (int)categories()[0]['id'], 1, 100);
    $check('adding a taken name throws', true, false);
} catch (PDOException) {
    $check('adding a taken name throws', true, true);
}

create_item('Edition sword', (int)categories()[0]['id'], 1, 100, '', '3.5e');
create_item('Edition sword', (int)categories()[0]['id'], 1, 100, '', '5e');
$check('each edition can have an item of the same name', 2, (int)q_val("SELECT COUNT(*) FROM items WHERE name = 'Edition sword'"));
$check('an item is found in its own edition', '3.5e', item_by_name('EDITION SWORD', '3.5e')['ruleset'] ?? null);

// The bundled SRD items have to fit the real columns, which SQLite wouldn't enforce.
foreach (SRD_RULESETS as $ruleset) {
    $rows = srd_items($ruleset);
    $slugIds = array_column(categories(), 'id', 'slug');
    transaction(function () use ($rows, $ruleset, $slugIds) {
        foreach ($rows as [$name, $slug, $weight, $price, $tags, $description]) {
            set_item_tags((int)create_item($name, (int)$slugIds[$slug], (float)$weight, parse_price($price) ?? 0, $description, $ruleset)['id'], $tags);
        }
    });
    $check("every {$ruleset} SRD item saves", count($rows), (int)q_val('SELECT COUNT(*) FROM items WHERE ruleset = ?', [$ruleset]) - ($ruleset === '5e' ? 2 : 1));
}
$check('SRD weights keep their fractions', '0.25', rtrim((string)q_val("SELECT weight FROM items WHERE name = 'Dart' AND ruleset = '5e'"), '0'));

// Setting a campaign's system moves what it holds, using updates MySQL is fussier about than SQLite.
$older = q_one("SELECT * FROM campaigns WHERE name = 'Older campaign'");
$other = create_campaign('Other 5e campaign', $owner);
$held = create_item('Held sword', (int)categories()[0]['id'], 3, 700, 'Notched.', '5e');
set_item_tags((int)$held['id'], ['martial']);
$addHolder = function (int $campaignId) use ($held): array {
    $character = insert('INSERT INTO characters (campaign_id, name, created_at) VALUES (?, ?, ?)', [$campaignId, 'Holder', now()]);
    $store = insert('INSERT INTO stores (campaign_id, name, created_at) VALUES (?, ?, ?)', [$campaignId, 'Shop', now()]);
    $cart = insert('INSERT INTO carts (character_id, store_id, created_at) VALUES (?, ?, ?)', [$character, $store, now()]);
    q('INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, ?, 1)', [$character, $held['id']]);
    q('INSERT INTO store_stock (store_id, item_id, price_cp) VALUES (?, ?, 700)', [$store, $held['id']]);
    q('INSERT INTO cart_items (cart_id, item_id, quantity, unit_price_cp) VALUES (?, ?, 1, 700)', [$cart, $held['id']]);
    return [$character, $store, $cart];
};
[$olderCharacter, $olderStore, $olderCart] = $addHolder((int)$older['id']);
[$otherCharacter] = $addHolder($other);
create_item('Unheld lamp', (int)categories()[0]['id'], 1, 10, '', '5e');
transaction(fn() => settle_campaign_system($older, '3.5e'));
$check('only what the campaign uses is copied', null, item_by_name('Unheld lamp', '3.5e'));
$copy = item_by_name('Held sword', '3.5e');
$check('setting a system copies the item, with its details and tags', [3.0, 700, 'Notched.', ['martial']], [(float)$copy['weight'], (int)$copy['price_cp'], $copy['description'], $tagsOf((int)$copy['id'])]);
$check('its pack, shelf and cart move to the copy', array_fill(0, 3, (int)$copy['id']), [
    (int)q_val('SELECT item_id FROM inventory WHERE character_id = ?', [$olderCharacter]),
    (int)q_val('SELECT item_id FROM store_stock WHERE store_id = ?', [$olderStore]),
    (int)q_val('SELECT item_id FROM cart_items WHERE cart_id = ?', [$olderCart]),
]);
$check('another campaign keeps the original', (int)$held['id'], (int)q_val('SELECT item_id FROM inventory WHERE character_id = ?', [$otherCharacter]));
$check('the campaign is locked to the new system', ['3.5e', 0], array_values(array_map(fn($v) => is_numeric($v) ? (int)$v : $v, q_one('SELECT ruleset, ruleset_open FROM campaigns WHERE id = ?', [$older['id']]))));

$secondPage = catalog_page('', '', [], 2);
$check('a catalog page holds one page of items', [CATALOG_PAGE_SIZE, 2, CATALOG_PAGE_SIZE + 1], [count($secondPage['items']), $secondPage['page'], $secondPage['first']]);
$check('a page past the end shows the last one', $secondPage['pages'], catalog_page('', '', [], 9999)['page']);

exit($failures ? 1 : 0);
