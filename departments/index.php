<?php
// =============================================================================
// ChainTrack — Department Management: Listing
// departments/index.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$search = trim($_GET['search'] ?? '');
$departments = getAllDepartments($db, $search);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Department Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Manage investigative departments in ChainTrack.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title"><?= esc($pageTitle) ?></span>
    <div class="topbar-right">
      <?php if ($flash): ?>
      <div class="ct-alert ct-alert-<?= esc($flash['type']) ?>" style="margin:0;padding:8px 14px;font-size:.8rem;">
        <?= esc($flash['message']) ?>
      </div>
      <?php endif; ?>
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
      <a href="/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="d-flex justify-between align-center mb-2">
      <div>
        <h2 style="margin:0;font-size:1.25rem;">Departments</h2>
        <span class="small text-muted">Organizational units and investigative divisions</span>
      </div>
      <a href="/departments/add.php" class="ct-btn ct-btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Department
      </a>
    </div>

    <!-- Search bar -->
    <div class="ct-card mb-2" style="padding:14px 18px;">
      <form method="GET" class="d-flex align-center gap-2" style="margin:0;">
        <div style="flex:1;">
          <input type="text" name="search" class="ct-input" placeholder="Search by department name or code…" value="<?= esc($search) ?>">
        </div>
        <button type="submit" class="ct-btn ct-btn-primary">Search</button>
        <?php if ($search !== ''): ?>
        <a href="/departments/index.php" class="ct-btn ct-btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table Card -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th style="width:110px;">Code</th>
              <th>Department Name</th>
              <th>Description</th>
              <th style="width:100px;text-align:center;">Users</th>
              <th style="width:110px;text-align:center;">Investigations</th>
              <th style="width:100px;text-align:center;">Status</th>
              <th style="width:160px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($departments as $d): ?>
            <tr>
              <td class="mono small">#<?= (int)$d['id'] ?></td>
              <td><span class="mono fw-600" style="color:var(--ct-accent);"><?= esc($d['dept_code'] ?: '—') ?></span></td>
              <td class="fw-600"><?= esc($d['dept_name']) ?></td>
              <td class="small text-muted"><?= esc(mb_strimwidth($d['description'] ?? '', 0, 60, '…')) ?></td>
              <td style="text-align:center;">
                <span class="badge bg-secondary"><?= (int)$d['user_count'] ?></span>
              </td>
              <td style="text-align:center;">
                <span class="badge bg-primary"><?= (int)$d['investigation_count'] ?></span>
              </td>
              <td style="text-align:center;">
                <?php if ((int)$d['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div class="d-flex gap-1 justify-end">
                  <a href="/departments/edit.php?id=<?= (int)$d['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    Edit
                  </a>
                  <a href="/departments/toggle_status.php?id=<?= (int)$d['id'] ?>"
                     class="ct-btn <?= (int)$d['is_active'] === 1 ? 'ct-btn-warning' : 'ct-btn-primary' ?> ct-btn-sm"
                     onclick="return confirm('Are you sure you want to <?= (int)$d['is_active'] === 1 ? 'deactivate' : 'activate' ?> this department?');">
                    <?= (int)$d['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($departments)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--ct-muted);padding:32px;">
                No departments found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
