<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();

$productsStmt = $pdo->query("SELECT id, sku, name FROM products ORDER BY name");
$products = $productsStmt->fetchAll();

$type = (string)get('type','');
$product_id = (string)get('product_id','');
$date_from = (string)get('date_from','');
$date_to = (string)get('date_to','');

[$page, $perPage, $offset] = paginate_params(10);

$where = '1=1';
$params = [];

if ($type !== '') {
    $where .= ' AND sm.movement_type = :type';
    $params[':type'] = $type;
}
if ($product_id !== '') {
    $where .= ' AND sm.product_id = :pid';
    $params[':pid'] = $product_id;
}
if ($date_from !== '') {
    $where .= ' AND DATE(sm.created_at) >= :df';
    $params[':df'] = $date_from;
}
if ($date_to !== '') {
    $where .= ' AND DATE(sm.created_at) <= :dt';
    $params[':dt'] = $date_to;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "
  SELECT sm.*, p.sku, p.name AS product_name, u.name AS user_name
  FROM stock_movements sm
  JOIN products p ON p.id = sm.product_id
  JOIN users u ON u.id = sm.user_id
  WHERE $where
  ORDER BY sm.created_at DESC
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
  <h4 class="mb-0">Stock Movements Ledger</h4>
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
      <div class="col-md-3">
        <label class="form-label">Product</label>
        <select class="form-select" name="product_id">
          <option value="">All</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= $product_id === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Type</label>
        <select class="form-select" name="type">
          <option value="">All</option>
          <option value="IN" <?= $type==='IN'?'selected':'' ?>>IN</option>
          <option value="OUT" <?= $type==='OUT'?'selected':'' ?>>OUT</option>
          <option value="ADJUST" <?= $type==='ADJUST'?'selected':'' ?>>ADJUST</option>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-outline-primary w-100" type="submit">Go</button>
        <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(base_url('modules/movements/index.php')) ?>">Reset</a>
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
          <th>Type</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Prev</th>
          <th class="text-end">New</th>
          <th>Ref</th>
          <th>User</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-3">No movements found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
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
              <td><?= e((string)($r['ref_table'] ?? '')) ?><?= $r['ref_id'] ? ' #' . e((string)$r['ref_id']) : '' ?></td>
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
