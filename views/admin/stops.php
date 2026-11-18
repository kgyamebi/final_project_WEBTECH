<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Stops</h1>
<p><a class="btn" href="/admin/stops/new">Add stop</a></p>
<div class="table-wrap"><table>
  <thead><tr><th>Name</th><th>Area</th><th>Active</th><th>Last checked</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($stops as $stop): ?>
    <tr>
      <td><?php if ($stop['is_demo']): ?><span class="badge badge-demo">Demo</span> <?php endif; ?><a href="/admin/stops/<?= (int) $stop['id'] ?>/edit"><?= e($stop['name']) ?></a></td>
      <td><?= e($stop['locality']) ?></td>
      <td><?= $stop['active'] ? 'Yes' : 'No' ?></td>
      <td><?= e(accra_time($stop['verified_at'])) ?></td>
      <td><form method="post" action="/admin/stops/<?= (int) $stop['id'] ?>/active"><?= csrf_field() ?><input type="hidden" name="active" value="<?= $stop['active'] ? '0' : '1' ?>"><button class="btn btn-small btn-ghost" type="submit"><?= $stop['active'] ? 'Deactivate' : 'Activate' ?></button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
