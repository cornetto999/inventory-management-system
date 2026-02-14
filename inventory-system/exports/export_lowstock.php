<?php
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$category_id = (string)get('category_id','');

$where = "p.status='active' AND p.stock <= p.reorder_level";
$params = [];
if ($category_id !== '') { $where .= ' AND p.category_id = :cat'; $params[':cat'] = $category_id; }

$sql = "
  SELECT p.sku, p.name, c.name AS category, p.stock, p.reorder_level
  FROM products p
  JOIN categories c ON c.id = p.category_id
  WHERE $where
  ORDER BY p.stock ASC, p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['sku','name','category','stock','reorder_level'];
$filename = 'lowstock_' . date('Y-m-d') . '.xlsx';
exports_send_xlsx($filename, 'Low Stock', $headers, $rows);
