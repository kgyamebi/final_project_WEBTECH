<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1><?= $stop ? 'Edit stop' : 'New stop' ?></h1>
<form method="post" action="<?= $stop ? '/admin/stops/' . (int) $stop['id'] : '/admin/stops' ?>">
  <?= csrf_field() ?>
  <div class="row-2">
    <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', $stop['name'] ?? '')) ?>"><?= field_error('name') ?></div>
    <div class="field"><label for="locality">Area</label><input id="locality" name="locality" required value="<?= e(old('locality', $stop['locality'] ?? '')) ?>"><?= field_error('locality') ?></div>
  </div>
  <div class="row-2">
    <div class="field"><label for="latitude">Latitude</label><input id="latitude" name="latitude" required value="<?= e(old('latitude', isset($stop) ? (string) $stop['latitude'] : '')) ?>"><?= field_error('latitude') ?></div>
    <div class="field"><label for="longitude">Longitude</label><input id="longitude" name="longitude" required value="<?= e(old('longitude', isset($stop) ? (string) $stop['longitude'] : '')) ?>"></div>
  </div>
  <div id="map" data-mode="pin" role="region" aria-label="Stop location"></div>
  <p id="map-status" class="help">Tap the map or type coordinates inside the covered area.</p>
  <div class="field"><label for="description">Description</label><textarea id="description" name="description"><?= e(old('description', $stop['description'] ?? '')) ?></textarea></div>
  <div class="field"><label for="facilities">Facilities</label><textarea id="facilities" name="facilities"><?= e(old('facilities', $stop['facilities'] ?? '')) ?></textarea></div>
  <div class="field"><label for="accessibility_notes">Accessibility notes</label><textarea id="accessibility_notes" name="accessibility_notes"><?= e(old('accessibility_notes', $stop['accessibility_notes'] ?? '')) ?></textarea></div>
  <div class="row-2">
    <div class="field"><label for="source_name">Source</label><input id="source_name" name="source_name" required value="<?= e(old('source_name', $stop['source_name'] ?? 'InsideAccra sample dataset')) ?>"><?= field_error('source_name') ?></div>
    <div class="field"><label for="source_type">Source type</label>
      <?php $sourceType = isset($stop['source_type']) ? $stop['source_type'] : 'demo'; ?>
      <select id="source_type" name="source_type"><?php foreach (['demo','community','reviewed','official'] as $type): ?><option value="<?= e($type) ?>"<?= old('source_type', $sourceType) === $type ? ' selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select>
    </div>
  </div>
  <div class="field"><label for="confidence">Confidence</label>
    <?php $confidence = isset($stop['confidence']) ? $stop['confidence'] : 'demo'; ?>
    <select id="confidence" name="confidence"><?php foreach (['demo','low','medium','high'] as $level): ?><option value="<?= e($level) ?>"<?= old('confidence', $confidence) === $level ? ' selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?></select>
  </div>
  <label class="check"><input type="checkbox" name="is_demo" value="1" <?= old('is_demo', (string) ($stop['is_demo'] ?? '1')) !== '0' ? 'checked' : '' ?>> Sample / demo data</label>
  <div class="actions"><button class="btn" type="submit">Save stop</button></div>
</form>
<?php if ($stop): ?><form method="post" action="/admin/stops/<?= (int) $stop['id'] ?>/check"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Mark checked today</button></form><?php endif; ?>
