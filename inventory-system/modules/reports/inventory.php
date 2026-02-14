<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$q = sanitize_string((string)get('q',''));
$category_id = (string)get('category_id','');
$status = (string)get('status','');

[$page, $perPage, $offset] = paginate_params(10);

$where = '1=1';
$params = [];

if ($q !== '') {
    $where .= ' AND (p.sku LIKE :q OR p.name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($category_id !== '') {
    $where .= ' AND p.category_id = :cat';
    $params[':cat'] = $category_id;
}
if ($status !== '') {
    $where .= ' AND p.status = :st';
    $params[':st'] = $status;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "
  SELECT p.*, c.name AS category_name, s.name AS supplier_name
  FROM products p
  JOIN categories c ON c.id = p.category_id
  LEFT JOIN suppliers s ON s.id = p.supplier_id
  WHERE $where
  ORDER BY p.name ASC
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
  <h4 class="mb-0">Inventory List Report</h4>
  <div>
    <a class="btn btn-outline-success btn-sm" href="<?= e(base_url('exports/export_inventory.php?' . http_build_query($_GET))) ?>">Export</a>
  </div>
</div>

<form class="card shadow-sm mb-3" method="get">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="SKU or name">
      </div>
      <div class="col-md-4">
        <label class="form-label">Category</label>
        <select class="form-select" name="category_id">
          <option value="">All</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= $category_id === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="">All</option>
          <option value="active" <?= $status==='active'?'selected':'' ?>>active</option>
          <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>inactive</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
        <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(base_url('modules/reports/inventory.php')) ?>">Reset</a>
      </div>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Name</th>
          <th>Category</th>
          <th>Supplier</th>
          <th>Unit</th>
          <th class="text-end">Cost</th>
          <th class="text-end">Selling</th>
          <th class="text-end">Stock</th>
          <th class="text-end">Value (Cost)</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="text-center text-muted py-3">No data.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="fw-semibold"><?= e($r['sku']) ?></td>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['category_name']) ?></td>
              <td><?= e((string)($r['supplier_name'] ?? '')) ?></td>
              <td><?= e($r['unit']) ?></td>
              <td class="text-end"><?= e(number_format((float)$r['cost_price'], 2)) ?></td>
              <td class="text-end"><?= e(number_format((float)$r['selling_price'], 2)) ?></td>
              <td class="text-end"><?= e((string)$r['stock']) ?></td>
              <td class="text-end"><?= e(number_format(((float)$r['cost_price']) * ((int)$r['stock']), 2)) ?></td>
              <td><?= e($r['status']) ?></td>
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
