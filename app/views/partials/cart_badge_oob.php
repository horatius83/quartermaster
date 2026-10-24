<?php
// Pending-cart count in the sidebar. Rendered inline by the layout, or out-of-band after approve/decline.
$n = pending_cart_count();
$oob = empty($inline) ? ' hx-swap-oob="true"' : '';
?><span id="cart-badge" class="badge<?= $n ? '' : ' badge-empty' ?>"<?= $oob ?>><?= $n ?: '' ?></span>
