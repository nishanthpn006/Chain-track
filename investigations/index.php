<?php
// =============================================================================
// ChainTrack — Investigation Management: Listing
// investigations/index.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db = getMySQL();
$userRole = sessionUserRole();

$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'status'        => trim($_GET['status'] ?? ''),
    'department_id' => (int)($_GET['department_id'] ?? 0) ?: null,
];

$validStatuses = ['open', 'closed', 'archived'];
if (!in_array($filters['status'], $validStatuses, true)) {
    $filters['status'] = '';
}

$investigations = getAllInvestigations($db, $filters);
$departments    = $db->query("SELECT id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetch_all(MYSQLI_ASSOC);

$canManage = in_array($userRole, ['administrator', 'investigator'], true);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$base = defined('BASE_PATH') ? BASE_PATH : '';

$pageTitle = 'Investigations';

function invStatusBadge(string $status): string {
    $map = [
        'open'     => ['Open',     'success'],
        'closed'   => ['Closed',   'danger'],
        'archived' => ['Archived', 'secondary'],
    ];
    $s = $map[$status] ?? [ucfirst($status), 'secondary'];
    return "<span class=\"badge bg-{$s[1]}\">{$s[0]}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="View and manage active and historical investigative cases.">
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
      <a href="<?= $base ?>/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="d-flex justify-between align-center mb-2">
      <div>
        <h2 style="margin:0;font-size:1.25rem;">Investigation Cases</h2>
        <span class="small text-muted">Primary investigative files and related evidence groupings</span>
      </div>
      <?php if ($canManage): ?>
      <a href="<?= $base ?>/investigations/add.php" class="ct-btn ct-btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Investigation
      </a>
      <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="ct-card mb-2" style="padding:14px 18px;">
      <form method="GET" class="d-flex align-center gap-2" style="margin:0;flex-wrap:wrap;">
        <div style="flex:2;min-width:200px;">
          <input type="text" name="search" class="ct-input" placeholder="Search reference or title…" value="<?= esc($filters['search']) ?>">
        </div>

        <div style="flex:1;min-width:140px;">
          <select name="status" class="ct-select">
            <option value="">All Statuses</option>
            <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="closed" <?= $filters['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
            <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>

        <div style="flex:1;min-width:160px;">
          <select name="department_id" class="ct-select">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $filters['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="ct-btn ct-btn-primary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['status'] !== '' || $filters['department_id']): ?>
        <a href="<?= $base ?>/investigations/index.php" class="ct-btn ct-btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table Card -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:130px;">Reference</th>
              <th>Case Title</th>
              <th>Department</th>
              <th>Lead Investigator</th>
              <th style="width:90px;text-align:center;">Items</th>
              <th style="width:110px;">Start Date</th>
              <th style="width:90px;text-align:center;">Status</th>
              <th style="width:150px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($investigations as $inv): ?>
            <tr>
              <td>
                <a href="<?= $base ?>/investigations/view.php?id=<?= (int)$inv['id'] ?>" class="ref-code" style="text-decoration:none;">
                  <?= esc($inv['inv_reference']) ?>
                </a>
              </td>
              <td class="fw-600">
                <a href="<?= $base ?>/investigations/view.php?id=<?= (int)$inv['id'] ?>" style="color:var(--ct-text);text-decoration:none;">
                  <?= esc(mb_strimwidth($inv['title'], 0, 48, '…')) ?>
                </a>
              </td>
              <td class="small text-muted"><?= esc($inv['dept_name'] ?: '—') ?></td>
              <td class="small"><?= esc($inv['lead_name'] ?: '—') ?></td>
              <td style="text-align:center;">
                <span class="badge bg-primary"><?= (int)$inv['item_count'] ?></span>
              </td>
              <td class="small text-muted">
                <?= $inv['start_date'] ? date('d M Y', strtotime($inv['start_date'])) : '—' ?>
              </td>
              <td style="text-align:center;">
                <?= invStatusBadge($inv['status']) ?>
              </td>
              <td style="text-align:right;">
                <div class="d-flex gap-1 justify-end">
                  <a href="<?= $base ?>/investigations/view.php?id=<?= (int)$inv['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    View
                  </a>
                  <?php if ($canManage): ?>
                  <a href="<?= $base ?>/investigations/edit.php?id=<?= (int)$inv['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    Edit
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($investigations)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--ct-muted);padding:32px;">
                No investigations found.
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
