<main class="main">
  <header>
    <h1 class="page-title">Carts awaiting your nod</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Approving a cart takes the coin from the player’s purse, puts the items in their pack and lowers the store’s stock. Change a quantity to adjust a cart before you approve it (0 removes the line).</p>
  </header>

  <?php if (!$pending): ?><div class="card empty">No carts are waiting. Players send them from the shops you open.</div><?php endif; ?>
  <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
    <?php foreach ($pending as $cart): ?><?= render('partials/cart_card', ['cart' => $cart]) ?><?php endforeach; ?>
  </div>

  <?php if ($recent): ?>
    <h2 class="section-title">Recently settled</h2>
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start">
      <?php foreach ($recent as $cart): ?><?= render('partials/cart_card', ['cart' => $cart]) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
