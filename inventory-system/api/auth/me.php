<?php
require_once __DIR__ . '/../bootstrap.php';

$u = api_require_login();
api_ok(['user' => $u, 'csrfToken' => csrf_token()]);
