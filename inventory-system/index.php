<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(base_url('modules/dashboard/index.php'));
}

redirect(base_url('auth/login.php'));
