<?php
require_once __DIR__ . '/../bootstrap.php';

if (request_method() !== 'POST') {
    api_error('Method not allowed', 405);
}

$body = api_body_json();
$email = sanitize_string((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($email === '' || !validate_email($email)) {
    api_error('Invalid email');
}
if ($password === '') {
    api_error('Password is required');
}

$stmt = db()->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    api_error('Invalid email or password', 401);
}
if (($user['status'] ?? '') !== 'active') {
    api_error('Account inactive', 403);
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (string)$user['id'],
    'name' => (string)$user['name'],
    'email' => (string)$user['email'],
    'role' => (string)$user['role'],
    'status' => (string)$user['status'],
];

api_ok(['user' => $_SESSION['user'], 'csrfToken' => csrf_token()]);
