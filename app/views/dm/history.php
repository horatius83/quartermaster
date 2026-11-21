<main class="main">
  <nav class="pills" aria-label="Choose a character">
    <a class="pill" href="<?= e(url('/dm/history')) ?>"<?= $char || $party ? '' : ' aria-current="page"' ?>>Whole party</a>
    <?php if ($partyPill): ?>
      <a class="pill" href="<?= e(url('/dm/history/party')) ?>"<?= $party ? ' aria-current="page"' : '' ?>>Party purse</a>
    <?php endif; ?>
    <?php foreach ($characters as $c): ?>
      <a class="pill" href="<?= e(url('/dm/history/' . $c['id'])) ?>"<?= $char && (int)$c['id'] === (int)$char['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <header class="page-head">
    <div>
      <h1 class="page-title"><?= $char ? e($char['name']) . '’s History' : ($party ? 'Party Purse History' : 'Pack History') ?></h1>
      <p class="muted" style="margin:6px 0 0;max-width:680px">Everything that went into or out of <?= $char ? 'this pack and purse' : ($party ? 'the party purse and stash' : 'the party’s packs and purses') ?>, newest first.</p>
    </div>
    <?php if ($char): ?><a class="btn" href="<?= e(url('/dm/inventory/' . $char['id'])) ?>">Open pack</a><?php endif; ?>
    <?php if ($party): ?><a class="btn" href="<?= e(url('/dm/party-stash')) ?>">Open stash</a><?php endif; ?>
  </header>

  <?= render('partials/history_table', [
      'rows' => $rows, 'hasMore' => $hasMore, 'showCharacter' => $showCharacter, 'markParty' => $markParty, 'path' => $path,
      'emptyText' => 'Nothing has changed yet.',
  ]) ?>
</main>
