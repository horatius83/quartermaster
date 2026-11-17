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
    // Every spell scroll the system's items have must give its level, or its notes would be offered every spell.
    foreach (srd_items($ruleset, 'magic') as [$name]) {
        if (scroll_level($name) === -1) {
            $problems[] = "{$ruleset} magic {$name}: a scroll whose name gives no spell level";
        }
    }

    // The spells suggested for a scroll's notes (see spell_suggestions()).
    $file = APP_DIR . "/data/srd-{$ruleset}-spells.php";
    if (!is_file($file)) {
        $problems[] = "{$ruleset}: no spell list";
        continue;
    }
    $count = 0;
    foreach (require $file as $level => $spells) {
        if (!is_int($level) || $level < 0 || $level > 9) {
            $problems[] = "{$ruleset} spells: a level {$level}";
            continue;
        }
        $names = [];
        foreach ($spells as $key => $value) {
            [$name, $lists] = is_int($key) ? [$value, null] : [$key, $value];
            $where = "{$ruleset} spells {$level} {$name}";
            $names[] = srd_name_key($name);
            $count++;
            // Picked, a name fills a shelf line's notes, which keep only so much.
            if (!is_string($name) || $name === '' || mb_strlen($name) > STOCK_NOTES_MAX) {
                $problems[] = "{$where}: bad name";
            }
            if ($lists !== null && ($lists === [] || array_filter($lists, fn($l) => !is_string($l) || $l === '') !== [])) {
                $problems[] = "{$where}: bad class lists";
            }
        }
        foreach (array_unique(array_diff_assoc($names, array_unique($names))) as $twice) {
            $problems[] = "{$ruleset} spells {$level} {$twice}: listed twice";
        }
    }
    // SRD 5.1 has 319 spells; the 3.5e lists have more than 600 spells, some under several levels.
    if ($count < 300) {
        $problems[] = "{$ruleset} spells: only {$count}";
    }
}
echo json_encode(['problems' => $problems]);
