<?php
// Checks every row of the bundled SRD data against what the catalog accepts, and prints any problems as JSON.
// A bad row would otherwise only surface when a DM clicks the button. Run with QM_CONFIG pointing at tests/e2e/config.php.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/handlers/dm_catalog.php';

$slugs = array_column(categories(), 'slug');
$problems = [];
foreach (SRD_RULESETS as $ruleset) {
    $seen = [];
    $rows = srd_items($ruleset);
    if (count($rows) < 100) {
        $problems[] = "{$ruleset}: only " . count($rows) . ' rows';
    }
    foreach ($rows as $row) {
        [$name, $slug, $weight, $price, $tags, $description] = $row;
        $where = "{$ruleset} {$name}";
        if (isset($seen[mb_strtolower($name)])) {
            $problems[] = "{$where}: listed twice";
        }
        $seen[mb_strtolower($name)] = true;
        if (mb_strlen($name) > 150) {
            $problems[] = "{$where}: name too long";
        }
        if (!in_array($slug, $slugs, true)) {
            $problems[] = "{$where}: no category {$slug}";
        }
        if (!is_int($weight) && !is_float($weight) || $weight < 0) {
            $problems[] = "{$where}: bad weight";
        }
        if (parse_price($price) === null) {
            $problems[] = "{$where}: can't read the price {$price}";
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
echo json_encode(['problems' => $problems]);
