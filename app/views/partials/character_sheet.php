<?php $p = coin_parts((int)$char['coin_cp']); ?>
<form id="sheet" class="card form-row" style="padding:12px 16px"
      hx-post="<?= e(url('/dm/inventory/' . $char['id'] . '/sheet')) ?>" hx-target="this" hx-swap="outerHTML">
  <input type="hidden" name="coin_was" value="<?= (int)$char['coin_cp'] ?>">
  <label class="field" style="width:84px">Gold <input name="gp" type="number" min="0" value="<?= $p['gp'] ?>"></label>
  <label class="field" style="width:70px">Silver <input name="sp" type="number" min="0" value="<?= $p['sp'] ?>"></label>
  <label class="field" style="width:70px">Copper <input name="cp" type="number" min="0" value="<?= $p['cp'] ?>"></label>
  <label class="field" style="width:150px">Class &amp; level <input name="class_label" maxlength="100" value="<?= e($char['class_label']) ?>"></label>
  <label class="field" style="width:90px">Carry (lb) <input name="carry_capacity" type="number" min="0" step="1" value="<?= e((float)$char['carry_capacity']) ?>"></label>
  <button class="btn btn-dark" type="submit">Save</button>
</form>
