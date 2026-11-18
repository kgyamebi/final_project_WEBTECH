<div class="wide">
  <p class="kicker">Map and list</p>
  <h1>Explore routes, stops, and reports</h1>
  <p class="lede"><?= e(COVERAGE_NOTE) ?> Selecting a marker opens details. The list below is the same information without the map.</p>
  <form class="card" method="get" action="/explore">
    <div class="row-2">
      <div class="field">
        <label for="category">Category</label>
        <select id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['slug']) ?>"<?= $filters['category'] === $category['slug'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Report status</label>
        <select id="status" name="status">
          <option value="">All public statuses</option>
          <?php foreach (REPORT_STATUSES as $value => $label): if ($value === 'rejected') continue; ?>
            <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row-2">
      <div class="field"><label for="from">Observed from</label><input id="from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
      <div class="field"><label for="to">Observed to</label><input id="to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    </div>
    <div class="actions">
      <button class="btn" type="submit">Apply filters</button>
      <a class="btn btn-ghost" href="/explore">Clear</a>
    </div>
    <fieldset style="border:0;padding:0;margin-top:.6rem">
      <legend class="help">Map layers</legend>
      <label class="check"><input id="layer-routes" type="checkbox" checked> Routes</label>
      <label class="check"><input id="layer-stops" type="checkbox" checked> Stops</label>
      <label class="check"><input id="layer-incidents" type="checkbox" checked> Reports</label>
    </fieldset>
  </form>
  <div class="map-layout" style="margin-top:1rem">
    <div>
      <div id="map" data-mode="explore" role="region" aria-label="Map of sample routes, stops, and community reports"></div>
      <p id="map-status" class="help">Loading map…</p>
    </div>
    <aside class="map-panel" aria-label="Map list">
      <p class="legend"><i class="swatch-stop"></i> Stop <i class="swatch-pending"></i> Pending <i class="swatch-verified"></i> Verified <i class="swatch-resolved"></i> Resolved</p>
      <h2>Routes</h2>
      <div class="list">
        <?php foreach ($payload['routes'] as $route): if ($route['status'] !== 'active') continue; ?>
          <a class="item" href="/routes/<?= (int) $route['id'] ?>">
            <strong class="mono"><?= e($route['code']) ?></strong> <?= e($route['name']) ?>
            <small><?= e($route['direction']) ?> · Demo data<?php if ($route['impacts']): ?> · <?= e($route['impacts'][0]['impact_label']) ?><?php endif; ?></small>
          </a>
        <?php endforeach; ?>
      </div>
      <h2>Reports</h2>
      <?php if (!$payload['incidents']): ?><p class="empty">No reports match these filters.</p><?php endif; ?>
      <div class="list">
        <?php foreach ($payload['incidents'] as $incident): ?>
          <a class="item" href="/incidents/<?= (int) $incident['id'] ?>">
            <?= status_badge($incident['status']) ?>
            <strong><?= e($incident['title']) ?></strong>
            <small><?= e($incident['trust_label']) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
  <?= json_script('map-data', $payload) ?>
</div>
