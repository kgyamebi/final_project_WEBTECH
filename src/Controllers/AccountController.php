<?php
declare(strict_types=1);

namespace App\Controllers;

final class AccountController
{
    public function profile(): void
    {
        $user = require_user();
        $routes = q_all(
            'SELECT rt.*, sr.id AS saved_id FROM saved_routes sr JOIN routes rt ON rt.id = sr.route_id WHERE sr.user_id = ? ORDER BY rt.code',
            [(int) $user['id']]
        );
        $stops = q_all(
            'SELECT s.*, ss.id AS saved_id FROM saved_stops ss JOIN stops s ON s.id = ss.stop_id WHERE ss.user_id = ? ORDER BY s.name',
            [(int) $user['id']]
        );
        view('pages/profile', [
            'title' => 'Profile',
            'account' => $user,
            'savedRoutes' => $routes,
            'savedStops' => $stops,
        ]);
    }

    public function update(): void
    {
        $user = require_user();
        $input = request_data();
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $notify = !empty($input['notify_disruptions']) ? 1 : 0;
        $errors = [];
        if (str_len($name) < 2) {
            $errors['name'] = 'Enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (q_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int) $user['id']])) {
            $errors['email'] = 'Another account already uses that email.';
        }
        if ($errors) {
            fail_validation($errors, $input);
        }
        q_exec(
            'UPDATE users SET name = ?, email = ?, notify_disruptions = ?, updated_at = ? WHERE id = ?',
            [$name, $email, $notify, utc_now(), (int) $user['id']]
        );
        flash('success', 'Profile updated.');
        redirect('/profile');
    }

    public function password(): void
    {
        $user = require_user();
        $input = request_data();
        $row = q_one('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        $errors = [];
        if (!password_verify((string) ($input['current_password'] ?? ''), $row['password_hash'])) {
            $errors['current_password'] = 'Current password does not match.';
        }
        $password = (string) ($input['password'] ?? '');
        if (strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        }
        if ($password !== (string) ($input['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
        if ($errors) {
            fail_validation($errors, $input);
        }
        q_exec('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), utc_now(), (int) $user['id']]);
        flash('success', 'Password changed.');
        redirect('/profile');
    }

    public function saveRoute(): void
    {
        $user = require_user();
        $routeId = (int) (request_data()['route_id'] ?? 0);
        $route = q_one('SELECT id, code FROM routes WHERE id = ?', [$routeId]);
        if (!$route) {
            abort(404, 'That route is not in the dataset.');
        }
        q_exec(
            'INSERT IGNORE INTO saved_routes (user_id, route_id, created_at) VALUES (?, ?, ?)',
            [(int) $user['id'], $routeId, utc_now()]
        );
        flash('success', $route['code'] . ' saved to your profile.');
        redirect('/routes/' . $routeId);
    }

    public function unsaveRoute(array $params): void
    {
        $user = require_user();
        q_exec('DELETE FROM saved_routes WHERE id = ? AND user_id = ?', [(int) $params['id'], (int) $user['id']]);
        flash('success', 'Route removed from your saved list.');
        redirect('/profile');
    }

    public function saveStop(): void
    {
        $user = require_user();
        $stopId = (int) (request_data()['stop_id'] ?? 0);
        if (!q_one('SELECT id FROM stops WHERE id = ?', [$stopId])) {
            abort(404, 'That stop is not in the dataset.');
        }
        q_exec(
            'INSERT IGNORE INTO saved_stops (user_id, stop_id, created_at) VALUES (?, ?, ?)',
            [(int) $user['id'], $stopId, utc_now()]
        );
        flash('success', 'Stop saved to your profile.');
        redirect('/stops/' . $stopId);
    }

    public function unsaveStop(array $params): void
    {
        $user = require_user();
        q_exec('DELETE FROM saved_stops WHERE id = ? AND user_id = ?', [(int) $params['id'], (int) $user['id']]);
        flash('success', 'Stop removed from your saved list.');
        redirect('/profile');
    }

    public function notifications(): void
    {
        $user = require_user();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) q_one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?', [(int) $user['id']])['c'];
        $pager = pager($total, $page, 20);
        $items = q_all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
            [(int) $user['id']]
        );
        view('pages/notifications', ['title' => 'Notifications', 'items' => $items, 'pager' => $pager]);
    }

    public function read(array $params): void
    {
        $user = require_user();
        $note = q_one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int) $params['id'], (int) $user['id']]);
        if (!$note) {
            abort(404, 'That notification is not yours.');
        }
        if ($note['read_at'] === null) {
            q_exec('UPDATE notifications SET read_at = ? WHERE id = ?', [utc_now(), (int) $note['id']]);
        }
        if (is_api()) {
            json_out(['ok' => true, 'data' => ['id' => (int) $note['id'], 'read' => true]]);
        }
        $target = '/notifications';
        if ($note['related_report_id']) {
            $target = '/incidents/' . (int) $note['related_report_id'];
        }
        redirect($target);
    }

    public function readAll(): void
    {
        $user = require_user();
        q_exec('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [utc_now(), (int) $user['id']]);
        flash('success', 'All notifications marked read.');
        redirect('/notifications');
    }
}
