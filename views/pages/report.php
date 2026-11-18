<p class="kicker">Community report</p>
<h1>Report an issue</h1>
<p class="lede">Tell InsideAccra what you observed. The report is stored as <strong>Pending Review</strong>. It is not treated as a verified fact, and it does not by itself close a road.</p>
<form class="card" method="post" action="/report" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="field" hidden>
    <label for="website">Leave this empty</label>
    <input id="website" name="website" tabindex="-1" autocomplete="off">
  </div>
  <div class="field">
    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required<?= invalid_attr('category_id') ?>>
      <option value="">Choose</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>"<?= old('category_id') == (string) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?= field_error('category_id') ?>
  </div>
  <div class="field">
    <label for="title">Title</label>
    <input id="title" name="title" required minlength="8" maxlength="160" value="<?= e(old('title')) ?>"<?= invalid_attr('title') ?>>
    <?= field_error('title') ?>
  </div>
  <div class="field">
    <label for="description">What did you see?</label>
    <textarea id="description" name="description" required minlength="20" maxlength="4000"<?= invalid_attr('description') ?>><?= e(old('description')) ?></textarea>
    <?= field_error('description') ?>
  </div>
  <div class="row-2">
    <div class="field">
      <label for="severity">Severity</label>
      <select id="severity" name="severity">
        <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= old('severity', 'medium') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="observed_at">Observed (Accra time)</label>
      <input id="observed_at" type="datetime-local" name="observed_at" required value="<?= e(old('observed_at')) ?>"<?= invalid_attr('observed_at') ?>>
      <?= field_error('observed_at') ?>
    </div>
  </div>
  <div class="field">
    <label for="place_label">Place name, optional</label>
    <input id="place_label" name="place_label" value="<?= e(old('place_label')) ?>" placeholder="Near Okponglo">
  </div>
  <p class="help">Place the pin on the map, or type coordinates. The browser will ask before using your location. If you refuse, the pin and the place name still work.</p>
  <div id="map" data-mode="pin" role="region" aria-label="Choose the report location"></div>
  <p id="map-status" class="help">Tap the map to drop a pin inside the covered Accra area.</p>
  <div class="actions"><button class="btn btn-ghost" type="button" id="use-location">Use my location</button></div>
  <div class="row-2">
    <div class="field">
      <label for="latitude">Latitude</label>
      <input id="latitude" name="latitude" inputmode="decimal" required value="<?= e(old('latitude')) ?>"<?= invalid_attr('latitude') ?>>
      <?= field_error('latitude') ?>
    </div>
    <div class="field">
      <label for="longitude">Longitude</label>
      <input id="longitude" name="longitude" inputmode="decimal" required value="<?= e(old('longitude')) ?>">
    </div>
  </div>
  <div class="row-2">
    <div class="field">
      <label for="related_route_id">Related route, optional</label>
      <select id="related_route_id" name="related_route_id">
        <option value="">None</option>
        <?php foreach ($routes as $route): ?>
          <option value="<?= (int) $route['id'] ?>"<?= old('related_route_id') == (string) $route['id'] ? ' selected' : '' ?>><?= e($route['code'] . ' ' . $route['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="related_stop_id">Related stop, optional</label>
      <select id="related_stop_id" name="related_stop_id">
        <option value="">None</option>
        <?php foreach ($stops as $stop): ?>
          <option value="<?= (int) $stop['id'] ?>"<?= old('related_stop_id') == (string) $stop['id'] ? ' selected' : '' ?>><?= e($stop['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="field">
    <label for="images">Photos, optional</label>
    <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="help">Up to 3 JPEG, PNG, or WebP images, 2 MB each.</p>
    <?= field_error('images') ?>
  </div>
  <p class="help">Your name and email are not published. Moderators can see your account so they can ask for more detail. See the <a href="/privacy">privacy notice</a>.</p>
  <button class="btn" type="submit">Submit report</button>
</form>
