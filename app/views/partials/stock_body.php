<tbody id="stock-body">
  <?php foreach ($stock as $s): ?><?= render('partials/stock_row', ['s' => $s]) ?><?php endforeach; ?>
  <?php if (!$stock): ?><tr><td colspan="5" class="empty">Empty shelves. Stock something above.</td></tr><?php endif; ?>
</tbody>
