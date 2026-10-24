<?php
// Fills an EMPTY database with a sample party, catalog and stores so you can click around.
// Run from the project root:  php scripts/seed-demo.php
// Logins created: dm / dungeon123, and players bryn, tamsin, oskar, wren (password: adventure).
// A second, smaller campaign has one player, sela (password: adventure), and one shop.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../app/bootstrap.php';

if ((int)q_val('SELECT COUNT(*) FROM users') > 0) {
    fwrite(STDERR, "The database already has users. Seed only runs on an empty install.\n");
    exit(1);
}

$cat = [];
foreach (categories() as $c) {
    $cat[$c['slug']] = (int)$c['id'];
}

transaction(function () use ($cat) {
    insert("INSERT INTO users (username, password_hash, role, created_at) VALUES ('dm', ?, 'dm', ?)", [password_hash('dungeon123', PASSWORD_DEFAULT), now()]);
    $campaign = create_campaign('The Sunken Crown');
    $winter = create_campaign('Winter’s Teeth');

    $party = [
        ['bryn',   'Bryn Ashvale',  'Fighter 5', 41200, 240],
        ['tamsin', 'Tamsin Reed',   'Ranger 5',  28800, 165],
        ['oskar',  'Oskar Vell',    'Wizard 5',  51700, 120],
        ['wren',   'Wren Holloway', 'Rogue 5',    6700, 150],
    ];
    $chars = [];
    foreach ($party as [$user, $name, $class, $coin, $cap]) {
        $uid = insert("INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'player', ?)", [$user, password_hash('adventure', PASSWORD_DEFAULT), now()]);
        $chars[$user] = insert('INSERT INTO characters (campaign_id, user_id, name, class_label, coin_cp, carry_capacity, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$campaign, $uid, $name, $class, $coin, $cap, now()]);
    }

    // name, category, weight, price (cp)  — prices from the 5e SRD
    $catalog = [
        ['Longsword', 'weapon', 3, 1500], ['Battleaxe', 'weapon', 4, 1000], ['Warhammer', 'weapon', 2, 1500],
        ['Shortbow', 'weapon', 2, 2500], ['Longbow', 'weapon', 2, 5000], ['Light crossbow', 'weapon', 5, 2500],
        ['Dagger', 'weapon', 1, 200], ['Quarterstaff', 'weapon', 4, 20],
        ['Chain mail', 'armor', 55, 7500], ['Scale mail', 'armor', 45, 5000], ['Leather armor', 'armor', 10, 1000], ['Shield', 'armor', 6, 1000],
        ['Arrows (20)', 'ammunition', 1, 100], ['Crossbow bolts (20)', 'ammunition', 1.5, 100],
        ['Potion of Healing', 'consumable', 0.5, 5000], ['Antitoxin', 'consumable', 0, 5000], ['Alchemist’s fire', 'consumable', 1, 5000], ['Rations (1 day)', 'consumable', 2, 50],
        ['Hempen rope (50 ft)', 'gear', 10, 100], ['Quiver', 'gear', 1, 100], ['Healer’s kit', 'gear', 3, 500], ['Torch', 'gear', 1, 1],
        ['Thieves’ tools', 'tool', 1, 2500], ['Smith’s tools', 'tool', 8, 2000], ['Herbalism kit', 'tool', 3, 500],
        ['Bag of Holding', 'magic', 15, 0], ['Driftglobe', 'magic', 1, 0],
        ['Silver locket', 'treasure', 0, 2500],
    ];
    $item = [];
    foreach ($catalog as [$name, $slug, $w, $price]) {
        $item[$name] = insert('INSERT INTO items (name, category_id, weight, price_cp) VALUES (?, ?, ?, ?)', [$name, $cat[$slug], $w, $price]);
    }

    $stores = [
        ['The Rusty Anvil', 'Smithy', 'Steel, mail and plate. Repairs while you wait.', 1,
            [['Longsword', 4], ['Battleaxe', 2], ['Warhammer', 1], ['Chain mail', 1], ['Scale mail', 2], ['Shield', null], ['Smith’s tools', 3]]],
        ['Fletcher’s Rest', 'Bowyer', 'Bows strung fresh each morning.', 1,
            [['Shortbow', 3], ['Longbow', 2], ['Arrows (20)', null], ['Crossbow bolts (20)', null], ['Quiver', 6]]],
        ['Mossbottom Apothecary', 'Alchemist', 'Tinctures, salves and the odd curiosity.', 1,
            [['Potion of Healing', 6], ['Antitoxin', 2], ['Herbalism kit', 2], ['Healer’s kit', 4], ['Alchemist’s fire', 3]]],
        ['The Gilded Curio', 'Oddities', 'Ask nicely and Madame Ferro may show you the back room.', 0,
            [['Bag of Holding', 1], ['Driftglobe', 1], ['Silver locket', 1]]],
    ];
    foreach ($stores as [$name, $kind, $blurb, $open, $stock]) {
        $sid = insert('INSERT INTO stores (campaign_id, name, kind, blurb, is_open, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$campaign, $name, $kind, $blurb, $open, now()]);
        foreach ($stock as [$itemName, $qty]) {
            $price = (int)q_val('SELECT price_cp FROM items WHERE id = ?', [$item[$itemName]]) ?: 50000;
            q('INSERT INTO store_stock (store_id, item_id, price_cp, quantity) VALUES (?, ?, ?, ?)', [$sid, $item[$itemName], $price, $qty]);
        }
    }

    $packs = [
        'bryn'   => [['Longsword', 1, '+1, “Heartwarden”'], ['Shield', 1, ''], ['Chain mail', 1, 'Equipped'], ['Light crossbow', 1, ''], ['Crossbow bolts (20)', 2, ''], ['Potion of Healing', 3, ''], ['Rations (1 day)', 6, ''], ['Hempen rope (50 ft)', 1, ''], ['Silver locket', 1, 'Found in the crypt']],
        'tamsin' => [['Shortbow', 1, ''], ['Arrows (20)', 2, ''], ['Leather armor', 1, 'Equipped'], ['Dagger', 2, ''], ['Rations (1 day)', 5, '']],
        'oskar'  => [['Quarterstaff', 1, ''], ['Dagger', 1, ''], ['Potion of Healing', 1, ''], ['Torch', 4, '']],
        'wren'   => [['Dagger', 3, ''], ['Leather armor', 1, 'Equipped'], ['Thieves’ tools', 1, ''], ['Hempen rope (50 ft)', 1, '']],
    ];
    foreach ($packs as $user => $rows) {
        foreach ($rows as [$itemName, $qty, $notes]) {
            add_to_inventory($chars[$user], $item[$itemName], $qty, $notes);
            log_inventory($chars[$user], 'give', ['item_id' => $item[$itemName], 'name' => $itemName], $qty, notes: $notes, detail: 'Starting gear');
        }
    }

    // The second campaign shares the catalog but nothing else.
    $uid = insert("INSERT INTO users (username, password_hash, role, created_at) VALUES ('sela', ?, 'player', ?)", [password_hash('adventure', PASSWORD_DEFAULT), now()]);
    $sela = insert('INSERT INTO characters (campaign_id, user_id, name, class_label, coin_cp, carry_capacity, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$winter, $uid, 'Sela Morrow', 'Cleric 2', 4500, 150, now()]);
    $sid = insert('INSERT INTO stores (campaign_id, name, kind, blurb, is_open, created_at) VALUES (?, ?, ?, ?, 1, ?)', [$winter, 'The Frostgate Market', 'General goods', 'Furs, rope and lamp oil. Prices rise with the snow.', now()]);
    foreach (['Hempen rope (50 ft)', 'Torch', 'Rations (1 day)', 'Healer’s kit'] as $itemName) {
        q('INSERT INTO store_stock (store_id, item_id, price_cp, quantity) VALUES (?, ?, (SELECT price_cp FROM items WHERE id = ?), NULL)', [$sid, $item[$itemName], $item[$itemName]]);
    }
    foreach ([['Dagger', 1], ['Rations (1 day)', 3]] as [$itemName, $qty]) {
        add_to_inventory($sela, $item[$itemName], $qty);
        log_inventory($sela, 'give', ['item_id' => $item[$itemName], 'name' => $itemName], $qty, detail: 'Starting gear');
    }
});

echo "Seeded. Sign in as dm / dungeon123, or a player (bryn, tamsin, oskar, wren, or sela in the second campaign) / adventure.\n";
