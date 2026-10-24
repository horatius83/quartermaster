<?php $u = url('/dm/catalog/' . $i['id']); $id = (int)$i['id'];
$attrs = 'hx-post="' . e($u) . '" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML"'; ?>
<tr>
  <td>
    <label class="visually-hidden" for="cn-<?= $id ?>">Name</label>
    <input class="input-sm" style="width:100%;min-width:180px" id="cn-<?= $id ?>" name="name" value="<?= e($i['name']) ?>" maxlength="150" <?= $attrs ?>>
  </td>
  <td>
    <label class="visually-hidden" for="cc-<?= $id ?>">Category</label>
    <select class="input-sm" style="width:auto" id="cc-<?= $id ?>" name="category_id" <?= $attrs ?>>
      <?php foreach (categories() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === (int)$i['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </td>
  <td>
    <label class="visually-hidden" for="cw-<?= $id ?>">Weight</label>
    <input class="input-sm" id="cw-<?= $id ?>" name="weight" type="number" min="0" step="0.01" value="<?= e((float)$i['weight']) ?>" <?= $attrs ?>>
  </td>
  <td>
    <label class="visually-hidden" for="cp-<?= $id ?>">Price</label>
    <input class="input-sm" style="width:120px" id="cp-<?= $id ?>" name="price" value="<?= e(coins((int)$i['price_cp'])) ?>" <?= $attrs ?>>
  </td>
  <td class="muted"><?= (int)$i['uses'] ?: '—' ?></td>
  <td class="right">
    <?php if ((int)$i['uses'] === 0): ?>
      <button class="link-btn danger" type="button" hx-post="<?= e($u . '/delete') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">Delete</button>
    <?php endif; ?>
  </td>
</tr>
