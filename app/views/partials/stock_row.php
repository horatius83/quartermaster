<?php
$u = url('/dm/stock/' . $s['id']);
$party ??= party_names();
$label = named_with_notes($s['name'], $s['notes']);
// null = everyone, including characters who join later; otherwise the ids of those who see it.
$viewers = $s['viewers'];
$seenBy = $viewers === null ? 'Everyone'
    : ($viewers === [] ? 'Nobody' : implode(', ', array_column(array_filter($party, fn($c) => in_array((int)$c['id'], $viewers, true)), 'name')));
?>
<tr<?= $viewers !== null ? ' class="stock-restricted"' : '' ?>>
  <td>
    <div class="item-name"><?= e($s['name']) ?></div>
    <label class="visually-hidden" for="nt-<?= (int)$s['id'] ?>">Notes on <?= e($s['name']) ?></label>
    <input class="input-sm" style="width:100%;margin-top:4px" id="nt-<?= (int)$s['id'] ?>" name="notes" maxlength="<?= STOCK_NOTES_MAX ?>" placeholder="Notes" value="<?= e($s['notes']) ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td><?= category_tag($s['category'], $s['category_slug']) ?></td>
  <td>
    <label class="visually-hidden" for="pr-<?= (int)$s['id'] ?>">Price of <?= e($label) ?></label>
    <input class="input-sm" style="width:120px" id="pr-<?= (int)$s['id'] ?>" name="price" value="<?= e(coins((int)$s['price_cp'])) ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td>
    <label class="visually-hidden" for="qt-<?= (int)$s['id'] ?>">Stock of <?= e($label) ?></label>
    <input class="input-sm" id="qt-<?= (int)$s['id'] ?>" name="quantity" inputmode="numeric" placeholder="∞" value="<?= $s['quantity'] === null ? '' : (int)$s['quantity'] ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td>
    <?php // Stays open while the DM ticks names: each tick redraws the row, and the checkboxes' ids keep the focus. The
          // names lock while a tick saves, so a second tick can't be undone by the first one's redraw. ?>
    <details class="inline seen-by"<?= !empty($seenOpen) ? ' open' : '' ?>>
      <summary aria-label="Who sees <?= e($label) ?>: <?= e($seenBy) ?>"><?= e($seenBy) ?></summary>
      <fieldset class="seen-by-list">
        <legend class="visually-hidden">Players who see <?= e($label) ?></legend>
        <?php foreach ($party as $c): $vid = 'sv-' . (int)$s['id'] . '-' . (int)$c['id']; ?>
          <input type="hidden" name="viewers_shown[]" value="<?= (int)$c['id'] ?>">
          <label class="check" for="<?= e($vid) ?>"><input type="checkbox" id="<?= e($vid) ?>" name="viewers[]" value="<?= (int)$c['id'] ?>"<?= $viewers === null || in_array((int)$c['id'], $viewers, true) ? ' checked' : '' ?>
            hx-post="<?= e($u . '/viewers') ?>" hx-trigger="change" hx-include="closest fieldset" hx-disabled-elt="closest fieldset" hx-target="closest tr" hx-swap="outerHTML"> <?= e($c['name']) ?></label>
        <?php endforeach; ?>
        <?php if (!$party): ?><span class="muted small">No characters yet.</span><?php endif; ?>
      </fieldset>
    </details>
  </td>
  <td class="right">
    <button class="link-btn danger" type="button" hx-post="<?= e($u . '/delete') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">Pull from shelf</button>
  </td>
</tr>
