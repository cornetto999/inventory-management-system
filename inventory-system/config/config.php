<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'Inventory System');
define('BASE_URL', '/inventory-system/');

require_once __DIR__ . '/connection.php';

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return rtrim(BASE_URL, '/') . '/' . $path;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function sanitize_string($value): string
{
    $value = (string)$value;
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function validate_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone(?string $phone): bool
{
    if ($phone === null || $phone === '') return true;
    return (bool)preg_match('/^[0-9+\-\s()]{7,20}$/', $phone);
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(): void
{
    $token = (string)post('csrf_token', '');
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Invalid CSRF token.');
    }
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('danger', 'Please login to continue.');
        redirect(base_url('auth/login.php'));
    }
}

function require_role(string $role): void
{
    require_login();
    $u = current_user();
    if (($u['role'] ?? '') !== $role) {
        http_response_code(403);
        die('Forbidden');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function paginate_params(int $defaultPerPage = 10, int $maxPerPage = 100): array
{
    $page = (int)get('page', 1);
    if ($page < 1) $page = 1;

    $perPage = (int)get('per_page', $defaultPerPage);
    if ($perPage < 1) $perPage = $defaultPerPage;
    if ($perPage > $maxPerPage) $perPage = $maxPerPage;

    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}

function pagination_links(int $totalRows, int $page, int $perPage, array $extraQuery = []): string
{
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages <= 1) return '';

    $qsBase = array_merge($_GET, $extraQuery);
    unset($qsBase['page']);

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';

    $prev = max(1, $page - 1);
    $next = min($totalPages, $page + 1);

    $qs = http_build_query(array_merge($qsBase, ['page' => $prev]));
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="?' . e($qs) . '">Prev</a></li>';

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    for ($p = $start; $p <= $end; $p++) {
        $qs = http_build_query(array_merge($qsBase, ['page' => $p]));
        $active = $p === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . e($qs) . '">' . $p . '</a></li>';
    }

    $qs = http_build_query(array_merge($qsBase, ['page' => $next]));
    $html .= '<li class="page-item' . ($page >= $totalPages ? ' disabled' : '') . '"><a class="page-link" href="?' . e($qs) . '">Next</a></li>';

    $html .= '</ul></nav>';
    return $html;
}
