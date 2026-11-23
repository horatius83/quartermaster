<?php $party ??= party_names(); ?>
<tbody id="stock-body">
  <?php foreach ($stock as $s): ?><?= render('partials/stock_row', ['s' => $s, 'party' => $party]) ?><?php endforeach; ?>
  <?php if (!$stock): ?><tr><td colspan="6" class="empty">Empty shelves. Stock something above.</td></tr><?php endif; ?>
</tbody>
