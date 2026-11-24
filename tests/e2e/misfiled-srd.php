<?php
// With "misfile": puts the 3.5e gear's moved items (app/data/srd-3.5e-moved.php) back the way an earlier version of the
// data loaded them, as magic items, then makes a few of the DM's own changes: Elven chain gets a description, a tag
// and a weight of the DM's, the Darkwood shield is moved to Gear by hand, and the Dwarven plate is a magic item of the
// DM's own, without the SRD table's tags. With "drop <name>": deletes that 3.5e item. With "read": prints each moved
// item's category slug, weight, tags and description as JSON. Run with QM_CONFIG pointing at tests/e2e/config.php.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$moved = (require APP_DIR . '/data/srd-3.5e-moved.php')['gear'];
$slugIds = array_column(categories(), 'id', 'slug');
$item = fn(string $name) => q_one("SELECT * FROM items WHERE ruleset = '3.5e' AND name = ?", [$name]);

if (($argv[1] ?? '') === 'misfile') {
    foreach ($moved as $name => [$slug, $tags, $description]) {
        $id = (int)$item($name)['id'];
        q('UPDATE items SET category_id = ?, weight = 0, description = ? WHERE id = ?', [$slugIds[$slug], $description, $id]);
        set_item_tags($id, $tags);
    }
    $elven = (int)$item('Elven chain')['id'];
    q('UPDATE items SET weight = 18, description = ? WHERE id = ?', ['Sela’s, from the elves of Frostgate.', $elven]);
    set_item_tags($elven, ['armor', 'medium', 'minor', 'sela']);
    q('UPDATE items SET category_id = ? WHERE id = ?', [$slugIds['gear'], (int)$item('Darkwood shield')['id']]);
    $plate = (int)$item('Dwarven plate')['id'];
    q('UPDATE items SET weight = 0, description = ? WHERE id = ?', ['+2 full plate forged by Moradin’s priests.', $plate]);
    set_item_tags($plate, ['armor', 'heirloom']);
    exit;
}
if (($argv[1] ?? '') === 'drop') {
    q("DELETE FROM items WHERE ruleset = '3.5e' AND name = ?", [$argv[2]]);
    exit;
}

$out = [];
foreach (array_keys($moved) as $name) {
    $row = with_item_tags([$item($name)])[0];
    $out[$name] = [
        'category'    => category_by_id((int)$row['category_id'])['slug'],
        'weight'      => (float)$row['weight'],
        'tags'        => $row['tags'],
        'description' => $row['description'],
    ];
}
echo json_encode($out);
