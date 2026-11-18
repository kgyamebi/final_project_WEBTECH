<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Review queue</h1>
<form class="card" method="get" action="/admin/reports">
  <div class="row-2">
    <div class="field"><label for="q">Search title or reference</label><input id="q" name="q" value="<?= e($filters['q']) ?>"></div>
    <div class="field"><label for="status">Status</label>
      <select id="status" name="status">
        <option value="">Any</option>
        <?php foreach (REPORT_STATUSES as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="row-2">
    <div class="field"><label for="category">Category</label>
      <select id="category" name="category">
        <option value="">Any</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>"<?= (int) $filters['category'] === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label for="from">Submitted from</label><input id="from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
  </div>
  <div class="field"><label for="to">Submitted to</label><input id="to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Reference</th><th>Title</th><th>Status</th><th>When</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4">No reports match.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><a href="/admin/reports/<?= (int) $row['id'] ?>"><?= e($row['public_ref']) ?></a><?php if ($row['is_demo']): ?><br><span class="badge badge-demo">Demo</span><?php endif; ?></td>
        <td><?= e($row['title']) ?><br><small><?= e($row['category_name']) ?> · <?= e($row['place_label'] ?: '') ?></small></td>
        <td><?= status_badge($row['status']) ?></td>
        <td><?= e(accra_time($row['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($pager['pages'] > 1): ?><p><?php for ($i = 1; $i <= $pager['pages']; $i++): ?><a href="?page=<?= $i ?>"> <?= $i ?> </a><?php endfor; ?></p><?php endif; ?>
