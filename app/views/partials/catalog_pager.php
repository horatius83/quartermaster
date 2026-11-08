<?php
// Sits outside #catalog-body because assets/app.js counts requests from inside it as row saves, and a page change has
// to wait for those rather than be one. It's also outside the table's scrolling box, so the buttons stay in view when
// the table is wider than the screen.
// It's always drawn, even empty, so a redraw's out-of-band swap has a target, and a full page leaves out hx-swap-oob
// because htmx would otherwise lift it out of the page.
// #catalog-page tells saves which page to redraw. Filters don't send it, so changing one goes back to page 1.
['page' => $page, 'pages' => $pages, 'total' => $total, 'first' => $first] = $catalog;
// The ids let assets/app.js find a button again when a row save's redraw replaces the pager while a page change waits.
$pageButton = function (string $id, int $to, string $label, bool $enabled): string {
    return '<button class="btn" id="' . $id . '" type="button"' . ($enabled ? '' : ' disabled')
        . ' hx-get="' . e(url('/dm/catalog')) . '" hx-vals=\'{"page": ' . $to . '}\''
        . ' hx-include="' . e(CATALOG_FILTERS) . '"'
        . ' hx-target="#catalog-body" hx-swap="outerHTML show:.table-wrap:top" hx-push-url="true"'
        . ' hx-disabled-elt="' . e(CATALOG_ROW_FIELDS) . '">' . e($label) . '</button>';
};
?>
<div id="catalog-pager"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
  <?php if ($pages > 1): ?>
  <nav class="row-between" aria-label="Catalog pages">
    <input type="hidden" id="catalog-page" name="page" value="<?= (int)$page ?>">
    <?= $pageButton('catalog-prev', $page - 1, 'Previous', $page > 1) ?>
    <span class="muted" aria-live="polite">Showing <?= (int)$first ?>–<?= $first + count($catalog['items']) - 1 ?> of <?= (int)$total ?> · page <?= (int)$page ?> of <?= (int)$pages ?></span>
    <?= $pageButton('catalog-next', $page + 1, 'Next', $page < $pages) ?>
  </nav>
  <?php endif; ?>
</div>
