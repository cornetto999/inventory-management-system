<?php
require_once __DIR__ . '/bootstrap.php';

api_require_admin();

$pdo = db();

if (request_method() === 'GET') {
    $stmt = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    api_ok(['users' => $rows ?: []]);
}

if (request_method() === 'POST') {
    $body = api_body_json();

    $name = sanitize_string((string)($body['name'] ?? ''));
    $email = sanitize_string((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $role = sanitize_string((string)($body['role'] ?? 'staff'));

    if ($name === '') api_error('Name is required');
    if ($email === '' || !validate_email($email)) api_error('Valid email is required');
    if ($password === '' || strlen($password) < 6) api_error('Password must be at least 6 characters');
    if (!in_array($role, ['admin', 'staff'], true)) api_error('Invalid role');

    $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute([':email' => $email]);
    if ($exists->fetchColumn()) {
        api_error('Email already exists', 409);
    }

    $id = uuid_v4();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role, status) VALUES (:id, :name, :email, :hash, :role, :status)');
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':email' => $email,
        ':hash' => $hash,
        ':role' => $role,
        ':status' => 'active',
    ]);

    api_ok([
        'user' => [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

api_error('Method not allowed', 405);
