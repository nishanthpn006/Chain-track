<?php
// =============================================================================
// ChainTrack — Controlled Items Management: Main Listing
// items/index.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db = getMySQL();
$userRole = sessionUserRole();
$userId   = sessionUserId();

$filters = [
    'search'           => trim($_GET['search'] ?? ''),
    'investigation_id' => (int)($_GET['investigation_id'] ?? 0) ?: null,
    'category_id'      => (int)($_GET['category_id'] ?? 0) ?: null,
    'status_id'        => (int)($_GET['status_id'] ?? 0) ?: null,
    'custodian_id'     => (int)($_GET['custodian_id'] ?? 0) ?: null,
    'location_id'      => (int)($_GET['location_id'] ?? 0) ?: null,
    'is_archived'      => isset($_GET['is_archived']) && $_GET['is_archived'] !== '' ? (int)$_GET['is_archived'] : 0,
];

// Non-admin/auditor only sees items they hold, or belonging to investigations they lead
if (!in_array($userRole, ['administrator', 'auditor'], true)) {
    // If investigator or custodian, default can filter or view assigned items
    // If not admin/auditor, restrict to items they currently hold unless filtering by case they are involved in
    if ($userRole === 'custodian' || $userRole === 'analyst') {
        $filters['restrict_to_user'] = $userId;
    }
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$data    = getAllItemsPaginated($db, $filters, $page, $perPage);
$items   = $data['rows'];

// Dropdown options
$categories     = $db->query("SELECT id, cat_name FROM item_categories WHERE is_active = 1 ORDER BY cat_name ASC")->fetch_all(MYSQLI_ASSOC);
$statuses       = $db->query("SELECT id, status_name FROM item_statuses WHERE is_active = 1 ORDER BY sort_order ASC, status_name ASC")->fetch_all(MYSQLI_ASSOC);
$investigations = $db->query("SELECT id, inv_reference, title FROM investigations ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$locations      = $db->query("SELECT id, location_name FROM locations WHERE is_active = 1 ORDER BY location_name ASC")->fetch_all(MYSQLI_ASSOC);
$custodians     = $db->query("SELECT id, full_name, employee_id FROM users WHERE is_active = 1 ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);

$canRegister = in_array($userRole, ['administrator', 'investigator'], true);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Controlled Evidence Items';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Search, filter, and track controlled evidence and investigative items.">
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
        <h2 style="margin:0;font-size:1.25rem;">Controlled Items</h2>
        <span class="small text-muted">
          Showing <?= count($items) ?> of <?= $data['total'] ?> total registered items
          <?php if (isset($filters['restrict_to_user'])): ?>
            · <strong style="color:var(--ct-accent);">Filtered to items currently in your custody</strong>
          <?php endif; ?>
        </span>
      </div>
      <div class="d-flex gap-1">
        <?php if ($canRegister): ?>
        <a href="/items/add_item.php" class="ct-btn ct-btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Register Item
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="ct-card mb-2" style="padding:14px 18px;">
      <form method="GET" class="d-flex align-center gap-2" style="margin:0;flex-wrap:wrap;">
        <div style="flex:2;min-width:180px;">
          <input type="text" name="search" class="ct-input" placeholder="Search reference, item name, or serial…" value="<?= esc($filters['search']) ?>">
        </div>

        <div style="flex:1;min-width:130px;">
          <select name="investigation_id" class="ct-select">
            <option value="">All Cases</option>
            <?php foreach ($investigations as $inv): ?>
            <option value="<?= (int)$inv['id'] ?>" <?= $filters['investigation_id'] === (int)$inv['id'] ? 'selected' : '' ?>>
              <?= esc($inv['inv_reference']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1;min-width:130px;">
          <select name="category_id" class="ct-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $filters['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= esc($cat['cat_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1;min-width:130px;">
          <select name="status_id" class="ct-select">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $st): ?>
            <option value="<?= (int)$st['id'] ?>" <?= $filters['status_id'] === (int)$st['id'] ? 'selected' : '' ?>>
              <?= esc($st['status_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (in_array($userRole, ['administrator', 'auditor'], true)): ?>
        <div style="flex:1;min-width:130px;">
          <select name="custodian_id" class="ct-select">
            <option value="">All Custodians</option>
            <?php foreach ($custodians as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filters['custodian_id'] === (int)$c['id'] ? 'selected' : '' ?>>
              <?= esc($c['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div style="flex:1;min-width:130px;">
          <select name="location_id" class="ct-select">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>" <?= $filters['location_id'] === (int)$loc['id'] ? 'selected' : '' ?>>
              <?= esc($loc['location_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="ct-btn ct-btn-primary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['investigation_id'] || $filters['category_id'] || $filters['status_id'] || $filters['custodian_id'] || $filters['location_id']): ?>
        <a href="/items/index.php" class="ct-btn ct-btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Items Table Card -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:130px;">Item Reference</th>
              <th>Item Name</th>
              <th>Case Reference</th>
              <th>Category</th>
              <th>Current Custodian</th>
              <th>Location</th>
              <th style="width:110px;text-align:center;">Status</th>
              <th style="width:100px;">Registered</th>
              <th style="width:150px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td>
                <a href="/items/item_details.php?id=<?= (int)$it['id'] ?>" class="ref-code" style="text-decoration:none;">
                  <?= esc($it['item_reference']) ?>
                </a>
              </td>
              <td class="fw-600">
                <a href="/items/item_details.php?id=<?= (int)$it['id'] ?>" style="color:var(--ct-text);text-decoration:none;">
                  <?= esc(mb_strimwidth($it['item_name'], 0, 36, '…')) ?>
                </a>
              </td>
              <td>
                <a href="/investigations/view.php?id=<?= (int)$it['investigation_id'] ?>" class="mono small" style="text-decoration:none;color:var(--ct-muted);">
                  <?= esc($it['inv_reference']) ?>
                </a>
              </td>
              <td class="small text-muted"><?= esc($it['category_name']) ?></td>
              <td class="small">
                <?= esc($it['custodian_name'] ?: '—') ?>
                <?php if ((int)$it['custodian_id'] === $userId): ?>
                  <span class="badge bg-dark" style="font-size:.65rem;margin-left:2px;">You</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= esc(mb_strimwidth($it['location_name'] ?: '—', 0, 22, '…')) ?></td>
              <td style="text-align:center;">
                <span class="badge bg-<?= esc($it['color_badge']) ?>">
                  <?= esc($it['status_name']) ?>
                </span>
              </td>
              <td class="small text-muted"><?= date('d M Y', strtotime($it['created_at'])) ?></td>
              <td style="text-align:right;">
                <div class="d-flex gap-1 justify-end">
                  <a href="/items/item_details.php?id=<?= (int)$it['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    View
                  </a>
                  <?php if ((int)$it['custodian_id'] === $userId && $it['status_slug'] !== 'pending_transfer' && (int)$it['is_archived'] === 0): ?>
                  <a href="/transfers/initiate.php?item_id=<?= (int)$it['id'] ?>" class="ct-btn ct-btn-primary ct-btn-sm" title="Initiate transfer">
                    Transfer
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <tr>
              <td colspan="9" style="text-align:center;color:var(--ct-muted);padding:36px;">
                No controlled items match the specified search or filter criteria.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <?php if ($data['pages'] > 1): ?>
      <div style="padding:14px 20px;border-top:1px solid var(--ct-border);" class="d-flex justify-between align-center">
        <span class="small text-muted">Page <?= $data['page'] ?> of <?= $data['pages'] ?> (<?= $data['total'] ?> items)</span>
        <div class="ct-pagination">
          <?php
          $q = $_GET;
          for ($p = 1; $p <= $data['pages']; $p++):
              $q['page'] = $p;
          ?>
          <a href="?<?= http_build_query($q) ?>" class="ct-page-btn <?= $p === $data['page'] ? 'active' : '' ?>">
            <?= $p ?>
          </a>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>

</body>
</html>
