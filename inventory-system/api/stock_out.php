<?php
require_once __DIR__ . '/bootstrap.php';

$u = api_require_login();
$pdo = db();

if (request_method() === 'GET') {
    $dateFrom = sanitize_string((string)($_GET['date_from'] ?? ''));
    $dateTo = sanitize_string((string)($_GET['date_to'] ?? ''));

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($perPage < 1) $perPage = 10;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($dateFrom !== '') {
        $where[] = 'so.created_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'so.created_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM stock_out so' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("\
        SELECT so.id, so.product_id, so.qty, so.customer, so.remarks, so.created_at,\
               p.name AS product_name, p.sku AS product_sku\
        FROM stock_out so\
        JOIN products p ON p.id = so.product_id\
        $whereSql\
        ORDER BY so.created_at DESC\
        LIMIT :limit OFFSET :offset\
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll() ?: [];
    $items = array_map(function ($r) {
        return [
            'id' => (string)$r['id'],
            'qty' => (int)$r['qty'],
            'remarks' => $r['remarks'] !== null ? (string)$r['remarks'] : null,
            'customer' => $r['customer'] !== null ? (string)$r['customer'] : null,
            'created_at' => (string)$r['created_at'],
            'products' => ['name' => (string)$r['product_name'], 'sku' => (string)$r['product_sku']],
        ];
    }, $rows);

    api_ok(['items' => $items, 'total' => $total]);
}

if (request_method() === 'POST') {
    $body = api_body_json();

    $productId = sanitize_string((string)($body['product_id'] ?? ''));
    $qty = (int)($body['qty'] ?? 0);
    $customer = sanitize_string((string)($body['customer'] ?? ''));
    $remarks = sanitize_string((string)($body['remarks'] ?? ''));

    if ($productId === '' || $qty <= 0) api_error('Product and quantity are required');

    $pdo->beginTransaction();
    try {
        $pstmt = $pdo->prepare('SELECT stock FROM products WHERE id = :id FOR UPDATE');
        $pstmt->execute([':id' => $productId]);
        $prevStock = (int)$pstmt->fetchColumn();

        if ($qty > $prevStock) {
            $pdo->rollBack();
            api_error('Insufficient stock', 400, ['available' => $prevStock]);
        }

        $newStock = $prevStock - $qty;

        $id = uuid_v4();
        $stmt = $pdo->prepare('INSERT INTO stock_out (id, product_id, qty, customer, remarks, created_by) VALUES (:id, :product_id, :qty, :customer, :remarks, :created_by)');
        $stmt->execute([
            ':id' => $id,
            ':product_id' => $productId,
            ':qty' => $qty,
            ':customer' => ($customer !== '' ? $customer : null),
            ':remarks' => ($remarks !== '' ? $remarks : null),
            ':created_by' => (string)$u['id'],
        ]);

        $ustmt = $pdo->prepare('UPDATE products SET stock = :stock WHERE id = :id');
        $ustmt->execute([':stock' => $newStock, ':id' => $productId]);

        $mstmt = $pdo->prepare('INSERT INTO stock_movements (id, product_id, movement_type, qty, prev_stock, new_stock, ref_table, ref_id, user_id, remarks) VALUES (:id, :product_id, :movement_type, :qty, :prev_stock, :new_stock, :ref_table, :ref_id, :user_id, :remarks)');
        $mstmt->execute([
            ':id' => uuid_v4(),
            ':product_id' => $productId,
            ':movement_type' => 'OUT',
            ':qty' => $qty,
            ':prev_stock' => $prevStock,
            ':new_stock' => $newStock,
            ':ref_table' => 'stock_out',
            ':ref_id' => $id,
            ':user_id' => (string)$u['id'],
            ':remarks' => ($remarks !== '' ? $remarks : null),
        ]);

        $pdo->commit();
        api_ok(['id' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        api_error('Failed to record stock out');
    }
}

api_error('Method not allowed', 405);
