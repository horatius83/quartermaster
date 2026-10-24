<?php $qtyUrl = url('/dm/inv/' . $r['id'] . '/qty'); ?>
<tr>
  <td>
    <div class="item-name"><?= e($r['name']) ?></div>
    <?php if ($r['notes'] !== ''): ?><div class="muted small"><?= e($r['notes']) ?></div><?php endif; ?>
  </td>
  <td><?= category_tag($r['category'], $r['category_slug']) ?></td>
  <td>
    <div class="stepper">
      <button class="round-btn" type="button" aria-label="Remove one <?= e($r['name']) ?>"
        hx-post="<?= e($qtyUrl) ?>" hx-vals='{"delta": -1}' hx-target="closest tr" hx-swap="outerHTML">−</button>
      <output><?= (int)$r['quantity'] ?></output>
      <button class="round-btn" type="button" aria-label="Add one <?= e($r['name']) ?>"
        hx-post="<?= e($qtyUrl) ?>" hx-vals='{"delta": 1}' hx-target="closest tr" hx-swap="outerHTML">+</button>
    </div>
  </td>
  <td class="nowrap"><?= e(fmt_weight((float)$r['weight'] * (int)$r['quantity'])) ?></td>
  <td class="nowrap"><?= e(coins((int)$r['price_cp'])) ?></td>
  <td class="right nowrap">
    <?php if ($characters): ?>
    <details class="inline" style="display:inline-block">
      <summary>Move to…</summary>
      <form class="move-form" hx-post="<?= e(url('/dm/inv/' . $r['id'] . '/move')) ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">
        <label class="visually-hidden" for="mv-<?= (int)$r['id'] ?>">Move <?= e($r['name']) ?> to</label>
        <select class="input-sm" style="width:auto" id="mv-<?= (int)$r['id'] ?>" name="to">
          <?php foreach ($characters as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Move</button>
      </form>
    </details>
    <?php endif; ?>
    <button class="link-btn danger" type="button"
      hx-post="<?= e(url('/dm/inv/' . $r['id'] . '/delete')) ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms"
      hx-confirm="Remove all <?= (int)$r['quantity'] ?> × <?= e($r['name']) ?>?">Remove</button>
  </td>
</tr>
