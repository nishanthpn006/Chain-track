<?php
// views/layouts/navbar.php
// Expects: $pageTitle
?>
<div id="ct-topbar">
  <span class="page-title"><?= e($pageTitle ?? '') ?></span>
  <div class="topbar-right">
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="ct-alert ct-alert-<?= e($flash['type']) ?>" style="margin:0;padding:8px 14px;font-size:.8rem;">
      <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>
    <div class="ct-user-pill">
      <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
      <span><?= e($_SESSION['user_name'] ?? '') ?></span>
    </div>
    <a href="<?= (defined('BASE_PATH') ? BASE_PATH : '') ?>/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </div>
</div>
