<main class="main">
  <header>
    <h1 class="page-title">Party &amp; logins</h1>
    <p class="muted" style="margin:6px 0 0;max-width:640px">Each player signs in with their own username and sees only their character’s pack and the shops you open to them.</p>
  </header>

  <form class="card card-dashed card-stack" method="post" action="<?= e(url('/dm/party')) ?>">
    <?= csrf_field() ?>
    <h2 class="section-title" style="font-size:28px">Add an adventurer</h2>
    <div class="form-row">
      <label class="field grow">Character name
        <input name="name" required maxlength="100" placeholder="e.g. Bryn Ashvale" value="<?= e($old['name'] ?? '') ?>">
      </label>
      <label class="field mid">Class &amp; level
        <input name="class_label" maxlength="100" placeholder="Fighter 5" value="<?= e($old['class_label'] ?? '') ?>">
      </label>
      <label class="field tiny">Carry (lb)
        <input name="carry_capacity" type="number" min="0" step="1" value="<?= e($old['carry_capacity'] ?? '150') ?>">
      </label>
    </div>
    <div class="form-row">
      <label class="field mid">Player username
        <input name="username" maxlength="50" autocomplete="off" placeholder="Leave blank for an NPC or hireling" value="<?= e($old['username'] ?? '') ?>">
      </label>
      <label class="field mid">Temporary password
        <input name="password" type="text" minlength="6" autocomplete="off">
      </label>
      <button class="btn btn-primary" type="submit">Add to party</button>
    </div>
  </form>

  <div class="table-wrap">
    <table class="ledger">
      <caption>The party</caption>
      <thead>
        <tr><th>Character</th><th>Login</th><th>Purse</th><th>Set / reset password</th><th class="right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($characters as $c): ?>
          <?= render('partials/party_row', ['c' => $c]) ?>
        <?php endforeach; ?>
        <?php if (!$characters): ?><tr><td colspan="5" class="empty">No one has joined yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
