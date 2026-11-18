<?php
declare(strict_types=1);

namespace App\Services;

use App\ValidationException;

final class ReportService
{
    public static function create(array $user, array $input, array $files): array
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            fail_validation(['form' => 'The report could not be submitted.'], $input);
        }
        $window = gmdate('Y-m-d H:i:s', time() - 3600);
        $recent = q_one('SELECT COUNT(*) AS c FROM submission_attempts WHERE user_id = ? AND created_at > ?', [(int) $user['id'], $window]);
        if ((int) ($recent['c'] ?? 0) >= 8) {
            fail_validation(['form' => 'You have submitted several reports in the past hour. Wait a little, then try again.'], $input);
        }

        $errors = [];
        $categoryId = (int) ($input['category_id'] ?? 0);
        $category = $categoryId ? q_one('SELECT * FROM report_categories WHERE id = ? AND active = 1', [$categoryId]) : null;
        if (!$category) {
            $errors['category_id'] = 'Choose a report category.';
        }
        $title = trim((string) ($input['title'] ?? ''));
        if (str_len($title) < 8 || str_len($title) > 160) {
            $errors['title'] = 'Enter a title between 8 and 160 characters.';
        }
        $description = trim((string) ($input['description'] ?? ''));
        if (str_len($description) < 20 || str_len($description) > 4000) {
            $errors['description'] = 'Enter a description between 20 and 4000 characters.';
        }
        $latRaw = trim((string) ($input['latitude'] ?? ''));
        $lngRaw = trim((string) ($input['longitude'] ?? ''));
        if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
            $errors['latitude'] = 'Place a pin on the map or enter a latitude and longitude inside the covered Accra area.';
        } elseif (!in_accra((float) $latRaw, (float) $lngRaw)) {
            $errors['latitude'] = 'That pin is outside the covered area (selected parts of Greater Accra). Move it inside the map area.';
        }
        $observed = accra_input_to_utc((string) ($input['observed_at'] ?? ''));
        if (!$observed) {
            $errors['observed_at'] = 'Enter the date and time you observed this, in Accra local time.';
        } else {
            $stamp = strtotime($observed . ' UTC');
            if ($stamp > time() + 300) {
                $errors['observed_at'] = 'Observed time cannot be in the future.';
            } elseif ($stamp < time() - 365 * 86400) {
                $errors['observed_at'] = 'Observed time must be within the past year.';
            }
        }
        $severity = (string) ($input['severity'] ?? 'medium');
        if (!in_array($severity, ['low', 'medium', 'high'], true)) {
            $errors['severity'] = 'Choose low, medium, or high severity.';
        }
        $place = trim((string) ($input['place_label'] ?? ''));
        if (str_len($place) > 160) {
            $errors['place_label'] = 'Place name must be 160 characters or fewer.';
        }
        $routeId = (int) ($input['related_route_id'] ?? 0);
        $stopId = (int) ($input['related_stop_id'] ?? 0);
        if ($routeId && !q_one('SELECT id FROM routes WHERE id = ?', [$routeId])) {
            $errors['related_route_id'] = 'That route is not in the dataset.';
        }
        if ($stopId && !q_one('SELECT id FROM stops WHERE id = ? AND active = 1', [$stopId])) {
            $errors['related_stop_id'] = 'That stop is not in the dataset.';
        }
        $images = self::collectImages($files, $errors);
        if ($errors) {
            fail_validation($errors, $input);
        }

        $now = utc_now();
        $reportId = db_tx(function () use ($user, $categoryId, $title, $description, $latRaw, $lngRaw, $place, $observed, $severity, $routeId, $stopId, $now, $images): int {
            $ref = self::makeRef();
            $id = q_insert(
                'INSERT INTO reports (public_ref, reporter_id, category_id, title, description, latitude, longitude, location, place_label, observed_at, status, severity, related_route_id, related_stop_id, is_demo, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ' . point_expr() . ', ?, ?, "pending_review", ?, ?, ?, 0, ?, ?)',
                [
                    $ref, (int) $user['id'], $categoryId, $title, $description,
                    $latRaw, $lngRaw, $lngRaw, $latRaw,
                    $place !== '' ? $place : null, $observed, $severity,
                    $routeId ?: null, $stopId ?: null, $now, $now,
                ]
            );
            q_insert(
                'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, NULL, "pending_review", ?, ?)',
                [$id, (int) $user['id'], 'Report submitted', $now]
            );
            q_insert('INSERT INTO submission_attempts (user_id, created_at) VALUES (?, ?)', [(int) $user['id'], $now]);
            foreach ($images as $image) {
                $stored = self::storeImage($image);
                q_insert(
                    'INSERT INTO report_media (report_id, storage_path, mime_type, file_size, original_name, uploaded_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$id, $stored['name'], $stored['mime'], $stored['size'], $image['original'], $now]
                );
            }
            return $id;
        });

        $report = self::find($reportId);
        notify(
            (int) $user['id'],
            'report_submitted',
            'Report ' . $report['public_ref'] . ' submitted',
            'Your report is Pending Review. It is a community submission, not a verified fact, until a moderator reviews it.',
            $reportId
        );
        audit((int) $user['id'], 'report.create', 'report', $reportId, ['public_ref' => $report['public_ref']]);
        return $report;
    }

    public static function addClarification(array $user, int $reportId, string $note): void
    {
        $report = self::find($reportId);
        if (!$report || (int) $report['reporter_id'] !== (int) $user['id']) {
            abort(403, 'You can only update your own reports.');
        }
        $note = trim($note);
        if (str_len($note) < 8) {
            fail_validation(['note' => 'Add at least 8 characters of detail.'], ['note' => $note]);
        }
        $now = utc_now();
        db_tx(function () use ($user, $report, $note, $now): void {
            q_insert(
                'INSERT INTO report_notes (report_id, author_id, note, visibility, created_at) VALUES (?, ?, ?, "reporter", ?)',
                [(int) $report['id'], (int) $user['id'], $note, $now]
            );
            if ($report['status'] === 'needs_more_information') {
                q_exec('UPDATE reports SET status = "under_review", updated_at = ? WHERE id = ?', [$now, (int) $report['id']]);
                q_insert(
                    'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, ?, "under_review", ?, ?)',
                    [(int) $report['id'], (int) $user['id'], $report['status'], 'Reporter added the requested details.', $now]
                );
            } else {
                q_exec('UPDATE reports SET updated_at = ? WHERE id = ?', [$now, (int) $report['id']]);
            }
        });
        self::notifyModerators(
            'Update on ' . $report['public_ref'],
            $user['name'] . ' added details to report ' . $report['public_ref'] . '.',
            (int) $report['id']
        );
    }

    public static function markNoLongerPresent(array $user, int $reportId, string $note): void
    {
        $report = self::find($reportId);
        if (!$report || (int) $report['reporter_id'] !== (int) $user['id']) {
            abort(403, 'You can only update your own reports.');
        }
        $note = trim($note);
        if (str_len($note) < 8) {
            fail_validation(['correction' => 'Describe what changed, in at least 8 characters.'], ['correction' => $note]);
        }
        $text = 'Reporter says the issue is no longer present. ' . $note;
        q_insert(
            'INSERT INTO report_notes (report_id, author_id, note, visibility, created_at) VALUES (?, ?, ?, "reporter", ?)',
            [(int) $report['id'], (int) $user['id'], $text, utc_now()]
        );
        q_exec('UPDATE reports SET updated_at = ? WHERE id = ?', [utc_now(), (int) $report['id']]);
        self::notifyModerators(
            'Possible resolution for ' . $report['public_ref'],
            'The reporter says report ' . $report['public_ref'] . ' may no longer be present. A moderator still needs to resolve it.',
            (int) $report['id']
        );
    }

    public static function flag(array $user, int $reportId, string $reason): void
    {
        $report = self::find($reportId);
        if (!$report || !report_is_public($report)) {
            abort(404, 'That report is not available.');
        }
        $reason = trim($reason);
        if (str_len($reason) < 8) {
            fail_validation(['reason' => 'Tell moderators why this report should be checked, in at least 8 characters.'], ['reason' => $reason]);
        }
        q_insert(
            'INSERT INTO report_flags (report_id, user_id, reason, created_at) VALUES (?, ?, ?, ?)',
            [$reportId, (int) $user['id'], $reason, utc_now()]
        );
        self::notifyModerators('Report flagged: ' . $report['public_ref'], 'A user flagged ' . $report['public_ref'] . ' for review.', $reportId);
        audit((int) $user['id'], 'report.flag', 'report', $reportId, []);
    }

    public static function find(int $id): ?array
    {
        return q_one(
            'SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.route_impact_rule
             FROM reports r JOIN report_categories c ON c.id = r.category_id WHERE r.id = ?',
            [$id]
        );
    }

    public static function findByRef(string $ref): ?array
    {
        $row = q_one('SELECT id FROM reports WHERE public_ref = ?', [$ref]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function publicMedia(int $reportId): array
    {
        $rows = q_all('SELECT id, mime_type, file_size FROM report_media WHERE report_id = ? ORDER BY id', [$reportId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'mime_type' => $row['mime_type'],
            'file_size' => (int) $row['file_size'],
            'url' => '/media/' . $row['id'],
        ], $rows);
    }

    public static function publicImpacts(int $reportId): array
    {
        $rows = q_all(
            'SELECT a.impact_level, a.distance_m, a.reason, a.calculated_at, rt.id AS route_id, rt.code, rt.name
             FROM affected_routes a JOIN routes rt ON rt.id = a.route_id
             WHERE a.report_id = ? AND a.active = 1 ORDER BY a.id',
            [$reportId]
        );
        return array_map(static fn (array $row): array => [
            'route_id' => (int) $row['route_id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'impact_level' => $row['impact_level'],
            'impact_label' => impact_label($row['impact_level']),
            'distance_m' => (int) $row['distance_m'],
            'reason' => $row['reason'],
            'calculated_at' => $row['calculated_at'],
            'summary' => JourneyPlanner::warningSummary($row['impact_level']),
        ], $rows);
    }

    public static function visibleHistory(int $reportId): array
    {
        return q_all(
            'SELECT old_status, new_status, reason, created_at FROM report_status_history WHERE report_id = ? ORDER BY id',
            [$reportId]
        );
    }

    public static function reporterNotes(int $reportId): array
    {
        return q_all(
            'SELECT note, created_at FROM report_notes WHERE report_id = ? AND visibility = "reporter" ORDER BY id',
            [$reportId]
        );
    }

    public static function notifyModerators(string $title, string $body, int $reportId): void
    {
        $mods = q_all('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name IN ("moderator", "admin") AND u.account_status = "active"');
        foreach ($mods as $mod) {
            notify((int) $mod['id'], 'moderation', $title, $body, $reportId, null);
        }
    }

    private static function makeRef(): string
    {
        do {
            $ref = 'IA-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $exists = q_one('SELECT id FROM reports WHERE public_ref = ?', [$ref]);
        } while ($exists);
        return $ref;
    }

    private static function collectImages(array $files, array &$errors): array
    {
        if (!isset($files['images']) || !is_array($files['images']['name'] ?? null)) {
            return [];
        }
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $collected = [];
        $names = $files['images']['name'];
        $count = 0;
        foreach ($names as $index => $name) {
            $error = (int) ($files['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE || $name === '') {
                continue;
            }
            $count++;
            if ($count > 3) {
                $errors['images'] = 'You can attach up to 3 images.';
                break;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors['images'] = 'One of the images did not upload. Try a smaller JPEG, PNG, or WebP.';
                break;
            }
            $size = (int) ($files['images']['size'][$index] ?? 0);
            if ($size <= 0 || $size > 2 * 1024 * 1024) {
                $errors['images'] = 'Each image must be 2 MB or smaller.';
                break;
            }
            $tmp = (string) ($files['images']['tmp_name'][$index] ?? '');
            $info = @getimagesize($tmp);
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
            if (!isset($allowed[$mime])) {
                $errors['images'] = 'Only JPEG, PNG, and WebP images are accepted.';
                break;
            }
            $collected[] = [
                'tmp' => $tmp,
                'mime' => $mime,
                'ext' => $allowed[$mime],
                'size' => $size,
                'original' => substr(basename((string) $name), 0, 160),
            ];
        }
        return $collected;
    }

    private static function storeImage(array $image): array
    {
        $dir = APP_ROOT . '/storage/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . '.' . $image['ext'];
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($image['tmp'], $dest)) {
            if (!rename($image['tmp'], $dest)) {
                throw new \RuntimeException('Could not store the uploaded image.');
            }
        }
        return ['name' => $name, 'mime' => $image['mime'], 'size' => $image['size']];
    }
}
