<main class="main">
  <?= render('partials/pack_pills', ['characters' => $characters, 'current' => (int)$char['id'], 'showStash' => $showStash]) ?>

  <header class="page-head">
    <div>
      <h1 class="page-title"><?= e($char['name']) ?>’s Pack</h1>
      <a class="small" href="<?= e(url('/dm/history/' . $char['id'])) ?>">See what’s changed</a>
    </div>
    <?= render('partials/character_sheet', ['char' => $char, 'shared' => $shared]) ?>
  </header>

  <?= render('partials/give_form', [
      'title' => 'Give an item', 'action' => url('/dm/inventory/' . $char['id'] . '/give'),
      'placeholder' => 'e.g. Potion of Healing', 'button' => 'Add to pack',
  ]) ?>

  <section class="card-stack" aria-labelledby="items-h">
    <div class="row-between" style="flex-wrap:wrap">
      <h2 id="items-h" class="section-title">Carried items</h2>
      <?= render('partials/inventory_filters', ['char' => $char, 'cat' => $cat, 'oob' => false]) ?>
    </div>
    <div class="table-wrap">
      <table class="ledger">
        <thead>
          <tr><th>Item</th><th>Category</th><th>Quantity</th><th>Weight</th><th>Value each</th><th class="right">Actions</th></tr>
        </thead>
        <?= render('partials/inventory_body', ['rows' => $rows, 'char' => $char, 'filtered' => $cat !== '', 'shared' => $shared]) ?>
      </table>
    </div>
    <?= render('partials/carried_oob', ['char' => $char, 'carried' => $carried, 'inline' => true]) ?>
  </section>
</main>
