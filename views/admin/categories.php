<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Report categories</h1>
<p class="help">Distances are metres. The maximum level for a community category cannot be a confirmed closure. Confirmed closure requires an administrator and a named source on a specific report.</p>
<?php foreach ($categories as $category): $rule = $category['rule']; ?>
  <form class="card" method="post" action="/admin/categories/<?= (int) $category['id'] ?>">
    <?= csrf_field() ?>
    <h2><?= e($category['name']) ?></h2>
    <div class="field"><label for="desc-<?= (int) $category['id'] ?>">Description</label><textarea id="desc-<?= (int) $category['id'] ?>" name="description"><?= e($category['description']) ?></textarea></div>
    <div class="row-2">
      <div class="field"><label>Line, likely (m)</label><input name="corridor_likely_m" type="number" value="<?= (int) ($rule['corridor_likely_m'] ?? 0) ?>"></div>
      <div class="field"><label>Line, possible (m)</label><input name="corridor_possible_m" type="number" value="<?= (int) ($rule['corridor_possible_m'] ?? 0) ?>"></div>
      <div class="field"><label>Stop, likely (m)</label><input name="stop_likely_m" type="number" value="<?= (int) ($rule['stop_likely_m'] ?? 0) ?>"></div>
      <div class="field"><label>Stop, possible (m)</label><input name="stop_possible_m" type="number" value="<?= (int) ($rule['stop_possible_m'] ?? 0) ?>"></div>
    </div>
    <div class="field"><label>Maximum impact</label>
      <select name="max_level">
        <?php foreach (['information','possible','likely'] as $level): ?><option value="<?= e($level) ?>"<?= ($rule['max_level'] ?? '') === $level ? ' selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?>
      </select>
    </div>
    <label class="check"><input type="checkbox" name="active" value="1"<?= $category['active'] ? ' checked' : '' ?>> Active</label>
    <button class="btn" type="submit">Save category</button>
  </form>
<?php endforeach; ?>
