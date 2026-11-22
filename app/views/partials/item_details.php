<?php
/**
 * What the catalog says about the item typed or picked on the give or stock form, read-only: its description, plus its
 * price, weight and category as data-* attributes, which app.js copies into the form's fields. Drawn inside the form's
 * details box (see views/partials/give_form.php and views/dm/stores.php), which fetches it again from
 * /dm/catalog/details whenever the item changes.
 *
 * @var string     $id   prefix of the item field's ids, as in partials/item_suggest: give-item or stock-item
 * @var array|null $item the catalog item, or null if the name isn't in the catalog (or nothing is typed)
 * @var string     $name what was typed
 */
if ($item) {
    $placeholder = 'The catalog has no description of ' . $item['name'] . '.';
    $data = ' data-found="1" data-name="' . e($item['name']) . '"'
        . ' data-price="' . e(coins((int)$item['price_cp'])) . '"'
        . ' data-weight="' . e(rtrim(rtrim(number_format(round((float)$item['weight'], 2), 2, '.', ''), '0'), '.')) . '"'
        . ' data-category-id="' . (int)$item['category_id'] . '"';
} else {
    $placeholder = $name === '' ? 'Pick an item to read its description.' : ($id === 'give-item'
        // The give form adds to a pack or to the party stash (partials/give_form.php), so this says neither.
        ? 'Not in the catalog yet. Adding it puts it in the catalog too, with this category, weight and value.'
        : 'Not in the catalog yet. Stocking it adds it, with this category, weight and price.');
    $data = ' data-found="0"';
}
?>
<label for="<?= e($id) ?>-description">Description <span class="small">(from the catalog)</span></label>
<textarea id="<?= e($id) ?>-description" rows="3" readonly placeholder="<?= e($placeholder) ?>"<?= $data ?>><?= e($item['description'] ?? '') ?></textarea>
