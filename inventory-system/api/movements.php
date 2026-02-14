<?php
require_once __DIR__ . '/bootstrap.php';

api_require_login();

$pdo = db();

if (request_method() !== 'GET') {
    api_error('Method not allowed', 405);
}

$type = sanitize_string((string)($_GET['type'] ?? 'all'));
$productId = sanitize_string((string)($_GET['product_id'] ?? 'all'));
$dateFrom = sanitize_string((string)($_GET['date_from'] ?? ''));
$dateTo = sanitize_string((string)($_GET['date_to'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 15);
if ($perPage < 1) $perPage = 15;
if ($perPage > 100) $perPage = 100;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($type !== '' && $type !== 'all') {
    $where[] = 'sm.movement_type = :type';
    $params[':type'] = $type;
}
if ($productId !== '' && $productId !== 'all') {
    $where[] = 'sm.product_id = :product_id';
    $params[':product_id'] = $productId;
}
if ($dateFrom !== '') {
    $where[] = 'sm.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'sm.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM stock_movements sm' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "
    SELECT sm.id, sm.movement_type, sm.qty, sm.prev_stock, sm.new_stock, sm.remarks, sm.created_at,
           p.name AS product_name, p.sku AS product_sku
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    $whereSql
    ORDER BY sm.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll() ?: [];
$items = array_map(function ($r) {
    return [
        'id' => (string)$r['id'],
        'movement_type' => (string)$r['movement_type'],
        'qty' => (int)$r['qty'],
        'prev_stock' => (int)$r['prev_stock'],
        'new_stock' => (int)$r['new_stock'],
        'remarks' => $r['remarks'] !== null ? (string)$r['remarks'] : null,
        'created_at' => (string)$r['created_at'],
        'products' => [
            'name' => (string)$r['product_name'],
            'sku' => (string)$r['product_sku'],
        ],
    ];
}, $rows);

api_ok(['items' => $items, 'total' => $total]);
