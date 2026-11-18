<?php
declare(strict_types=1);

use App\ValidationException;

const REPORT_STATUSES = [
    'pending_review' => 'Pending Review',
    'needs_more_information' => 'Needs More Information',
    'under_review' => 'Under Review',
    'verified' => 'Verified',
    'resolved' => 'Resolved',
    'duplicate' => 'Duplicate',
    'rejected' => 'Rejected',
];

const IMPACT_LEVELS = [
    'information' => 'Information',
    'possible' => 'Possible impact',
    'likely' => 'Likely impact',
    'confirmed_closure' => 'Confirmed closure',
];

const IMPACT_RANK = [
    'information' => 1,
    'possible' => 2,
    'likely' => 3,
    'confirmed_closure' => 4,
];

const ACCRA_BOUNDS = ['south' => 5.50, 'north' => 5.75, 'west' => -0.36, 'east' => -0.12];

const COVERAGE_NOTE = 'Coverage is a small sample of Greater Accra corridors for demonstration. It is not a citywide or official trotro inventory.';

const ROUTE_COLORS = ['#0F766E', '#1D4ED8', '#B45309', '#7C3AED', '#0E7490', '#BE123C', '#3F6212', '#9A3412'];

function env_value(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_value('DB_HOST', '127.0.0.1'),
        env_value('DB_PORT', '3306'),
        env_value('DB_NAME', 'insideaccra')
    );
    $pdo = new PDO($dsn, env_value('DB_USER', 'insideaccra'), env_value('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function q_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function q_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function q_insert(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}

function db_tx(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function url_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/') ?: '/';
    }
    return $path;
}

function is_api(): bool
{
    return str_starts_with(url_path(), '/api');
}

function json_out(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function safe_referer(string $fallback): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $parts = parse_url($ref);
    if (!is_array($parts) || empty($parts['path']) || !str_starts_with((string) $parts['path'], '/')) {
        return $fallback;
    }
    $host = $parts['host'] ?? '';
    $mine = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '' && strcasecmp($host, $mine) !== 0) {
        return $fallback;
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $parts['path'] . $query;
}

function request_data(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($type, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function abort(int $code, string $message): never
{
    http_response_code($code);
    if (is_api() || PHP_SAPI === 'cli') {
        json_out(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $code);
    }
    $titles = [
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Form expired',
        429 => 'Too many attempts',
        500 => 'Something went wrong',
    ];
    view('errors/http', [
        'title' => $titles[$code] ?? 'Error',
        'code' => $code,
        'message' => $message,
    ]);
    exit;
}

function view(string $template, array $data = []): void
{
    $data['title'] = $data['title'] ?? 'InsideAccra';
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $template . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_request_ok(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = $_SESSION['_csrf'] ?? '';
    return is_string($sent) && $sent !== '' && is_string($known) && hash_equals($known, $sent);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

function take_errors(): array
{
    $items = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $items;
}

function take_old(): array
{
    $items = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return $items;
}

function errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = take_errors();
    }
    return $errors;
}

function form_old(): array
{
    static $old = null;
    if ($old === null) {
        $old = take_old();
    }
    return $old;
}

function old(string $key, string $default = ''): string
{
    $value = form_old()[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return (string) $value;
}

function old_array(string $key): array
{
    $value = form_old()[$key] ?? [];
    return is_array($value) ? $value : [];
}

function fail_validation(array $fields, array $source): never
{
    unset($source['password'], $source['password_confirm'], $source['current_password'], $source['_csrf'], $source['images']);
    throw new ValidationException($fields, $source);
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }
    $user = q_one(
        'SELECT u.id, u.name, u.email, u.role_id, u.account_status, u.notify_disruptions, u.created_at, r.name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
        [(int) $id]
    );
    if (!$user || $user['account_status'] !== 'active') {
        $user = null;
        unset($_SESSION['uid']);
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function gate(?array $user, string $ability): bool
{
    $role = $user['role_name'] ?? 'guest';
    return match ($ability) {
        'account' => in_array($role, ['user', 'moderator', 'admin'], true),
        'report.moderate' => in_array($role, ['moderator', 'admin'], true),
        'admin.manage' => $role === 'admin',
        default => false,
    };
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        if (is_api()) {
            abort(401, 'Sign in to continue.');
        }
        $next = url_path();
        $query = $_SERVER['QUERY_STRING'] ?? '';
        if ($query !== '') {
            $next .= '?' . $query;
        }
        redirect('/login?next=' . rawurlencode($next));
    }
    return $user;
}

function require_ability(string $ability): array
{
    $user = require_user();
    if (!gate($user, $ability)) {
        abort(403, 'Your account cannot open that area.');
    }
    return $user;
}

function safe_next(string $next, string $fallback = '/'): string
{
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
        return $fallback;
    }
    return $next;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function accra_time(?string $utc, string $format = 'j M Y, H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Africa/Accra'));
    return $dt->format($format) . ' (Accra)';
}

function accra_input_to_utc(string $local): ?string
{
    $local = trim($local);
    if ($local === '') {
        return null;
    }
    $local = str_replace('T', ' ', $local);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $local)) {
        $local .= ':00';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $local, new DateTimeZone('Africa/Accra'));
    if (!$dt) {
        return null;
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

function status_label(string $status): string
{
    return REPORT_STATUSES[$status] ?? $status;
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'verified' => 'badge badge-verified',
        'pending_review' => 'badge badge-pending',
        'under_review' => 'badge badge-review',
        'needs_more_information' => 'badge badge-warn',
        'resolved' => 'badge badge-resolved',
        'rejected', 'duplicate' => 'badge badge-muted',
        default => 'badge',
    };
    return '<span class="' . $class . '">' . e(status_label($status)) . '</span>';
}

function impact_label(string $level): string
{
    return IMPACT_LEVELS[$level] ?? $level;
}

function trust_label(array $report): string
{
    $demo = !empty($report['is_demo']) ? 'Demo data · ' : '';
    return $demo . match ($report['status'] ?? '') {
        'pending_review' => 'Community report — pending verification',
        'needs_more_information' => 'Community report — needs more information',
        'under_review' => 'Community report — under review',
        'verified' => 'Verified by moderator',
        'resolved' => 'Resolved',
        'duplicate' => 'Duplicate report',
        'rejected' => 'Rejected',
        default => 'Community report',
    };
}

function in_accra(float $lat, float $lng): bool
{
    return $lat >= ACCRA_BOUNDS['south'] && $lat <= ACCRA_BOUNDS['north']
        && $lng >= ACCRA_BOUNDS['west'] && $lng <= ACCRA_BOUNDS['east'];
}

function is_stale_report(array $report): bool
{
    $days = setting_int('stale_days', 21);
    $observed = strtotime(($report['observed_at'] ?? '') . ' UTC');
    if ($observed === false) {
        return false;
    }
    return $observed < time() - ($days * 86400);
}

function setting(string $key, string $default): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function setting_int(string $key, int $default): int
{
    return (int) setting($key, (string) $default);
}

function clear_settings_cache(): void
{
    // Settings are read once per request. Updates redirect before the next read.
}

function route_color(int $id): string
{
    return ROUTE_COLORS[$id % count(ROUTE_COLORS)];
}

function audit(?int $actorId, string $action, string $entityType, ?int $entityId, array $metadata = []): void
{
    q_insert(
        'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$actorId, $action, $entityType, $entityId, $metadata === [] ? null : json_encode($metadata), utc_now()]
    );
}

function notify(int $userId, string $type, string $title, string $body, ?int $reportId = null, ?int $routeId = null): void
{
    if ($type === 'disruption' && $reportId && $routeId) {
        $existing = q_one(
            'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND related_report_id = ? AND related_route_id = ? AND created_at > ?',
            [$userId, $type, $reportId, $routeId, gmdate('Y-m-d H:i:s', time() - 86400)]
        );
        if ($existing) {
            return;
        }
    }
    q_insert(
        'INSERT INTO notifications (user_id, type, title, body, related_report_id, related_route_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $type, $title, $body, $reportId, $routeId, utc_now()]
    );
}

function unread_count(?array $user): int
{
    if (!$user) {
        return 0;
    }
    $row = q_one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int) $user['id']]);
    return (int) ($row['c'] ?? 0);
}

function public_incident(array $report, array $media = [], array $impacts = []): array
{
    return [
        'id' => (int) $report['id'],
        'public_ref' => $report['public_ref'],
        'title' => $report['title'],
        'description' => $report['description'],
        'category' => $report['category_name'] ?? null,
        'category_slug' => $report['category_slug'] ?? null,
        'status' => $report['status'],
        'status_label' => status_label((string) $report['status']),
        'trust_label' => trust_label($report),
        'severity' => $report['severity'],
        'latitude' => (float) $report['latitude'],
        'longitude' => (float) $report['longitude'],
        'place_label' => $report['place_label'],
        'observed_at' => $report['observed_at'],
        'created_at' => $report['created_at'],
        'updated_at' => $report['updated_at'],
        'resolved_at' => $report['resolved_at'],
        'is_demo' => (bool) $report['is_demo'],
        'stale' => is_stale_report($report),
        'duplicate_of_id' => $report['duplicate_of_id'] ? (int) $report['duplicate_of_id'] : null,
        'media' => $media,
        'affected_routes' => $impacts,
    ];
}

function report_is_public(array $report): bool
{
    return ($report['status'] ?? '') !== 'rejected';
}

function pager(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, $page));
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function nav_current(string $path): string
{
    $current = url_path();
    $on = $path === '/' ? $current === '/' : ($current === $path || str_starts_with($current, $path . '/'));
    return $on ? ' aria-current="page"' : '';
}

function field_error(string $key): string
{
    $message = errors()[$key] ?? '';
    if ($message === '') {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . e($message) . '</p>';
}

function invalid_attr(string $key): string
{
    return isset(errors()[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

function json_script(string $id, array $data): string
{
    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    return '<script type="application/json" id="' . e($id) . '">' . $json . '</script>';
}

function log_exception(Throwable $e): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = gmdate('c') . ' ' . $e::class . ' ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    file_put_contents($dir . '/app.log', $line, FILE_APPEND);
}

function run_sql_script(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
    foreach ($parts as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function point_expr(): string
{
    return 'POINT(?, ?)';
}
