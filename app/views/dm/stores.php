<main class="main" style="flex-direction:row;flex-wrap:wrap;align-items:flex-start;gap:28px">
  <section class="card-stack" style="flex:1 1 260px" aria-labelledby="list-h">
    <h1 id="list-h" class="page-title" style="font-size:48px">Stores</h1>
    <nav class="card-stack" aria-label="Stores">
      <?php foreach ($stores as $s): ?>
        <a class="pill" style="flex-direction:column;align-items:flex-start;padding:10px 16px"
           href="<?= e(url('/dm/stores/' . $s['id'])) ?>"<?= $store && (int)$s['id'] === (int)$store['id'] ? ' aria-current="page"' : '' ?>>
          <span style="font-size:21px"><?= e($s['name']) ?></span>
          <span class="small" style="opacity:.85"><?= e($s['kind'] ?: 'Shop') ?> · <?= (int)$s['stock_count'] ?> items · <?= $s['is_open'] ? 'Open' : 'Hidden' ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <form class="card card-dashed card-stack" method="post" action="<?= e(url('/dm/stores')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="from" value="<?= $store ? (int)$store['id'] : '' ?>">
      <label class="field">New store name <input name="name" required maxlength="100" placeholder="The Rusty Anvil" value="<?= e($old['name'] ?? '') ?>"></label>
      <label class="field">Kind of shop <input name="kind" maxlength="100" placeholder="Smithy" value="<?= e($old['kind'] ?? '') ?>"></label>
      <button class="btn btn-ghost" type="submit">+ Open a new store</button>
    </form>
  </section>

  <?php if (!$store): ?>
    <section class="card empty" style="flex:999 1 560px">No stores yet. Name your first one to start stocking shelves.</section>
  <?php else: ?>
  <section class="card-stack" style="flex:999 1 560px;min-width:0;gap:20px" aria-labelledby="store-h">
    <form class="card card-stack" hx-post="<?= e(url('/dm/stores/' . $store['id'])) ?>">
      <div class="row-between" style="flex-wrap:wrap">
        <h2 id="store-h" class="page-title" style="font-size:44px"><?= e($store['name']) ?></h2>
        <?= render('partials/store_switch', ['store' => $store]) ?>
      </div>
      <div class="form-row">
        <label class="field mid">Name <input name="name" value="<?= e($store['name']) ?>" maxlength="100" required></label>
        <label class="field mid">Kind of shop <input name="kind" value="<?= e($store['kind']) ?>" maxlength="100"></label>
      </div>
      <div class="form-row" style="align-items:stretch">
        <label class="field grow">Shopkeeper’s note to players
          <textarea name="blurb" rows="2" maxlength="1000"><?= e($store['blurb']) ?></textarea>
        </label>
        <fieldset class="box mid">
          <legend>Who can shop here</legend>
          <?php foreach ($characters as $c): ?>
            <label class="check"><input type="checkbox" name="access[]" value="<?= (int)$c['id'] ?>"<?= (!$access || in_array((int)$c['id'], $access, true)) ? ' checked' : '' ?>> <?= e($c['name']) ?></label>
          <?php endforeach; ?>
          <?php if (!$characters): ?><span class="muted small">No characters yet.</span><?php endif; ?>
        </fieldset>
      </div>
      <div class="actions">
        <button class="btn btn-dark" type="submit">Save details</button>
        <button class="link-btn danger" type="button" hx-post="<?= e(url('/dm/stores/' . $store['id'] . '/delete')) ?>"
          hx-confirm="Close <?= e($store['name']) ?> for good? Its shelves and any carts here will be removed.">Delete store</button>
      </div>
    </form>

    <form id="stock-form" class="card card-dashed form-row"
          hx-post="<?= e(url('/dm/stores/' . $store['id'] . '/stock')) ?>" hx-target="#stock-body" hx-swap="outerHTML">
      <label class="field grow">Stock an item <span class="small">(new names are added to the catalog)</span>
        <input name="item" list="item-suggest" required maxlength="150" autocomplete="off" placeholder="Search the catalog…"
               hx-get="<?= e(url('/dm/catalog/suggest')) ?>" hx-trigger="input changed delay:250ms" hx-target="#item-suggest" hx-swap="innerHTML">
        <datalist id="item-suggest"></datalist>
      </label>
      <label class="field tiny">Price <input name="price" placeholder="catalog"></label>
      <label class="field tiny">In stock <input name="quantity" placeholder="∞"></label>
      <label class="field mid">Category
        <select name="category_id">
          <?php foreach (categories() as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field tiny">Weight <input name="weight" type="number" min="0" step="0.01" value="0"></label>
      <button class="btn btn-primary" type="submit">Add to shelf</button>
    </form>

    <div class="table-wrap">
      <table class="ledger">
        <caption>On the shelves</caption>
        <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>In stock <span class="small">(blank = ∞)</span></th><th class="right">Actions</th></tr></thead>
        <?= render('partials/stock_body', ['stock' => $stock]) ?>
      </table>
    </div>
  </section>
  <?php endif; ?>
</main>
