<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(base_url('assets/app.css')) ?>" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="<?= e(base_url('modules/dashboard/index.php')) ?>"><?= e(APP_NAME) ?></a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div id="topNav" class="collapse navbar-collapse">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <span class="navbar-text text-white-50 me-3">
              <?= e($u['name'] ?? '') ?> (<?= e($u['role'] ?? '') ?>)
            </span>
          </li>
          <li class="nav-item">
            <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('auth/logout.php')) ?>">Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row">
