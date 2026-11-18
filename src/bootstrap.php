<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        $existing = getenv($key);
        if ($existing !== false && $existing !== '') {
            $_ENV[$key] = $existing;
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

load_env(APP_ROOT . '/.env');
date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/functions.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof App\ValidationException) {
        if (is_api()) {
            json_out([
                'ok' => false,
                'error' => [
                    'code' => 'validation',
                    'message' => 'Check the highlighted fields.',
                    'fields' => $e->fields,
                ],
            ], 422);
        }
        $_SESSION['_errors'] = $e->fields;
        $_SESSION['_old'] = $e->old;
        redirect(safe_referer('/'));
    }
    log_exception($e);
    $message = 'The server could not complete that request.';
    if ($e instanceof PDOException) {
        $message = 'The database is not available. Check MariaDB, the .env file, and that migrations have been run.';
    }
    if (!headers_sent()) {
        abort(500, $message);
    }
    echo e($message);
});

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; style-src 'self'; script-src 'self'; connect-src 'self' https://tile.openstreetmap.org https://*.tile.openstreetmap.org; base-uri 'self'; form-action 'self'");

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !csrf_request_ok()) {
        abort(419, 'The form session expired. Go back, refresh the page, and try again.');
    }
}
