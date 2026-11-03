<?php /** @var array $rows @var bool $hasMore */ ?>
<main class="main">
  <header>
    <h1 class="page-title">Account log</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Every login added or deleted, every password changed or reset, every campaign handed to another DM, and who did it, newest first. That includes what DMs do to their players’ logins. Passwords themselves are never recorded.</p>
  </header>

  <div class="table-wrap">
    <table class="ledger">
      <thead><tr><th>When</th><th>What happened</th><th>By</th></tr></thead>
      <tbody>
        <?= render('admin/log_rows', ['rows' => $rows, 'hasMore' => $hasMore]) ?>
        <?php if (!$rows): ?><tr><td colspan="3" class="empty">No logins have been added or deleted yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
