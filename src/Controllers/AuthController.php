<?php
declare(strict_types=1);

namespace App\Controllers;

final class AuthController
{
    public function form(): void
    {
        if (current_user()) {
            redirect('/profile');
        }
        view('pages/login', ['title' => 'Sign in', 'next' => (string) ($_GET['next'] ?? '/')]);
    }

    public function login(): void
    {
        $input = request_data();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $next = safe_next((string) ($input['next'] ?? '/'));
        $window = gmdate('Y-m-d H:i:s', time() - 900);
        $attempts = q_one(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND email = ? AND created_at > ?',
            [client_ip(), $email, $window]
        );
        if ((int) ($attempts['c'] ?? 0) >= 8) {
            abort(429, 'Too many sign-in attempts. Wait 15 minutes and try again.');
        }
        $user = q_one(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE email = ?',
            [$email]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            q_insert('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, ?)', [client_ip(), $email, utc_now()]);
            fail_validation(['email' => 'Email or password does not match.'], $input);
        }
        if ($user['account_status'] !== 'active') {
            fail_validation(['email' => 'This account is suspended.'], $input);
        }
        login_user($user);
        audit((int) $user['id'], 'auth.login', 'user', (int) $user['id'], []);
        redirect($next);
    }

    public function logout(): void
    {
        $user = current_user();
        if ($user) {
            audit((int) $user['id'], 'auth.logout', 'user', (int) $user['id'], []);
        }
        logout_user();
        redirect('/');
    }

    public function registerForm(): void
    {
        if (current_user()) {
            redirect('/profile');
        }
        view('pages/register', ['title' => 'Create account']);
    }

    public function register(): void
    {
        $input = request_data();
        if (trim((string) ($input['website'] ?? '')) !== '') {
            fail_validation(['form' => 'The account could not be created.'], $input);
        }
        $window = gmdate('Y-m-d H:i:s', time() - 3600);
        $attempts = q_one(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND email = "register" AND created_at > ?',
            [client_ip(), $window]
        );
        if ((int) ($attempts['c'] ?? 0) >= 5) {
            abort(429, 'Too many new accounts from this network. Try again later.');
        }
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');
        if (str_len($name) < 2 || str_len($name) > 120) {
            $errors['name'] = 'Enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (q_one('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors['email'] = 'An account with that email already exists.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
        if ($errors) {
            q_insert('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, "register", ?)', [client_ip(), utc_now()]);
            fail_validation($errors, $input);
        }
        $role = q_one('SELECT id FROM roles WHERE name = "user"');
        $id = q_insert(
            'INSERT INTO users (name, email, password_hash, role_id, account_status, notify_disruptions, created_at, updated_at) VALUES (?, ?, ?, ?, "active", 1, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), (int) $role['id'], utc_now(), utc_now()]
        );
        $user = q_one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
        login_user($user);
        audit($id, 'auth.register', 'user', $id, []);
        flash('success', 'Account created. You can now submit a report.');
        redirect('/report');
    }
}
