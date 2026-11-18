<?php require APP_ROOT . '/views/partials/admin-nav.php'; ?>
<h1>Settings</h1>
<form class="card" method="post" action="/admin/settings">
  <?= csrf_field() ?>
  <div class="field"><label for="access_radius_m">Walk to the first or last stop (metres)</label><input id="access_radius_m" name="access_radius_m" type="number" value="<?= e($settings['access_radius_m']) ?>"><?= field_error('access_radius_m') ?><p class="help">Straight-line distance. Stops farther than this are not offered as boarding or alighting points.</p></div>
  <div class="field"><label for="transfer_radius_m">Transfer walk between stops (metres)</label><input id="transfer_radius_m" name="transfer_radius_m" type="number" value="<?= e($settings['transfer_radius_m']) ?>"><?= field_error('transfer_radius_m') ?><p class="help">A transfer is allowed only when two stops are within this distance, or they are the same stop.</p></div>
  <div class="field"><label for="stale_days">Days before a verified report is stale</label><input id="stale_days" name="stale_days" type="number" value="<?= e($settings['stale_days']) ?>"><?= field_error('stale_days') ?></div>
  <label class="check"><input type="checkbox" name="suppress_confirmed_closures" value="1"<?= $settings['suppress_confirmed_closures'] === '1' ? ' checked' : '' ?>> Hide journey legs when an administrator has recorded a confirmed closure</label>
  <div class="actions"><button class="btn" type="submit">Save settings</button></div>
</form>
