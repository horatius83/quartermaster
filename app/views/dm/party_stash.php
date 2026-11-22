<main class="main">
  <?= render('partials/pack_pills', ['characters' => $characters, 'current' => 0, 'showStash' => true]) ?>

  <header class="page-head">
    <div>
      <h1 class="page-title">Party Stash</h1>
      <a class="small" href="<?= e(url('/dm/history/party')) ?>">See what’s changed</a>
    </div>
    <?php if ($shared): ?><?= render('partials/party_purse_form', ['coin' => (int)$purse['coin_cp']]) ?><?php endif; ?>
  </header>

  <?php if ($shared): ?>
    <p class="muted" style="margin:0;max-width:680px">The whole party shares this purse and stash. Carts are paid from the purse and sales paid into it. Players put things from their packs in the stash, and anyone can take them out.</p>

    <?= render('partials/give_form', [
        'title' => 'Add to the stash', 'action' => url('/dm/party-stash/give'),
        'placeholder' => 'e.g. Bag of Holding', 'button' => 'Add to stash',
    ]) ?>
  <?php else: ?>
    <p class="notice notice-warn" style="margin:0">This campaign no longer shares a purse, so players can’t see the stash. Give out what’s left, or share the purse again under <a href="<?= e(url('/dm/campaigns')) ?>">Campaigns</a>.</p>
  <?php endif; ?>

  <section class="card-stack" aria-labelledby="items-h">
    <h2 id="items-h" class="section-title">In the stash</h2>
    <div class="table-wrap">
      <table class="ledger">
        <thead>
          <tr><th>Item</th><th>Category</th><th>Quantity</th><th>Weight</th><th>Value each</th><th class="right">Actions</th></tr>
        </thead>
        <?= render('partials/inventory_body', ['rows' => $rows, 'stash' => true, 'filtered' => false, 'shared' => $shared]) ?>
      </table>
    </div>
  </section>
</main>
