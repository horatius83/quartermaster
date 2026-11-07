<?php
// Checks item tags against a real MySQL or MariaDB, which the SQLite end-to-end tests can't: that upgrade 005
// runs, and that the tag column stores, sorts and cascades the way the app relies on. Run by tests/mysql/run.mjs.
declare(strict_types=1);

putenv('QM_CONFIG=' . __DIR__ . '/config.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$cfg = (require __DIR__ . '/config.php')['mysql'];

// Build the current schema, then take the table away again: a database from just before upgrade 005.
$raw = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../app/sql/schema.mysql.sql'));
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $raw->exec($statement);
}
$raw->exec('DROP TABLE item_tags');

require __DIR__ . '/../../app/bootstrap.php'; // connecting below runs the missing upgrade

$failures = 0;
$check = function (string $what, mixed $expected, mixed $actual) use (&$failures): void {
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . ($ok ? '' : "\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual)) . "\n";
};
$tagsOf = fn(int $id) => with_item_tags([['id' => $id]])[0]['tags'];

$check('upgrade 005 created the table', 0, (int)q_val('SELECT COUNT(*) FROM item_tags'));

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

exit($failures ? 1 : 0);
