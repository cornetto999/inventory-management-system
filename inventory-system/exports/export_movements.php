<?php
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$date_from = (string)get('date_from','');
$date_to = (string)get('date_to','');
$product_id = (string)get('product_id','');
$type = (string)get('type','');

$where = '1=1';
$params = [];
if ($date_from !== '') { $where .= ' AND DATE(sm.created_at) >= :df'; $params[':df'] = $date_from; }
if ($date_to !== '') { $where .= ' AND DATE(sm.created_at) <= :dt'; $params[':dt'] = $date_to; }
if ($product_id !== '') { $where .= ' AND sm.product_id = :pid'; $params[':pid'] = $product_id; }
if ($type !== '') { $where .= ' AND sm.movement_type = :type'; $params[':type'] = $type; }

$sql = "
  SELECT sm.created_at, p.sku, p.name AS product, sm.movement_type, sm.qty, sm.prev_stock, sm.new_stock,
         sm.ref_table, sm.ref_id, u.name AS user, sm.remarks
  FROM stock_movements sm
  JOIN products p ON p.id = sm.product_id
  JOIN users u ON u.id = sm.user_id
  WHERE $where
  ORDER BY sm.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['created_at','sku','product','movement_type','qty','prev_stock','new_stock','ref_table','ref_id','user','remarks'];
$filename = 'movements_' . date('Y-m-d') . '.xlsx';
exports_send_xlsx($filename, 'Movements', $headers, $rows);
