<h1>Profile</h1>
<p>Role: <strong><?= e($account['role_name']) ?></strong></p>
<form class="card" method="post" action="/profile">
  <?= csrf_field() ?>
  <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', $account['name'])) ?>"><?= field_error('name') ?></div>
  <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required value="<?= e(old('email', $account['email'])) ?>"><?= field_error('email') ?></div>
  <label class="check"><input type="checkbox" name="notify_disruptions" value="1"<?= $account['notify_disruptions'] ? ' checked' : '' ?>> Tell me when a verified report may affect a saved route</label>
  <div class="actions"><button class="btn" type="submit">Save profile</button></div>
</form>
<form class="card" method="post" action="/profile/password">
  <?= csrf_field() ?>
  <h2>Change password</h2>
  <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"><?= field_error('current_password') ?></div>
  <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><?= field_error('password') ?></div>
  <div class="field"><label for="password_confirm">Confirm new password</label><input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password"><?= field_error('password_confirm') ?></div>
  <button class="btn btn-ghost" type="submit">Update password</button>
</form>
<section class="grid-2">
  <div>
    <h2>Saved routes</h2>
    <?php if (!$savedRoutes): ?><p class="empty">No saved routes yet.</p><?php endif; ?>
    <?php foreach ($savedRoutes as $route): ?>
      <article class="item">
        <a href="/routes/<?= (int) $route['id'] ?>"><?= e($route['code']) ?> <?= e($route['name']) ?></a>
        <form method="post" action="/saved-routes/<?= (int) $route['saved_id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit">Remove</button></form>
      </article>
    <?php endforeach; ?>
  </div>
  <div>
    <h2>Saved stops</h2>
    <?php if (!$savedStops): ?><p class="empty">No saved stops yet.</p><?php endif; ?>
    <?php foreach ($savedStops as $stop): ?>
      <article class="item">
        <a href="/stops/<?= (int) $stop['id'] ?>"><?= e($stop['name']) ?></a>
        <form method="post" action="/saved-stops/<?= (int) $stop['saved_id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit">Remove</button></form>
      </article>
    <?php endforeach; ?>
  </div>
</section>
