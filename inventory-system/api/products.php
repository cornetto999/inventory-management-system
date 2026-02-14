<?php
require_once __DIR__ . '/bootstrap.php';

api_require_login();

$pdo = db();

if (request_method() === 'GET') {
    $search = sanitize_string((string)($_GET['search'] ?? ''));
    $categoryId = sanitize_string((string)($_GET['category_id'] ?? ''));
    $status = sanitize_string((string)($_GET['status'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($perPage < 1) $perPage = 10;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.name LIKE :q OR p.sku LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    if ($categoryId !== '' && $categoryId !== 'all') {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }
    if ($status !== '' && $status !== 'all') {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products p' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT p.id, p.sku, p.name, p.category_id, p.supplier_id, p.unit,
               p.cost_price, p.selling_price, p.stock, p.reorder_level, p.status, p.created_at,
               c.name AS category_name,
               s.name AS supplier_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        $whereSql
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll() ?: [];
    $items = array_map(function ($r) {
        $r['cost_price'] = (float)$r['cost_price'];
        $r['selling_price'] = (float)$r['selling_price'];
        $r['stock'] = (int)$r['stock'];
        $r['reorder_level'] = (int)$r['reorder_level'];
        $r['categories'] = ['name' => (string)$r['category_name']];
        $r['suppliers'] = $r['supplier_name'] !== null ? ['name' => (string)$r['supplier_name']] : null;
        unset($r['category_name'], $r['supplier_name']);
        return $r;
    }, $rows);

    api_ok(['items' => $items, 'total' => $total]);
}

if (request_method() === 'POST') {
    $body = api_body_json();

    $sku = sanitize_string((string)($body['sku'] ?? ''));
    $name = sanitize_string((string)($body['name'] ?? ''));
    $categoryId = sanitize_string((string)($body['category_id'] ?? ''));
    $supplierId = sanitize_string((string)($body['supplier_id'] ?? ''));
    $unit = sanitize_string((string)($body['unit'] ?? 'pcs'));
    $costPrice = (float)($body['cost_price'] ?? 0);
    $sellingPrice = (float)($body['selling_price'] ?? 0);
    $stock = (int)($body['stock'] ?? 0);
    $reorderLevel = (int)($body['reorder_level'] ?? 0);
    $status = sanitize_string((string)($body['status'] ?? 'active'));

    if ($sku === '' || $name === '' || $categoryId === '') api_error('SKU, Name, and Category are required');
    if ($costPrice < 0 || $sellingPrice < 0) api_error('Prices must be >= 0');
    if (!in_array($status, ['active', 'inactive'], true)) api_error('Invalid status');

    $id = uuid_v4();

    $stmt = $pdo->prepare('INSERT INTO products (id, sku, name, category_id, supplier_id, unit, cost_price, selling_price, stock, reorder_level, status) VALUES (:id, :sku, :name, :category_id, :supplier_id, :unit, :cost_price, :selling_price, :stock, :reorder_level, :status)');
    $stmt->execute([
        ':id' => $id,
        ':sku' => $sku,
        ':name' => $name,
        ':category_id' => $categoryId,
        ':supplier_id' => ($supplierId !== '' ? $supplierId : null),
        ':unit' => ($unit !== '' ? $unit : 'pcs'),
        ':cost_price' => $costPrice,
        ':selling_price' => $sellingPrice,
        ':stock' => $stock,
        ':reorder_level' => $reorderLevel,
        ':status' => $status,
    ]);

    api_ok(['id' => $id]);
}

if (request_method() === 'PUT') {
    $body = api_body_json();

    $id = sanitize_string((string)($body['id'] ?? ''));
    if ($id === '') api_error('ID is required');

    $sku = sanitize_string((string)($body['sku'] ?? ''));
    $name = sanitize_string((string)($body['name'] ?? ''));
    $categoryId = sanitize_string((string)($body['category_id'] ?? ''));
    $supplierId = sanitize_string((string)($body['supplier_id'] ?? ''));
    $unit = sanitize_string((string)($body['unit'] ?? 'pcs'));
    $costPrice = (float)($body['cost_price'] ?? 0);
    $sellingPrice = (float)($body['selling_price'] ?? 0);
    $stock = (int)($body['stock'] ?? 0);
    $reorderLevel = (int)($body['reorder_level'] ?? 0);
    $status = sanitize_string((string)($body['status'] ?? 'active'));

    if ($sku === '' || $name === '' || $categoryId === '') api_error('SKU, Name, and Category are required');
    if ($costPrice < 0 || $sellingPrice < 0) api_error('Prices must be >= 0');
    if (!in_array($status, ['active', 'inactive'], true)) api_error('Invalid status');

    $stmt = $pdo->prepare('UPDATE products SET sku=:sku, name=:name, category_id=:category_id, supplier_id=:supplier_id, unit=:unit, cost_price=:cost_price, selling_price=:selling_price, stock=:stock, reorder_level=:reorder_level, status=:status WHERE id=:id');
    $stmt->execute([
        ':sku' => $sku,
        ':name' => $name,
        ':category_id' => $categoryId,
        ':supplier_id' => ($supplierId !== '' ? $supplierId : null),
        ':unit' => ($unit !== '' ? $unit : 'pcs'),
        ':cost_price' => $costPrice,
        ':selling_price' => $sellingPrice,
        ':stock' => $stock,
        ':reorder_level' => $reorderLevel,
        ':status' => $status,
        ':id' => $id,
    ]);

    api_ok();
}

if (request_method() === 'DELETE') {
    $body = api_body_json();
    $id = sanitize_string((string)($body['id'] ?? ''));
    if ($id === '') api_error('ID is required');

    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (Throwable $e) {
        api_error('Failed to delete product');
    }

    api_ok();
}

api_error('Method not allowed', 405);
