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

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM suppliers' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, name, phone, address, created_at FROM suppliers' . $where . ' ORDER BY name ASC LIMIT :limit OFFSET :offset');
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    api_ok(['items' => $stmt->fetchAll() ?: [], 'total' => $total]);
}

if (request_method() === 'POST') {
    $body = api_body_json();
    $name = sanitize_string((string)($body['name'] ?? ''));
    $phone = sanitize_string((string)($body['phone'] ?? ''));
    $address = sanitize_string((string)($body['address'] ?? ''));

    if ($name === '') api_error('Name is required');
    if (mb_strlen($name) > 200) api_error('Name must be under 200 characters');
    if ($phone !== '' && !validate_phone($phone)) api_error('Invalid phone format');

    $id = uuid_v4();

    $stmt = $pdo->prepare('INSERT INTO suppliers (id, name, phone, address) VALUES (:id, :name, :phone, :address)');
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':phone' => ($phone !== '' ? $phone : null),
        ':address' => ($address !== '' ? $address : null),
    ]);

    api_ok(['item' => ['id' => $id, 'name' => $name, 'phone' => ($phone !== '' ? $phone : null), 'address' => ($address !== '' ? $address : null), 'created_at' => date('Y-m-d H:i:s')]]);
}

if (request_method() === 'PUT') {
    $body = api_body_json();
    $id = sanitize_string((string)($body['id'] ?? ''));
    $name = sanitize_string((string)($body['name'] ?? ''));
    $phone = sanitize_string((string)($body['phone'] ?? ''));
    $address = sanitize_string((string)($body['address'] ?? ''));

    if ($id === '') api_error('ID is required');
    if ($name === '') api_error('Name is required');
    if (mb_strlen($name) > 200) api_error('Name must be under 200 characters');
    if ($phone !== '' && !validate_phone($phone)) api_error('Invalid phone format');

    $stmt = $pdo->prepare('UPDATE suppliers SET name=:name, phone=:phone, address=:address WHERE id=:id');
    $stmt->execute([
        ':name' => $name,
        ':phone' => ($phone !== '' ? $phone : null),
        ':address' => ($address !== '' ? $address : null),
        ':id' => $id,
    ]);

    api_ok();
}

if (request_method() === 'DELETE') {
    $body = api_body_json();
    $id = sanitize_string((string)($body['id'] ?? ''));
    if ($id === '') api_error('ID is required');

    try {
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (Throwable $e) {
        api_error('Failed to delete supplier');
    }

    api_ok();
}

api_error('Method not allowed', 405);
