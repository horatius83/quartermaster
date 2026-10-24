<?php
/** @var array $rows @var bool $showCharacter @var string $path */
$cols = $showCharacter ? 5 : 4;
$labels = [
    'give'     => 'Given by the DM',
    'adjust'   => 'Count changed',
    'remove'   => 'Removed',
    'move_out' => 'Handed over',
    'move_in'  => 'Received',
    'purchase' => 'Bought',
    'coin'     => 'Purse changed',
];
foreach ($rows as $l):
    $up = (int)$l['quantity'] > 0 || (int)$l['coin_cp'] > 0; ?>
  <tr>
    <td class="nowrap" title="<?= e($l['created_at']) ?> UTC"><?= e(time_ago($l['created_at'])) ?></td>
    <?php if ($showCharacter): ?><td><a href="<?= e(url('/dm/history/' . $l['character_id'])) ?>"><?= e($l['character_name']) ?></a></td><?php endif; ?>
    <td>
      <?php if ($l['item_name'] !== ''): ?>
        <span class="delta <?= $up ? 'delta-up' : 'delta-down' ?>"><?= $up ? '+' : '−' ?><?= abs((int)$l['quantity']) ?></span>
        <span class="item-name"><?= e($l['item_name']) ?></span>
        <?php if ($l['notes'] !== ''): ?><div class="muted small"><?= e($l['notes']) ?></div><?php endif; ?>
      <?php else: ?>
        <span class="delta <?= $up ? 'delta-up' : 'delta-down' ?>"><?= $up ? '+' : '−' ?><?= e(coins(abs((int)$l['coin_cp']))) ?></span>
      <?php endif; ?>
    </td>
    <td><?= e($labels[$l['action']] ?? $l['action']) ?><?php if ($l['detail'] !== ''): ?><div class="muted small"><?= e($l['detail']) ?></div><?php endif; ?></td>
    <td class="muted"><?= e($l['actor_name'] !== '' ? $l['actor_name'] : '—') ?></td>
  </tr>
<?php endforeach; ?>
<?php if (count($rows) >= 50): $older = url($path . '?before=' . end($rows)['id']); ?>
  <tr id="log-more">
    <td colspan="<?= $cols ?>" class="empty">
      <a class="btn btn-ghost" href="<?= e($older) ?>" hx-get="<?= e($older) ?>" hx-target="#log-more" hx-swap="outerHTML">Show older</a>
    </td>
  </tr>
<?php endif; ?>
