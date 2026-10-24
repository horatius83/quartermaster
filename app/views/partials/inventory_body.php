<?php $others = other_characters((int)$char['id']); ?>
<tbody id="inv-body">
  <?php foreach ($rows as $r): ?>
    <?= render('partials/inventory_row', ['r' => $r, 'characters' => $others]) ?>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="empty"><?= $filtered ? 'Nothing of that kind in this pack.' : 'This pack is empty. Give them something above.' ?></td></tr>
  <?php endif; ?>
</tbody>
