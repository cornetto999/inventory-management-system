<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOrigin = '';

// Allow local dev origins (Vite) + same-host origins.
if ($origin && preg_match('#^https?://(localhost|127\.0\.0\.1)(?::\d+)?$#', $origin)) {
    $allowOrigin = $origin;
}

if ($allowOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

if (request_method() === 'OPTIONS') {
    // Preflight request
    http_response_code(204);
    exit;
}

function api_json($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function api_error(string $message, int $status = 400, array $extra = []): void
{
    api_json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function api_ok(array $data = []): void
{
    api_json(array_merge(['ok' => true], $data), 200);
}

function api_body_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    return $decoded;
}

function api_require_login(): array
{
    if (!is_logged_in()) {
        api_error('Unauthorized', 401);
    }
    return (array)current_user();
}

function api_require_admin(): array
{
    $u = api_require_login();
    if (($u['role'] ?? '') !== 'admin') {
        api_error('Forbidden', 403);
    }
    return $u;
}
