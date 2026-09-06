<?php
// =============================================================================
// ChainTrack — User Management: Listing
// users/index.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();

$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'role_id'       => (int)($_GET['role_id'] ?? 0) ?: null,
    'department_id' => (int)($_GET['department_id'] ?? 0) ?: null,
    'is_active'     => isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : '',
];

$users       = getAllUsers($db, $filters);
$roles       = getAllRoles($db);
$departments = $db->query("SELECT id, dept_name FROM departments ORDER BY dept_name ASC")->fetch_all(MYSQLI_ASSOC);

$currentUid = sessionUserId();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'User Management';

function roleBadge(string $slug, string $name): string {
    $map = [
        'administrator' => 'danger',
        'investigator'  => 'primary',
        'custodian'    => 'warning',
        'analyst'      => 'info',
        'auditor'      => 'secondary',
    ];
    $color = $map[$slug] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">" . esc($name) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Manage user accounts, roles, and departmental assignments.">
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
        <h2 style="margin:0;font-size:1.25rem;">Authorized Users</h2>
        <span class="small text-muted">System accounts, roles, credentials, and access statuses</span>
      </div>
      <a href="/users/add.php" class="ct-btn ct-btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add User
      </a>
    </div>

    <!-- Filters -->
    <div class="ct-card mb-2" style="padding:14px 18px;">
      <form method="GET" class="d-flex align-center gap-2" style="margin:0;flex-wrap:wrap;">
        <div style="flex:2;min-width:200px;">
          <input type="text" name="search" class="ct-input" placeholder="Search name, username, email, badge…" value="<?= esc($filters['search']) ?>">
        </div>

        <div style="flex:1;min-width:140px;">
          <select name="role_id" class="ct-select">
            <option value="">All Roles</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $filters['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= esc($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1;min-width:140px;">
          <select name="department_id" class="ct-select">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $filters['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="width:120px;">
          <select name="is_active" class="ct-select">
            <option value="">All Status</option>
            <option value="1" <?= (string)$filters['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (string)$filters['is_active'] === '0' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <button type="submit" class="ct-btn ct-btn-primary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['role_id'] || $filters['department_id'] || $filters['is_active'] !== ''): ?>
        <a href="/users/index.php" class="ct-btn ct-btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table Card -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th>Full Name</th>
              <th>Username</th>
              <th>Email</th>
              <th style="width:110px;">Badge ID</th>
              <th style="width:120px;">Role</th>
              <th>Department</th>
              <th style="width:90px;text-align:center;">Items Held</th>
              <th style="width:90px;text-align:center;">Status</th>
              <th style="width:160px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td class="mono small">#<?= (int)$u['id'] ?></td>
              <td class="fw-600">
                <?= esc($u['full_name']) ?>
                <?php if ((int)$u['id'] === $currentUid): ?>
                  <span class="badge bg-dark" style="font-size:.65rem;margin-left:4px;">You</span>
                <?php endif; ?>
              </td>
              <td class="mono small"><?= esc($u['username']) ?></td>
              <td class="small text-muted"><?= esc($u['email']) ?></td>
              <td class="mono small"><?= esc($u['employee_id'] ?: '—') ?></td>
              <td><?= roleBadge($u['role_slug'], $u['role_name']) ?></td>
              <td class="small"><?= esc($u['dept_name'] ?: '—') ?></td>
              <td style="text-align:center;">
                <span class="badge bg-secondary"><?= (int)$u['held_items_count'] ?></span>
              </td>
              <td style="text-align:center;">
                <?php if ((int)$u['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div class="d-flex gap-1 justify-end">
                  <a href="/users/edit.php?id=<?= (int)$u['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    Edit
                  </a>
                  <?php if ((int)$u['id'] !== $currentUid): ?>
                  <a href="/users/toggle_status.php?id=<?= (int)$u['id'] ?>"
                     class="ct-btn <?= (int)$u['is_active'] === 1 ? 'ct-btn-warning' : 'ct-btn-primary' ?> ct-btn-sm"
                     onclick="return confirm('Are you sure you want to <?= (int)$u['is_active'] === 1 ? 'deactivate' : 'activate' ?> user <?= esc($u['username']) ?>?');">
                    <?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                  </a>
                  <?php else: ?>
                  <span class="ct-btn ct-btn-secondary ct-btn-sm" style="opacity:0.4;cursor:not-allowed;" title="Cannot deactivate yourself">
                    Deactivate
                  </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr>
              <td colspan="10" style="text-align:center;color:var(--ct-muted);padding:32px;">
                No users found.
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
