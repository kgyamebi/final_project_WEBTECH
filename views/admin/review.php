<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<p class="kicker"><?= e($report['public_ref']) ?><?php if ($report['is_demo']): ?> · Demo data<?php endif; ?></p>
<h1><?= e($report['title']) ?></h1>
<p><?= status_badge($report['status']) ?> Severity <?= e($report['severity']) ?></p>
<div class="grid-2">
  <div>
    <div id="map" data-mode="point" role="region" aria-label="Report location"></div>
    <p id="map-status" class="help"><?= e($report['place_label'] ?: 'Pinned location') ?> · <?= e($report['latitude']) ?>, <?= e($report['longitude']) ?></p>
    <?= json_script('point-data', ['lat' => (float) $report['latitude'], 'lng' => (float) $report['longitude'], 'label' => $report['title']]) ?>
    <article class="card">
      <p><?= e($report['description']) ?></p>
      <p>Category: <?= e($report['category_name']) ?></p>
      <p>Observed <?= e(accra_time($report['observed_at'])) ?></p>
      <p><a href="/incidents/<?= (int) $report['id'] ?>">Public page</a></p>
    </article>
    <?php if ($media): ?><h2>Evidence</h2><?php foreach ($media as $image): ?><img src="<?= e($image['url']) ?>" alt="Evidence for <?= e($report['public_ref']) ?>"><?php endforeach; ?><?php endif; ?>
  </div>
  <div>
    <form class="card" method="post" action="/admin/reports/<?= (int) $report['id'] ?>/status">
      <?= csrf_field() ?>
      <h2>Decision</h2>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          <?php foreach (REPORT_STATUSES as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $report['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="severity">Severity</label>
        <select id="severity" name="severity">
          <?php foreach (['low','medium','high'] as $level): ?><option value="<?= e($level) ?>"<?= $report['severity'] === $level ? ' selected' : '' ?>><?= e(ucfirst($level)) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="reason">Reason</label><textarea id="reason" name="reason" required minlength="8"><?= e(old('reason')) ?></textarea><?= field_error('reason') ?><p class="help">Required. The reporter can see this reason on their timeline.</p></div>
      <div class="field"><label for="duplicate_of_id">Primary report ID if duplicate</label><input id="duplicate_of_id" name="duplicate_of_id" value="<?= e(old('duplicate_of_id', (string) ($report['duplicate_of_id'] ?? ''))) ?>"><?= field_error('duplicate_of_id') ?></div>
      <?php if ($isAdmin): ?>
        <label class="check"><input type="checkbox" name="authoritative_closure" value="1"> Record an authoritative closure</label>
        <div class="field"><label for="closure_source">Closure source</label><input id="closure_source" name="closure_source" value="<?= e(old('closure_source')) ?>"><?= field_error('closure_source') ?><p class="help">Only use this with an official source. A nearby user report must not be labelled a closure.</p></div>
      <?php endif; ?>
      <button class="btn" type="submit">Save status</button>
    </form>
    <h2>Possible duplicates</h2>
    <?php if (!$suggestions): ?><p class="empty">No same-category report within 250 m in the last 30 days. This is a suggestion, not a decision.</p><?php endif; ?>
    <ul>
      <?php foreach ($suggestions as $item): ?>
        <li><a href="/admin/reports/<?= (int) $item['id'] ?>"><?= e($item['public_ref']) ?></a> · <?= (int) $item['distance_m'] ?> m · <?= e(status_label($item['status'])) ?> · ID <?= (int) $item['id'] ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($impacts): ?>
      <h2>Active route flags</h2>
      <?php foreach ($impacts as $impact): ?><p><?= e($impact['code']) ?> · <?= e($impact['impact_label']) ?> · <?= e(accra_time($impact['calculated_at'])) ?></p><?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<section class="grid-2">
  <div>
    <h2>History</h2>
    <ol><?php foreach ($history as $event): ?><li><?= e(accra_time($event['created_at'])) ?> · <?= e($event['actor_name'] ?: 'System') ?> · <?= e(status_label($event['new_status'])) ?> — <?= e($event['reason'] ?? '') ?></li><?php endforeach; ?></ol>
  </div>
  <div>
    <h2>Notes</h2>
    <?php foreach ($notes as $note): ?>
      <p class="notice"><?= e($note['visibility']) ?> · <?= e($note['author_name']) ?> · <?= e(accra_time($note['created_at'])) ?><br><?= e($note['note']) ?></p>
    <?php endforeach; ?>
    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/note">
      <?= csrf_field() ?>
      <label for="note">Add a note</label>
      <textarea id="note" name="note" required></textarea>
      <label class="check"><input type="radio" name="visibility" value="internal" checked> Internal only</label>
      <label class="check"><input type="radio" name="visibility" value="reporter"> Visible to the reporter</label>
      <button class="btn btn-ghost" type="submit">Save note</button>
    </form>
    <?php if ($flags): ?><h2>Flags</h2><?php foreach ($flags as $flag): ?><p><?= e($flag['reason']) ?> · <?= e(accra_time($flag['created_at'])) ?></p><?php endforeach; ?><?php endif; ?>
  </div>
</section>
