<?php
$base = url('/shop/' . $store['id']);
// A link to these wares filtered by this category and these tags.
$filtered = function (string $cat, array $tags) use ($base): string {
    $query = http_build_query(array_filter(['cat' => $cat, 'tag' => $tags]), '', '&');
    return $query === '' ? $base : $base . '?' . $query;
};
?>
<div id="wares" class="card-stack" style="gap:16px">
  <?php // Each chip's link is built from what's showing now, so a second click before the page updates is ignored rather than losing the first. ?>
  <div style="display:contents" hx-sync="this:drop">
  <?php if (count($catsHere) > 1 || $cat !== ''): ?>
  <nav class="chips" aria-label="Filter by category">
    <?php foreach (array_merge(['' => 'All'], $catsHere) as $slug => $name):
      $href = $filtered((string)$slug, $tags); ?>
      <a class="chip" href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#wares" hx-swap="outerHTML" hx-push-url="true"<?= $cat === (string)$slug ? ' aria-current="true"' : '' ?>><?= e($name) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?php if ($tagsHere): ?>
  <nav class="chips" aria-label="Filter by tag">
    <?php foreach ($tagsHere as $tag):
      $picked = in_array($tag, $tags, true);
      $href = $filtered($cat, $picked ? array_values(array_diff($tags, [$tag])) : [...$tags, $tag]); ?>
      <a class="chip chip-sm" href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#wares" hx-swap="outerHTML" hx-push-url="true"<?= $picked ? ' aria-current="true"' : '' ?>><?= e($tag) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  </div>
  <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($wares as $w): $soldOut = $w['quantity'] !== null && (int)$w['quantity'] <= 0; ?>
      <article class="card ware">
        <?= category_tag($w['category'], $w['category_slug']) ?>
        <h2 class="card-title"><?= e($w['name']) ?></h2>
        <?php if ($w['tags']): ?>
        <div class="item-tags"><?php foreach ($w['tags'] as $tag): ?><span class="item-tag"><?= e($tag) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="row-between muted small">
          <span><?= e(fmt_weight($w['weight'])) ?></span>
          <span><?= $w['quantity'] === null ? 'Plenty' : ($soldOut ? 'Sold out' : (int)$w['quantity'] . ' left') ?></span>
        </div>
        <div class="ware-foot">
          <span class="price"><?= e(coins((int)$w['price_cp'])) ?></span>
          <button class="btn btn-dark" type="button"<?= $soldOut ? ' disabled' : '' ?>
            hx-post="<?= e(url('/shop/' . $store['id'] . '/add')) ?>" hx-vals='{"stock_id": <?= (int)$w['id'] ?>}'
            hx-target="#cart" hx-swap="outerHTML" aria-label="Add <?= e($w['name']) ?> to cart">Add to cart</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$wares): ?><div class="card empty"><?= $tags ? 'Nothing here has all of those tags.' : 'The shelves are bare right now.' ?></div><?php endif; ?>
</div>
