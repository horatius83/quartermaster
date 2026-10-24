<tbody id="catalog-body">
  <?php foreach ($items as $i): ?><?= render('partials/catalog_row', ['i' => $i]) ?><?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="6" class="empty">No items match.</td></tr><?php endif; ?>
</tbody>
