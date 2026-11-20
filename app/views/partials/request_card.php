<?php
/** @var array $request from request_detail() */
$open = $request['status'] === 'open';
$fid = 'request-' . (int)$request['id'];
$when = $open ? 'sent ' . time_ago($request['created_at']) : 'closed ' . time_ago($request['closed_at']);
?>
<article id="<?= $fid ?>" class="card card-stack request-card">
  <div class="row-between" style="align-items:flex-start">
    <div>
      <h3 class="card-title" style="font-size:34px"><?= e($request['character_name']) ?></h3>
      <div class="muted small"><?= e($when) ?></div>
    </div>
    <span class="status status-<?= $open ? 'pending' : 'closed' ?>"><?= $open ? 'Open' : ($request['dm_reply'] !== '' ? 'Answered' : 'Closed') ?></span>
  </div>

  <p class="request-body" style="margin:0"><?= e($request['body']) ?></p>

  <?php if ($open): ?>
    <form class="card-stack" hx-post="<?= e(url('/dm/requests/' . $request['id'] . '/reply')) ?>" hx-target="closest article" hx-swap="outerHTML">
      <label class="field" for="<?= $fid ?>-reply"><span>Reply <span class="small">(shown to the player)</span></span>
        <textarea id="<?= $fid ?>-reply" name="dm_reply" rows="3" maxlength="<?= MAX_REQUEST_REPLY_CHARS ?>" placeholder="The smith can have one ready by the next session…"></textarea>
      </label>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Send reply</button>
        <button class="btn btn-ghost" type="button" hx-params="none"
          hx-post="<?= e(url('/dm/requests/' . $request['id'] . '/close')) ?>" hx-target="closest article" hx-swap="outerHTML">Close without reply</button>
      </div>
    </form>
  <?php elseif ($request['dm_reply'] !== ''): ?>
    <p class="notice notice-ok request-body" style="margin:0">You: <?= e($request['dm_reply']) ?></p>
  <?php endif; ?>
</article>
