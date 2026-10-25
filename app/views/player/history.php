<div class="player-main">
  <main class="main">
    <header class="page-head">
      <div>
        <p class="muted" style="margin:0"><?= e($char['class_label']) ?></p>
        <h1 class="page-title"><?= e($char['name']) ?>’s History</h1>
      </div>
      <a class="btn" href="<?= e(url('/pack')) ?>">Back to my pack</a>
    </header>
    <p class="muted" style="margin:0;max-width:680px">Everything that went into or out of your pack and purse, newest first.</p>

    <?= render('partials/history_table', [
        'rows' => $rows, 'hasMore' => $hasMore, 'showCharacter' => false, 'path' => $path,
        'emptyText' => 'Nothing has changed in your pack yet.',
    ]) ?>
  </main>
</div>
