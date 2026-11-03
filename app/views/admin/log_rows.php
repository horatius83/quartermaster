<?php
/** @var array $rows @var bool $hasMore */
$roleLabels = ['admin' => 'admin', 'dm' => 'DM', 'player' => 'player'];
$actions = ['create' => ['Added', 'delta-up'], 'delete' => ['Deleted', 'delta-down'], 'move' => ['Handed a campaign to', 'delta-up']];
foreach ($rows as $l):
    [$label, $class] = $actions[$l['action']] ?? [$l['action'], '']; ?>
  <tr>
    <td class="nowrap" title="<?= e($l['created_at']) ?> UTC"><?= e(time_ago($l['created_at'])) ?></td>
    <td>
      <span class="delta <?= $class ?>"><?= e($label) ?></span>
      <?= e($roleLabels[$l['role']] ?? $l['role']) ?> <span class="item-name"><?= e($l['username']) ?></span>
      <?php if ($l['detail'] !== ''): ?><div class="muted small"><?= e($l['detail']) ?></div><?php endif; ?>
    </td>
    <td class="muted"><?= e($l['actor_name'] !== '' ? $l['actor_name'] : '—') ?></td>
  </tr>
<?php endforeach; ?>
<?php if ($hasMore): $older = url('/admin/log?before=' . end($rows)['id']); ?>
  <tr id="log-more">
    <td colspan="3" class="empty">
      <a class="btn btn-ghost" href="<?= e($older) ?>" hx-get="<?= e($older) ?>" hx-target="#log-more" hx-swap="outerHTML">Show older</a>
    </td>
  </tr>
<?php endif; ?>
