<?php
// =============================================================================
// ChainTrack — Shared Sidebar Navigation
// views/layouts/sidebar.php
// =============================================================================

$role = $_SESSION['user_role'] ?? '';
$isAdmin = ($role === 'administrator');
$isAuditor = ($role === 'auditor');
$userName = $_SESSION['user_name'] ?? 'Authorized User';
$roleLabel = ucfirst(str_replace('_', ' ', $role ?: 'user'));

if (!function_exists('navItem')) {
    function navItem(string $href, string $label, string $icon): string {
        $reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $hrefPath = parse_url($href, PHP_URL_PATH);
        $active = '';
        if ($reqUri === $hrefPath) {
            $active = ' active';
        } elseif ($hrefPath !== '' && $hrefPath !== '/' && str_contains($reqUri, rtrim(dirname($hrefPath), '/'))) {
            $active = ' active';
        }
        return '<a href="'.$href.'" class="ct-nav-item'.$active.'">'.$icon.'<span>'.$label.'</span></a>';
    }
}
$base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<nav id="ct-sidebar">
  <div class="ct-brand">
    <h1>&#x26D3;&#xFE0F; <?= defined('APP_NAME') ? APP_NAME : 'ChainTrack' ?></h1>
    <small>Chain-of-Custody System</small>
  </div>

  <div class="ct-nav">
    <?= navItem($base . '/views/dashboard/index.php', 'Dashboard',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>') ?>

    <div class="ct-nav-section">Operations</div>
    <?= navItem($base . '/investigations/index.php', 'Investigations',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>') ?>
    <?= navItem($base . '/items/index.php', 'Controlled Items',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>') ?>
    <?= navItem($base . '/transfers/index.php', 'Custody Transfers',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>') ?>

    <?php if ($isAdmin): ?>
    <div class="ct-nav-section">Management</div>
    <?= navItem($base . '/users/index.php', 'Users',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>') ?>
    <?= navItem($base . '/departments/index.php', 'Departments',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>') ?>
    <?= navItem($base . '/categories/index.php', 'Categories',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>') ?>
    <?= navItem($base . '/locations/index.php', 'Locations',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>') ?>
    <?php endif; ?>

    <?php if ($isAdmin || $isAuditor): ?>
    <div class="ct-nav-section">Analysis</div>
    <?= navItem($base . '/views/reports/index.php', 'Reports',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>') ?>
    <?= navItem($base . '/views/audit/index.php', 'Audit Logs',
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>') ?>
    <?php endif; ?>
  </div>

  <div class="ct-sidebar-footer">
    <strong><?= htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8') ?></strong>
    <span class="small" style="opacity:.8;display:block;"><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</nav>
