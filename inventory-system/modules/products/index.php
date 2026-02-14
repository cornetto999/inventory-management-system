<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();
$action = (string)get('action', 'list');
$errors = [];

$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $catStmt->fetchAll();

$supStmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
$suppliers = $supStmt->fetchAll();

function find_name(array $rows, string $id): string {
    foreach ($rows as $r) if ($r['id'] === $id) return (string)$r['name'];
    return '';
}

if (request_method() === 'POST') {
    csrf_validate();

    if ($action === 'create') {
        $sku = sanitize_string((string)post('sku',''));
        $name = sanitize_string((string)post('name',''));
        $category_id = (string)post('category_id','');
        $supplier_id = (string)post('supplier_id','');
        $unit = sanitize_string((string)post('unit','pcs'));
        $cost_price = (string)post('cost_price','0');
        $selling_price = (string)post('selling_price','0');
        $stock = (string)post('stock','0');
        $reorder_level = (string)post('reorder_level','0');
        $status = (string)post('status','active');

        if ($sku === '') $errors[] = 'SKU is required.';
        if ($name === '') $errors[] = 'Name is required.';
        if ($category_id === '') $errors[] = 'Category is required.';
        if ($unit === '') $errors[] = 'Unit is required.';
        if (!is_numeric($cost_price) || (float)$cost_price < 0) $errors[] = 'Cost price must be >= 0.';
        if (!is_numeric($selling_price) || (float)$selling_price < 0) $errors[] = 'Selling price must be >= 0.';
        if (!ctype_digit((string)$stock) || (int)$stock < 0) $errors[] = 'Stock must be an integer >= 0.';
        if (!ctype_digit((string)$reorder_level) || (int)$reorder_level < 0) $errors[] = 'Reorder level must be an integer >= 0.';
        if (!in_array($status, ['active','inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = :sku");
            $stmt->execute([':sku'=>$sku]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'SKU already exists.';
        }

        if (!$errors) {
            $id = uuid_v4();
            $stmt = $pdo->prepare("INSERT INTO products (id, sku, name, category_id, supplier_id, unit, cost_price, selling_price, stock, reorder_level, status) VALUES (:id,:sku,:name,:category_id,:supplier_id,:unit,:cost_price,:selling_price,:stock,:reorder_level,:status)");
            $stmt->execute([
                ':id'=>$id,
                ':sku'=>$sku,
                ':name'=>$name,
                ':category_id'=>$category_id,
                ':supplier_id'=>($supplier_id === '' ? null : $supplier_id),
                ':unit'=>$unit,
                ':cost_price'=>(float)$cost_price,
                ':selling_price'=>(float)$selling_price,
                ':stock'=>(int)$stock,
                ':reorder_level'=>(int)$reorder_level,
                ':status'=>$status,
            ]);
            flash_set('success', 'Product created.');
            redirect(base_url('modules/products/index.php'));
        }
    }

    if ($action === 'edit') {
        $id = (string)get('id','');
        $sku = sanitize_string((string)post('sku',''));
        $name = sanitize_string((string)post('name',''));
        $category_id = (string)post('category_id','');
        $supplier_id = (string)post('supplier_id','');
        $unit = sanitize_string((string)post('unit','pcs'));
        $cost_price = (string)post('cost_price','0');
        $selling_price = (string)post('selling_price','0');
        $reorder_level = (string)post('reorder_level','0');
        $status = (string)post('status','active');

        if ($id === '') $errors[] = 'Missing product id.';
        if ($sku === '') $errors[] = 'SKU is required.';
        if ($name === '') $errors[] = 'Name is required.';
        if ($category_id === '') $errors[] = 'Category is required.';
        if ($unit === '') $errors[] = 'Unit is required.';
        if (!is_numeric($cost_price) || (float)$cost_price < 0) $errors[] = 'Cost price must be >= 0.';
        if (!is_numeric($selling_price) || (float)$selling_price < 0) $errors[] = 'Selling price must be >= 0.';
        if (!ctype_digit((string)$reorder_level) || (int)$reorder_level < 0) $errors[] = 'Reorder level must be an integer >= 0.';
        if (!in_array($status, ['active','inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = :sku AND id <> :id");
            $stmt->execute([':sku'=>$sku, ':id'=>$id]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'SKU already exists.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE products SET sku=:sku, name=:name, category_id=:category_id, supplier_id=:supplier_id, unit=:unit, cost_price=:cost_price, selling_price=:selling_price, reorder_level=:reorder_level, status=:status WHERE id=:id");
            $stmt->execute([
                ':sku'=>$sku,
                ':name'=>$name,
                ':category_id'=>$category_id,
                ':supplier_id'=>($supplier_id === '' ? null : $supplier_id),
                ':unit'=>$unit,
                ':cost_price'=>(float)$cost_price,
                ':selling_price'=>(float)$selling_price,
                ':reorder_level'=>(int)$reorder_level,
                ':status'=>$status,
                ':id'=>$id,
            ]);
            flash_set('success', 'Product updated.');
            redirect(base_url('modules/products/index.php'));
        }
    }

    if ($action === 'delete') {
        $id = (string)get('id','');
        if ($id === '') {
            $errors[] = 'Missing product id.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            flash_set('success', 'Product deleted.');
            redirect(base_url('modules/products/index.php'));
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
require_once __DIR__ . '/../../views/sidebar.php';

if ($action === 'create'):
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Product</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/products/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">SKU</label>
          <input class="form-control" name="sku" required value="<?= e((string)post('sku','')) ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required value="<?= e((string)post('name','')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <select class="form-select" name="category_id" required>
            <option value="">-- Select --</option>
            <?php $selCat = (string)post('category_id',''); foreach ($categories as $c): ?>
              <option value="<?= e($c['id']) ?>" <?= $selCat === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Supplier (optional)</label>
          <select class="form-select" name="supplier_id">
            <option value="">-- None --</option>
            <?php $selSup = (string)post('supplier_id',''); foreach ($suppliers as $s): ?>
              <option value="<?= e($s['id']) ?>" <?= $selSup === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Unit</label>
          <input class="form-control" name="unit" required value="<?= e((string)post('unit','pcs')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cost Price</label>
          <input class="form-control" name="cost_price" type="number" step="0.01" min="0" value="<?= e((string)post('cost_price','0')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Selling Price</label>
          <input class="form-control" name="selling_price" type="number" step="0.01" min="0" value="<?= e((string)post('selling_price','0')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock</label>
          <input class="form-control" name="stock" type="number" min="0" value="<?= e((string)post('stock','0')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Reorder Level</label>
          <input class="form-control" name="reorder_level" type="number" min="0" value="<?= e((string)post('reorder_level','0')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="active" <?= post('status','active')==='active'?'selected':'' ?>>active</option>
            <option value="inactive" <?= post('status','active')==='inactive'?'selected':'' ?>>inactive</option>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer bg-white">
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>

<?php
elseif ($action === 'edit'):
    $id = (string)get('id','');
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo '<div class="alert alert-danger">Product not found.</div>';
    } else {
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Product</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/products/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">SKU</label>
          <input class="form-control" name="sku" required value="<?= e((string)post('sku', $row['sku'])) ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required value="<?= e((string)post('name', $row['name'])) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <select class="form-select" name="category_id" required>
            <option value="">-- Select --</option>
            <?php $selCat = (string)post('category_id', $row['category_id']); foreach ($categories as $c): ?>
              <option value="<?= e($c['id']) ?>" <?= $selCat === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Supplier (optional)</label>
          <select class="form-select" name="supplier_id">
            <option value="">-- None --</option>
            <?php $selSup = (string)post('supplier_id', (string)($row['supplier_id'] ?? '')); foreach ($suppliers as $s): ?>
              <option value="<?= e($s['id']) ?>" <?= $selSup === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Unit</label>
          <input class="form-control" name="unit" required value="<?= e((string)post('unit', $row['unit'])) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cost Price</label>
          <input class="form-control" name="cost_price" type="number" step="0.01" min="0" value="<?= e((string)post('cost_price', (string)$row['cost_price'])) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Selling Price</label>
          <input class="form-control" name="selling_price" type="number" step="0.01" min="0" value="<?= e((string)post('selling_price', (string)$row['selling_price'])) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Reorder Level</label>
          <input class="form-control" name="reorder_level" type="number" min="0" value="<?= e((string)post('reorder_level', (string)$row['reorder_level'])) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <?php $st = (string)post('status', $row['status']); ?>
          <select class="form-select" name="status">
            <option value="active" <?= $st==='active'?'selected':'' ?>>active</option>
            <option value="inactive" <?= $st==='inactive'?'selected':'' ?>>inactive</option>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer bg-white">
      <button class="btn btn-primary" type="submit">Update</button>
    </div>
  </form>

<?php
    }
else:
    $q = sanitize_string((string)get('q',''));
    $categoryFilter = (string)get('category_id','');
    $statusFilter = (string)get('status','');

    [$page, $perPage, $offset] = paginate_params(10);

    $where = '1=1';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (p.sku LIKE :q OR p.name LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($categoryFilter !== '') {
        $where .= ' AND p.category_id = :cat';
        $params[':cat'] = $categoryFilter;
    }
    if ($statusFilter !== '') {
        $where .= ' AND p.status = :st';
        $params[':st'] = $statusFilter;
    }

    $countSql = "SELECT COUNT(*) FROM products p WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "
      SELECT p.*, c.name AS category_name, s.name AS supplier_name
      FROM products p
      JOIN categories c ON c.id = p.category_id
      LEFT JOIN suppliers s ON s.id = p.supplier_id
      WHERE $where
      ORDER BY p.created_at DESC
      LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Products</h4>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('modules/products/index.php?action=create')) ?>">Add Product</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

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
              <option value="<?= e($c['id']) ?>" <?= $categoryFilter === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">All</option>
            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>active</option>
            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>inactive</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
          <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(base_url('modules/products/index.php')) ?>">Reset</a>
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
            <th class="text-end">Stock</th>
            <th class="text-end">Reorder</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No products found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= e($r['sku']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['category_name']) ?></td>
                <td><?= e((string)($r['supplier_name'] ?? '')) ?></td>
                <td class="text-end"><?= e((string)$r['stock']) ?></td>
                <td class="text-end"><?= e((string)$r['reorder_level']) ?></td>
                <td><?= e($r['status']) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('modules/products/index.php?action=edit&id=' . urlencode($r['id']))) ?>">Edit</a>
                  <form method="post" action="<?= e(base_url('modules/products/index.php?action=delete&id=' . urlencode($r['id']))) ?>" class="d-inline" onsubmit="return confirm('Delete this product?')">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                  </form>
                </td>
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
<?php
endif;

require_once __DIR__ . '/../../views/footer.php';
