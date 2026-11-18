<p class="kicker"><?= e($report['public_ref']) ?></p>
<h1><?= e($report['title']) ?></h1>
<p><?= status_badge($report['status']) ?> <span class="badge"><?= e(trust_label($report)) ?></span> <?php if ($report['is_demo']): ?><span class="badge badge-demo">Demo data</span><?php endif; ?></p>
<?php if (is_stale_report($report)): ?><p class="notice notice-warn">This report is older than the stale-report window. Treat it as possibly outdated.</p><?php endif; ?>
<div class="map-layout">
  <div>
    <div id="map" data-mode="point" role="region" aria-label="Report location"></div>
    <p id="map-status" class="help"><?= e($report['place_label'] ?: 'Pinned location') ?></p>
    <?= json_script('point-data', ['lat' => (float) $report['latitude'], 'lng' => (float) $report['longitude'], 'label' => $report['title']]) ?>
  </div>
  <aside class="map-panel">
    <p><?= e($report['description']) ?></p>
    <p>Category: <?= e($report['category_name']) ?> · Severity: <?= e($report['severity']) ?></p>
    <p>Observed <?= e(accra_time($report['observed_at'])) ?></p>
    <p>Submitted <?= e(accra_time($report['created_at'])) ?> · Updated <?= e(accra_time($report['updated_at'])) ?></p>
    <?php if ($report['resolved_at']): ?><p>Resolved <?= e(accra_time($report['resolved_at'])) ?></p><?php endif; ?>
    <?php if ($report['duplicate_of_id']): ?><p>Duplicate of <a href="/incidents/<?= (int) $report['duplicate_of_id'] ?>">report <?= (int) $report['duplicate_of_id'] ?></a>.</p><?php endif; ?>
    <?php if ($user && (int) $user['id'] === (int) $report['reporter_id']): ?>
      <p><a href="/my/reports/<?= (int) $report['id'] ?>">Manage your report</a></p>
    <?php endif; ?>
  </aside>
</div>
<?php if ($media): ?>
  <h2>Evidence</h2>
  <div class="grid-3">
    <?php foreach ($media as $image): ?>
      <img src="<?= e($image['url']) ?>" alt="Evidence attached to <?= e($report['public_ref']) ?>">
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<h2>Route warnings</h2>
<?php if ($report['status'] !== 'verified'): ?>
  <p class="empty">This report is <?= e(status_label($report['status'])) ?>. It does not create an active disruption warning. Pending reports are not described as closures.</p>
<?php elseif (!$impacts): ?>
  <p class="empty">Verified, but not close enough to a stored route or stop to flag one. The report remains on the map.</p>
<?php else: ?>
  <?php foreach ($impacts as $impact): ?>
    <div class="notice notice-warn">
      <strong><?= e($impact['impact_label']) ?> · <a href="/routes/<?= (int) $impact['route_id'] ?>"><?= e($impact['code']) ?> <?= e($impact['name']) ?></a></strong>
      <p><?= e($impact['summary']) ?></p>
      <p><?= e($impact['reason']) ?></p>
      <p>Calculated <?= e(accra_time($impact['calculated_at'])) ?></p>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<h2>Status history</h2>
<ol>
  <?php foreach ($history as $event): ?>
    <li><?= e(accra_time($event['created_at'])) ?> · <?= e($event['old_status'] ? status_label($event['old_status']) . ' → ' : '') ?><?= e(status_label($event['new_status'])) ?><?php if ($event['reason']): ?> — <?= e($event['reason']) ?><?php endif; ?></li>
  <?php endforeach; ?>
</ol>
<?php if ($notes): ?>
  <h2>Messages</h2>
  <?php foreach ($notes as $note): ?><p class="notice"><?= e($note['note']) ?><small><?= e(accra_time($note['created_at'])) ?></small></p><?php endforeach; ?>
<?php endif; ?>
<?php if ($user): ?>
  <form class="card" method="post" action="/incidents/<?= (int) $report['id'] ?>/flag">
    <?= csrf_field() ?>
    <h2>Flag this report</h2>
    <label for="reason">Why should a moderator look again?</label>
    <textarea id="reason" name="reason" required minlength="8"></textarea>
    <?= field_error('reason') ?>
    <button class="btn btn-ghost" type="submit">Send flag</button>
  </form>
<?php endif; ?>
