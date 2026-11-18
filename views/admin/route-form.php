<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<?php
$isNew = !$route;
if ($isNew) {
    $route = [
        'id' => 0,
        'code' => '',
        'name' => '',
        'origin_label' => '',
        'destination_label' => '',
        'direction' => '',
        'description' => '',
        'source_name' => 'InsideAccra sample dataset',
        'source_type' => 'demo',
        'confidence' => 'demo',
        'limitation_notes' => 'Sample data. Not an official route listing. Fare and duration are not verified.',
        'is_demo' => 1,
        'status' => 'active',
    ];
}
?>
<h1><?= $isNew ? 'New route' : 'Edit route' ?></h1>
<?php $seq = errors() ? old_array('sequence') : ($selected ?? []); ?>
<form method="post" action="<?= $isNew ? '/admin/routes' : '/admin/routes/' . (int) $route['id'] ?>">
  <?= csrf_field() ?>
  <div class="row-2">
    <div class="field"><label for="code">Code</label><input id="code" name="code" required value="<?= e(old('code', $route['code'] ?? '')) ?>"><?= field_error('code') ?></div>
    <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', $route['name'] ?? '')) ?>"><?= field_error('name') ?></div>
  </div>
  <div class="row-2">
    <div class="field"><label for="origin_label">Origin area</label><input id="origin_label" name="origin_label" required value="<?= e(old('origin_label', $route['origin_label'] ?? '')) ?>"></div>
    <div class="field"><label for="destination_label">Destination area</label><input id="destination_label" name="destination_label" required value="<?= e(old('destination_label', $route['destination_label'] ?? '')) ?>"></div>
  </div>
  <div class="field"><label for="direction">Direction</label><input id="direction" name="direction" required value="<?= e(old('direction', $route['direction'] ?? '')) ?>"><?= field_error('direction') ?></div>
  <div class="field"><label for="description">Description</label><textarea id="description" name="description"><?= e(old('description', $route['description'] ?? '')) ?></textarea></div>
  <div class="row-2">
    <div class="field"><label for="source_name">Source name</label><input id="source_name" name="source_name" required value="<?= e(old('source_name', $route['source_name'] ?? 'InsideAccra sample dataset')) ?>"><?= field_error('source_name') ?></div>
    <div class="field"><label for="source_type">Source type</label>
      <select id="source_type" name="source_type">
        <?php foreach (['demo','community','reviewed','official'] as $type): ?>
          <option value="<?= e($type) ?>"<?= old('source_type', $route['source_type'] ?? 'demo') === $type ? ' selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="field"><label for="confidence">Confidence</label>
    <select id="confidence" name="confidence"><?php foreach (['demo','low','medium','high'] as $level): ?><option value="<?= e($level) ?>"<?= old('confidence', $route['confidence'] ?? 'demo') === $level ? ' selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?></select>
  </div>
  <div class="field"><label for="limitation_notes">Limitation notes</label><textarea id="limitation_notes" name="limitation_notes" required><?= e(old('limitation_notes', $route['limitation_notes'] ?? 'Sample data. Not an official route listing. Fare and duration are not verified.')) ?></textarea><?= field_error('limitation_notes') ?></div>
  <label class="check"><input type="checkbox" name="is_demo" value="1"<?= old('is_demo', (string) ($route['is_demo'] ?? '1')) !== '0' ? ' checked' : '' ?>> Mark as sample / demo data</label>
  <input type="hidden" name="status" value="<?= e($route['status'] ?? 'active') ?>">
  <h2>Stops in order</h2>
  <p class="help">Enter 1, 2, 3… Leave blank to omit a stop. At least two stops.</p>
  <?= field_error('sequence') ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order</th><th>Stop</th></tr></thead>
      <tbody>
      <?php foreach ($stops as $stop): ?>
        <tr>
          <td><label class="sr-only" for="seq-<?= (int) $stop['id'] ?>">Order for <?= e($stop['name']) ?></label><input id="seq-<?= (int) $stop['id'] ?>" name="sequence[<?= (int) $stop['id'] ?>]" type="number" min="1" style="width:5rem" value="<?= e((string) ($seq[$stop['id']] ?? $seq[(string) $stop['id']] ?? '')) ?>"></td>
          <td><?= e($stop['name']) ?> · <?= e($stop['locality']) ?><?php if (!$stop['active']): ?> (inactive)<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="actions"><button class="btn" type="submit">Save route</button></div>
</form>
<?php if (!$isNew): ?>
  <form method="post" action="/admin/routes/<?= (int) $route['id'] ?>/check"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Mark checked today</button></form>
<?php endif; ?>
