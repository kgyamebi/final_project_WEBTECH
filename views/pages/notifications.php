<h1>Notifications</h1>
<form method="post" action="/notifications/read-all"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Mark all read</button></form>
<?php if (!$items): ?><p class="empty">No notifications yet. Status changes and saved-route warnings appear here.</p><?php endif; ?>
<div class="list">
  <?php foreach ($items as $item): ?>
    <article class="item">
      <strong><?= e($item['title']) ?></strong>
      <?php if (!$item['read_at']): ?><span class="badge badge-pending">Unread</span><?php endif; ?>
      <p><?= e($item['body']) ?></p>
      <small><?= e(accra_time($item['created_at'])) ?></small>
      <form method="post" action="/notifications/<?= (int) $item['id'] ?>/read"><?= csrf_field() ?><button class="btn btn-small" type="submit"><?= $item['related_report_id'] ? 'Open and mark read' : 'Mark read' ?></button></form>
    </article>
  <?php endforeach; ?>
</div>
<?php if ($pager['pages'] > 1): ?>
  <p>
    <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
      <a href="/notifications?page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </p>
<?php endif; ?>
