<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Analytics</h1>
<?php if ($analytics['demo']): ?><p class="notice notice-warn">Demo analytics. These figures include seeded sample reports and should not be read as a citywide measure.</p><?php endif; ?>
<p>Average time to first review: <?= $analytics['review_avg_minutes'] === null ? 'not enough history' : e((string) round($analytics['review_avg_minutes'])) . ' minutes' ?>.</p>
<p>Average time to resolution, for resolved reports: <?= $analytics['resolve_avg_minutes'] === null ? 'none yet' : e((string) round($analytics['resolve_avg_minutes'])) . ' minutes' ?>.</p>
<div class="grid-2">
  <section class="card">
    <h2>By status</h2>
    <?php $max = max(1, ...array_map(static fn ($r) => (int) $r['c'], $analytics['by_status'] ?: [['c' => 1]])); ?>
    <?php foreach ($analytics['by_status'] as $row): ?>
      <p><?= e(status_label($row['status'])) ?> · <?= (int) $row['c'] ?></p>
      <div class="bar"><span style="width: <?= (int) round(((int) $row['c'] / $max) * 100) ?>%"></span></div>
    <?php endforeach; ?>
  </section>
  <section class="card">
    <h2>By category</h2>
    <?php foreach ($analytics['by_category'] as $row): ?><p><?= e($row['name']) ?> · <?= (int) $row['c'] ?></p><?php endforeach; ?>
  </section>
  <section class="card">
    <h2>Routes with active warnings</h2>
    <?php if (!$analytics['routes']): ?><p>None.</p><?php endif; ?>
    <?php foreach ($analytics['routes'] as $row): ?><p><?= e($row['code']) ?> <?= e($row['name']) ?> · <?= (int) $row['c'] ?></p><?php endforeach; ?>
  </section>
  <section class="card">
    <h2>Approximate clusters</h2>
    <p class="help">Coordinates are rounded to about 1 km. This is not a precise heatmap.</p>
    <?php foreach ($analytics['areas'] as $row): ?><p><?= e((string) $row['lat']) ?>, <?= e((string) $row['lng']) ?> · <?= (int) $row['c'] ?> reports</p><?php endforeach; ?>
    <h3>Place labels</h3>
    <?php foreach ($analytics['places'] as $row): ?><p><?= e($row['place_label']) ?> · <?= (int) $row['c'] ?></p><?php endforeach; ?>
  </section>
</div>
