<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();
$isAdmin = (($u['role'] ?? '') === 'admin');
?>
<div class="col-12 col-md-3 col-lg-2 bg-light border-end min-vh-100 p-0">
  <div class="list-group list-group-flush">
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/dashboard/index.php')) ?>">Dashboard</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/products/index.php')) ?>">Products</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/categories/index.php')) ?>">Categories</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/suppliers/index.php')) ?>">Suppliers</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/stock_in/index.php')) ?>">Stock In</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/stock_out/index.php')) ?>">Stock Out</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/movements/index.php')) ?>">Movements</a>

    <div class="list-group-item text-muted small fw-semibold">Reports</div>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/reports/inventory.php')) ?>">Inventory List</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/reports/low_stock.php')) ?>">Low Stock</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/reports/stock_in.php')) ?>">Stock In</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/reports/stock_out.php')) ?>">Stock Out</a>
    <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/reports/movements.php')) ?>">Ledger</a>

    <?php if ($isAdmin): ?>
      <div class="list-group-item text-muted small fw-semibold">Admin</div>
      <a class="list-group-item list-group-item-action" href="<?= e(base_url('modules/users/index.php')) ?>">Users</a>
      <a class="list-group-item list-group-item-action" href="<?= e(base_url('exports/export_full_db.php')) ?>">Export Full DB</a>
    <?php endif; ?>
  </div>
</div>

<div class="col-12 col-md-9 col-lg-10 p-3">
  <?php $flash = flash_get(); ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
