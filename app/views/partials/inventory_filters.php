<nav id="inv-filters" class="chips" aria-label="Filter by category"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
  <?php
  $base = url('/dm/inventory/' . $char['id']);
  $opts = array_merge([['slug' => '', 'name' => 'All']], categories());
  foreach ($opts as $o):
    $href = $o['slug'] === '' ? $base : $base . '?cat=' . urlencode($o['slug']); ?>
    <a class="chip" href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#inv-body" hx-swap="outerHTML" hx-push-url="true"<?= $cat === $o['slug'] ? ' aria-current="true"' : '' ?>><?= e($o['name']) ?></a>
  <?php endforeach; ?>
</nav>
