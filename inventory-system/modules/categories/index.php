<?php
require_once __DIR__ . '/../../middleware/auth.php';

$pdo = db();
$action = (string)get('action', 'list');
$errors = [];

if (request_method() === 'POST') {
    csrf_validate();

    if ($action === 'create') {
        $name = sanitize_string((string)post('name',''));
        if ($name === '') $errors[] = 'Name is required.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name");
            $stmt->execute([':name'=>$name]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Category name already exists.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO categories (id, name) VALUES (:id,:name)");
            $stmt->execute([':id'=>uuid_v4(), ':name'=>$name]);
            flash_set('success', 'Category created.');
            redirect(base_url('modules/categories/index.php'));
        }
    }

    if ($action === 'edit') {
        $id = (string)get('id','');
        $name = sanitize_string((string)post('name',''));
        if ($id === '') $errors[] = 'Missing category id.';
        if ($name === '') $errors[] = 'Name is required.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id <> :id");
            $stmt->execute([':name'=>$name, ':id'=>$id]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Category name already exists.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE categories SET name=:name WHERE id=:id");
            $stmt->execute([':name'=>$name, ':id'=>$id]);
            flash_set('success', 'Category updated.');
            redirect(base_url('modules/categories/index.php'));
        }
    }

    if ($action === 'delete') {
        $id = (string)get('id','');
        if ($id === '') {
            $errors[] = 'Missing category id.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            flash_set('success', 'Category deleted.');
            redirect(base_url('modules/categories/index.php'));
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
require_once __DIR__ . '/../../views/sidebar.php';

if ($action === 'create'):
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Category</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/categories/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label class="form-label">Name</label>
      <input class="form-control" name="name" required value="<?= e((string)post('name','')) ?>">
    </div>
    <div class="card-footer bg-white">
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>

<?php
elseif ($action === 'edit'):
    $id = (string)get('id','');
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo '<div class="alert alert-danger">Category not found.</div>';
    } else {
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Category</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/categories/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label class="form-label">Name</label>
      <input class="form-control" name="name" required value="<?= e((string)post('name', $row['name'])) ?>">
    </div>
    <div class="card-footer bg-white">
      <button class="btn btn-primary" type="submit">Update</button>
    </div>
  </form>

<?php
    }
else:
    $q = sanitize_string((string)get('q',''));
    [$page, $perPage, $offset] = paginate_params(10);

    $where = '1=1';
    $params = [];
    if ($q !== '') {
        $where .= ' AND name LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, name, created_at FROM categories WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Categories</h4>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('modules/categories/index.php?action=create')) ?>">Add Category</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form class="card shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Search</label>
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Category name">
        </div>
        <div class="col-md-6">
          <button class="btn btn-outline-primary" type="submit">Filter</button>
          <a class="btn btn-outline-secondary" href="<?= e(base_url('modules/categories/index.php')) ?>">Reset</a>
        </div>
      </div>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">No categories found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= e($r['name']) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('modules/categories/index.php?action=edit&id=' . urlencode($r['id']))) ?>">Edit</a>
                  <form method="post" action="<?= e(base_url('modules/categories/index.php?action=delete&id=' . urlencode($r['id']))) ?>" class="d-inline" onsubmit="return confirm('Delete this category?')">
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
