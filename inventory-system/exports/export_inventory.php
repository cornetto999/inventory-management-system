<?php
require_once __DIR__ . '/export_utils.php';

$pdo = db();

$q = sanitize_string((string)get('q',''));
$category_id = (string)get('category_id','');
$status = (string)get('status','');

$where = '1=1';
$params = [];

if ($q !== '') { $where .= ' AND (p.sku LIKE :q OR p.name LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
if ($category_id !== '') { $where .= ' AND p.category_id = :cat'; $params[':cat'] = $category_id; }
if ($status !== '') { $where .= ' AND p.status = :st'; $params[':st'] = $status; }

$sql = "
  SELECT p.sku, p.name, c.name AS category, s.name AS supplier, p.unit, p.cost_price, p.selling_price, p.stock, p.reorder_level, p.status
  FROM products p
  JOIN categories c ON c.id = p.category_id
  LEFT JOIN suppliers s ON s.id = p.supplier_id
  WHERE $where
  ORDER BY p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['sku','name','category','supplier','unit','cost_price','selling_price','stock','reorder_level','status'];

$filename = 'inventory_' . date('Y-m-d') . '.xlsx';
exports_send_xlsx($filename, 'Inventory', $headers, $rows);
