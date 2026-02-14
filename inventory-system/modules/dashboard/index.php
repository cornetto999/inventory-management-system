<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();

$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

$lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock <= reorder_level")->fetchColumn();

$inventoryValue = (float)$pdo->query("SELECT COALESCE(SUM(cost_price * stock),0) FROM products WHERE status='active'")->fetchColumn();
$sellingValue = (float)$pdo->query("SELECT COALESCE(SUM(selling_price * stock),0) FROM products WHERE status='active'")->fetchColumn();

$todayIn = (float)$pdo->query("SELECT COALESCE(SUM(qty * cost_per_unit),0) FROM stock_in WHERE DATE(created_at)=CURDATE() ")->fetchColumn();
$monthIn = (float)$pdo->query("SELECT COALESCE(SUM(qty * cost_per_unit),0) FROM stock_in WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) ")->fetchColumn();

$todayOut = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE DATE(created_at)=CURDATE() ")->fetchColumn();
$monthOut = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_out WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) ")->fetchColumn();

$recentStmt = $pdo->prepare("
    SELECT sm.created_at, sm.movement_type, sm.qty, sm.prev_stock, sm.new_stock,
           p.sku, p.name AS product_name,
           u.name AS user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    ORDER BY sm.created_at DESC
    LIMIT 10
");
$recentStmt->execute();
$recent = $recentStmt->fetchAll();

require_once __DIR__ . '/../../views/header.php';
require_once __DIR__ . '/../../views/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Dashboard</h4>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Total Products</div>
      <div class="h4 mb-0"><?= e((string)$totalProducts) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Total Categories</div>
      <div class="h4 mb-0"><?= e((string)$totalCategories) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Total Suppliers</div>
      <div class="h4 mb-0"><?= e((string)$totalSuppliers) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Low Stock</div>
      <div class="h4 mb-0"><?= e((string)$lowStockCount) ?></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Inventory Value (Cost)</div>
      <div class="h4 mb-0"><?= e(number_format($inventoryValue, 2)) ?></div>
      <div class="text-muted small mt-2">Selling Value</div>
      <div class="h5 mb-0"><?= e(number_format($sellingValue, 2)) ?></div>
    </div></div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <div class="row">
        <div class="col-6">
          <div class="text-muted small">Stock In Today</div>
          <div class="h5 mb-0"><?= e(number_format($todayIn, 2)) ?></div>
          <div class="text-muted small mt-2">Stock In This Month</div>
          <div class="h6 mb-0"><?= e(number_format($monthIn, 2)) ?></div>
        </div>
        <div class="col-6">
          <div class="text-muted small">Stock Out Today (Qty)</div>
          <div class="h5 mb-0"><?= e((string)$todayOut) ?></div>
          <div class="text-muted small mt-2">Stock Out This Month (Qty)</div>
          <div class="h6 mb-0"><?= e((string)$monthOut) ?></div>
        </div>
      </div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold">Recent Activity</div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th>Product</th>
          <th>Type</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Prev</th>
          <th class="text-end">New</th>
          <th>User</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No movements yet.</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?= e($r['created_at']) ?></td>
              <td>
                <div class="fw-semibold"><?= e($r['product_name']) ?></div>
                <div class="text-muted small"><?= e($r['sku']) ?></div>
              </td>
              <td><?= e($r['movement_type']) ?></td>
              <td class="text-end"><?= e((string)$r['qty']) ?></td>
              <td class="text-end"><?= e((string)$r['prev_stock']) ?></td>
              <td class="text-end"><?= e((string)$r['new_stock']) ?></td>
              <td><?= e($r['user_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once __DIR__ . '/../../views/footer.php';
