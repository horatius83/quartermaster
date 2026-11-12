<?php
/** @var array $sale from sale_detail() */
$open = in_array($sale['status'], ['pending', 'countered'], true);
$labels = ['pending' => 'Pending', 'countered' => 'Countered', 'sold' => 'Sold', 'rejected' => 'Rejected', 'declined' => 'Declined'];
$when = match ($sale['status']) {
    'pending'   => 'sent ' . time_ago($sale['created_at']),
    'countered' => 'you countered ' . time_ago($sale['countered_at']),
    'declined'  => 'player declined ' . time_ago($sale['decided_at']),
    default     => strtolower($labels[$sale['status']]) . ' ' . time_ago($sale['decided_at']),
};
$qty = (int)$sale['quantity'];
$list = (int)$sale['list_price_cp'];
$fid = 'sale-' . (int)$sale['id'];
?>
<article id="<?= $fid ?>" class="card card-stack sale-card">
  <div class="row-between" style="align-items:flex-start">
    <div>
      <h3 class="card-title" style="font-size:34px"><?= e($sale['character_name']) ?></h3>
      <div class="muted small">wants to sell · <?= e($when) ?></div>
    </div>
    <span class="status status-<?= e($sale['status']) ?>"><?= e($labels[$sale['status']]) ?></span>
  </div>

  <ul class="list-plain" style="border-top:1px dashed var(--rule)">
    <li class="cart-line">
      <span class="name"><?= e($sale['name']) ?><?= str_replace('class="tag ', 'class="tag tag-sm ', category_tag($sale['category'], $sale['category_slug'])) ?>
        <?php if ($sale['notes'] !== ''): ?><br><span class="muted small"><?= e($sale['notes']) ?></span><?php endif; ?>
        <?php if ($open && (int)$sale['held'] < $qty): ?>
          <br><span class="small" style="color:var(--clay-dark)"><?= (int)$sale['held'] ? 'Only ' . (int)$sale['held'] . ' left in the pack' : 'No longer in the pack' ?></span>
        <?php endif; ?>
      </span>
      <span class="muted"><?= $qty ?> ×</span>
      <span class="nowrap muted small" style="min-width:70px;text-align:right"><?= $list ? e(coins($list)) . ' each in the catalog' : '' ?></span>
    </li>
  </ul>

  <div class="row-between" style="align-items:baseline">
    <span class="muted">Asking</span>
    <span class="price price-lg"><?= e(coins((int)$sale['asking_cp'])) ?></span>
  </div>
  <?php if ($sale['offer_cp'] !== null): ?>
    <div class="row-between" style="align-items:baseline">
      <span class="muted">Your counter-offer</span>
      <span class="price price-lg"><?= e(coins((int)$sale['offer_cp'])) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($sale['status'] === 'sold'): ?>
    <div class="row-between" style="align-items:baseline">
      <span class="muted">Sold for</span>
      <span class="price price-lg"><?= e(coins($sale['price_cp'])) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($sale['player_note'] !== ''): ?>
    <p class="notice notice-warn" style="margin:0">“<?= e($sale['player_note']) ?>”</p>
  <?php endif; ?>

  <?php if ($sale['status'] === 'countered'): ?>
    <p class="notice notice-warn" style="margin:0">Waiting on the player to accept or decline. You can change your offer, take it back, or accept their asking price after all.</p>
  <?php endif; ?>
  <?php if ($open): ?>
    <p class="notice notice-ok" style="margin:0">Purse: <?= e(coins((int)$sale['coin_cp'])) ?> → <?= e(coins((int)$sale['coin_cp'] + (int)$sale['asking_cp'])) ?> at the asking price.</p>
    <div class="actions">
      <button class="btn btn-go" type="button" style="flex:1 1 120px"
        hx-post="<?= e(url('/dm/sales/' . $sale['id'] . '/accept')) ?>" hx-target="closest article" hx-swap="outerHTML">Accept <?= e(coins((int)$sale['asking_cp'])) ?></button>
    </div>
  <?php endif; ?>

  <?php if ($open): ?>
    <form class="card-stack" hx-post="<?= e(url('/dm/sales/' . $sale['id'] . '/counter')) ?>" hx-target="closest article" hx-swap="outerHTML">
      <div class="form-row">
        <label class="field mid" for="<?= $fid ?>-offer">Counter-offer, for all
          <input id="<?= $fid ?>-offer" name="offer" maxlength="40" placeholder="e.g. 6 gp"<?= $sale['offer_cp'] !== null ? ' value="' . e(coins((int)$sale['offer_cp'])) . '"' : '' ?>>
        </label>
        <label class="field grow" for="<?= $fid ?>-note">Note <span class="small">(optional, shown to the player)</span>
          <input id="<?= $fid ?>-note" name="dm_note" maxlength="500" placeholder="The fence won’t go higher…" value="<?= e($sale['dm_note']) ?>">
        </label>
      </div>
      <div class="actions">
        <button class="btn" type="submit"><?= $sale['offer_cp'] !== null ? 'Change counter-offer' : 'Send counter-offer' ?></button>
        <button class="btn btn-ghost" type="button"
          hx-post="<?= e(url('/dm/sales/' . $sale['id'] . '/reject')) ?>" hx-target="closest article" hx-swap="outerHTML"><?= $sale['status'] === 'countered' ? 'Withdraw and reject' : 'Reject' ?></button>
      </div>
    </form>
  <?php elseif ($sale['dm_note'] !== ''): ?>
    <p class="muted small" style="margin:0">Your note: “<?= e($sale['dm_note']) ?>”</p>
  <?php endif; ?>
</article>
