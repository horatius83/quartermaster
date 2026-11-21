<?php /** The DM's editor for the party purse. @var int $coin */ ?>
<form id="party-purse" class="card form-row" style="padding:12px 16px"
      hx-post="<?= e(url('/dm/party-stash/purse')) ?>" hx-target="this" hx-swap="outerHTML">
  <?= render('partials/coin_fields', ['coin' => (int)$coin]) ?>
  <button class="btn btn-dark" type="submit">Save purse</button>
</form>
