<?php
require_once __DIR__ . '/../../core/bootstrap.php';
// Already logged in?
if (isLoggedIn()) { redirect(BASE_PATH . '/views/dashboard/index.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(input('username'));
    $password = input('password');
    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        $user = attemptLogin($username, $password);
        if ($user) {
            loginUser($user);
            $audit = new AuditLog();
            $audit->log($user['id'], 'user.login', 'user', $user['id'], 'User logged in.');
            redirect(BASE_PATH . '/views/dashboard/index.php');
        } else {
            $error = 'Invalid credentials or account inactive.';
        }
    }
}
$pageTitle = 'Sign In';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sign In – <?= APP_NAME ?></title>
  <meta name="description" content="Sign in to <?= APP_NAME ?> – Digital Chain-of-Custody Tracking System">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/public/css/chaintrack.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
<div id="login-page">
  <div class="login-card">
    <div class="login-logo">
      <h1>&#x26D3; <?= APP_NAME ?></h1>
      <p>Digital Chain-of-Custody Tracking System</p>
    </div>
    <?php if ($error): ?>
    <div class="ct-alert ct-alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="ct-alert ct-alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <form method="POST" action="" id="login-form">
      <div class="ct-form-group">
        <label class="ct-label" for="username">Username</label>
        <input id="username" name="username" type="text" class="ct-input" autocomplete="username"
               value="<?= e(input('username')) ?>" placeholder="Enter your username" required>
      </div>
      <div class="ct-form-group">
        <label class="ct-label" for="password">Password</label>
        <input id="password" name="password" type="password" class="ct-input" autocomplete="current-password"
               placeholder="Enter your password" required>
      </div>
      <button type="submit" class="ct-btn ct-btn-primary w-100" style="justify-content:center;margin-top:8px;padding:10px;">
        Sign In to ChainTrack
      </button>
    </form>
    
    <details style="margin-top:20px;border-top:1px solid var(--ct-border);padding-top:12px;font-size:.78rem;color:var(--ct-muted);">
      <summary style="cursor:pointer;font-weight:600;color:var(--ct-text-secondary);text-align:center;">
        Demonstration Accounts Reference
      </summary>
      <div style="margin-top:10px;line-height:1.6;background:var(--ct-surface2);padding:10px;border-radius:var(--ct-radius-sm);border:1px solid var(--ct-border);">
        <div><strong>Administrator:</strong> <code>admin</code> / <code>Password@123</code></div>
        <div><strong>Investigator:</strong> <code>reza.hartono</code> / <code>Password@123</code></div>
        <div><strong>Investigator:</strong> <code>deni.oviya.a</code> / <code>Password@123</code></div>
        <div><strong>Custodian:</strong> <code>budi.santoso</code> / <code>Password@123</code></div>
        <div><strong>Analyst:</strong> <code>chen.wei</code> / <code>Password@123</code></div>
        <div><strong>Auditor:</strong> <code>sven.larsen</code> / <code>Password@123</code></div>
      </div>
    </details>

    <p style="text-align:center;margin-top:16px;font-size:.74rem;color:var(--ct-muted);">
      Official investigation management system. Authorized access only.
    </p>
  </div>
</div>
</body>
</html>
