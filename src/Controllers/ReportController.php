<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;

final class ReportController
{
    public function form(): void
    {
        $user = require_user();
        view('pages/report', [
            'title' => 'Report an issue',
            'categories' => q_all('SELECT * FROM report_categories WHERE active = 1 ORDER BY name'),
            'routes' => q_all('SELECT id, code, name, direction FROM routes WHERE status = "active" ORDER BY code'),
            'stops' => q_all('SELECT id, name, locality FROM stops WHERE active = 1 ORDER BY name'),
            'user' => $user,
        ]);
    }

    public function submit(): void
    {
        $user = require_user();
        $report = ReportService::create($user, request_data(), $_FILES);
        flash('success', 'Report ' . $report['public_ref'] . ' is Pending Review. It is not shown as a verified fact.');
        redirect('/my/reports/' . $report['id']);
    }

    public function show(array $params): void
    {
        $report = ReportService::find((int) $params['id']);
        if (!$report) {
            abort(404, 'That report does not exist.');
        }
        $user = current_user();
        if (!report_is_public($report)) {
            $owns = $user && (int) $user['id'] === (int) $report['reporter_id'];
            if (!$owns && !gate($user, 'report.moderate')) {
                abort(404, 'That report is not public.');
            }
        }
        view('pages/incident', [
            'title' => $report['public_ref'],
            'report' => $report,
            'media' => ReportService::publicMedia((int) $report['id']),
            'impacts' => ($report['status'] === 'verified') ? ReportService::publicImpacts((int) $report['id']) : [],
            'history' => ReportService::visibleHistory((int) $report['id']),
            'notes' => ($user && ((int) $user['id'] === (int) $report['reporter_id'] || gate($user, 'report.moderate')))
                ? ReportService::reporterNotes((int) $report['id']) : [],
            'user' => $user,
        ]);
    }

    public function flag(array $params): void
    {
        $user = require_user();
        ReportService::flag($user, (int) $params['id'], (string) (request_data()['reason'] ?? ''));
        flash('success', 'Moderators have been told about your flag. Your name is not published on the report.');
        redirect('/incidents/' . (int) $params['id']);
    }

    public function mine(): void
    {
        $user = require_user();
        $reports = q_all(
            'SELECT r.*, c.name AS category_name FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE r.reporter_id = ? ORDER BY r.created_at DESC',
            [(int) $user['id']]
        );
        view('pages/my-reports', ['title' => 'My reports', 'reports' => $reports]);
    }

    public function mineOne(array $params): void
    {
        $user = require_user();
        $report = ReportService::find((int) $params['id']);
        if (!$report || (int) $report['reporter_id'] !== (int) $user['id']) {
            abort(403, 'You can only open your own reports here.');
        }
        $internal = gate($user, 'report.moderate')
            ? q_all('SELECT note, visibility, created_at FROM report_notes WHERE report_id = ? ORDER BY id', [(int) $report['id']])
            : [];
        view('pages/my-report', [
            'title' => $report['public_ref'],
            'report' => $report,
            'history' => ReportService::visibleHistory((int) $report['id']),
            'notes' => ReportService::reporterNotes((int) $report['id']),
            'media' => ReportService::publicMedia((int) $report['id']),
            'impacts' => ReportService::publicImpacts((int) $report['id']),
            'moderatorNotes' => $internal,
        ]);
    }

    public function clarify(array $params): void
    {
        $user = require_user();
        ReportService::addClarification($user, (int) $params['id'], (string) (request_data()['note'] ?? ''));
        flash('success', 'Your details were sent to the moderators.');
        redirect('/my/reports/' . (int) $params['id']);
    }

    public function correction(array $params): void
    {
        $user = require_user();
        ReportService::markNoLongerPresent($user, (int) $params['id'], (string) (request_data()['correction'] ?? ''));
        flash('success', 'Moderators have your note. The report stays in its current status until they resolve it.');
        redirect('/my/reports/' . (int) $params['id']);
    }

    public function media(array $params): void
    {
        $media = q_one(
            'SELECT m.*, r.status, r.reporter_id FROM report_media m JOIN reports r ON r.id = m.report_id WHERE m.id = ?',
            [(int) $params['id']]
        );
        if (!$media) {
            abort(404, 'That image is not available.');
        }
        if ($media['status'] === 'rejected') {
            $user = current_user();
            $owns = $user && (int) $user['id'] === (int) $media['reporter_id'];
            if (!$owns && !gate($user, 'report.moderate')) {
                abort(404, 'That image is not available.');
            }
        }
        $path = APP_ROOT . '/storage/uploads/' . basename((string) $media['storage_path']);
        if (!is_file($path)) {
            abort(404, 'That image file is missing.');
        }
        header('Content-Type: ' . $media['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="evidence-' . (int) $media['id'] . '"');
        readfile($path);
        exit;
    }
}
