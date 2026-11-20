<?php
/**
 * Gold, silver and copper inputs for a purse, with what it held when drawn, so posted_coin_delta() applies only the change.
 * @var int $coin
 */
$p = coin_parts((int)$coin);
?>
<input type="hidden" name="coin_was" value="<?= (int)$coin ?>">
<label class="field" style="width:84px">Gold <input name="gp" type="number" min="0" value="<?= $p['gp'] ?>"></label>
<label class="field" style="width:70px">Silver <input name="sp" type="number" min="0" value="<?= $p['sp'] ?>"></label>
<label class="field" style="width:70px">Copper <input name="cp" type="number" min="0" value="<?= $p['cp'] ?>"></label>
