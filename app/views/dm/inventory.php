<main class="main">
  <nav class="pills" aria-label="Choose a character">
    <?php foreach ($characters as $c): ?>
      <a class="pill" href="<?= e(url('/dm/inventory/' . $c['id'])) ?>"<?= (int)$c['id'] === (int)$char['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <header class="page-head">
    <h1 class="page-title"><?= e($char['name']) ?>’s Pack</h1>
    <?= render('partials/character_sheet', ['char' => $char]) ?>
  </header>

  <section class="card card-dashed card-stack" aria-labelledby="give-h">
    <h2 id="give-h" class="section-title" style="font-size:28px">Give an item</h2>
    <p class="muted small" style="margin:-6px 0 0">Category, weight and value are only used when the item isn’t in the catalog yet.</p>
    <form id="give-form" class="form-row"
          hx-post="<?= e(url('/dm/inventory/' . $char['id'] . '/give')) ?>"
          hx-target="#inv-body" hx-swap="outerHTML">
      <label class="field grow">Item
        <input name="item" list="item-suggest" required maxlength="150" autocomplete="off" placeholder="e.g. Potion of Healing"
               hx-get="<?= e(url('/dm/catalog/suggest')) ?>" hx-trigger="input changed delay:250ms" hx-target="#item-suggest" hx-swap="innerHTML">
        <datalist id="item-suggest"></datalist>
      </label>
      <label class="field mid">Category
        <select name="category_id">
          <?php foreach (categories() as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field tiny">Qty
        <input name="quantity" type="number" min="1" value="1">
      </label>
      <label class="field tiny">Weight (lb)
        <input name="weight" type="number" min="0" step="0.01" value="0">
      </label>
      <label class="field tiny">Value
        <input name="price" placeholder="15 gp">
      </label>
      <label class="field mid">Notes
        <input name="notes" maxlength="255" placeholder="+1, attuned…">
      </label>
      <button class="btn btn-primary" type="submit">Add to pack</button>
    </form>
  </section>

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
        <?= render('partials/inventory_body', ['rows' => $rows, 'char' => $char, 'filtered' => $cat !== '']) ?>
      </table>
    </div>
    <?= render('partials/carried_oob', ['char' => $char, 'carried' => $carried, 'inline' => true]) ?>
  </section>
</main>
