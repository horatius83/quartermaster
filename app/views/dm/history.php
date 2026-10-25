<main class="main">
  <nav class="pills" aria-label="Choose a character">
    <a class="pill" href="<?= e(url('/dm/history')) ?>"<?= $char ? '' : ' aria-current="page"' ?>>Whole party</a>
    <?php foreach ($characters as $c): ?>
      <a class="pill" href="<?= e(url('/dm/history/' . $c['id'])) ?>"<?= $char && (int)$c['id'] === (int)$char['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <header class="page-head">
    <div>
      <h1 class="page-title"><?= $char ? e($char['name']) . '’s History' : 'Pack History' ?></h1>
      <p class="muted" style="margin:6px 0 0;max-width:680px">Everything that went into or out of <?= $char ? 'this pack and purse' : 'the party’s packs and purses' ?>, newest first.</p>
    </div>
    <?php if ($char): ?><a class="btn" href="<?= e(url('/dm/inventory/' . $char['id'])) ?>">Open pack</a><?php endif; ?>
  </header>

  <?= render('partials/history_table', [
      'rows' => $rows, 'hasMore' => $hasMore, 'showCharacter' => $showCharacter, 'path' => $path,
      'emptyText' => 'Nothing has changed yet.',
  ]) ?>
</main>
