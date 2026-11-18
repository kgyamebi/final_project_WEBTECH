<?php
declare(strict_types=1);

namespace App\Services;

final class ModerationService
{
    public static function changeStatus(array $actor, int $reportId, array $input): array
    {
        if (!gate($actor, 'report.moderate')) {
            abort(403, 'Only moderators can change report status.');
        }
        $report = ReportService::find($reportId);
        if (!$report) {
            abort(404, 'That report does not exist.');
        }
        $status = (string) ($input['status'] ?? '');
        $errors = [];
        if (!isset(REPORT_STATUSES[$status])) {
            $errors['status'] = 'Choose a valid status.';
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if (str_len($reason) < 8 || str_len($reason) > 500) {
            $errors['reason'] = 'Enter a reason between 8 and 500 characters.';
        }
        $severity = (string) ($input['severity'] ?? $report['severity']);
        if (!in_array($severity, ['low', 'medium', 'high'], true)) {
            $errors['severity'] = 'Choose a severity.';
        }
        $duplicateOf = (int) ($input['duplicate_of_id'] ?? 0);
        if ($status === 'duplicate') {
            if ($duplicateOf === 0) {
                $errors['duplicate_of_id'] = 'Enter the primary report ID this duplicates, if you know it. Use the numeric ID.';
            } elseif ($duplicateOf === $reportId || !q_one('SELECT id FROM reports WHERE id = ?', [$duplicateOf])) {
                $errors['duplicate_of_id'] = 'That primary report does not exist.';
            }
        } else {
            $duplicateOf = 0;
        }
        $closure = 0;
        $closureSource = null;
        if (!empty($input['authoritative_closure'])) {
            if (!gate($actor, 'admin.manage')) {
                $errors['authoritative_closure'] = 'Only an administrator can record an authoritative closure.';
            } elseif ($status !== 'verified') {
                $errors['authoritative_closure'] = 'An authoritative closure can only be recorded on a verified report.';
            } else {
                $closureSource = trim((string) ($input['closure_source'] ?? ''));
                if (str_len($closureSource) < 12) {
                    $errors['closure_source'] = 'Name the authoritative source in at least 12 characters.';
                } else {
                    $closure = 1;
                }
            }
        }
        if ($errors) {
            fail_validation($errors, $input);
        }

        $now = utc_now();
        db_tx(function () use ($actor, $report, $status, $reason, $severity, $duplicateOf, $closure, $closureSource, $now): void {
            q_exec(
                'UPDATE reports SET status = ?, severity = ?, duplicate_of_id = ?, authoritative_closure = ?, closure_source = ?, resolved_at = ?, updated_at = ? WHERE id = ?',
                [
                    $status,
                    $severity,
                    $duplicateOf ?: null,
                    $closure,
                    $closureSource,
                    $status === 'resolved' ? $now : null,
                    $now,
                    (int) $report['id'],
                ]
            );
            q_insert(
                'INSERT INTO report_status_history (report_id, actor_id, old_status, new_status, reason, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [(int) $report['id'], (int) $actor['id'], $report['status'], $status, $reason, $now]
            );
            if ($status === 'needs_more_information') {
                q_insert(
                    'INSERT INTO report_notes (report_id, author_id, note, visibility, created_at) VALUES (?, ?, ?, "reporter", ?)',
                    [(int) $report['id'], (int) $actor['id'], $reason, $now]
                );
            }
            audit((int) $actor['id'], 'report.status', 'report', (int) $report['id'], [
                'from' => $report['status'],
                'to' => $status,
            ]);
        });

        DisruptionEngine::syncReport($reportId);
        self::notifyReporter($report, $status, $reason);
        return ReportService::find($reportId) ?? $report;
    }

    public static function addNote(array $actor, int $reportId, string $note, string $visibility): void
    {
        if (!gate($actor, 'report.moderate')) {
            abort(403, 'Only moderators can add notes.');
        }
        $report = ReportService::find($reportId);
        if (!$report) {
            abort(404, 'That report does not exist.');
        }
        $note = trim($note);
        if (!in_array($visibility, ['internal', 'reporter'], true)) {
            $visibility = 'internal';
        }
        if (str_len($note) < 4) {
            fail_validation(['note' => 'Write a note of at least 4 characters.'], ['note' => $note, 'visibility' => $visibility]);
        }
        q_insert(
            'INSERT INTO report_notes (report_id, author_id, note, visibility, created_at) VALUES (?, ?, ?, ?, ?)',
            [$reportId, (int) $actor['id'], $note, $visibility, utc_now()]
        );
        if ($visibility === 'reporter') {
            notify((int) $report['reporter_id'], 'moderator_request', 'Note on ' . $report['public_ref'], $note, $reportId, null);
        }
        audit((int) $actor['id'], 'report.note', 'report', $reportId, ['visibility' => $visibility]);
    }

    public static function duplicateSuggestions(array $report): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
        $rows = q_all(
            'SELECT r.id, r.public_ref, r.title, r.status, r.latitude, r.longitude, r.created_at, c.name AS category_name
             FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE r.id <> ? AND r.category_id = ? AND r.status <> "rejected" AND r.created_at > ?
             ORDER BY r.created_at DESC LIMIT 80',
            [(int) $report['id'], (int) $report['category_id'], $since]
        );
        $suggestions = [];
        foreach ($rows as $row) {
            $distance = Geo::meters((float) $report['latitude'], (float) $report['longitude'], (float) $row['latitude'], (float) $row['longitude']);
            if ($distance <= 250) {
                $row['distance_m'] = (int) round($distance);
                $suggestions[] = $row;
            }
        }
        return $suggestions;
    }

    private static function notifyReporter(array $report, string $status, string $reason): void
    {
        $label = status_label($status);
        $body = 'Report ' . $report['public_ref'] . ' is now ' . $label . '. ' . $reason;
        if (strlen($body) > 500) {
            $body = substr($body, 0, 497) . '...';
        }
        notify((int) $report['reporter_id'], 'status_changed', 'Report ' . $report['public_ref'] . ': ' . $label, $body, (int) $report['id'], null);
    }
}
