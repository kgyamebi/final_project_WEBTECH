<h1>Create account</h1>
<p>New accounts are residents and commuters. Moderator and administrator roles are assigned by an administrator.</p>
<form class="card" method="post" action="/register">
  <?= csrf_field() ?>
  <div hidden><label for="website">Leave empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name')) ?>"<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
  <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required value="<?= e(old('email')) ?>" autocomplete="username"<?= invalid_attr('email') ?>><?= field_error('email') ?></div>
  <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"<?= invalid_attr('password') ?>><?= field_error('password') ?></div>
  <div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password"><?= field_error('password_confirm') ?></div>
  <button class="btn" type="submit">Create account</button>
</form>
