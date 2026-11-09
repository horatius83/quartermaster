<?php
/**
 * Item-name field with catalog suggestions, for the give / stock forms.
 * Typing searches the whole catalog. The ▾ button lists the category chosen in the form's Category field, A–Z.
 * A list we draw ourselves rather than a <datalist>: mobile browsers don't redraw a datalist whose options
 * arrive after the keystroke, so suggestions loaded from the server never showed up there. (See app.js.)
 *
 * @var string $id          prefix for the element ids, unique on the page
 * @var string $label       plain text
 * @var string $hint        optional plain text shown small after the label
 * @var string $placeholder
 */
$hint ??= '';
?>
<div class="field grow suggest">
  <label for="<?= e($id) ?>-input"><?= e($label) ?><?php if ($hint !== ''): ?> <span class="small"><?= e($hint) ?></span><?php endif; ?></label>
  <div class="suggest-control">
    <input id="<?= e($id) ?>-input" name="item" required maxlength="150" autocomplete="off" placeholder="<?= e($placeholder) ?>"
           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= e($id) ?>-suggest"
           hx-get="<?= e(url('/dm/catalog/suggest')) ?>" hx-trigger="input changed delay:250ms"
           hx-sync="this:replace" hx-target="#<?= e($id) ?>-suggest" hx-swap="innerHTML">
    <button type="button" class="suggest-toggle" aria-label="Browse the catalog by category"
            aria-controls="<?= e($id) ?>-suggest" aria-expanded="false">▾</button>
  </div>
  <ul id="<?= e($id) ?>-suggest" class="suggest-list" role="listbox" aria-label="Catalog items" hidden></ul>
</div>
