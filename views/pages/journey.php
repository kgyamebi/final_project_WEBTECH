<p class="kicker">Journey search</p>
<h1>Plan a journey</h1>
<p class="lede">Results come only from stored routes and stops. Direct rides are listed before one-transfer rides. <?= e(COVERAGE_NOTE) ?></p>
<form class="card" method="get" action="/journey">
  <div class="row-2">
    <div class="field">
      <label for="from">Origin</label>
      <input id="from" name="from" required value="<?= e($from) ?>" placeholder="Madina, a stop, or lat, lng" data-suggest="from-suggest" autocomplete="off"<?= invalid_attr('from') ?>>
      <ul class="suggest" id="from-suggest"></ul>
    </div>
    <div class="field">
      <label for="to">Destination</label>
      <input id="to" name="to" required value="<?= e($to) ?>" placeholder="Kaneshie" data-suggest="to-suggest" autocomplete="off">
      <ul class="suggest" id="to-suggest"></ul>
    </div>
  </div>
  <button class="btn" type="submit">Search journeys</button>
</form>

<?php
$selected = null;
if ($result && $result['options']) {
    $selected = $result['options'][$option] ?? $result['options'][0];
}
$journeyJson = ['geometry' => null, 'stops' => []];
if ($selected) {
    $coords = [];
    $stops = [];
    foreach ($selected['legs'] as $leg) {
        foreach ($leg['geometry'] as $pair) {
            $coords[] = $pair;
        }
        foreach ($leg['stops'] as $stop) {
            $stops[] = $stop;
        }
    }
    $journeyJson = ['geometry' => ['type' => 'LineString', 'coordinates' => $coords], 'stops' => $stops];
}
?>
<div class="map-layout" style="margin-top:1rem">
  <div>
    <div id="map" data-mode="journey" role="region" aria-label="Map of the selected journey"></div>
    <p id="map-status" class="help">The map draws a journey only when the dataset supports one.</p>
    <?= json_script('journey-data', $journeyJson) ?>
  </div>
  <div class="map-panel" aria-live="polite">
    <?php if ($from !== '' && $to !== '' && $fromRes && $fromRes['status'] === 'ambiguous'): ?>
      <h2>Choose an origin</h2>
      <p>Several places match “<?= e($from) ?>”.</p>
      <div class="list">
        <?php foreach ($fromRes['options'] as $place): ?>
          <a class="item" href="/journey?from=<?= urlencode($place['name'] ?? $place['label']) ?>&amp;to=<?= urlencode($to) ?>"><?= e($place['label']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php elseif ($from !== '' && $to !== '' && $toRes && $toRes['status'] === 'ambiguous'): ?>
      <h2>Choose a destination</h2>
      <div class="list">
        <?php foreach ($toRes['options'] as $place): ?>
          <a class="item" href="/journey?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($place['name'] ?? $place['label']) ?>"><?= e($place['label']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php elseif ($from !== '' && $to !== '' && (($fromRes['status'] ?? '') === 'none' || ($toRes['status'] ?? '') === 'none' || ($fromRes['status'] ?? '') === 'outside' || ($toRes['status'] ?? '') === 'outside')): ?>
      <div class="empty">
        <strong>Those places are not in the covered dataset.</strong>
        <p>No route was invented. Search a sample stop such as Madina, Circle, Kaneshie, Osu, Lapaz, or Achimota, or enter coordinates inside Greater Accra.</p>
      </div>
    <?php elseif ($result): ?>
      <p class="help"><?= e($result['rank_note']) ?></p>
      <?php if ($result['suppressed']): ?>
        <p class="notice notice-danger"><?= (int) $result['suppressed'] ?> option(s) hidden because an administrator confirmed a closure and suppression is turned on.</p>
      <?php endif; ?>
      <?php if (!$result['options']): ?>
        <div class="empty">
          <strong>No supported journey</strong>
          <p>The stored routes do not connect these places within the walking access radius (<?= (int) $result['access_radius_m'] ?> m) and transfer radius (<?= (int) $result['transfer_radius_m'] ?> m).</p>
        </div>
      <?php endif; ?>
      <?php foreach ($result['options'] as $index => $item): ?>
        <article class="item" style="margin-bottom:.7rem;<?= $index === $option ? 'outline:2px solid #0f766e' : '' ?>">
          <a href="/journey?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>&amp;option=<?= (int) $index ?>">
            <strong><?= $item['transfers'] === 0 ? 'Direct' : '1 transfer' ?></strong>
            <span class="mono"><?php foreach ($item['legs'] as $leg) { echo e($leg['code']) . ' '; } ?></span>
          </a>
          <p>Straight-line walk about <?= (int) $item['walk_m'] ?> m. Mapped stop spacing about <?= number_format($item['spacing_m'] / 1000, 1) ?> km. Duration not verified.</p>
          <?php foreach ($item['limitations'] as $note): ?><p class="help"><?= e($note) ?></p><?php endforeach; ?>
          <ol>
            <?php foreach ($item['instructions'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
          </ol>
          <?php foreach ($item['legs'] as $leg): ?>
            <p><a href="/routes/<?= (int) $leg['route_id'] ?>"><?= e($leg['code']) ?> <?= e($leg['name']) ?></a> · <?= e($leg['fare_label']) ?> · Last checked <?= e(accra_time($leg['verified_at'])) ?></p>
            <?php foreach ($leg['warnings'] as $warning): ?>
              <div class="notice <?= $warning['impact_level'] === 'information' ? 'notice-info' : ($warning['impact_level'] === 'confirmed_closure' ? 'notice-danger' : 'notice-warn') ?>">
                <strong><?= e($warning['impact_label']) ?></strong>
                <p><?= e($warning['summary']) ?></p>
                <p><?= e($warning['reason']) ?></p>
                <p><a href="/incidents/<?= (int) $warning['report_id'] ?>"><?= e($warning['public_ref']) ?></a> · Calculated <?= e(accra_time($warning['calculated_at'])) ?><?php if ($warning['is_demo']): ?> · Demo data<?php endif; ?></p>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty">Enter an origin and destination. Sample searches: Madina to Circle, Lapaz to Osu, Mallam to Madina.</p>
    <?php endif; ?>
  </div>
</div>
