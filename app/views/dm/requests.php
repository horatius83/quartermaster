<main class="main">
  <header>
    <h1 class="page-title">Player requests</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Players write to you here from their <em>Requests</em> page: an item they’re hunting for, a question, a comment. Reply to answer them, which closes the request and shows your reply to the player, or close it without a reply once it’s dealt with.</p>
  </header>

  <?php if (!$open): ?><div class="card empty">No requests are waiting.</div><?php endif; ?>
  <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
    <?php foreach ($open as $request): ?><?= render('partials/request_card', ['request' => $request]) ?><?php endforeach; ?>
  </div>

  <?php if ($recent): ?>
    <h2 class="section-title">Recently closed</h2>
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
      <?php foreach ($recent as $request): ?><?= render('partials/request_card', ['request' => $request]) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
