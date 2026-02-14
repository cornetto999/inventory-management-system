<?php
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$date_from = (string)get('date_from','');
$date_to = (string)get('date_to','');

$where = '1=1';
$params = [];
if ($date_from !== '') { $where .= ' AND DATE(so.created_at) >= :df'; $params[':df'] = $date_from; }
if ($date_to !== '') { $where .= ' AND DATE(so.created_at) <= :dt'; $params[':dt'] = $date_to; }

$sql = "
  SELECT so.created_at, p.sku, p.name AS product, so.qty, so.customer, u.name AS user, so.remarks
  FROM stock_out so
  JOIN products p ON p.id = so.product_id
  JOIN users u ON u.id = so.created_by
  WHERE $where
  ORDER BY so.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['created_at','sku','product','qty','customer','user','remarks'];
$filename = 'stockout_' . date('Y-m-d') . '.xlsx';
exports_send_xlsx($filename, 'Stock Out', $headers, $rows);
