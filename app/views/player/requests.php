<div class="player-main">
  <main class="main">
    <header class="page-head">
      <div>
        <p class="muted" style="margin:0"><?= e($char['class_label']) ?></p>
        <h1 class="page-title">Requests to the DM</h1>
      </div>
    </header>

    <section class="card card-stack" aria-labelledby="new-request-h" style="max-width:720px">
      <h2 id="new-request-h" class="card-title">Write to your DM</h2>
      <?php if ($openCount >= MAX_OPEN_REQUESTS): ?>
        <p class="notice notice-warn" style="margin:0">You have <?= $openCount ?> requests waiting on your DM. Withdraw one, or wait for an answer, before sending more.</p>
      <?php else: ?>
        <form class="card-stack" method="post" action="<?= e(url('/requests')) ?>">
          <?= csrf_field() ?>
          <label class="field" for="request-body">Your request or comment
            <textarea id="request-body" name="body" rows="5" maxlength="<?= MAX_REQUEST_CHARS ?>" required
              placeholder="Could the blacksmith in Millbrook make me a silvered longsword?"><?= e($draft) ?></textarea>
          </label>
          <p class="muted small" style="margin:0">Ask for something a shop doesn’t stock, ask a question, or just leave a comment. Only your DM sees it.</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">Send to the DM</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($requests): ?>
      <h2 class="section-title">Your requests</h2>
      <ul class="list-plain card" style="padding:0 18px;max-width:720px" id="requests">
        <?php foreach ($requests as $r):
          $open = $r['status'] === 'open';
          $label = $open ? 'Waiting on DM' : ($r['dm_reply'] !== '' ? 'Answered' : 'Closed');
          $new = !$open && $r['dm_reply'] !== '' && $r['reply_seen_at'] === null; ?>
          <li class="request-item" style="padding:12px 0">
            <div class="row-between" style="align-items:flex-start">
              <span class="muted small">Sent <?= e(time_ago($r['created_at'])) ?><?php if (!$open): ?> · closed <?= e(time_ago($r['closed_at'])) ?><?php endif; ?></span>
              <span><?php if ($new): ?><span class="badge">New</span> <?php endif; ?><span class="status status-<?= $open ? 'pending' : 'closed' ?>"><?= e($label) ?></span></span>
            </div>
            <p class="request-body" style="margin:6px 0 0"><?= e($r['body']) ?></p>
            <?php if ($r['dm_reply'] !== ''): ?>
              <p class="notice notice-ok request-body" style="margin:8px 0 0">DM: <?= e($r['dm_reply']) ?></p>
            <?php endif; ?>
            <?php if ($open): ?>
              <form method="post" action="<?= e(url('/requests/' . $r['id'] . '/withdraw')) ?>" style="margin-top:4px">
                <?= csrf_field() ?>
                <button class="link-btn" type="submit">Withdraw request</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </main>
</div>
