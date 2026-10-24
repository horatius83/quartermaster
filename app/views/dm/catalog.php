<main class="main">
  <header>
    <h1 class="page-title">Item catalog</h1>
    <p class="muted" style="margin:6px 0 0;max-width:640px">Every item that can sit in a pack or on a shelf. Items you type into the give or stock forms are added here automatically.</p>
  </header>

  <form id="catalog-form" class="card card-dashed form-row" hx-post="<?= e(url('/dm/catalog')) ?>" hx-target="#catalog-body" hx-swap="outerHTML">
    <label class="field grow">New item <input name="name" required maxlength="150" placeholder="Bag of Holding"></label>
    <label class="field mid">Category
      <select name="category_id"><?php foreach (categories() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label class="field tiny">Weight (lb) <input name="weight" type="number" min="0" step="0.01" value="0"></label>
    <label class="field tiny">Price <input name="price" placeholder="15 gp"></label>
    <button class="btn btn-primary" type="submit">Add item</button>
  </form>

  <div class="form-row" role="search">
    <label class="field grow">Search
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Type to filter…"
        hx-get="<?= e(url('/dm/catalog')) ?>" hx-trigger="input changed delay:300ms, search" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="#cat-filter" hx-push-url="true">
    </label>
    <label class="field mid">Category
      <select id="cat-filter" name="cat" hx-get="<?= e(url('/dm/catalog')) ?>" hx-trigger="change" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="[name=q]" hx-push-url="true">
        <option value="">All categories</option>
        <?php foreach (categories() as $c): ?><option value="<?= e($c['slug']) ?>"<?= $cat === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="table-wrap">
    <table class="ledger">
      <thead><tr><th>Name</th><th>Category</th><th>Weight (lb)</th><th>Base price</th><th>In use</th><th class="right">Actions</th></tr></thead>
      <?= render('partials/catalog_body', ['items' => $items]) ?>
    </table>
  </div>
</main>
