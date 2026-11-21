<?php
/**
 * A pack's rows, or with $stash the party stash's. $shared is whether the campaign shares a purse, so a pack's
 * stacks can move to the stash. @var array $rows @var bool $filtered @var ?array $char @var bool $stash @var bool $shared
 */
$stash = !empty($stash);
$others = $stash ? party_names() : other_characters((int)$char['id']);
?>
<tbody id="inv-body">
  <?php foreach ($rows as $r): ?>
    <?= render('partials/inventory_row', ['r' => $r, 'characters' => $others, 'stash' => $stash, 'shared' => $shared]) ?>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="empty"><?= $stash ? 'The party stash is empty.' : ($filtered ? 'Nothing of that kind in this pack.' : 'This pack is empty. Give them something above.') ?></td></tr>
  <?php endif; ?>
</tbody>
