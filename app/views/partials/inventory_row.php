<?php
/**
 * A stack in a pack, or with $stash in the party stash. $characters are who it can be moved to; a pack's stack can
 * also go to the party stash while the campaign shares one ($shared).
 * @var array $r @var array $characters @var bool $stash @var bool $shared
 */
$stash = !empty($stash);
$base = url(($stash ? '/dm/stash/' : '/dm/inv/') . $r['id']);
$toStash = !$stash && $shared;
?>
<tr>
  <td>
    <div class="item-name"><?= e($r['name']) ?></div>
    <?php if ($r['notes'] !== ''): ?><div class="muted small"><?= e($r['notes']) ?></div><?php endif; ?>
  </td>
  <td><?= category_tag($r['category'], $r['category_slug']) ?></td>
  <td>
    <div class="stepper">
      <button class="round-btn" type="button" aria-label="Remove one <?= e($r['name']) ?>"
        hx-post="<?= e($base . '/qty') ?>" hx-vals='{"delta": -1}' hx-target="closest tr" hx-swap="outerHTML">−</button>
      <output><?= (int)$r['quantity'] ?></output>
      <button class="round-btn" type="button" aria-label="Add one <?= e($r['name']) ?>"
        hx-post="<?= e($base . '/qty') ?>" hx-vals='{"delta": 1}' hx-target="closest tr" hx-swap="outerHTML">+</button>
    </div>
  </td>
  <td class="nowrap"><?= e(fmt_weight((float)$r['weight'] * (int)$r['quantity'])) ?></td>
  <td class="nowrap"><?= e(coins((int)$r['price_cp'])) ?></td>
  <td class="right nowrap">
    <?php if ($characters || $toStash): ?>
    <details class="inline" style="display:inline-block">
      <summary><?= $stash ? 'Give to…' : 'Move to…' ?></summary>
      <form class="move-form" hx-post="<?= e($base . '/move') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms">
        <label class="visually-hidden" for="mv-<?= $stash ? 's' : '' ?><?= (int)$r['id'] ?>"><?= $stash ? 'Give' : 'Move' ?> <?= e($r['name']) ?> to</label>
        <select class="input-sm" style="width:auto" id="mv-<?= $stash ? 's' : '' ?><?= (int)$r['id'] ?>" name="to">
          <?php if ($toStash): ?><option value="party">Party stash</option><?php endif; ?>
          <?php foreach ($characters as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= $stash ? 'Give' : 'Move' ?></button>
      </form>
    </details>
    <?php endif; ?>
    <button class="link-btn danger" type="button"
      hx-post="<?= e($base . '/delete') ?>" hx-target="closest tr" hx-swap="outerHTML swap:300ms"
      hx-confirm="Remove all <?= (int)$r['quantity'] ?> × <?= e($r['name']) ?>?">Remove</button>
  </td>
</tr>
