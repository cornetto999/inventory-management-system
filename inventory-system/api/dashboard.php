<?php
require_once __DIR__ . '/bootstrap.php';

api_require_login();

$pdo = db();

$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

$lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock <= reorder_level")->fetchColumn();

$inventoryValue = (float)$pdo->query("SELECT COALESCE(SUM(cost_price * stock),0) FROM products WHERE status='active'")->fetchColumn();
$sellingValue = (float)$pdo->query("SELECT COALESCE(SUM(selling_price * stock),0) FROM products WHERE status='active'")->fetchColumn();

$stockInToday = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_in WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stockInMonth = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_in WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();

$stockOutToday = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stockOutMonth = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();

$recentStmt = $pdo->prepare("
    SELECT sm.id, sm.movement_type, sm.qty, sm.prev_stock, sm.new_stock, sm.remarks, sm.created_at,
           p.sku, p.name AS product_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    ORDER BY sm.created_at DESC
    LIMIT 10
");
$recentStmt->execute();
$recentRows = $recentStmt->fetchAll();

$recentMovements = array_map(function ($r) {
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
            'sku' => (string)$r['sku'],
        ],
    ];
}, $recentRows ?: []);

$trendDays = 14;
$trendStart = (new DateTime('now'))->modify('-' . ($trendDays - 1) . ' day');
$trendStartStr = $trendStart->format('Y-m-d 00:00:00');

$trend = [];
for ($i = 0; $i < $trendDays; $i++) {
    $d = (clone $trendStart)->modify('+' . $i . ' day');
    $key = $d->format('Y-m-d');
    $trend[$key] = ['stockIn' => 0, 'stockOut' => 0];
}

$inStmt = $pdo->prepare("SELECT DATE(created_at) AS d, COALESCE(SUM(qty),0) AS total_qty FROM stock_in WHERE created_at >= :start GROUP BY DATE(created_at)");
$inStmt->execute([':start' => $trendStartStr]);
foreach ($inStmt->fetchAll() as $r) {
    $key = (string)$r['d'];
    if (isset($trend[$key])) $trend[$key]['stockIn'] = (int)$r['total_qty'];
}

$outStmt = $pdo->prepare("SELECT DATE(created_at) AS d, COALESCE(SUM(qty),0) AS total_qty FROM stock_out WHERE created_at >= :start GROUP BY DATE(created_at)");
$outStmt->execute([':start' => $trendStartStr]);
foreach ($outStmt->fetchAll() as $r) {
    $key = (string)$r['d'];
    if (isset($trend[$key])) $trend[$key]['stockOut'] = (int)$r['total_qty'];
}

$dailyTrends = [];
foreach ($trend as $date => $vals) {
    $dailyTrends[] = array_merge(['date' => $date], $vals);
}

$catStmt = $pdo->query("
    SELECT c.name AS category_name, COALESCE(SUM(p.stock),0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
");
$categoryStock = [];
foreach ($catStmt->fetchAll() as $r) {
    $categoryStock[] = [
        'name' => (string)$r['category_name'],
        'value' => (int)$r['total_stock'],
    ];
}

api_ok([
    'stats' => [
        'totalProducts' => $totalProducts,
        'totalCategories' => $totalCategories,
        'totalSuppliers' => $totalSuppliers,
        'lowStockCount' => $lowStockCount,
        'stockInToday' => $stockInToday,
        'stockInMonth' => $stockInMonth,
        'stockOutToday' => $stockOutToday,
        'stockOutMonth' => $stockOutMonth,
        'inventoryValue' => $inventoryValue,
        'sellingValue' => $sellingValue,
    ],
    'recentMovements' => $recentMovements,
    'dailyTrends' => $dailyTrends,
    'categoryStock' => $categoryStock,
]);
