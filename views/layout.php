<?php
$user = current_user();
$unread = unread_count($user);
$flashes = take_flashes();
$formErrors = errors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · InsideAccra</title>
  <meta name="description" content="InsideAccra connects sample trotro journey information for parts of Accra with community reports of road and transport conditions.">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
  <link rel="stylesheet" href="/assets/css/app.css">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
  <a class="skip" href="#content">Skip to content</a>
  <div class="demo-banner">SAMPLE / DEMO DATA — routes, stops, and seeded incidents are not an official Accra transport feed and are not live vehicle positions.</div>
  <header class="topbar">
    <a class="brand" href="/">
      <span class="brand-mark" aria-hidden="true"></span>
      <span>InsideAccra</span>
    </a>
    <nav class="desk-nav" aria-label="Primary">
      <a href="/"<?= nav_current('/') ?>>Home</a>
      <a href="/explore"<?= nav_current('/explore') ?>>Explore map</a>
      <a href="/journey"<?= nav_current('/journey') ?>>Plan journey</a>
      <a href="/report"<?= nav_current('/report') ?>>Report an issue</a>
    </nav>
    <div class="account">
      <?php if ($user): ?>
        <details>
          <summary><?= e($user['name']) ?><?php if ($unread): ?> <span class="count"><?= (int) $unread ?></span><?php endif; ?></summary>
          <div class="menu">
            <a href="/profile">Profile</a>
            <a href="/my/reports">My reports</a>
            <a href="/notifications">Notifications<?php if ($unread): ?> (<?= (int) $unread ?>)<?php endif; ?></a>
            <?php if (gate($user, 'report.moderate')): ?><a href="/admin/reports">Review queue</a><?php endif; ?>
            <?php if (gate($user, 'admin.manage')): ?><a href="/admin">Admin</a><?php endif; ?>
            <form method="post" action="/logout"><?= csrf_field() ?><button type="submit">Sign out</button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="btn btn-small" href="/login">Sign in</a>
      <?php endif; ?>
    </div>
  </header>
  <?php if ($flashes || isset($formErrors['form'])): ?>
    <div class="flash-wrap">
      <?php foreach ($flashes as $flash): ?>
        <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
      <?php endforeach; ?>
      <?php if (isset($formErrors['form'])): ?>
        <p class="flash flash-error" role="alert"><?= e($formErrors['form']) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <main id="content">
    <?= $content ?>
  </main>
  <footer class="site-footer">
    <p>InsideAccra is a community and mobility information tool for demonstration. It is not an official emergency service, government complaint desk, or live trotro tracker.</p>
    <p><a href="/privacy">Privacy and corrections</a> · Map tiles © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a></p>
  </footer>
  <nav class="bottom-nav" aria-label="Mobile">
    <a href="/"<?= nav_current('/') ?>>Home</a>
    <a href="/explore"<?= nav_current('/explore') ?>>Map</a>
    <a href="/journey"<?= nav_current('/journey') ?>>Journey</a>
    <a href="/report"<?= nav_current('/report') ?>>Report</a>
    <a href="<?= $user ? '/profile' : '/login' ?>"<?= nav_current($user ? '/profile' : '/login') ?>>Account</a>
  </nav>
  <script src="/assets/vendor/leaflet/leaflet.js"></script>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/map.js"></script>
</body>
</html>
