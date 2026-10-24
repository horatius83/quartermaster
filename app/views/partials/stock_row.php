<?php $u = url('/dm/stock/' . $s['id']); ?>
<tr>
  <td class="item-name"><?= e($s['name']) ?></td>
  <td><?= category_tag($s['category'], $s['category_slug']) ?></td>
  <td>
    <label class="visually-hidden" for="pr-<?= (int)$s['id'] ?>">Price of <?= e($s['name']) ?></label>
    <input class="input-sm" style="width:120px" id="pr-<?= (int)$s['id'] ?>" name="price" value="<?= e(coins((int)$s['price_cp'])) ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td>
    <label class="visually-hidden" for="qt-<?= (int)$s['id'] ?>">Stock of <?= e($s['name']) ?></label>
    <input class="input-sm" id="qt-<?= (int)$s['id'] ?>" name="quantity" inputmode="numeric" placeholder="∞" value="<?= $s['quantity'] === null ? '' : (int)$s['quantity'] ?>"
      hx-post="<?= e($u) ?>" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML">
  </td>
  <td class="right">
    <button class="link-btn danger" type="button" hx-post="<?= e($u . '/delete') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">Pull from shelf</button>
  </td>
</tr>
