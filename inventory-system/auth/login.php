<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(base_url('modules/dashboard/index.php'));
}

$errors = [];
$email = '';

if (request_method() === 'POST') {
    csrf_validate();

    $email = sanitize_string((string)post('email', ''));
    $password = (string)post('password', '');

    if ($email === '' || !validate_email($email)) {
        $errors[] = 'Please enter a valid email.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $stmt = db()->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } elseif (($user['status'] ?? '') !== 'active') {
            $errors[] = 'Your account is inactive. Contact admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (string)$user['id'],
                'name' => (string)$user['name'],
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
                'status' => (string)$user['status'],
            ];
            flash_set('success', 'Welcome back, ' . (string)$user['name'] . '!');
            redirect(base_url('modules/dashboard/index.php'));
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(base_url('assets/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h4 class="mb-3"><?= e(APP_NAME) ?></h4>
            <p class="text-muted mb-4">Sign in to continue</p>

            <?php if ($errors): ?>
              <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Please fix the following:</div>
                <ul class="mb-0">
                  <?php foreach ($errors as $er): ?>
                    <li><?= e($er) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php $flash = flash_get(); ?>
            <?php if ($flash): ?>
              <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= e($email) ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
              </div>

              <button class="btn btn-primary w-100" type="submit">Login</button>

              <div class="mt-3 small text-muted">
                Default accounts:
                <div>Admin: admin@example.com / Admin@12345</div>
                <div>Staff: staff@example.com / Staff@12345</div>
              </div>
            </form>

          </div>
        </div>
        <div class="text-center text-muted small mt-3">
          PHP + MySQL (XAMPP)
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
