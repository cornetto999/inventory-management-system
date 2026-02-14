<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();
$u = current_user();
$errors = [];

$productsStmt = $pdo->query("SELECT id, sku, name, stock, cost_price FROM products WHERE status='active' ORDER BY name");
$products = $productsStmt->fetchAll();

$suppliersStmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
$suppliers = $suppliersStmt->fetchAll();

if (request_method() === 'POST') {
    csrf_validate();

    $product_id = (string)post('product_id','');
    $qty = (string)post('qty','');
    $cost_per_unit = (string)post('cost_per_unit','0');
    $supplier_id = (string)post('supplier_id','');
    $remarks = sanitize_string((string)post('remarks',''));

    if ($product_id === '') $errors[] = 'Product is required.';
    if (!ctype_digit($qty) || (int)$qty <= 0) $errors[] = 'Quantity must be an integer > 0.';
    if (!is_numeric($cost_per_unit) || (float)$cost_per_unit < 0) $errors[] = 'Cost per unit must be >= 0.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT id, stock FROM products WHERE id=:id FOR UPDATE");
            $lock->execute([':id'=>$product_id]);
            $p = $lock->fetch();
            if (!$p) {
                throw new RuntimeException('Product not found.');
            }

            $prev = (int)$p['stock'];
            $new = $prev + (int)$qty;

            $refId = uuid_v4();
            $ins = $pdo->prepare("INSERT INTO stock_in (id, product_id, qty, cost_per_unit, supplier_id, remarks, created_by) VALUES (:id,:product_id,:qty,:cpu,:supplier_id,:remarks,:created_by)");
            $ins->execute([
                ':id'=>$refId,
                ':product_id'=>$product_id,
                ':qty'=>(int)$qty,
                ':cpu'=>(float)$cost_per_unit,
                ':supplier_id'=>($supplier_id === '' ? null : $supplier_id),
                ':remarks'=>($remarks === '' ? null : $remarks),
                ':created_by'=>(string)$u['id'],
            ]);

            $upd = $pdo->prepare("UPDATE products SET stock=:stock WHERE id=:id");
            $upd->execute([':stock'=>$new, ':id'=>$product_id]);

            $mov = $pdo->prepare("INSERT INTO stock_movements (id, product_id, movement_type, qty, prev_stock, new_stock, ref_table, ref_id, user_id, remarks) VALUES (:id,:product_id,'IN',:qty,:prev,:new,'stock_in',:ref_id,:user_id,:remarks)");
            $mov->execute([
                ':id'=>uuid_v4(),
                ':product_id'=>$product_id,
                ':qty'=>(int)$qty,
                ':prev'=>$prev,
                ':new'=>$new,
                ':ref_id'=>$refId,
                ':user_id'=>(string)$u['id'],
                ':remarks'=>($remarks === '' ? null : $remarks),
            ]);

            $pdo->commit();
            flash_set('success', 'Stock in recorded.');
            redirect(base_url('modules/stock_in/index.php'));
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$q = sanitize_string((string)get('q',''));
[$page, $perPage, $offset] = paginate_params(10);

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (p.sku LIKE :q OR p.name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_in si JOIN products p ON p.id=si.product_id WHERE $where");
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
  <h4 class="mb-0">Stock In</h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm mb-3">
  <div class="card-body">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Product</label>
        <select class="form-select" name="product_id" required>
          <option value="">-- Select --</option>
          <?php $selP = (string)post('product_id',''); foreach ($products as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= $selP === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['sku']) ?>) - Stock: <?= e((string)$p['stock']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Qty</label>
        <input class="form-control" type="number" min="1" name="qty" required value="<?= e((string)post('qty','1')) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Cost / Unit</label>
        <input class="form-control" type="number" min="0" step="0.01" name="cost_per_unit" value="<?= e((string)post('cost_per_unit','0')) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Supplier</label>
        <select class="form-select" name="supplier_id">
          <option value="">-- Optional --</option>
          <?php $selS = (string)post('supplier_id',''); foreach ($suppliers as $s): ?>
            <option value="<?= e($s['id']) ?>" <?= $selS === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Remarks</label>
        <input class="form-control" name="remarks" value="<?= e((string)post('remarks','')) ?>">
      </div>
    </div>
  </div>
  <div class="card-footer bg-white">
    <button class="btn btn-primary" type="submit">Record Stock In</button>
  </div>
</form>

<form class="card shadow-sm mb-3" method="get">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Search</label>
        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="SKU or product name">
      </div>
      <div class="col-md-6">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('modules/stock_in/index.php')) ?>">Reset</a>
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
          <th>Supplier</th>
          <th>User</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No records found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['created_at']) ?></td>
              <td>
                <div class="fw-semibold"><?= e($r['product_name']) ?></div>
                <div class="text-muted small"><?= e($r['sku']) ?></div>
              </td>
              <td class="text-end"><?= e((string)$r['qty']) ?></td>
              <td class="text-end"><?= e(number_format((float)$r['cost_per_unit'], 2)) ?></td>
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
