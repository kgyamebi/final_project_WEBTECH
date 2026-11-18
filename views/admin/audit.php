<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Audit log</h1>
<div class="table-wrap"><table>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e(accra_time($row['created_at'])) ?></td>
      <td><?= e($row['actor_name'] ?: '—') ?></td>
      <td><?= e($row['action']) ?></td>
      <td><?= e($row['entity_type']) ?> <?= e((string) $row['entity_id']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pager['pages'] > 1): ?><p><?php for ($i = 1; $i <= $pager['pages']; $i++): ?><a href="?page=<?= $i ?>"> <?= $i ?> </a><?php endfor; ?></p><?php endif; ?>
