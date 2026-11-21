<?php
/**
 * The DM's form for giving an item to a pack or the party stash; posted_give() reads it.
 * @var string $title @var string $action @var string $placeholder @var string $button
 */
?>
<section class="card card-dashed card-stack" aria-labelledby="give-h">
  <h2 id="give-h" class="section-title" style="font-size:28px"><?= e($title) ?></h2>
  <p class="muted small" style="margin:-6px 0 0">Category, weight and value are only used when the item isn’t in the catalog yet.</p>
  <form id="give-form" class="form-row" hx-post="<?= e($action) ?>" hx-target="#inv-body" hx-swap="outerHTML">
    <?= render('partials/item_suggest', ['id' => 'give-item', 'label' => 'Item', 'placeholder' => $placeholder]) ?>
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
    <button class="btn btn-primary" type="submit"><?= e($button) ?></button>
  </form>
</section>
