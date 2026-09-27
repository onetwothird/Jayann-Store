<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

redirect(is_admin_logged_in() ? 'pages/dashboard.php' : 'pages/admin_login.php');
