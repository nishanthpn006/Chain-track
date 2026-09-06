<?php
// public/index.php -- entry point redirect
require_once __DIR__ . '/../core/bootstrap.php';
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard/index.php');
} else {
    redirect(BASE_URL . '/auth/login.php');
}
