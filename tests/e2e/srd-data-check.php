<?php
// Checks every row of the bundled SRD data against what the catalog accepts, and prints any problems as JSON.
// A bad row would otherwise only surface when a DM clicks the button. Run with QM_CONFIG pointing at tests/e2e/config.php.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';

$slugs = array_column(categories(), 'slug');
$problems = [];
const SRD_TABLE_REFERENCE = '/\b(?:roll(?:ed|s)?|see|consult|use|using|shown|appears?|listed|given|determined?)\b[^.;]{0,30}?\btables?\b'
    . '|\btables?\s+(?:below|above|that follows|shows?|lists?|gives?)\b|\bfollowing\s+tables?\b/i';
// The pattern has to catch the references the data once had, and leave furniture alone.
foreach (['roll on the table below.' => 1, 'Use the following tables to determine' => 1, 'appear on the Tools table' => 1,
          'The table shows examples' => 1, 'shown on the table as examples' => 1, 'roll on the Creature Type table' => 1,
          'it is set on the table to activate' => 0, 'The cloth can be spread over a table.' => 0] as $text => $want) {
    if (preg_match(SRD_TABLE_REFERENCE, $text) !== $want) {
        $problems[] = "the table check gets \"{$text}\" wrong";
    }
}
foreach (SRD_RULESETS as $ruleset) {
    // Names are compared across a system's sets too: the catalog holds both, so a name in each would load only once.
    $seen = [];
    foreach (array_keys(SRD_SETS) as $set) {
        $rows = srd_items($ruleset, $set);
        if (count($rows) < 100) {
            $problems[] = "{$ruleset} {$set}: only " . count($rows) . ' rows';
        }
        foreach ($rows as $row) {
            [$name, $slug, $weight, $price, $tags, $description] = $row;
            $where = "{$ruleset} {$set} {$name}";
            $key = srd_name_key($name);
            if (isset($seen[$key])) {
                $problems[] = "{$where}: listed twice";
            }
            $seen[$key] = true;
            if (mb_strlen($name) > 150) {
                $problems[] = "{$where}: name too long";
            }
            if (!in_array($slug, $slugs, true)) {
                $problems[] = "{$where}: no category {$slug}";
            }
            if ($set === 'magic' && $slug !== 'magic') {
                $problems[] = "{$where}: a magic item outside the Magic Item category";
            }
            if (!is_int($weight) && !is_float($weight) || $weight < 0) {
                $problems[] = "{$where}: bad weight";
            }
            if (parse_price($price) === null) {
                $problems[] = "{$where}: can't read the price {$price}";
            }
            // The price column is an INT of copper pieces, and a cart of a few of them still has to fit in one.
            if (parse_price($price) > 100_000_000) {
                $problems[] = "{$where}: price too high";
            }
            // The SRD's tables (a bag of tricks' animals, a slaying arrow's creature types) are dropped when its text is
            // condensed, so a description that still sends the reader to one (rolled on it, shown on it, the table
            // below) has lost what it refers to. A table as furniture ("spread over a table") is fine.
            if (preg_match(SRD_TABLE_REFERENCE, $description)) {
                $problems[] = "{$where}: refers to a table that isn't there";
            }
            // A row's save cuts a description to this length, so a longer one would silently lose its end.
            if (mb_strlen($description) > 1000) {
                $problems[] = "{$where}: description too long";
            }
            // Rows are compared with what the database reads back, so tags must already be in parse_tags()'s form.
            if ($tags !== parse_tags(implode(',', $tags))) {
                $problems[] = "{$where}: tags not sorted lowercase";
            }
            foreach ($tags as $tag) {
                if (mb_strlen($tag) > MAX_TAG_LENGTH) {
                    $problems[] = "{$where}: tag too long";
                }
            }
        }
    }
}
echo json_encode(['problems' => $problems]);
