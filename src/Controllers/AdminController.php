<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DisruptionEngine;
use App\Services\ModerationService;
use App\Services\NetworkAdmin;
use App\Services\ReportService;

final class AdminController
{
    public function dashboard(): void
    {
        $user = require_ability('report.moderate');
        view('admin/dashboard', [
            'title' => gate($user, 'admin.manage') ? 'Admin dashboard' : 'Moderator queue',
            'stats' => $this->stats(),
            'user' => $user,
        ]);
    }

    public function reports(): void
    {
        require_ability('report.moderate');
        $status = trim((string) ($_GET['status'] ?? ''));
        $category = (int) ($_GET['category'] ?? 0);
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = ['1=1'];
        $params = [];
        if ($status !== '' && isset(REPORT_STATUSES[$status])) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($category) {
            $where[] = 'r.category_id = ?';
            $params[] = $category;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'r.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'r.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        if ($q !== '') {
            $where[] = '(r.title LIKE ? ESCAPE \'\\\\\' OR r.public_ref LIKE ? ESCAPE \'\\\\\')';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $count = q_one('SELECT COUNT(*) AS c FROM reports r WHERE ' . implode(' AND ', $where), $params);
        $pager = pager((int) $count['c'], $page, 15);
        $rows = q_all(
            'SELECT r.id, r.public_ref, r.title, r.status, r.severity, r.place_label, r.created_at, r.is_demo, c.name AS category_name
             FROM reports r JOIN report_categories c ON c.id = r.category_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
            $params
        );
        view('admin/reports', [
            'title' => 'Review queue',
            'rows' => $rows,
            'pager' => $pager,
            'filters' => compact('status', 'category', 'from', 'to', 'q'),
            'categories' => q_all('SELECT id, name FROM report_categories ORDER BY name'),
        ]);
    }

    public function review(array $params): void
    {
        require_ability('report.moderate');
        $report = ReportService::find((int) $params['id']);
        if (!$report) {
            abort(404, 'That report does not exist.');
        }
        view('admin/review', [
            'title' => 'Review ' . $report['public_ref'],
            'report' => $report,
            'media' => ReportService::publicMedia((int) $report['id']),
            'history' => q_all(
                'SELECT h.*, u.name AS actor_name FROM report_status_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.report_id = ? ORDER BY h.id',
                [(int) $report['id']]
            ),
            'notes' => q_all(
                'SELECT n.*, u.name AS author_name FROM report_notes n JOIN users u ON u.id = n.author_id WHERE n.report_id = ? ORDER BY n.id',
                [(int) $report['id']]
            ),
            'flags' => q_all('SELECT reason, created_at FROM report_flags WHERE report_id = ? ORDER BY id DESC', [(int) $report['id']]),
            'suggestions' => ModerationService::duplicateSuggestions($report),
            'impacts' => ReportService::publicImpacts((int) $report['id']),
            'isAdmin' => gate(current_user(), 'admin.manage'),
        ]);
    }

    public function status(array $params): void
    {
        $actor = require_ability('report.moderate');
        ModerationService::changeStatus($actor, (int) $params['id'], request_data());
        if (is_api()) {
            $report = ReportService::find((int) $params['id']);
            json_out(['ok' => true, 'data' => public_incident($report, ReportService::publicMedia((int) $report['id']), ReportService::publicImpacts((int) $report['id']))]);
        }
        flash('success', 'Status updated and the disruption flags were recalculated.');
        redirect('/admin/reports/' . (int) $params['id']);
    }

    public function note(array $params): void
    {
        $actor = require_ability('report.moderate');
        $input = request_data();
        ModerationService::addNote($actor, (int) $params['id'], (string) ($input['note'] ?? ''), (string) ($input['visibility'] ?? 'internal'));
        flash('success', 'Note saved.');
        redirect('/admin/reports/' . (int) $params['id']);
    }

    public function routes(): void
    {
        require_ability('admin.manage');
        view('admin/routes', [
            'title' => 'Routes',
            'routes' => q_all('SELECT * FROM routes ORDER BY code'),
        ]);
    }

    public function routeForm(array $params = []): void
    {
        require_ability('admin.manage');
        $route = null;
        $selected = [];
        if (!empty($params['id'])) {
            $route = q_one('SELECT * FROM routes WHERE id = ?', [(int) $params['id']]);
            if (!$route) {
                abort(404, 'That route does not exist.');
            }
            foreach (q_all('SELECT stop_id, sequence_no FROM route_stops WHERE route_id = ?', [(int) $route['id']]) as $row) {
                $selected[(int) $row['stop_id']] = (int) $row['sequence_no'];
            }
        }
        view('admin/route-form', [
            'title' => $route ? 'Edit route' : 'New route',
            'route' => $route,
            'stops' => q_all('SELECT id, name, locality, active FROM stops ORDER BY name'),
            'selected' => $selected,
        ]);
    }

    public function routeCreate(): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveRoute(request_data(), null, (int) $actor['id']);
        flash('success', 'Route saved. Disruption flags were recalculated from the new geometry.');
        redirect('/admin/routes/' . $id . '/edit');
    }

    public function routeUpdate(array $params): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveRoute(request_data(), (int) $params['id'], (int) $actor['id']);
        flash('success', 'Route updated. Disruption flags were recalculated.');
        redirect('/admin/routes/' . $id . '/edit');
    }

    public function routeActive(array $params): void
    {
        $actor = require_ability('admin.manage');
        $active = (request_data()['active'] ?? '0') === '1';
        NetworkAdmin::setRouteActive((int) $params['id'], $active, (int) $actor['id']);
        flash('success', $active ? 'Route activated.' : 'Route deactivated. It will not be offered in journey search.');
        redirect('/admin/routes');
    }

    public function routeCheck(array $params): void
    {
        $actor = require_ability('admin.manage');
        NetworkAdmin::markRouteChecked((int) $params['id'], (int) $actor['id']);
        flash('success', 'Last-checked date updated. This does not make the route an official listing.');
        redirect('/admin/routes/' . (int) $params['id'] . '/edit');
    }

    public function stops(): void
    {
        require_ability('admin.manage');
        view('admin/stops', ['title' => 'Stops', 'stops' => q_all('SELECT * FROM stops ORDER BY name')]);
    }

    public function stopForm(array $params = []): void
    {
        require_ability('admin.manage');
        $stop = null;
        if (!empty($params['id'])) {
            $stop = q_one('SELECT * FROM stops WHERE id = ?', [(int) $params['id']]);
            if (!$stop) {
                abort(404, 'That stop does not exist.');
            }
        }
        view('admin/stop-form', ['title' => $stop ? 'Edit stop' : 'New stop', 'stop' => $stop]);
    }

    public function stopCreate(): void
    {
        $actor = require_ability('admin.manage');
        $id = NetworkAdmin::saveStop(request_data(), null, (int) $actor['id']);
        flash('success', 'Stop saved.');
        redirect('/admin/stops/' . $id . '/edit');
    }

    public function stopUpdate(array $params): void
    {
        $actor = require_ability('admin.manage');
        NetworkAdmin::saveStop(request_data(), (int) $params['id'], (int) $actor['id']);
        flash('success', 'Stop updated.');
        redirect('/admin/stops/' . (int) $params['id'] . '/edit');
    }

    public function stopActive(array $params): void
    {
        $actor = require_ability('admin.manage');
        $active = (request_data()['active'] ?? '0') === '1';
        NetworkAdmin::setStopActive((int) $params['id'], $active, (int) $actor['id']);
        flash('success', $active ? 'Stop activated.' : 'Stop deactivated.');
        redirect('/admin/stops');
    }

    public function stopCheck(array $params): void
    {
        $actor = require_ability('admin.manage');
        NetworkAdmin::markStopChecked((int) $params['id'], (int) $actor['id']);
        flash('success', 'Last-checked date updated.');
        redirect('/admin/stops/' . (int) $params['id'] . '/edit');
    }

    public function users(): void
    {
        require_ability('admin.manage');
        view('admin/users', [
            'title' => 'Users and roles',
            'users' => q_all('SELECT u.id, u.name, u.email, u.account_status, u.created_at, r.name AS role_name, u.role_id FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.name'),
            'roles' => q_all('SELECT id, name FROM roles ORDER BY id'),
        ]);
    }

    public function userUpdate(array $params): void
    {
        $actor = require_ability('admin.manage');
        $input = request_data();
        $user = q_one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $params['id']]);
        if (!$user) {
            abort(404, 'That user does not exist.');
        }
        $role = q_one('SELECT * FROM roles WHERE id = ?', [(int) ($input['role_id'] ?? 0)]);
        $status = (string) ($input['account_status'] ?? '');
        $errors = [];
        if (!$role) {
            $errors['role_id'] = 'Choose a role from the list.';
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            $errors['account_status'] = 'Choose active or suspended.';
        }
        if ((int) $actor['id'] === (int) $user['id'] && $status === 'suspended') {
            $errors['account_status'] = 'You cannot suspend your own account.';
        }
        if ((int) $actor['id'] === (int) $user['id'] && $role && $role['name'] !== 'admin') {
            $errors['role_id'] = 'You cannot remove your own admin role.';
        }
        if ($user['role_name'] === 'admin' && $role && $role['name'] !== 'admin') {
            $others = q_one('SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "admin" AND u.id <> ? AND u.account_status = "active"', [(int) $user['id']]);
            if ((int) $others['c'] < 1) {
                $errors['role_id'] = 'Keep at least one active administrator.';
            }
        }
        if ($errors) {
            fail_validation($errors, $input);
        }
        q_exec('UPDATE users SET role_id = ?, account_status = ?, updated_at = ? WHERE id = ?', [(int) $role['id'], $status, utc_now(), (int) $user['id']]);
        audit((int) $actor['id'], 'user.role', 'user', (int) $user['id'], ['role' => $role['name'], 'status' => $status]);
        flash('success', 'Account updated.');
        redirect('/admin/users');
    }

    public function categories(): void
    {
        require_ability('admin.manage');
        $rows = q_all('SELECT * FROM report_categories ORDER BY name');
        foreach ($rows as &$row) {
            $row['rule'] = json_decode((string) $row['route_impact_rule'], true) ?: [];
        }
        unset($row);
        view('admin/categories', ['title' => 'Report categories', 'categories' => $rows]);
    }

    public function categoryUpdate(array $params): void
    {
        $actor = require_ability('admin.manage');
        $input = request_data();
        $rule = [
            'corridor_likely_m' => max(0, (int) ($input['corridor_likely_m'] ?? 0)),
            'corridor_possible_m' => max(0, (int) ($input['corridor_possible_m'] ?? 0)),
            'stop_likely_m' => max(0, (int) ($input['stop_likely_m'] ?? 0)),
            'stop_possible_m' => max(0, (int) ($input['stop_possible_m'] ?? 0)),
            'max_level' => in_array(($input['max_level'] ?? ''), ['information', 'possible', 'likely'], true) ? $input['max_level'] : 'possible',
        ];
        $description = trim((string) ($input['description'] ?? ''));
        if (str_len($description) < 8) {
            fail_validation(['description' => 'Describe the category.'], $input);
        }
        q_exec(
            'UPDATE report_categories SET description = ?, route_impact_rule = ?, active = ? WHERE id = ?',
            [$description, json_encode($rule), !empty($input['active']) ? 1 : 0, (int) $params['id']]
        );
        audit((int) $actor['id'], 'category.update', 'category', (int) $params['id'], $rule);
        DisruptionEngine::recalculateAll();
        flash('success', 'Category updated and disruption flags recalculated. Confirmed closure is never the maximum for a community category.');
        redirect('/admin/categories');
    }

    public function analytics(): void
    {
        require_ability('admin.manage');
        view('admin/analytics', ['title' => 'Analytics', 'analytics' => $this->analyticsData()]);
    }

    public function analyticsData(): array
    {
        $byStatus = q_all('SELECT status, COUNT(*) AS c FROM reports GROUP BY status');
        $byCategory = q_all('SELECT c.name, COUNT(*) AS c FROM reports r JOIN report_categories c ON c.id = r.category_id GROUP BY c.id, c.name ORDER BY c DESC');
        $routes = q_all(
            'SELECT rt.code, rt.name, COUNT(*) AS c FROM affected_routes a JOIN routes rt ON rt.id = a.route_id WHERE a.active = 1 GROUP BY rt.id, rt.code, rt.name ORDER BY c DESC LIMIT 8'
        );
        $areas = q_all(
            'SELECT ROUND(latitude, 2) AS lat, ROUND(longitude, 2) AS lng, COUNT(*) AS c FROM reports WHERE status <> "rejected" GROUP BY ROUND(latitude, 2), ROUND(longitude, 2) ORDER BY c DESC LIMIT 8'
        );
        $places = q_all(
            'SELECT COALESCE(place_label, "Unlabelled") AS place_label, COUNT(*) AS c FROM reports GROUP BY place_label ORDER BY c DESC LIMIT 8'
        );
        $reports = q_all('SELECT id, created_at, resolved_at, status, is_demo FROM reports');
        $history = q_all('SELECT report_id, old_status, new_status, created_at FROM report_status_history ORDER BY id');
        $byReport = [];
        foreach ($history as $row) {
            $byReport[(int) $row['report_id']][] = $row;
        }
        $reviewMinutes = [];
        $resolveMinutes = [];
        foreach ($reports as $report) {
            $events = $byReport[(int) $report['id']] ?? [];
            if (count($events) >= 2) {
                $start = strtotime($events[0]['created_at'] . ' UTC');
                $next = strtotime($events[1]['created_at'] . ' UTC');
                if ($start && $next) {
                    $reviewMinutes[] = ($next - $start) / 60;
                }
            }
            if ($report['resolved_at']) {
                $start = strtotime($report['created_at'] . ' UTC');
                $end = strtotime($report['resolved_at'] . ' UTC');
                if ($start && $end) {
                    $resolveMinutes[] = ($end - $start) / 60;
                }
            }
        }
        $demo = q_one('SELECT COUNT(*) AS c FROM reports WHERE is_demo = 1');
        return [
            'demo' => ((int) ($demo['c'] ?? 0)) > 0,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'routes' => $routes,
            'areas' => $areas,
            'places' => $places,
            'review_avg_minutes' => $reviewMinutes ? array_sum($reviewMinutes) / count($reviewMinutes) : null,
            'resolve_avg_minutes' => $resolveMinutes ? array_sum($resolveMinutes) / count($resolveMinutes) : null,
            'pending' => $this->stats()['pending'],
        ];
    }

    public function audit(): void
    {
        require_ability('admin.manage');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) q_one('SELECT COUNT(*) AS c FROM audit_logs')['c'];
        $pager = pager($total, $page, 30);
        $rows = q_all(
            'SELECT a.*, u.name AS actor_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id ORDER BY a.id DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
        );
        view('admin/audit', ['title' => 'Audit log', 'rows' => $rows, 'pager' => $pager]);
    }

    public function settings(): void
    {
        require_ability('admin.manage');
        view('admin/settings', [
            'title' => 'Platform settings',
            'settings' => [
                'access_radius_m' => setting('access_radius_m', '1000'),
                'transfer_radius_m' => setting('transfer_radius_m', '400'),
                'stale_days' => setting('stale_days', '21'),
                'suppress_confirmed_closures' => setting('suppress_confirmed_closures', '0'),
            ],
        ]);
    }

    public function settingsSave(): void
    {
        $actor = require_ability('admin.manage');
        $input = request_data();
        $access = (int) ($input['access_radius_m'] ?? 1000);
        $transfer = (int) ($input['transfer_radius_m'] ?? 400);
        $stale = (int) ($input['stale_days'] ?? 21);
        $errors = [];
        if ($access < 200 || $access > 3000) {
            $errors['access_radius_m'] = 'Use a walking access radius between 200 and 3000 metres.';
        }
        if ($transfer < 50 || $transfer > 1500) {
            $errors['transfer_radius_m'] = 'Use a transfer radius between 50 and 1500 metres.';
        }
        if ($stale < 1 || $stale > 90) {
            $errors['stale_days'] = 'Stale period must be between 1 and 90 days.';
        }
        if ($errors) {
            fail_validation($errors, $input);
        }
        $now = utc_now();
        foreach ([
            'access_radius_m' => (string) $access,
            'transfer_radius_m' => (string) $transfer,
            'stale_days' => (string) $stale,
            'suppress_confirmed_closures' => !empty($input['suppress_confirmed_closures']) ? '1' : '0',
        ] as $key => $value) {
            q_exec(
                'INSERT INTO settings (skey, svalue, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = VALUES(updated_at)',
                [$key, $value, $now]
            );
        }
        audit((int) $actor['id'], 'settings.update', 'settings', null, []);
        DisruptionEngine::recalculateAll();
        flash('success', 'Settings saved and disruption flags recalculated.');
        redirect('/admin/settings');
    }

    public function recalculate(): void
    {
        $actor = require_ability('report.moderate');
        $count = DisruptionEngine::recalculateAll();
        audit((int) $actor['id'], 'disruption.recalculate', 'report', null, ['reports' => $count]);
        flash('success', 'Disruption flags recalculated for ' . $count . ' reports. Only verified, non-stale reports can stay active.');
        redirect('/admin');
    }

    private function stats(): array
    {
        $count = static fn (string $sql): int => (int) q_one($sql)['c'];
        return [
            'pending' => $count('SELECT COUNT(*) AS c FROM reports WHERE status = "pending_review"'),
            'attention' => $count('SELECT COUNT(*) AS c FROM reports WHERE status IN ("pending_review", "needs_more_information")'),
            'verified' => $count('SELECT COUNT(*) AS c FROM reports WHERE status = "verified"'),
            'resolved' => $count('SELECT COUNT(*) AS c FROM reports WHERE status = "resolved"'),
            'routes' => $count('SELECT COUNT(*) AS c FROM routes WHERE status = "active"'),
            'stops' => $count('SELECT COUNT(*) AS c FROM stops WHERE active = 1'),
        ];
    }
}
