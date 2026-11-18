<p class="kicker"><?= $route['is_demo'] ? 'Demo data' : 'Route record' ?></p>
<h1><span class="mono"><?= e($route['code']) ?></span> <?= e($route['name']) ?></h1>
<p><?= e($route['direction']) ?> · <?= e($route['origin_label']) ?> to <?= e($route['destination_label']) ?></p>
<p><?php if ($route['is_demo']): ?><span class="badge badge-demo">Demo data</span><?php endif; ?> <span class="badge"><?= e($route['status'] === 'active' ? 'Active in dataset' : 'Inactive') ?></span></p>
<div class="map-layout">
  <div>
    <div id="map" data-mode="line" role="region" aria-label="Map of this route"></div>
    <p id="map-status" class="help">Straight lines join the stored stops. This is not a surveyed road centreline.</p>
    <?= json_script('line-data', [
        'geometry' => $geometry,
        'stops' => array_map(static fn (array $stop): array => [
            'name' => $stop['name'],
            'lat' => (float) $stop['latitude'],
            'lng' => (float) $stop['longitude'],
        ], $stops),
    ]) ?>
  </div>
  <aside class="map-panel">
    <h2>Ordered stops</h2>
    <ol>
      <?php foreach ($stops as $stop): ?>
        <li>
          <a href="/stops/<?= (int) $stop['id'] ?>"><?= e($stop['name']) ?></a>
          <small class="help"><?= e($stop['locality']) ?><?php if ($stop['distance_from_prev_m']): ?> · <?= (int) $stop['distance_from_prev_m'] ?> m from previous stop, straight line<?php endif; ?></small>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (current_user()): ?>
      <?php if ($saved): ?>
        <p>Saved on your profile.</p>
      <?php else: ?>
        <form method="post" action="/saved-routes"><?= csrf_field() ?><input type="hidden" name="route_id" value="<?= (int) $route['id'] ?>"><button class="btn" type="submit">Save route</button></form>
      <?php endif; ?>
    <?php else: ?>
      <p><a href="/login?next=<?= urlencode('/routes/' . $route['id']) ?>">Sign in</a> to save this route.</p>
    <?php endif; ?>
    <p><a class="btn btn-ghost" href="/journey?from=<?= urlencode($route['origin_label']) ?>&amp;to=<?= urlencode($route['destination_label']) ?>">Plan this direction</a></p>
  </aside>
</div>
<section class="grid-2" style="margin-top:1rem">
  <article class="card">
    <h2>Data source</h2>
    <p><?= e($route['description']) ?></p>
    <p>Source: <?= e($route['source_name']) ?> (<?= e($route['source_type']) ?>)</p>
    <p>Confidence: <?= e($route['confidence']) ?></p>
    <p>Last checked: <?= e(accra_time($route['verified_at'])) ?></p>
    <p><?= e($route['limitation_notes']) ?></p>
    <p><strong>Fare not verified. Duration not verified.</strong></p>
  </article>
  <article>
    <h2>Warnings</h2>
    <?php if (!$impacts): ?><p class="empty">No active warning from a verified report.</p><?php endif; ?>
    <?php foreach ($impacts as $impact): ?>
      <div class="notice <?= $impact['impact_level'] === 'confirmed_closure' ? 'notice-danger' : 'notice-warn' ?>">
        <strong><?= e(impact_label($impact['impact_level'])) ?></strong>
        <p><?= e(\App\Services\JourneyPlanner::warningSummary($impact['impact_level'])) ?></p>
        <p><?= e($impact['reason']) ?></p>
        <p><a href="/incidents/<?= (int) $impact['report_id'] ?>"><?= e($impact['public_ref']) ?> · <?= e($impact['title']) ?></a></p>
        <p>Calculated <?= e(accra_time($impact['calculated_at'])) ?><?php if ($impact['is_demo']): ?> · Demo data<?php endif; ?></p>
      </div>
    <?php endforeach; ?>
  </article>
</section>
