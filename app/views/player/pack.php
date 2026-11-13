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
      <table class="ledger" style="min-width:640px">
        <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Weight</th><th><span class="visually-hidden">Sell</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $sid = 'sell-' . (int)$r['id']; ?>
            <tr>
              <td><div class="item-name"><?= e($r['name']) ?></div><?php if ($r['notes'] !== ''): ?><div class="muted small"><?= e($r['notes']) ?></div><?php endif; ?></td>
              <td><?= category_tag($r['category'], $r['category_slug']) ?></td>
              <td><?= (int)$r['quantity'] ?></td>
              <td class="nowrap"><?= e(fmt_weight((float)$r['weight'] * (int)$r['quantity'])) ?></td>
              <td class="right">
                <?php if ($r['sellable'] > 0): ?>
                  <details class="inline sell-details">
                    <summary aria-label="Sell <?= e($r['name']) ?>">Sell…</summary>
                    <form class="sell-form card-stack" method="post" action="<?= e(url('/pack/' . $r['id'] . '/sell')) ?>">
                      <?= csrf_field() ?>
                      <div class="form-row">
                        <label class="field tiny" for="<?= $sid ?>-qty">How many
                          <input id="<?= $sid ?>-qty" name="quantity" type="number" min="1" max="<?= (int)$r['sellable'] ?>" value="<?= (int)$r['sellable'] ?>" required>
                        </label>
                        <label class="field mid" for="<?= $sid ?>-price">Asking price, for all
                          <input id="<?= $sid ?>-price" name="price" maxlength="40" placeholder="e.g. 8 gp" required>
                        </label>
                      </div>
                      <?php if ((int)$r['price_cp'] > 0): ?>
                        <p class="muted small" style="margin:0">The catalog prices it at <?= e(coins((int)$r['price_cp'])) ?> each.</p>
                      <?php endif; ?>
                      <label class="field" for="<?= $sid ?>-note">Note to the DM (optional)
                        <input id="<?= $sid ?>-note" name="player_note" maxlength="500" placeholder="Who are you selling to?">
                      </label>
                      <button class="btn btn-primary" type="submit">Offer to the DM</button>
                    </form>
                  </details>
                <?php else: ?>
                  <span class="muted small">On offer</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="5" class="empty">Your pack is empty.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= render('partials/carried_oob', ['char' => $char, 'carried' => $carried, 'inline' => true]) ?>

    <?php if ($offers || $sold): ?>
      <h2 class="section-title">Your offers to sell</h2>
      <ul class="list-plain card" style="padding:0 18px" id="sales">
        <?php foreach (array_merge($offers, $sold) as $s):
          $label = ['pending' => 'Waiting on DM', 'countered' => 'Counter-offer', 'sold' => 'Sold', 'rejected' => 'Rejected', 'declined' => 'You declined'][$s['status']] ?? $s['status']; ?>
          <li class="sale-offer" style="padding:12px 0">
            <div class="row-between">
              <span><strong><?= (int)$s['quantity'] ?> × <?= e($s['name']) ?></strong><?php if ($s['notes'] !== ''): ?> <span class="muted small">(<?= e($s['notes']) ?>)</span><?php endif; ?>
                · you asked <?= e(coins((int)$s['asking_cp'])) ?><?php if ($s['status'] === 'sold'): ?> · sold for <?= e(coins($s['price_cp'])) ?><?php endif; ?></span>
              <span class="status status-<?= e($s['status']) ?>"><?= e($label) ?></span>
            </div>
            <?php if ($s['dm_note'] !== ''): ?><div class="muted small">DM: “<?= e($s['dm_note']) ?>”</div><?php endif; ?>
            <?php if ($s['status'] === 'countered'): ?>
              <p class="notice notice-warn" style="margin:8px 0">Your DM offers <strong><?= e(coins((int)$s['offer_cp'])) ?></strong> for the lot.</p>
              <div class="actions">
                <form method="post" action="<?= e(url('/pack/sales/' . $s['id'] . '/accept')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="offer_cp" value="<?= (int)$s['offer_cp'] ?>">
                  <button class="btn btn-go" type="submit">Accept <?= e(coins((int)$s['offer_cp'])) ?></button>
                </form>
                <form method="post" action="<?= e(url('/pack/sales/' . $s['id'] . '/decline')) ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn-ghost" type="submit">Decline</button>
                </form>
              </div>
            <?php elseif ($s['status'] === 'pending'): ?>
              <form method="post" action="<?= e(url('/pack/sales/' . $s['id'] . '/withdraw')) ?>" style="margin-top:4px">
                <?= csrf_field() ?>
                <button class="link-btn" type="submit">Withdraw offer</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

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
