<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$date_from = (string)get('date_from','');
$date_to = (string)get('date_to','');
$supplier_id = (string)get('supplier_id','');

[$page, $perPage, $offset] = paginate_params(10);

$where = '1=1';
$params = [];

if ($date_from !== '') {
    $where .= ' AND DATE(si.created_at) >= :df';
    $params[':df'] = $date_from;
}
if ($date_to !== '') {
    $where .= ' AND DATE(si.created_at) <= :dt';
    $params[':dt'] = $date_to;
}
if ($supplier_id !== '') {
    $where .= ' AND si.supplier_id = :sid';
    $params[':sid'] = $supplier_id;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_in si WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "
  SELECT si.*, p.sku, p.name AS product_name, s.name AS supplier_name, u.name AS user_name
  FROM stock_in si
  JOIN products p ON p.id = si.product_id
  LEFT JOIN suppliers s ON s.id = si.supplier_id
  JOIN users u ON u.id = si.created_by
  WHERE $where
  ORDER BY si.created_at DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../../views/header.php';
require_once __DIR__ . '/../../views/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Stock In Report</h4>
  <a class="btn btn-outline-success btn-sm" href="<?= e(base_url('exports/export_stockin.php?' . http_build_query($_GET))) ?>">Export</a>
</div>

<form class="card shadow-sm mb-3" method="get">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <input class="form-control" type="date" name="date_from" value="<?= e($date_from) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <input class="form-control" type="date" name="date_to" value="<?= e($date_to) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Supplier</label>
        <select class="form-select" name="supplier_id">
          <option value="">All</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= e($s['id']) ?>" <?= $supplier_id === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
        <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(base_url('modules/reports/stock_in.php')) ?>">Reset</a>
      </div>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th>Product</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Cost/Unit</th>
          <th class="text-end">Total</th>
          <th>Supplier</th>
          <th>User</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">No data.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php $totalRow = ((int)$r['qty']) * ((float)$r['cost_per_unit']); ?>
            <tr>
              <td><?= e($r['created_at']) ?></td>
              <td>
                <div class="fw-semibold"><?= e($r['product_name']) ?></div>
                <div class="text-muted small"><?= e($r['sku']) ?></div>
              </td>
              <td class="text-end"><?= e((string)$r['qty']) ?></td>
              <td class="text-end"><?= e(number_format((float)$r['cost_per_unit'], 2)) ?></td>
              <td class="text-end"><?= e(number_format($totalRow, 2)) ?></td>
              <td><?= e((string)($r['supplier_name'] ?? '')) ?></td>
              <td><?= e($r['user_name']) ?></td>
              <td><?= e((string)($r['remarks'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <div class="text-muted small">Total: <?= e((string)$total) ?></div>
    <?= pagination_links($total, $page, $perPage) ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php';
