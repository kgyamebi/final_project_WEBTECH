<section class="hero">
  <div>
    <p class="kicker">Accra · trotro routes and community reports</p>
    <h1>Navigate the city. Report what matters.</h1>
    <p class="lede">InsideAccra connects journey information with community-reported road and transport conditions. A verified report near a stored route can warn commuters. It does not invent routes, fares, or live vehicle positions.</p>
    <form class="card search-card" method="get" action="/journey" style="margin-top:1rem">
      <div class="row-2">
        <div class="field">
          <label for="from">From</label>
          <input id="from" name="from" required placeholder="Madina" data-suggest="from-suggest" autocomplete="off">
          <ul class="suggest" id="from-suggest"></ul>
        </div>
        <div class="field">
          <label for="to">To</label>
          <input id="to" name="to" required placeholder="Circle" data-suggest="to-suggest" autocomplete="off">
          <ul class="suggest" id="to-suggest"></ul>
        </div>
      </div>
      <p class="help">Try Madina to Circle, or Madina to Kaneshie for a sample transfer. <?= e(COVERAGE_NOTE) ?></p>
      <div class="actions">
        <button class="btn" type="submit">Find supported journeys</button>
        <a class="btn btn-ghost" href="/report">Report an issue</a>
      </div>
    </form>
  </div>
  <div>
    <div id="map" data-mode="preview" role="region" aria-label="Preview map of sample Accra routes"></div>
    <p id="map-status" class="help">Loading map…</p>
    <?= json_script('map-data', $payload ?? []) ?>
  </div>
</section>
<?php if (!isset($payload)): ?>
<?php /* preview payload is passed below if set; home controller does not pass it */ ?>
<?php endif; ?>

<section class="grid-2">
  <div>
    <h2>Disruption notices</h2>
    <p class="help">Only verified reports can create these warnings. Wording stays cautious: proximity is not a road closure.</p>
    <?php if (!$disruptions): ?>
      <p class="empty">No active verified warnings in the sample dataset.</p>
    <?php endif; ?>
    <div class="list">
      <?php foreach ($disruptions as $item): ?>
        <a class="item" href="/routes/<?= (int) $item['route_id'] ?>">
          <span class="badge badge-warn"><?= e(impact_label($item['impact_level'])) ?></span>
          <strong><?= e($item['code']) ?> <?= e($item['name']) ?></strong>
          <small><?= e($item['public_ref']) ?> · <?= e($item['title']) ?></small>
          <small>Calculated <?= e(accra_time($item['calculated_at'])) ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div>
    <h2>Community reports</h2>
    <div class="list">
      <?php foreach ($highlights as $item): ?>
        <a class="item" href="/incidents/<?= (int) $item['id'] ?>">
          <?= status_badge($item['status']) ?>
          <?php if ($item['is_demo']): ?><span class="badge badge-demo">Demo data</span><?php endif; ?>
          <strong><?= e($item['title']) ?></strong>
          <small><?= e($item['category_name']) ?> · <?= e($item['place_label'] ?: 'Accra') ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <h2>How it works</h2>
  <div class="steps">
    <article><h3>1. Plan with stored routes</h3><p>Search origin and destination. InsideAccra returns only journeys supported by the route and stop records, including one transfer when the stops connect.</p></article>
    <article><h3>2. Report what you see</h3><p>Sign in, choose a category, and pin the place. The report starts as Pending Review and gets a reference number.</p></article>
    <article><h3>3. Moderators verify</h3><p>A verified report near a route line or stop can show a warning. Resolved or rejected reports drop that warning.</p></article>
  </div>
</section>
