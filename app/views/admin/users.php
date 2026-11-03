<?php /** @var array $users @var array $campaigns @var array $campaignsByOwner @var array $dms @var array $me @var array $old */
$roleLabels = ['admin' => 'Admin', 'dm' => 'DM', 'player' => 'Player']; ?>
<main class="main">
  <header>
    <h1 class="page-title">Users</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Every login, and what it does. Add a DM here and they can sign in and start their own campaigns; each DM sees only theirs. Players are added by their DM under <em>Party &amp; logins</em>, with a character. Every login added or deleted is written to the <a href="<?= e(url('/admin/log')) ?>">account log</a>.</p>
  </header>

  <form class="card card-dashed card-stack" method="post" action="<?= e(url('/admin/users')) ?>">
    <?= csrf_field() ?>
    <h2 class="section-title" style="font-size:28px">Add a login</h2>
    <div class="form-row">
      <label class="field mid">Username
        <input name="username" required maxlength="50" autocomplete="off" value="<?= e($old['username'] ?? '') ?>">
      </label>
      <label class="field mid">Password (8+ characters)
        <input name="password" type="text" required minlength="8" autocomplete="off">
      </label>
      <label class="field mid">Role
        <select name="role">
          <option value="dm"<?= ($old['role'] ?? 'dm') === 'dm' ? ' selected' : '' ?>>Dungeon Master</option>
          <option value="admin"<?= ($old['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Add login</button>
    </div>
  </form>

  <div class="table-wrap">
    <table class="ledger">
      <caption>All logins</caption>
      <thead><tr><th>Username</th><th>Role</th><th>Plays in / runs</th><th>Added</th><th class="right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u):
          $isMe = (int)$u['id'] === (int)$me['id'];
          $runs = $campaignsByOwner[(int)$u['id']] ?? []; ?>
          <tr id="user-<?= (int)$u['id'] ?>">
            <td><span class="item-name"><?= e($u['username']) ?></span><?php if ($isMe): ?> <span class="muted small">(you)</span><?php endif; ?></td>
            <td class="nowrap"><?= e($roleLabels[$u['role']] ?? $u['role']) ?></td>
            <td>
              <?php if ($u['role'] === 'dm'): ?>
                <?= $runs ? e(implode(', ', $runs)) : '<span class="muted">No campaigns yet</span>' ?>
              <?php elseif ($u['role'] === 'player'): ?>
                <?php if ($u['character_name'] !== null): ?>
                  <?= e($u['character_name']) ?> <span class="muted small">in <?= e($u['campaign_name']) ?><?= $u['dm_name'] !== null ? ' (DM ' . e($u['dm_name']) . ')' : '' ?></span>
                <?php else: ?>
                  <span class="muted">No character</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="nowrap muted"><?= e(substr($u['created_at'], 0, 10)) ?></td>
            <td class="right nowrap">
              <?php if (!$isMe):
                $confirm = match ($u['role']) {
                    'dm'     => "Delete {$u['username']}? This only works once their campaigns have no characters or stores; their empty campaigns go too.",
                    'player' => "Delete {$u['username']}? " . ($u['character_name'] !== null ? "{$u['character_name']} stays in the party without a login." : ''),
                    default  => "Delete the admin login {$u['username']}?",
                }; ?>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" style="display:inline" hx-confirm="<?= e(trim($confirm)) ?>">
                  <?= csrf_field() ?>
                  <button class="link-btn danger" type="submit" aria-label="Delete <?= e($u['username']) ?>">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="table-wrap">
    <table class="ledger">
      <caption>Campaigns</caption>
      <thead><tr><th>Campaign</th><th>Party</th><th>Run by</th></tr></thead>
      <tbody>
        <?php foreach ($campaigns as $c): ?>
          <tr id="campaign-<?= (int)$c['id'] ?>">
            <td class="item-name"><?= e($c['name']) ?></td>
            <td class="nowrap"><?= (int)$c['character_count'] ?> <?= (int)$c['character_count'] === 1 ? 'adventurer' : 'adventurers' ?></td>
            <td>
              <form class="move-form" method="post" action="<?= e(url('/admin/campaigns/' . $c['id'] . '/owner')) ?>">
                <?= csrf_field() ?>
                <label class="visually-hidden" for="owner-<?= (int)$c['id'] ?>">DM running <?= e($c['name']) ?></label>
                <select id="owner-<?= (int)$c['id'] ?>" name="owner_user_id">
                  <?php if ($c['owner_user_id'] === null): ?><option value="" selected>No DM</option><?php endif; ?>
                  <?php foreach ($dms as $dm): ?>
                    <option value="<?= (int)$dm['id'] ?>"<?= (int)$dm['id'] === (int)$c['owner_user_id'] ? ' selected' : '' ?>><?= e($dm['username']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Hand over</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$campaigns): ?><tr><td colspan="3" class="empty">No campaigns yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small" style="margin:0">Handing over a campaign moves its party, stores, carts and history to the other DM. The DM who had it no longer sees it.</p>
</main>
