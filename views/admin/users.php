<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Users and roles</h1>
<div class="table-wrap"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $person): ?>
    <tr>
      <td><?= e($person['name']) ?></td>
      <td><?= e($person['email']) ?></td>
      <td colspan="3">
        <form method="post" action="/admin/users/<?= (int) $person['id'] ?>" class="inline">
          <?= csrf_field() ?>
          <select name="role_id" aria-label="Role for <?= e($person['name']) ?>">
            <?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"<?= (int) $person['role_id'] === (int) $role['id'] ? ' selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?>
          </select>
          <select name="account_status" aria-label="Status for <?= e($person['name']) ?>">
            <option value="active"<?= $person['account_status'] === 'active' ? ' selected' : '' ?>>active</option>
            <option value="suspended"<?= $person['account_status'] === 'suspended' ? ' selected' : '' ?>>suspended</option>
          </select>
          <button class="btn btn-small" type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
