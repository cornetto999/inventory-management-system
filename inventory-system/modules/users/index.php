<?php
require_once __DIR__ . '/../../middleware/admin_only.php';

$pdo = db();
$action = (string)get('action', 'list');

$errors = [];

if (request_method() === 'POST') {
    csrf_validate();

    if ($action === 'create') {
        $name = sanitize_string((string)post('name', ''));
        $email = sanitize_string((string)post('email', ''));
        $password = (string)post('password', '');
        $role = (string)post('role', 'staff');
        $status = (string)post('status', 'active');

        if ($name === '') $errors[] = 'Name is required.';
        if ($email === '' || !validate_email($email)) $errors[] = 'Valid email is required.';
        if ($password === '' || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['admin','staff'], true)) $errors[] = 'Invalid role.';
        if (!in_array($status, ['active','inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists.';
            }
        }

        if (!$errors) {
            $id = uuid_v4();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, status) VALUES (:id,:name,:email,:hash,:role,:status)");
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':email' => $email,
                ':hash' => $hash,
                ':role' => $role,
                ':status' => $status,
            ]);
            flash_set('success', 'User created.');
            redirect(base_url('modules/users/index.php'));
        }
    }

    if ($action === 'edit') {
        $id = (string)get('id', '');
        $name = sanitize_string((string)post('name', ''));
        $email = sanitize_string((string)post('email', ''));
        $role = (string)post('role', 'staff');
        $status = (string)post('status', 'active');
        $newPassword = (string)post('password', '');

        if ($id === '') $errors[] = 'Missing user id.';
        if ($name === '') $errors[] = 'Name is required.';
        if ($email === '' || !validate_email($email)) $errors[] = 'Valid email is required.';
        if (!in_array($role, ['admin','staff'], true)) $errors[] = 'Invalid role.';
        if (!in_array($status, ['active','inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id");
            $stmt->execute([':email' => $email, ':id' => $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists.';
            }
        }

        if (!$errors) {
            if ($newPassword !== '') {
                if (strlen($newPassword) < 6) {
                    $errors[] = 'New password must be at least 6 characters.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name=:name, email=:email, role=:role, status=:status, password_hash=:hash WHERE id=:id");
                    $stmt->execute([':name'=>$name, ':email'=>$email, ':role'=>$role, ':status'=>$status, ':hash'=>$hash, ':id'=>$id]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=:name, email=:email, role=:role, status=:status WHERE id=:id");
                $stmt->execute([':name'=>$name, ':email'=>$email, ':role'=>$role, ':status'=>$status, ':id'=>$id]);
            }
            if (!$errors) {
                flash_set('success', 'User updated.');
                redirect(base_url('modules/users/index.php'));
            }
        }
    }

    if ($action === 'delete') {
        $id = (string)get('id', '');
        if ($id === '') {
            $errors[] = 'Missing user id.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            flash_set('success', 'User deleted.');
            redirect(base_url('modules/users/index.php'));
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
require_once __DIR__ . '/../../views/sidebar.php';

if ($action === 'create'):
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create User</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/users/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required value="<?= e((string)post('name','')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= e((string)post('email','')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <option value="staff" <?= post('role','staff')==='staff'?'selected':'' ?>>staff</option>
            <option value="admin" <?= post('role','staff')==='admin'?'selected':'' ?>>admin</option>
          </select>
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
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $user = $stmt->fetch();
    if (!$user) {
        echo '<div class="alert alert-danger">User not found.</div>';
    } else {
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit User</h4>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('modules/users/index.php')) ?>">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required value="<?= e((string)post('name', $user['name'])) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= e((string)post('email', $user['email'])) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">New Password (optional)</label>
          <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current">
        </div>
        <div class="col-md-3">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <?php $r = (string)post('role', $user['role']); ?>
            <option value="staff" <?= $r==='staff'?'selected':'' ?>>staff</option>
            <option value="admin" <?= $r==='admin'?'selected':'' ?>>admin</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <?php $s = (string)post('status', $user['status']); ?>
            <option value="active" <?= $s==='active'?'selected':'' ?>>active</option>
            <option value="inactive" <?= $s==='inactive'?'selected':'' ?>>inactive</option>
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
    [$page, $perPage, $offset] = paginate_params(10);

    $where = '1=1';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (name LIKE :q OR email LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Users</h4>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('modules/users/index.php?action=create')) ?>">Add User</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form class="card shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Search</label>
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name or email">
        </div>
        <div class="col-md-6">
          <button class="btn btn-outline-primary" type="submit">Filter</button>
          <a class="btn btn-outline-secondary" href="<?= e(base_url('modules/users/index.php')) ?>">Reset</a>
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
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= e($r['name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['role']) ?></td>
                <td><?= e($r['status']) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('modules/users/index.php?action=edit&id=' . urlencode($r['id']))) ?>">Edit</a>
                  <form method="post" action="<?= e(base_url('modules/users/index.php?action=delete&id=' . urlencode($r['id']))) ?>" class="d-inline" onsubmit="return confirm('Delete this user?')">
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
