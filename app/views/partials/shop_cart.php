<?php
$coin = (int)$char['coin_cp'];
$waiting = $cart && $cart['status'] === 'submitted';
$lines = $cart['lines'] ?? [];
$total = $cart['total_cp'] ?? 0;
?>
<aside id="cart" class="card cart-panel" aria-labelledby="cart-h">
  <h2 id="cart-h" class="card-title" style="font-size:38px">Your cart</h2>
  <div class="muted small" style="margin-top:-8px">for <?= e($store['name']) ?></div>

  <?php if ($waiting): ?>
    <p class="notice notice-warn" style="margin:0">Waiting on your DM. You’ll see the result in <a href="<?= e(url('/pack')) ?>">My pack</a>.</p>
  <?php endif; ?>

  <?php if (!$lines): ?>
    <p class="muted" style="margin:8px 0">Nothing here yet. Browse the shelves and add what you need.</p>
  <?php else: ?>
    <ul class="list-plain">
      <?php foreach ($lines as $l): $u = url('/shop/line/' . $l['id']); ?>
        <li class="cart-line">
          <span class="name"><?= e($l['name']) ?></span>
          <?php if ($waiting): ?>
            <span class="muted"><?= (int)$l['quantity'] ?> ×</span>
          <?php else: ?>
            <span class="stepper">
              <button class="round-btn" style="width:32px;height:32px" type="button" aria-label="One fewer <?= e($l['name']) ?>"
                hx-post="<?= e($u) ?>" hx-vals='{"delta": -1}' hx-target="#cart" hx-swap="outerHTML">−</button>
              <output style="min-width:24px;font-size:18px"><?= (int)$l['quantity'] ?></output>
              <button class="round-btn" style="width:32px;height:32px" type="button" aria-label="One more <?= e($l['name']) ?>"
                hx-post="<?= e($u) ?>" hx-vals='{"delta": 1}' hx-target="#cart" hx-swap="outerHTML">+</button>
            </span>
          <?php endif; ?>
          <span class="nowrap" style="min-width:64px;text-align:right"><?= e(coins((int)$l['quantity'] * (int)$l['unit_price_cp'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="row-between" style="align-items:baseline">
    <span class="muted">Total</span>
    <span class="price price-lg"><?= e(coins($total)) ?></span>
  </div>

  <?php if ($lines): ?>
    <p class="notice <?= $total > $coin ? 'notice-err' : 'notice-ok' ?>" style="margin:0">
      <?= $total > $coin
        ? 'That’s ' . e(coins($total - $coin)) . ' more than your purse holds.'
        : 'You’ll have ' . e(coins($coin - $total)) . ' left.' ?>
    </p>
  <?php endif; ?>

  <?php if ($waiting): ?>
    <?php if ($cart['player_note'] !== ''): ?><p class="muted small" style="margin:0">Your note: “<?= e($cart['player_note']) ?>”</p><?php endif; ?>
    <button class="btn btn-ghost" type="button" hx-post="<?= e(url('/shop/' . $store['id'] . '/withdraw')) ?>" hx-target="#cart" hx-swap="outerHTML">Withdraw to make changes</button>
  <?php elseif ($lines): ?>
    <form class="card-stack" hx-post="<?= e(url('/shop/' . $store['id'] . '/submit')) ?>" hx-target="#cart" hx-swap="outerHTML">
      <label class="field">Note to the DM (optional)
        <textarea name="player_note" rows="2" maxlength="500" placeholder="Haggling? Trading something in?"></textarea>
      </label>
      <button class="btn btn-primary btn-big" type="submit">Send to DM for approval</button>
      <p class="muted small" style="margin:0;text-align:center">Coin is only spent once your DM approves.</p>
    </form>
  <?php endif; ?>
</aside>
