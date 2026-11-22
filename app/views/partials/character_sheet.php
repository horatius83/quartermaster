<?php
/**
 * @var array $char @var bool $shared whether the campaign shares a purse, which holds the coin instead
 * hx-disinherit keeps the form's target and swap to itself: the party purse link inside it is a boosted link, which
 * would otherwise swap the whole page it fetches into the sheet's place.
 */
?>
<form id="sheet" class="card form-row" style="padding:12px 16px"
      hx-post="<?= e(url('/dm/inventory/' . $char['id'] . '/sheet')) ?>" hx-target="this" hx-swap="outerHTML" hx-disinherit="*">
  <?php if ($shared): ?>
    <p class="muted small" style="margin:0;align-self:center;max-width:150px">Their coin is in the <a href="<?= e(url('/dm/party-stash')) ?>">party purse</a>.</p>
  <?php else: ?>
    <?= render('partials/coin_fields', ['coin' => (int)$char['coin_cp']]) ?>
  <?php endif; ?>
  <label class="field" style="width:150px">Class &amp; level <input name="class_label" maxlength="100" value="<?= e($char['class_label']) ?>"></label>
  <label class="field" style="width:90px">Carry (lb) <input name="carry_capacity" type="number" min="0" step="1" value="<?= e((float)$char['carry_capacity']) ?>"></label>
  <button class="btn btn-dark" type="submit">Save</button>
</form>
