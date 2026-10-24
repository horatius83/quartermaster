<?php $over = $carried > (float)$char['carry_capacity']; ?>
<p id="carried" class="muted right" style="margin:0"<?= empty($inline) ? ' hx-swap-oob="true"' : '' ?>>
  Total carried: <strong style="color:var(--ink)"><?= e(fmt_weight($carried)) ?></strong> of <?= e(fmt_weight($char['carry_capacity'])) ?>
  <?php if ($over): ?><span class="status status-submitted" style="margin-left:6px">Encumbered</span><?php endif; ?>
</p>
