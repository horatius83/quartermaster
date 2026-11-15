<?php $u = url('/dm/stock/' . $s['id']); ?>
<tr>
  <td>
    <div class="item-name"><?= e($s['name']) ?></div>
    <label class="visually-hidden" for="nt-<?= (int)$s['id'] ?>">Notes on <?= e($s['name']) ?></label>
    <input class="input-sm" style="width:100%;margin-top:4px" id="nt-<?= (int)$s['id'] ?>" name="notes" maxlength="<?= STOCK_NOTES_MAX ?>" placeholder="Notes" value="<?= e($s['notes']) ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td><?= category_tag($s['category'], $s['category_slug']) ?></td>
  <td>
    <label class="visually-hidden" for="pr-<?= (int)$s['id'] ?>">Price of <?= e(named_with_notes($s['name'], $s['notes'])) ?></label>
    <input class="input-sm" style="width:120px" id="pr-<?= (int)$s['id'] ?>" name="price" value="<?= e(coins((int)$s['price_cp'])) ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td>
    <label class="visually-hidden" for="qt-<?= (int)$s['id'] ?>">Stock of <?= e(named_with_notes($s['name'], $s['notes'])) ?></label>
    <input class="input-sm" id="qt-<?= (int)$s['id'] ?>" name="quantity" inputmode="numeric" placeholder="∞" value="<?= $s['quantity'] === null ? '' : (int)$s['quantity'] ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td class="right">
    <button class="link-btn danger" type="button" hx-post="<?= e($u . '/delete') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">Pull from shelf</button>
  </td>
</tr>
