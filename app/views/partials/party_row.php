<tr id="party-<?= (int)$c['id'] ?>">
  <td>
    <div class="item-name"><?= e($c['name']) ?></div>
    <div class="muted small"><?= e($c['class_label'] ?: '—') ?></div>
  </td>
  <td><?= $c['username'] ? e($c['username']) : '<span class="muted">none</span>' ?></td>
  <td class="nowrap"><?= e(coins((int)$c['coin_cp'])) ?></td>
  <td>
    <form class="move-form" hx-post="<?= e(url('/dm/party/' . $c['id'] . '/password')) ?>" hx-target="#party-<?= (int)$c['id'] ?>" hx-swap="outerHTML">
      <?php if (!$c['username']): ?>
        <label class="visually-hidden" for="u-<?= (int)$c['id'] ?>">Username for <?= e($c['name']) ?></label>
        <input class="input-sm" style="width:130px" id="u-<?= (int)$c['id'] ?>" name="username" placeholder="username" autocomplete="off">
      <?php endif; ?>
      <label class="visually-hidden" for="p-<?= (int)$c['id'] ?>">New password for <?= e($c['name']) ?></label>
      <input class="input-sm" style="width:140px" id="p-<?= (int)$c['id'] ?>" name="password" placeholder="new password" minlength="6" autocomplete="off">
      <button class="btn" type="submit"><?= $c['username'] ? 'Reset' : 'Create login' ?></button>
    </form>
  </td>
  <td class="right nowrap">
    <a class="link-btn" href="<?= e(url('/dm/inventory/' . $c['id'])) ?>">Pack</a>
    <button class="link-btn danger" type="button"
      hx-post="<?= e(url('/dm/party/' . $c['id'] . '/delete')) ?>"
      hx-target="#party-<?= (int)$c['id'] ?>" hx-swap="outerHTML swap:300ms"
      hx-confirm="Remove <?= e($c['name']) ?> and everything in their pack? This can’t be undone.">Remove</button>
  </td>
</tr>
