<?php $admin = gate(current_user(), 'admin.manage'); ?>
<nav class="desk-nav" aria-label="Workspace" style="margin: .4rem 0 1rem; flex-wrap: wrap;">
  <a href="/admin"<?= nav_current('/admin') && url_path() === '/admin' ? ' aria-current="page"' : '' ?>>Overview</a>
  <a href="/admin/reports">Queue</a>
  <?php if ($admin): ?>
    <a href="/admin/routes">Routes</a>
    <a href="/admin/stops">Stops</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/categories">Categories</a>
    <a href="/admin/analytics">Analytics</a>
    <a href="/admin/audit">Audit</a>
    <a href="/admin/settings">Settings</a>
  <?php endif; ?>
</nav>
