<p class="kicker">Your report</p>
<h1><?= e($report['public_ref']) ?></h1>
<p><?= status_badge($report['status']) ?> <span class="badge"><?= e(trust_label($report)) ?></span></p>
<p><a href="/incidents/<?= (int) $report['id'] ?>">Public page</a></p>
<article class="card">
  <h2><?= e($report['title']) ?></h2>
  <p><?= e($report['description']) ?></p>
  <p>Observed <?= e(accra_time($report['observed_at'])) ?> at <?= e($report['place_label'] ?: ($report['latitude'] . ', ' . $report['longitude'])) ?>.</p>
</article>
<h2>Status timeline</h2>
<ol>
  <?php foreach ($history as $event): ?>
    <li><?= e(accra_time($event['created_at'])) ?> · <?= e(status_label($event['new_status'])) ?><?php if ($event['reason']): ?> — <?= e($event['reason']) ?><?php endif; ?></li>
  <?php endforeach; ?>
</ol>
<?php if ($notes): ?>
  <h2>Notes shared with you</h2>
  <?php foreach ($notes as $note): ?><p class="notice"><?= e($note['note']) ?></p><?php endforeach; ?>
<?php endif; ?>
<form class="card" method="post" action="/my/reports/<?= (int) $report['id'] ?>/clarification">
  <?= csrf_field() ?>
  <h2>Add details</h2>
  <p class="help">Use this when a moderator asks for more information, or to correct the description.</p>
  <label for="note">Details</label>
  <textarea id="note" name="note" required minlength="8"><?= e(old('note')) ?></textarea>
  <?= field_error('note') ?>
  <button class="btn" type="submit">Send details</button>
</form>
<form class="card" method="post" action="/my/reports/<?= (int) $report['id'] ?>/correction">
  <?= csrf_field() ?>
  <h2>Issue no longer present</h2>
  <label for="correction">What changed?</label>
  <textarea id="correction" name="correction" required minlength="8"><?= e(old('correction')) ?></textarea>
  <?= field_error('correction') ?>
  <button class="btn btn-ghost" type="submit">Tell moderators</button>
</form>
<?php if ($media): ?>
  <h2>Your images</h2>
  <?php foreach ($media as $image): ?><img src="<?= e($image['url']) ?>" alt="Your evidence for <?= e($report['public_ref']) ?>"><?php endforeach; ?>
<?php endif; ?>
