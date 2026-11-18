<h1>Sign in</h1>
<p>Guests can browse the map and plan journeys. Sign in to submit reports and save routes.</p>
<form class="card" method="post" action="/login">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required value="<?= e(old('email')) ?>" autocomplete="username"<?= invalid_attr('email') ?>>
    <?= field_error('email') ?>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </div>
  <button class="btn" type="submit">Sign in</button>
  <p>No account? <a href="/register">Create one</a>.</p>
</form>
<aside class="notice notice-info">
  <strong>Demo accounts</strong>
  <p>Password for all three: <span class="mono">DemoPass123!</span></p>
  <ul>
    <li>Commuter: ama@example.com</li>
    <li>Moderator: kofi.mod@example.com</li>
    <li>Administrator: admin@example.com</li>
  </ul>
</aside>
