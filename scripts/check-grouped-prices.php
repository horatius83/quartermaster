<?php
// Lists prices that may have been saved wrong before prices like "1,500 gp" were read correctly. Changes nothing.
// Run from the project root:  php scripts/check-grouped-prices.php
//
// The shelf's and the catalog's price fields show a price as coins() writes it, with thousands grouped by commas,
// and send it back whenever the row is saved. parse_price() used to read only the digits after the last comma, so a
// price of 1,000 gp or more could be saved with its thousands dropped: "1,500 gp" as 500 gp, "1,000 gp" as 0.
// A price that is exactly what that would have left is listed here to look at. Some may be prices a DM set on purpose.
// Catalog items a DM added (not from the bundled SRD data) have nothing to compare with, so only their shelf lines
// are checked.
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../app/bootstrap.php';

const THOUSAND_GP = 1000 * CP_PER_GP;

/** What saving $cp through the old parse_price() would have left: the price less its whole thousands of gold. */
function thousands_dropped(int $cp): ?int
{
    return $cp >= THOUSAND_GP ? $cp % THOUSAND_GP : null;
}

$found = 0;

// Shelf lines priced at what their catalog item's price would have become.
$lines = q_all(
    'SELECT st.price_cp, st.notes, i.name, i.price_cp AS catalog_cp, s.name AS store, c.name AS campaign
       FROM store_stock st
       JOIN items i ON i.id = st.item_id
       JOIN stores s ON s.id = st.store_id
       JOIN campaigns c ON c.id = s.campaign_id
      WHERE i.price_cp >= ?
      ORDER BY c.name, s.name, i.name',
    [THOUSAND_GP]
);
foreach ($lines as $l) {
    if ((int)$l['price_cp'] === thousands_dropped((int)$l['catalog_cp'])) {
        $label = $l['name'] . ($l['notes'] !== '' ? " ({$l['notes']})" : '');
        echo "Shelf: {$l['campaign']} / {$l['store']} / {$label} is " . coins((int)$l['price_cp'])
            . ', and the catalog says ' . coins((int)$l['catalog_cp']) . ".\n";
        $found++;
    }
}

// SRD catalog items priced at what their bundled price would have become.
foreach (SRD_RULESETS as $ruleset) {
    foreach (array_keys(SRD_SETS) as $set) {
        foreach (srd_items($ruleset, $set) as [$name, , , $price]) {
            $srd = parse_price($price) ?? 0;
            $dropped = thousands_dropped($srd);
            $item = $dropped === null ? null : item_by_name($name, $ruleset);
            if ($item && (int)$item['price_cp'] === $dropped) {
                echo "Catalog: {$ruleset} {$item['name']} is " . coins((int)$item['price_cp'])
                    . ', and the SRD says ' . coins($srd) . ".\n";
                $found++;
            }
        }
    }
}

echo $found === 0
    ? "No prices look like they lost their thousands.\n"
    : "{$found} price(s) to look at. Fix them on the Stores or Catalog page; this script changes nothing.\n";
