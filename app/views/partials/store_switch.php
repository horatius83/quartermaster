<?php $open = (int)$store['is_open'] === 1; ?>
<button class="btn switch" type="button" role="switch" aria-checked="<?= $open ? 'true' : 'false' ?>"
  hx-post="<?= e(url('/dm/stores/' . $store['id'] . '/toggle')) ?>" hx-target="this" hx-swap="outerHTML">
  <?= $open ? 'Open to players' : 'Hidden from players' ?>
</button>
