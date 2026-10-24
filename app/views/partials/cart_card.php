<?php
$pending = $cart['status'] === 'submitted';
$short = $cart['total_cp'] > (int)$cart['coin_cp'];
$labels = ['submitted' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined', 'open' => 'Open'];
$when = $pending ? 'sent ' . time_ago($cart['submitted_at']) : strtolower($labels[$cart['status']]) . ' ' . time_ago($cart['decided_at']);
?>
<article id="cart-<?= (int)$cart['id'] ?>" class="card card-stack">
  <div class="row-between" style="align-items:flex-start">
    <div>
      <h3 class="card-title" style="font-size:34px"><?= e($cart['character_name']) ?></h3>
      <div class="muted small">at <?= e($cart['store_name']) ?> · <?= e($when) ?></div>
    </div>
    <span class="status status-<?= e($cart['status']) ?>"><?= e($labels[$cart['status']]) ?></span>
  </div>

  <ul class="list-plain" style="border-top:1px dashed var(--rule)">
    <?php foreach ($cart['lines'] as $l): ?>
      <li class="cart-line">
        <span class="name"><?= e($l['name']) ?><?= str_replace('class="tag ', 'class="tag tag-sm ', category_tag($l['category'], $l['category_slug'])) ?>
          <?php if ($pending && $l['stock_left'] !== null && (int)$l['stock_left'] < (int)$l['quantity']): ?>
            <br><span class="small" style="color:var(--clay-dark)">Only <?= (int)$l['stock_left'] ?> in stock</span>
          <?php endif; ?>
        </span>
        <?php if ($pending): ?>
          <label class="visually-hidden" for="cq-<?= (int)$l['id'] ?>">Quantity of <?= e($l['name']) ?></label>
          <input class="input-sm" style="width:64px" id="cq-<?= (int)$l['id'] ?>" type="number" min="0" name="quantity" value="<?= (int)$l['quantity'] ?>"
            hx-post="<?= e(url('/dm/cart-items/' . $l['id'])) ?>" hx-trigger="change" hx-target="closest article" hx-swap="outerHTML">
        <?php else: ?>
          <span class="muted"><?= (int)$l['quantity'] ?> ×</span>
        <?php endif; ?>
        <span class="nowrap" style="min-width:70px;text-align:right"><?= e(coins((int)$l['quantity'] * (int)$l['unit_price_cp'])) ?></span>
      </li>
    <?php endforeach; ?>
    <?php if (!$cart['lines']): ?><li class="empty">Empty cart.</li><?php endif; ?>
  </ul>

  <div class="row-between" style="align-items:baseline">
    <span class="muted">Total</span>
    <span class="price price-lg"><?= e(coins($cart['total_cp'])) ?></span>
  </div>

  <?php if ($cart['player_note'] !== ''): ?>
    <p class="notice notice-warn" style="margin:0">“<?= e($cart['player_note']) ?>”</p>
  <?php endif; ?>

  <?php if ($pending): ?>
    <p class="notice <?= $short ? 'notice-err' : 'notice-ok' ?>" style="margin:0">
      <?php if ($short): ?>
        Short on coin: purse holds <?= e(coins((int)$cart['coin_cp'])) ?>, needs <?= e(coins($cart['total_cp'] - (int)$cart['coin_cp'])) ?> more.
      <?php else: ?>
        Purse: <?= e(coins((int)$cart['coin_cp'])) ?> → <?= e(coins((int)$cart['coin_cp'] - $cart['total_cp'])) ?> after purchase.
      <?php endif; ?>
    </p>
    <div class="actions">
      <button class="btn btn-go" type="button" style="flex:1 1 120px"
        hx-post="<?= e(url('/dm/carts/' . $cart['id'] . '/approve')) ?>" hx-target="closest article" hx-swap="outerHTML">Approve</button>
    </div>
    <form class="form-row" hx-post="<?= e(url('/dm/carts/' . $cart['id'] . '/decline')) ?>" hx-target="closest article" hx-swap="outerHTML">
      <label class="field grow">Reason <span class="small">(optional, shown to the player)</span>
        <input name="dm_note" maxlength="500" placeholder="The shopkeeper won’t haggle…">
      </label>
      <button class="btn btn-ghost" type="submit">Decline</button>
    </form>
  <?php elseif ($cart['dm_note'] !== ''): ?>
    <p class="muted small" style="margin:0">Your note: “<?= e($cart['dm_note']) ?>”</p>
  <?php endif; ?>
</article>
