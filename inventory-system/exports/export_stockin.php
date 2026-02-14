<?php
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$date_from = (string)get('date_from','');
$date_to = (string)get('date_to','');
$supplier_id = (string)get('supplier_id','');

$where = '1=1';
$params = [];
if ($date_from !== '') { $where .= ' AND DATE(si.created_at) >= :df'; $params[':df'] = $date_from; }
if ($date_to !== '') { $where .= ' AND DATE(si.created_at) <= :dt'; $params[':dt'] = $date_to; }
if ($supplier_id !== '') { $where .= ' AND si.supplier_id = :sid'; $params[':sid'] = $supplier_id; }

$sql = "
  SELECT si.created_at, p.sku, p.name AS product, si.qty, si.cost_per_unit, (si.qty * si.cost_per_unit) AS total_cost,
         s.name AS supplier, u.name AS user, si.remarks
  FROM stock_in si
  JOIN products p ON p.id = si.product_id
  LEFT JOIN suppliers s ON s.id = si.supplier_id
  JOIN users u ON u.id = si.created_by
  WHERE $where
  ORDER BY si.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['created_at','sku','product','qty','cost_per_unit','total_cost','supplier','user','remarks'];
$filename = 'stockin_' . date('Y-m-d') . '.xlsx';
exports_send_xlsx($filename, 'Stock In', $headers, $rows);
