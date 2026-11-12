<?php
/**
 * A count beside a nav link (carts or offers waiting on the DM, counter-offers waiting on a player), hidden at zero.
 * Rendered inline by the layout, or out-of-band after an action changes the count.
 * @var string $id @var int $count, and optionally $inline, and $label for screen readers ("counter-offers waiting").
 */
$oob = empty($inline) ? ' hx-swap-oob="true"' : '';
?><span id="<?= e($id) ?>" class="badge<?= $count ? '' : ' badge-empty' ?>"<?= $oob ?>><?= $count ?: '' ?><?php if ($count && !empty($label)): ?><span class="visually-hidden"> <?= e($label) ?></span><?php endif; ?></span>
