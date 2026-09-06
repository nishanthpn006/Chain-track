<?php
require_once __DIR__ . '/../../core/bootstrap.php';
if (isLoggedIn()) {
    $audit = new AuditLog();
    $audit->log(currentUserId(), 'user.logout', 'user', currentUserId(), 'User logged out.');
}
logoutUser();
setFlash('success', 'You have been signed out successfully.');
redirect(BASE_PATH . '/views/auth/login.php');
