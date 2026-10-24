<?php $p = coin_parts((int)$coin); ?>
<span id="purse" class="purse-pill" aria-label="Purse: <?= e(coins((int)$coin)) ?>"<?= !empty($oob) ? ' hx-swap-oob="true"' : '' ?>>
  <span><strong class="gp"><?= number_format($p['gp']) ?></strong> gp</span>
  <span><strong><?= $p['sp'] ?></strong> sp</span>
  <span><strong><?= $p['cp'] ?></strong> cp</span>
</span>
