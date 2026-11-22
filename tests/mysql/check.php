<?php
// Checks item tags, rulesets, sales and requests against a real MySQL or MariaDB, which the SQLite end-to-end tests can't: that
// upgrades 005, 006, 008, 009, 010, 011 and 012 run, that 006, 009, 011 and 012 finish if interrupted, that the tag column stores, sorts and
// cascades the way the app relies on, and that a sale settles and the party stash moves under MySQL's row locks. Run by
// tests/mysql/run.mjs.
declare(strict_types=1);

putenv('QM_CONFIG=' . __DIR__ . '/config.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$cfg = (require __DIR__ . '/config.php')['mysql'];

// Build the current schema, then undo upgrades 012, 011, 010, 009, 008, 006 and 005: a database from just before them.
$raw = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../app/sql/schema.mysql.sql'));
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $raw->exec($statement);
}
$undoStoreBias = function () use ($raw): void {
    $raw->exec('DROP TABLE store_prices');
    $raw->exec('DROP TABLE stock_viewers');
    $raw->exec('ALTER TABLE carts DROP COLUMN price_pct');
    $raw->exec('ALTER TABLE store_stock DROP COLUMN restricted');
    $raw->exec('ALTER TABLE stores DROP COLUMN restricted');
};
$undoStoreBias();
$undoPartyPurse = function () use ($raw): void {
    $raw->exec('DROP TABLE party_items');
    $raw->exec('ALTER TABLE inventory_log DROP COLUMN party');
    $raw->exec('ALTER TABLE campaigns DROP COLUMN purse_cp, DROP COLUMN shared_purse');
};
$undoPartyPurse();
$raw->exec('DROP TABLE requests');
$raw->exec('ALTER TABLE store_stock ADD UNIQUE KEY uq_stock_store_item (store_id, item_id), DROP INDEX uq_stock_store_item_notes, DROP COLUMN notes');
$raw->exec('ALTER TABLE cart_items ADD UNIQUE KEY uq_cart_item (cart_id, item_id), DROP INDEX uq_cart_item_notes, DROP COLUMN notes');
$raw->exec('DROP TABLE sales');
$raw->exec('DROP TABLE item_tags');
$raw->exec('ALTER TABLE campaigns DROP COLUMN ruleset_open, DROP COLUMN ruleset');
$raw->exec("INSERT INTO campaigns (name, created_at) VALUES ('Older campaign', '2026-01-01 00:00:00')");
// Two stores from before upgrade 012: one open to everyone, one with a list of who may shop there.
$olderCampaign = (int)$raw->lastInsertId();
$raw->exec("INSERT INTO characters (campaign_id, name, created_at) VALUES ({$olderCampaign}, 'Gate keeper', '2026-01-01 00:00:00')");
$gateKeeper = (int)$raw->lastInsertId();
$raw->exec("INSERT INTO stores (campaign_id, name, is_open, created_at) VALUES ({$olderCampaign}, 'Open shop', 1, '2026-01-01 00:00:00')");
$openShop = (int)$raw->lastInsertId();
$raw->exec("INSERT INTO stores (campaign_id, name, is_open, created_at) VALUES ({$olderCampaign}, 'Gated shop', 1, '2026-01-01 00:00:00')");
$gatedShop = (int)$raw->lastInsertId();
$raw->exec("INSERT INTO store_access (store_id, character_id) VALUES ({$gatedShop}, {$gateKeeper})");
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
$check('upgrade 008 created the sales table', 0, (int)q_val('SELECT COUNT(*) FROM sales'));
$check('upgrade 010 created the requests table', 0, (int)q_val('SELECT COUNT(*) FROM requests'));
$indexes = fn() => array_column(q_all("SHOW INDEX FROM items WHERE Key_name LIKE 'uq_items%'"), 'Key_name');
$check('upgrade 006 made names unique per ruleset', ['uq_items_ruleset_name', 'uq_items_ruleset_name'], $indexes());
$check('upgrade 006 gave campaigns a ruleset', 'ruleset', q_one('SHOW COLUMNS FROM campaigns WHERE Field = ?', ['ruleset'])['Field'] ?? null);
$uniqueOn = fn(string $table) => array_values(array_unique(array_column(q_all("SHOW INDEX FROM {$table} WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'"), 'Key_name')));
$check('upgrade 009 made shelf lines unique with their notes', ['uq_stock_store_item_notes'], $uniqueOn('store_stock'));
$check('upgrade 009 made cart lines unique with their notes', ['uq_cart_item_notes'], $uniqueOn('cart_items'));
$columnsOf = fn(string $table, array $names) => array_values(array_intersect($names, array_column(q_all("SHOW COLUMNS FROM {$table}"), 'Field')));
$check('upgrade 011 gave campaigns a party purse', ['shared_purse', 'purse_cp'], $columnsOf('campaigns', ['shared_purse', 'purse_cp']));
$check('upgrade 011 created the party stash', 0, (int)q_val('SELECT COUNT(*) FROM party_items'));
$check('upgrade 012 let shelf lines be hidden', ['restricted'], $columnsOf('store_stock', ['restricted']));
$check('upgrade 012 lets carts keep the share they were sent at', ['price_pct'], $columnsOf('carts', ['price_pct']));
$check('upgrade 012 keeps a store’s list of who may shop there', [0, 1], [
    (int)q_val('SELECT restricted FROM stores WHERE id = ?', [$openShop]),
    (int)q_val('SELECT restricted FROM stores WHERE id = ?', [$gatedShop]),
]);
$check('so the character on it can still shop there, and no one else is let in', [true, false], [
    in_array($gatedShop, array_map('intval', array_column(stores_for_character($gateKeeper), 'id')), true),
    (bool)q_val('SELECT 1 FROM stores s WHERE s.id = ? AND s.restricted = 0', [$gatedShop]),
]);
q('DELETE FROM stores WHERE id IN (?, ?)', [$openShop, $gatedShop]);
q('DELETE FROM characters WHERE id = ?', [$gateKeeper]);
$check('upgrade 012 created the price and viewer tables', [0, 0], [(int)q_val('SELECT COUNT(*) FROM store_prices'), (int)q_val('SELECT COUNT(*) FROM stock_viewers')]);

// Upgrade 012 stopped before its last table: the next run skips the column and table that are there and makes it.
$raw->exec('DROP TABLE store_prices');
apply_upgrades(db());
$check('an interrupted upgrade 012 is finished by the next run', 0, (int)q_val('SELECT COUNT(*) FROM store_prices'));

// Upgrade 011 stopped before its table: the next run skips the columns that are there and makes it. Upgrade 012 never
// ran after it, so it's undone too.
$undoStoreBias();
$raw->exec('DROP TABLE party_items');
apply_upgrades(db());
$check('an interrupted upgrade 011 is finished by the next run', 0, (int)q_val('SELECT COUNT(*) FROM party_items'));

// Upgrade 009 stopped after its first table: the next run skips that one and does the other. Upgrades 010, 011
// and 012 never ran after it, so they're undone too (the app only looks further when the newest one is missing).
$raw->exec('DROP TABLE requests');
$raw->exec('ALTER TABLE store_stock ADD UNIQUE KEY uq_stock_store_item (store_id, item_id), DROP INDEX uq_stock_store_item_notes, DROP COLUMN notes');
$undoStoreBias();
$undoPartyPurse();
apply_upgrades(db());
$check('an interrupted upgrade 009 is finished by the next run', ['uq_stock_store_item_notes'], $uniqueOn('store_stock'));
$check('finishing upgrade 009 leaves the cart lines as they were', ['uq_cart_item_notes'], $uniqueOn('cart_items'));
// Older MySQL and COMPACT tables index at most 767 bytes of a column; the notes key has to fit.
$raw->exec('ALTER TABLE store_stock ROW_FORMAT=COMPACT');
$raw->exec('ALTER TABLE cart_items ROW_FORMAT=COMPACT');
$check('the notes keys fit a COMPACT table', ['uq_stock_store_item_notes'], $uniqueOn('store_stock'));

// An upgrade that stopped just before its last statement: the probe fails, and the next run has to skip every
// change that's already there, including the index it already dropped, then finish. The upgrades after it never
// ran, so they're undone too (the app only looks further when the newest one is missing).
$raw->exec('ALTER TABLE store_stock ADD UNIQUE KEY uq_stock_store_item (store_id, item_id), DROP INDEX uq_stock_store_item_notes, DROP COLUMN notes');
$raw->exec('ALTER TABLE cart_items ADD UNIQUE KEY uq_cart_item (cart_id, item_id), DROP INDEX uq_cart_item_notes, DROP COLUMN notes');
$raw->exec('DROP TABLE sales');
$undoStoreBias();
$undoPartyPurse();
$raw->exec('DROP TABLE requests');
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
    $rows = array_merge(...array_map(fn($set) => srd_items($ruleset, $set), array_keys(SRD_SETS)));
    $slugIds = array_column(categories(), 'id', 'slug');
    transaction(function () use ($rows, $ruleset, $slugIds) {
        foreach ($rows as [$name, $slug, $weight, $price, $tags, $description]) {
            set_item_tags((int)create_item($name, (int)$slugIds[$slug], (float)$weight, parse_price($price) ?? 0, $description, $ruleset)['id'], $tags);
        }
    });
    $check("every {$ruleset} SRD item saves", count($rows), (int)q_val('SELECT COUNT(*) FROM items WHERE ruleset = ?', [$ruleset]) - ($ruleset === '5e' ? 2 : 1));
}
$check('SRD weights keep their fractions', '0.25', rtrim((string)q_val("SELECT weight FROM items WHERE name = 'Dart' AND ruleset = '5e'"), '0'));

// Loading takes the sentences an earlier version of the data had out of descriptions, with MySQL's collation comparing.
q("UPDATE items SET description = ? WHERE name = 'Lute' AND ruleset = '5e'", [SRD_STALE_TEXT['5e']['gear'][1] . 'Cracked neck.']);
q("UPDATE items SET description = ? WHERE name = 'Drum' AND ruleset = '5e'", [mb_strtoupper(SRD_STALE_TEXT['5e']['gear'][1]) . 'Loud.']);
$check('a stale sentence is counted', 1, srd_stale_count('5e', 'gear'));
$check('loading tidies it', 1, import_srd('5e', 'gear')['tidied']);
$check('the rest of the description stays', 'Cracked neck.', q_val("SELECT description FROM items WHERE name = 'Lute' AND ruleset = '5e'"));
$check('a sentence differing in case is left alone', 'Loud.', substr((string)q_val("SELECT description FROM items WHERE name = 'Drum' AND ruleset = '5e'"), -5));
$check('nothing is left to tidy', 0, srd_stale_count('5e', 'gear'));

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
    $sale = insert('INSERT INTO sales (character_id, item_id, quantity, asking_cp, created_at) VALUES (?, ?, 1, 300, ?)', [$character, $held['id'], now()]);
    return [$character, $store, $cart, $sale];
};
[$olderCharacter, $olderStore, $olderCart, $olderSale] = $addHolder((int)$older['id']);
[$otherCharacter] = $addHolder($other);
create_item('Unheld lamp', (int)categories()[0]['id'], 1, 10, '', '5e');
transaction(fn() => settle_campaign_system($older, '3.5e'));
$check('only what the campaign uses is copied', null, item_by_name('Unheld lamp', '3.5e'));
$copy = item_by_name('Held sword', '3.5e');
$check('setting a system copies the item, with its details and tags', [3.0, 700, 'Notched.', ['martial']], [(float)$copy['weight'], (int)$copy['price_cp'], $copy['description'], $tagsOf((int)$copy['id'])]);
$check('its pack, shelf, cart and sale move to the copy', array_fill(0, 4, (int)$copy['id']), [
    (int)q_val('SELECT item_id FROM inventory WHERE character_id = ?', [$olderCharacter]),
    (int)q_val('SELECT item_id FROM store_stock WHERE store_id = ?', [$olderStore]),
    (int)q_val('SELECT item_id FROM cart_items WHERE cart_id = ?', [$olderCart]),
    (int)q_val('SELECT item_id FROM sales WHERE id = ?', [$olderSale]),
]);
$check('another campaign keeps the original', (int)$held['id'], (int)q_val('SELECT item_id FROM inventory WHERE character_id = ?', [$otherCharacter]));
$check('the campaign is locked to the new system', ['3.5e', 0], array_values(array_map(fn($v) => is_numeric($v) ? (int)$v : $v, q_one('SELECT ruleset, ruleset_open FROM campaigns WHERE id = ?', [$older['id']]))));

// Selling part of a stack at the DM's counter-offer, with the row locks MySQL takes and SQLite doesn't.
$seller = insert('INSERT INTO characters (campaign_id, name, coin_cp, created_at) VALUES (?, ?, 100, ?)', [$other, 'Seller', now()]);
q('INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, ?, 3)', [$seller, $held['id']]);
$sale = insert(
    "INSERT INTO sales (character_id, item_id, quantity, asking_cp, offer_cp, status, created_at) VALUES (?, ?, 2, 1000, 900, 'countered', ?)",
    [$seller, $held['id'], now()]
);
settle_sale($sale, byPlayer: true, seenPrice: 900);
$check('a sale takes the items, pays the purse and logs both', [1, 1000, 'sold', 900, 2], [
    (int)q_val('SELECT quantity FROM inventory WHERE character_id = ?', [$seller]),
    (int)q_val('SELECT coin_cp FROM characters WHERE id = ?', [$seller]),
    q_val('SELECT status FROM sales WHERE id = ?', [$sale]),
    (int)q_val('SELECT sold_cp FROM sales WHERE id = ?', [$sale]),
    (int)q_val("SELECT COUNT(*) FROM inventory_log WHERE character_id = ? AND action = 'sell'", [$seller]),
]);
try {
    settle_sale($sale, byPlayer: true, seenPrice: 900);
    $check('a sale can only be settled once', true, false);
} catch (RuntimeException) {
    $check('a sale can only be settled once', true, true);
}

// The same sale in a campaign that shares a purse pays the party, and its stash moves stacks under the same locks.
q('UPDATE campaigns SET shared_purse = 1, purse_cp = 500 WHERE id = ?', [$other]);
q('INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, ?, 2)', [$seller, $held['id']]);
$shared = insert("INSERT INTO sales (character_id, item_id, quantity, asking_cp, status, created_at) VALUES (?, ?, 1, 250, 'pending', ?)", [$seller, $held['id'], now()]);
settle_sale($shared, byPlayer: false);
$check('a shared purse is paid instead of the seller', [750, 1000], [
    (int)q_val('SELECT purse_cp FROM campaigns WHERE id = ?', [$other]),
    (int)q_val('SELECT coin_cp FROM characters WHERE id = ?', [$seller]),
]);
$stack = q_one('SELECT inv.*, inv.item_id, i.name FROM inventory inv JOIN items i ON i.id = inv.item_id WHERE inv.character_id = ? ORDER BY inv.id LIMIT 1', [$seller]);
$check('a stack goes into the party stash', 2, pack_to_stash($stack, null));
$stashed = q_one('SELECT pi.*, i.name FROM party_items pi JOIN items i ON i.id = pi.item_id WHERE pi.campaign_id = ?', [$other]);
$check('and comes back out', 1, stash_to_pack($stashed, ['id' => $seller, 'name' => 'Seller'], 1));
$check('leaving the rest in the stash, logged on both sides', [1, 2], [
    (int)q_val('SELECT quantity FROM party_items WHERE id = ?', [$stashed['id']]),
    (int)q_val("SELECT COUNT(*) FROM inventory_log WHERE campaign_id = ? AND party = 1 AND action IN ('move_in', 'move_out')", [$other]),
]);

// Shelf notes match exactly, as in SQLite, and the longest the form allows fits.
$scroll = (int)create_item('Check scroll', (int)categories()[0]['id'], 0, 100, ruleset: '5e')['id'];
$shop = insert("INSERT INTO stores (campaign_id, name, created_at) VALUES ((SELECT MIN(id) FROM campaigns), 'Check shop', ?)", [now()]);
$longestNotes = str_repeat('é', STOCK_NOTES_MAX);
foreach (['Shield', 'shield', $longestNotes] as $notes) {
    q('INSERT INTO store_stock (store_id, item_id, notes, price_cp) VALUES (?, ?, ?, 100)', [$shop, $scroll, $notes]);
}
$check('notes differing only in case are two shelf lines', 2, (int)q_val('SELECT COUNT(*) FROM store_stock WHERE store_id = ? AND notes = ?', [$shop, 'Shield']) + (int)q_val('SELECT COUNT(*) FROM store_stock WHERE store_id = ? AND notes = ?', [$shop, 'shield']));
$check('the longest notes are kept whole', $longestNotes, q_val('SELECT notes FROM store_stock WHERE store_id = ? AND notes LIKE ?', [$shop, 'é%']));
// A character's share of the prices reprices their open cart, and hiding a line from them takes it out of it (a delete
// MySQL only allows through a derived table).
$buyer = insert('INSERT INTO characters (campaign_id, name, created_at) VALUES ((SELECT MIN(id) FROM campaigns), ?, ?)', ['Buyer', now()]);
$line = (int)q_val('SELECT id FROM store_stock WHERE store_id = ? AND notes = ?', [$shop, 'Shield']);
$cart = insert("INSERT INTO carts (character_id, store_id, status, created_at) VALUES (?, ?, 'open', ?)", [$buyer, $shop, now()]);
q("INSERT INTO cart_items (cart_id, item_id, notes, quantity, unit_price_cp) VALUES (?, ?, 'Shield', 1, 100)", [$cart, $scroll]);
q('INSERT INTO store_prices (store_id, character_id, price_pct) VALUES (?, ?, 150)', [$shop, $buyer]);
reprice_open_cart($shop, $buyer);
$check('a share of the prices reprices an open cart', 150, (int)q_val('SELECT unit_price_cp FROM cart_items WHERE cart_id = ?', [$cart]));
q('UPDATE store_stock SET restricted = 1 WHERE id = ?', [$line]);
$check('hiding a line takes it out of open carts', [1, 0], [remove_unseen_from_open_carts($line), (int)q_val('SELECT COUNT(*) FROM cart_items WHERE cart_id = ?', [$cart])]);
// A cart back from the DM loses what was hidden (Shield) or pulled (Gone) meanwhile, and keeps the rest (shield).
foreach (['Shield', 'shield', 'Gone'] as $notes) {
    q('INSERT INTO cart_items (cart_id, item_id, notes, quantity, unit_price_cp) VALUES (?, ?, ?, 1, 100)', [$cart, $scroll, $notes]);
}
$check('a withdrawn cart loses what is no longer for sale', [2, ['shield']], [remove_unsold_from_cart($cart), array_column(q_all('SELECT notes FROM cart_items WHERE cart_id = ?', [$cart]), 'notes')]);
// The dearest line at the highest share still fits the price column.
q('UPDATE cart_items SET unit_price_cp = ? WHERE cart_id = ?', [biased_price(PRICE_CP_LIMIT, MAX_PRICE_PCT), $cart]);
$check('a biased price stops at what the column holds', PRICE_CP_LIMIT, (int)q_val('SELECT unit_price_cp FROM cart_items WHERE cart_id = ?', [$cart]));
q('DELETE FROM characters WHERE id = ?', [$buyer]);
q('DELETE FROM stores WHERE id = ?', [$shop]);
q('DELETE FROM items WHERE id = ?', [$scroll]);

// The longest request and reply the forms allow fit, and they go when their character does.
$writer = insert('INSERT INTO characters (campaign_id, name, created_at) VALUES (?, ?, ?)', [$other, 'Writer', now()]);
$longestRequest = str_repeat("é\n", MAX_REQUEST_CHARS / 2);
$request = insert("INSERT INTO requests (character_id, body, dm_reply, created_at) VALUES (?, ?, ?, ?)", [$writer, $longestRequest, str_repeat('é', MAX_REQUEST_REPLY_CHARS), now()]);
$check('the longest request and reply are kept whole', [$longestRequest, MAX_REQUEST_REPLY_CHARS], [q_val('SELECT body FROM requests WHERE id = ?', [$request]), mb_strlen(q_val('SELECT dm_reply FROM requests WHERE id = ?', [$request]))]);
q('DELETE FROM characters WHERE id = ?', [$writer]);
$check('a character’s requests go with them', 0, (int)q_val('SELECT COUNT(*) FROM requests WHERE character_id = ?', [$writer]));

$secondPage = catalog_page('', '', [], 2);
$check('a catalog page holds one page of items', [CATALOG_PAGE_SIZE, 2, CATALOG_PAGE_SIZE + 1], [count($secondPage['items']), $secondPage['page'], $secondPage['first']]);
$check('a page past the end shows the last one', $secondPage['pages'], catalog_page('', '', [], 9999)['page']);

exit($failures ? 1 : 0);
