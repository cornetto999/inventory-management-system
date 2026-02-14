<?php
require_once __DIR__ . '/bootstrap.php';

api_require_login();

$pdo = db();

if (request_method() === 'GET') {
    $search = sanitize_string((string)($_GET['search'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($perPage < 1) $perPage = 10;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE name LIKE :q ';
        $params[':q'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM categories' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, name, created_at FROM categories' . $where . ' ORDER BY name ASC LIMIT :limit OFFSET :offset');
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    api_ok(['items' => $stmt->fetchAll() ?: [], 'total' => $total]);
}

if (request_method() === 'POST') {
    $body = api_body_json();
    $name = sanitize_string((string)($body['name'] ?? ''));
    if ($name === '') api_error('Name is required');
    if (mb_strlen($name) > 100) api_error('Name must be under 100 characters');

    $id = uuid_v4();

    try {
        $stmt = $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)');
        $stmt->execute([':id' => $id, ':name' => $name]);
    } catch (Throwable $e) {
        api_error('Failed to create category');
    }

    api_ok(['item' => ['id' => $id, 'name' => $name, 'created_at' => date('Y-m-d H:i:s')]]);
}

if (request_method() === 'PUT') {
    $body = api_body_json();
    $id = sanitize_string((string)($body['id'] ?? ''));
    $name = sanitize_string((string)($body['name'] ?? ''));
    if ($id === '') api_error('ID is required');
    if ($name === '') api_error('Name is required');
    if (mb_strlen($name) > 100) api_error('Name must be under 100 characters');

    $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
    $stmt->execute([':name' => $name, ':id' => $id]);
    api_ok();
}

if (request_method() === 'DELETE') {
    $body = api_body_json();
    $id = sanitize_string((string)($body['id'] ?? ''));
    if ($id === '') api_error('ID is required');

    try {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (Throwable $e) {
        api_error('Failed to delete category');
    }

    api_ok();
}

api_error('Method not allowed', 405);
