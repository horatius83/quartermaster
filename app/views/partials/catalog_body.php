<tbody id="catalog-body">
  <?php foreach ($catalog['items'] as $i): ?><?= render('partials/catalog_row', ['i' => $i]) ?><?php endforeach; ?>
  <?php if (!$catalog['items']): ?><tr><td colspan="7" class="empty">No items match.</td></tr><?php endif; ?>
</tbody>
<?php // A redraw brings the pager along. It must come after the tbody, or the HTML parser drops the tbody. ?>
<?php if ($oob): ?><?= render('partials/catalog_pager', ['catalog' => $catalog, 'oob' => true]) ?><?php endif; ?>
