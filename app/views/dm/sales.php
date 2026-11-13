<main class="main">
  <header>
    <h1 class="page-title">Offers to sell</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Players offer things from their packs here. Accepting pays the asking price into their purse and takes the items from their pack. A counter-offer goes back to the player to accept or decline; nothing changes hands until they do. Rejecting leaves the items where they are.</p>
  </header>

  <?php if (!$pending): ?><div class="card empty">No offers are waiting. Players make them from <em>My pack</em>.</div><?php endif; ?>
  <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
    <?php foreach ($pending as $sale): ?><?= render('partials/sale_card', ['sale' => $sale]) ?><?php endforeach; ?>
  </div>

  <?php if ($waiting): ?>
    <h2 class="section-title">Waiting on the player</h2>
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
      <?php foreach ($waiting as $sale): ?><?= render('partials/sale_card', ['sale' => $sale]) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($recent): ?>
    <h2 class="section-title">Recently settled</h2>
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
      <?php foreach ($recent as $sale): ?><?= render('partials/sale_card', ['sale' => $sale]) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
