<?php
// A tag being filtered by stays listed after its last item loses it, so it can still be unticked. Unticking it
// fetches the filter again, which drops it.
$inUse = all_tags();
$options = merge_tags($inUse, $selected);
$u = e(url('/dm/catalog'));
?>
<div id="tag-filter" style="flex:1 1 100%" hx-get="<?= $u ?>" hx-trigger="qm-tags-changed from:body, change[target.dataset.unused&&!target.checked]" hx-target="this" hx-swap="outerHTML" hx-include="this">
  <?php if ($options): ?>
  <div class="chips" role="group" aria-label="Filter by tag" hx-get="<?= $u ?>" hx-trigger="change" hx-target="#catalog-body" hx-swap="outerHTML" hx-include="#tag-filter, [name=q], #cat-filter" hx-push-url="true" hx-disabled-elt="<?= e(CATALOG_ROW_FIELDS) ?>">
    <?php foreach ($options as $tag): ?>
      <label class="chip"><input class="visually-hidden" type="checkbox" name="tag[]" value="<?= e($tag) ?>"<?= in_array($tag, $selected, true) ? ' checked' : '' ?><?= in_array($tag, $inUse, true) ? '' : ' data-unused="1"' ?>><?= e($tag) ?></label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
