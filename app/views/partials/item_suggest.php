<?php
/**
 * Item-name field with catalog suggestions, for the give / stock forms.
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
  <input id="<?= e($id) ?>-input" name="item" required maxlength="150" autocomplete="off" placeholder="<?= e($placeholder) ?>"
         role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= e($id) ?>-suggest"
         hx-get="<?= e(url('/dm/catalog/suggest')) ?>" hx-trigger="input changed delay:250ms"
         hx-sync="this:replace" hx-target="#<?= e($id) ?>-suggest" hx-swap="innerHTML">
  <ul id="<?= e($id) ?>-suggest" class="suggest-list" role="listbox" aria-label="Catalog matches" hidden></ul>
</div>
