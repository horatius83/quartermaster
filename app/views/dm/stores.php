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
        <?php // Each character's share of the shelf prices: 100% is the price on the shelf, 120% a fifth more, 90% a tenth off. Players only see what they pay. ?>
        <fieldset class="box mid shop-access">
          <legend>Who can shop here</legend>
          <?php if ($characters): ?><div class="pct-hint muted small" aria-hidden="true">pays, % of shelf price</div><?php endif; ?>
          <?php foreach ($characters as $c): $cid = (int)$c['id']; ?>
            <div class="access-row">
              <?php // Names shown, so a character who joins while this page is open keeps their place (see posted_party_choice()). ?>
              <input type="hidden" name="access_shown[]" value="<?= $cid ?>">
              <label class="check"><input type="checkbox" name="access[]" value="<?= $cid ?>"<?= ($access === null || in_array($cid, $access, true)) ? ' checked' : '' ?>> <?= e($c['name']) ?></label>
              <span class="pct-field">
                <label class="visually-hidden" for="pct-<?= $cid ?>">Prices for <?= e($c['name']) ?>, as a percentage</label>
                <input class="input-sm<?= ($pcts[$cid] ?? 100) !== 100 ? ' pct-biased' : '' ?>" style="width:72px" id="pct-<?= $cid ?>" name="pct[<?= $cid ?>]"
                  type="number" min="0" max="<?= MAX_PRICE_PCT ?>" step="1" inputmode="numeric" value="<?= (int)($pcts[$cid] ?? 100) ?>">%
              </span>
            </div>
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
      <?= render('partials/item_suggest', ['id' => 'stock-item', 'label' => 'Stock an item', 'hint' => '(new names are added to the catalog)', 'placeholder' => 'Search the catalog…']) ?>
      <?php // For a spell scroll, typing here suggests the spells a scroll of its level can hold (app.js handles the list, as for the item). ?>
      <div class="field mid suggest">
        <label for="stock-notes-input">Notes</label>
        <input id="stock-notes-input" name="notes" maxlength="<?= STOCK_NOTES_MAX ?>" autocomplete="off" placeholder="optional, e.g. Magic Missile"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="stock-notes-suggest"
               hx-get="<?= e(url('/dm/stock/spells')) ?>" hx-include="#stock-item-input" hx-trigger="input changed delay:250ms, qm-refresh delay:250ms"
               hx-sync="this:replace" hx-target="#stock-notes-suggest" hx-swap="innerHTML">
        <ul id="stock-notes-suggest" class="suggest-list" role="listbox" aria-label="Spells" hidden></ul>
      </div>
      <label class="field tiny">Price <input name="price" placeholder="catalog"></label>
      <label class="field tiny">In stock <input name="quantity" placeholder="∞"></label>
      <label class="field mid">Category
        <select name="category_id">
          <?php foreach (categories() as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field tiny">Weight <input name="weight" type="number" min="0" step="0.01" value="0"></label>
      <?php // Picking an item (or typing its name) shows what the catalog says about it, and app.js fills in Price, Weight and Category from it. ?>
      <div id="stock-item-details" class="field item-details" style="flex:1 1 100%"
           hx-get="<?= e(url('/dm/catalog/details')) ?>" hx-include="#stock-item-input" hx-vals='{"for": "stock-item"}'
           hx-trigger="qm-item-changed from:#stock-item-input, input changed delay:300ms from:#stock-item-input" hx-sync="this:replace" hx-target="this" hx-swap="innerHTML">
        <?= render('partials/item_details', ['id' => 'stock-item', 'item' => null, 'name' => '']) ?>
      </div>
      <button class="btn btn-primary" type="submit">Add to shelf</button>
    </form>

    <div class="table-wrap">
      <table class="ledger">
        <caption>On the shelves</caption>
        <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>In stock <span class="small">(blank = ∞)</span></th><th>Seen by</th><th class="right">Actions</th></tr></thead>
        <?= render('partials/stock_body', ['stock' => $stock, 'party' => $characters]) ?>
      </table>
    </div>
  </section>
  <?php endif; ?>
</main>
