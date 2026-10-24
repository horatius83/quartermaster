<?php $p = coin_parts((int)$char['coin_cp']); ?>
<div class="player-main">
  <main class="main">
    <header class="page-head">
      <div>
        <p class="muted" style="margin:0"><?= e($char['class_label']) ?></p>
        <h1 class="page-title"><?= e($char['name']) ?>’s Pack</h1>
      </div>
      <div class="card coins" style="font-size:22px">
        <span><strong class="gp"><?= number_format($p['gp']) ?></strong> gp</span>
        <span><strong class="sp"><?= $p['sp'] ?></strong> sp</span>
        <span><strong class="cp"><?= $p['cp'] ?></strong> cp</span>
      </div>
    </header>

    <div class="table-wrap">
      <table class="ledger" style="min-width:560px">
        <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Weight</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><div class="item-name"><?= e($r['name']) ?></div><?php if ($r['notes'] !== ''): ?><div class="muted small"><?= e($r['notes']) ?></div><?php endif; ?></td>
              <td><?= category_tag($r['category'], $r['category_slug']) ?></td>
              <td><?= (int)$r['quantity'] ?></td>
              <td class="nowrap"><?= e(fmt_weight((float)$r['weight'] * (int)$r['quantity'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="4" class="empty">Your pack is empty.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= render('partials/carried_oob', ['char' => $char, 'carried' => $carried, 'inline' => true]) ?>

    <?php if ($orders): ?>
      <h2 class="section-title">Your orders</h2>
      <ul class="list-plain card" style="padding:0 18px">
        <?php foreach ($orders as $o):
          $label = ['submitted' => 'Waiting on DM', 'approved' => 'Approved', 'declined' => 'Declined'][$o['status']] ?? $o['status']; ?>
          <li style="padding:12px 0">
            <div class="row-between">
              <span><strong><?= e($o['store_name']) ?></strong> · <?= (int)$o['unit_count'] ?> items · <?= e(coins($o['total_cp'])) ?></span>
              <span class="status status-<?= e($o['status']) ?>"><?= e($label) ?></span>
            </div>
            <?php if ($o['status'] === 'declined' && $o['dm_note'] !== ''): ?>
              <div class="muted small">DM: “<?= e($o['dm_note']) ?>”</div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </main>
</div>
