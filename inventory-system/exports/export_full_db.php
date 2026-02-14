<?php
require_once __DIR__ . '/../middleware/admin_only.php';
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
$stockIn = $pdo->query("SELECT * FROM stock_in ORDER BY created_at DESC")->fetchAll();
$stockOut = $pdo->query("SELECT * FROM stock_out ORDER BY created_at DESC")->fetchAll();
$movements = $pdo->query("SELECT * FROM stock_movements ORDER BY created_at DESC")->fetchAll();

$filename = 'full_db_' . date('Y-m-d') . '.xlsx';

if (exports_try_load_phpspreadsheet()) {
    $sheets = [
        ['name'=>'products', 'headers'=>array_keys($products[0] ?? ['id'=>1]), 'rows'=>$products],
        ['name'=>'categories', 'headers'=>array_keys($categories[0] ?? ['id'=>1]), 'rows'=>$categories],
        ['name'=>'suppliers', 'headers'=>array_keys($suppliers[0] ?? ['id'=>1]), 'rows'=>$suppliers],
        ['name'=>'stock_in', 'headers'=>array_keys($stockIn[0] ?? ['id'=>1]), 'rows'=>$stockIn],
        ['name'=>'stock_out', 'headers'=>array_keys($stockOut[0] ?? ['id'=>1]), 'rows'=>$stockOut],
        ['name'=>'movements', 'headers'=>array_keys($movements[0] ?? ['id'=>1]), 'rows'=>$movements],
    ];

    exports_send_xlsx_multi($filename, $sheets);
}

$table = (string)get('table','');
$allowed = ['products','categories','suppliers','stock_in','stock_out','stock_movements'];
if ($table === '' || !in_array($table, $allowed, true)) {
    http_response_code(400);
    die('PhpSpreadsheet not installed. CSV fallback: add ?table=products|categories|suppliers|stock_in|stock_out|stock_movements');
}

$rows = $pdo->query("SELECT * FROM `" . $table . "`")->fetchAll();
$headers = array_keys($rows[0] ?? ['id'=>1]);
exports_send_csv($table . '_' . date('Y-m-d') . '.csv', $headers, $rows);
