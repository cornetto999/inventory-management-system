<?php
require_once __DIR__ . '/bootstrap.php';

api_require_login();

$pdo = db();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll() ?: [];
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll() ?: [];
$productsActive = $pdo->query("SELECT id, name, sku, stock FROM products WHERE status='active' ORDER BY name ASC")->fetchAll() ?: [];

api_ok([
    'categories' => $categories,
    'suppliers' => $suppliers,
    'productsActive' => $productsActive,
]);
