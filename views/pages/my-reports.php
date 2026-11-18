<h1>My reports</h1>
<p>Only your submissions are listed here. Other people cannot open this page as you.</p>
<?php if (!$reports): ?><p class="empty">You have not submitted a report yet. <a href="/report">Report an issue</a>.</p><?php endif; ?>
<div class="list">
  <?php foreach ($reports as $report): ?>
    <a class="item" href="/my/reports/<?= (int) $report['id'] ?>">
      <?= status_badge($report['status']) ?>
      <strong><?= e($report['public_ref']) ?> · <?= e($report['title']) ?></strong>
      <small><?= e($report['category_name']) ?> · Updated <?= e(accra_time($report['updated_at'])) ?></small>
    </a>
  <?php endforeach; ?>
</div>
