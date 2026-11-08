<?php
$system = ruleset_label(campaign_ruleset());
// A row posts all its fields whenever one changes, so a row showing old values would save them over newer ones.
// To keep rows current: Back reloads from the server rather than a snapshot (hx-history), rows lock while the
// table redraws (hx-disabled-elt, here and on each row while it saves), and redraws wait for saves (assets/app.js).
$lockRows = 'hx-disabled-elt="' . e(CATALOG_ROW_FIELDS) . '"';
?>
<main class="main" hx-history="false">
  <header>
    <h1 class="page-title">Item catalog</h1>
    <p class="muted" style="margin:6px 0 0;max-width:640px">Every item that can sit in a pack or on a shelf in a <?= e($system) ?> campaign. Items you type into the give or stock forms are added here automatically. Every DM’s <?= e($system) ?> campaigns share these items.</p>
  </header>

  <?php if (!(int)current_campaign()['ruleset_open'] && in_array(campaign_ruleset(), SRD_RULESETS, true) && srd_status(campaign_ruleset())['present'] === 0): ?>
  <p class="muted" style="margin:0">The <?= e($system) ?> SRD weapons, armor and gear aren’t in this catalog yet. <?= admin_exists() ? 'The admin can load them from their dashboard.' : 'An admin can load them from their dashboard, once there is one: <a href="' . e(url('/dm/admin-account')) . '">create the admin account</a>.' ?></p>
  <?php endif; ?>

  <form id="catalog-form" class="card card-dashed form-row" hx-post="<?= e(url('/dm/catalog')) ?>" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="<?= e(CATALOG_SAVE_INCLUDES) ?>" <?= $lockRows ?>>
    <label class="field grow">New item <input name="name" required maxlength="150" placeholder="Bag of Holding"></label>
    <label class="field mid">Category
      <select name="category_id"><?php foreach (categories() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label class="field tiny">Weight (lb) <input name="weight" type="number" min="0" step="0.01" value="0"></label>
    <label class="field tiny">Price <input name="price" placeholder="15 gp"></label>
    <label class="field" style="flex:1 1 100%">Description <textarea name="description" rows="2" maxlength="1000" placeholder="Holds far more than it should."></textarea></label>
    <label class="field" style="flex:1 1 100%">Tags, separated by commas. Players see these in shops, in every <?= e($system) ?> campaign. <input name="tags" maxlength="255" placeholder="simple, ranged"></label>
    <button class="btn btn-primary" type="submit">Add item</button>
  </form>

  <div class="form-row" role="search">
    <label class="field grow">Search
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Type to filter…"
        hx-get="<?= e(url('/dm/catalog')) ?>" hx-trigger="input changed delay:300ms, search" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="#cat-filter, #tag-filter" hx-push-url="true" <?= $lockRows ?>>
    </label>
    <label class="field mid">Category
      <select id="cat-filter" name="cat" hx-get="<?= e(url('/dm/catalog')) ?>" hx-trigger="change" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="[name=q], #tag-filter" hx-push-url="true" <?= $lockRows ?>>
        <option value="">All categories</option>
        <?php foreach (categories() as $c): ?><option value="<?= e($c['slug']) ?>"<?= $cat === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?= render('partials/catalog_tag_filter', ['selected' => $tags]) ?>
  </div>

  <div class="table-wrap">
    <table class="ledger">
      <thead><tr><th>Name</th><th>Description and tags <span class="small">(players see tags)</span></th><th>Category</th><th>Weight (lb)</th><th>Base price</th><th>In use</th><th class="right">Actions</th></tr></thead>
      <?= render('partials/catalog_body', ['catalog' => $catalog, 'oob' => false]) ?>
    </table>
  </div>
  <?= render('partials/catalog_pager', ['catalog' => $catalog, 'oob' => false]) ?>
</main>
