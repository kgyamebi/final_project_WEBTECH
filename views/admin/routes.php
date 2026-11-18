<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Routes</h1>
<p><a class="btn" href="/admin/routes/new">Add route</a></p>
<div class="table-wrap">
<table>
  <thead><tr><th>Code</th><th>Direction</th><th>Status</th><th>Last checked</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($routes as $route): ?>
    <tr>
      <td><?php if ($route['is_demo']): ?><span class="badge badge-demo">Demo</span> <?php endif; ?><a href="/admin/routes/<?= (int) $route['id'] ?>/edit"><?= e($route['code']) ?></a><br><?= e($route['name']) ?></td>
      <td><?= e($route['direction']) ?></td>
      <td><?= e($route['status']) ?></td>
      <td><?= e(accra_time($route['verified_at'])) ?></td>
      <td>
        <form method="post" action="/admin/routes/<?= (int) $route['id'] ?>/active"><?= csrf_field() ?>
          <input type="hidden" name="active" value="<?= $route['status'] === 'active' ? '0' : '1' ?>">
          <button class="btn btn-small btn-ghost" type="submit"><?= $route['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
