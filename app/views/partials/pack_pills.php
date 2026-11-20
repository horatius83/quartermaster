<?php
/**
 * The DM's pick of whose pack to open, with the party stash first when $showStash (the campaign shares a purse, or
 * its stash still holds something). @var array $characters @var int $current a character's id, or 0 for the stash
 * @var bool $showStash
 */
?>
<nav class="pills" aria-label="Choose a character">
  <?php if ($showStash): ?>
    <a class="pill" href="<?= e(url('/dm/party-stash')) ?>"<?= $current === 0 ? ' aria-current="page"' : '' ?>>Party stash</a>
  <?php endif; ?>
  <?php foreach ($characters as $c): ?>
    <a class="pill" href="<?= e(url('/dm/inventory/' . $c['id'])) ?>"<?= (int)$c['id'] === $current ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
  <?php endforeach; ?>
</nav>
