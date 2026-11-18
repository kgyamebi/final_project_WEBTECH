<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1><?= e($title) ?></h1>
<p class="help">Counts include the labelled demo dataset until those rows are removed.</p>
<div class="stat-grid">
  <article class="stat"><strong><?= (int) $stats['pending'] ?></strong> Pending review</article>
  <article class="stat"><strong><?= (int) $stats['verified'] ?></strong> Verified active reports</article>
  <article class="stat"><strong><?= (int) $stats['resolved'] ?></strong> Resolved</article>
  <article class="stat"><strong><?= (int) $stats['attention'] ?></strong> Need attention</article>
  <article class="stat"><strong><?= (int) $stats['routes'] ?></strong> Active routes</article>
  <article class="stat"><strong><?= (int) $stats['stops'] ?></strong> Active stops</article>
</div>
<form method="post" action="/admin/disruption/recalculate" style="margin-top:1rem"><?= csrf_field() ?><button class="btn" type="submit">Recalculate disruption flags</button></form>
<p><a class="btn btn-navy" href="/admin/reports">Open the review queue</a></p>
