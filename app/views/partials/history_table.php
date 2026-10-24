<?php /** @var array $rows @var bool $hasMore @var bool $showCharacter @var string $path @var string $emptyText */ ?>
<div class="table-wrap">
  <table class="ledger">
    <thead>
      <tr><th>When</th><?php if ($showCharacter): ?><th>Character</th><?php endif; ?><th>Change</th><th>What happened</th><th>By</th></tr>
    </thead>
    <tbody>
      <?= render('partials/history_rows', ['rows' => $rows, 'hasMore' => $hasMore, 'showCharacter' => $showCharacter, 'path' => $path]) ?>
      <?php if (!$rows): ?><tr><td colspan="<?= $showCharacter ? 5 : 4 ?>" class="empty"><?= e($emptyText) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
