<p class="kicker"><?= $stop['is_demo'] ? 'Demo data' : 'Stop record' ?> · <?= e($stop['locality']) ?></p>
<h1><?= e($stop['name']) ?></h1>
<p><?= $stop['active'] ? '<span class="badge badge-verified">Active in dataset</span>' : '<span class="badge badge-muted">Inactive</span>' ?></p>
<div class="map-layout">
  <div>
    <div id="map" data-mode="point" role="region" aria-label="Map location of this stop"></div>
    <p id="map-status" class="help">Approximate coordinates from the stop record.</p>
    <?= json_script('point-data', ['lat' => (float) $stop['latitude'], 'lng' => (float) $stop['longitude'], 'label' => $stop['name']]) ?>
  </div>
  <aside class="map-panel">
    <p><?= e($stop['description']) ?></p>
    <p><strong>Facilities:</strong> <?= e($stop['facilities'] ?: 'Not recorded') ?></p>
    <p><strong>Access:</strong> <?= e($stop['accessibility_notes'] ?: 'Not recorded') ?></p>
    <p>Source: <?= e($stop['source_name']) ?> (<?= e($stop['source_type']) ?>). Last checked <?= e(accra_time($stop['verified_at'])) ?>.</p>
    <p class="mono"><?= e($stop['latitude']) ?>, <?= e($stop['longitude']) ?></p>
    <?php if (current_user() && !$saved): ?>
      <form method="post" action="/saved-stops"><?= csrf_field() ?><input type="hidden" name="stop_id" value="<?= (int) $stop['id'] ?>"><button class="btn" type="submit">Save stop</button></form>
    <?php elseif ($saved): ?>
      <p>Saved on your profile.</p>
    <?php endif; ?>
  </aside>
</div>
<section class="grid-2" style="margin-top:1rem">
  <div>
    <h2>Routes serving this stop</h2>
    <?php if (!$routes): ?><p class="empty">No stored route currently serves this stop.</p><?php endif; ?>
    <div class="list">
      <?php foreach ($routes as $route): ?>
        <a class="item" href="/routes/<?= (int) $route['id'] ?>"><span class="mono"><?= e($route['code']) ?></span> <?= e($route['name']) ?><small><?= e($route['direction']) ?> · <?= e($route['status']) ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div>
    <h2>Reports within 500 m</h2>
    <?php if (!$nearby): ?><p class="empty">No public reports within 500 metres.</p><?php endif; ?>
    <div class="list">
      <?php foreach ($nearby as $report): ?>
        <a class="item" href="/incidents/<?= (int) $report['id'] ?>"><?= status_badge($report['status']) ?> <strong><?= e($report['title']) ?></strong><small><?= (int) $report['distance_m'] ?> m · <?= e($report['category_name']) ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
