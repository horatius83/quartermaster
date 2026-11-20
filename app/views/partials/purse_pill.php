<?php $p = coin_parts((int)$coin); $name = !empty($party) ? 'Party purse' : 'Purse'; ?>
<span id="purse" class="purse-pill" aria-label="<?= $name ?>: <?= e(coins((int)$coin)) ?>"<?= !empty($oob) ? ' hx-swap-oob="true"' : '' ?>>
  <?php if (!empty($party)): ?><span class="small">Party</span><?php endif; ?>
  <span><strong class="gp"><?= number_format($p['gp']) ?></strong> gp</span>
  <span><strong><?= $p['sp'] ?></strong> sp</span>
  <span><strong><?= $p['cp'] ?></strong> cp</span>
</span>
