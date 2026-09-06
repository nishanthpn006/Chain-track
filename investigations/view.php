<?php
// =============================================================================
// ChainTrack — Investigation Management: Case Details & Related Items
// investigations/view.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid investigation ID.'];
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/index.php');
    exit;
}

$inv = getInvestigationById($db, $id);
if (!$inv) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Investigation not found.'];
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/index.php');
    exit;
}

$items = getInvestigationItems($db, $id);

$userRole = sessionUserRole();
$canEdit  = in_array($userRole, ['administrator', 'investigator'], true);
$canAddItem = $canEdit && $inv['status'] === 'open';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$base = defined('BASE_PATH') ? BASE_PATH : '';
$pageTitle = 'Investigation: ' . $inv['inv_reference'];

function invStatusBadgeLarge(string $status): string {
    $map = [
        'open'     => ['Open (Active)', 'success'],
        'closed'   => ['Closed',        'danger'],
        'archived' => ['Archived',      'secondary'],
    ];
    $s = $map[$status] ?? [ucfirst($status), 'secondary'];
    return "<span class=\"badge bg-{$s[1]}\" style=\"font-size:.85rem;padding:5px 10px;\">{$s[0]}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Detailed investigation case file and associated evidence items.">
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
        <div class="small text-muted mb-1">
          <a href="<?= $base ?>/investigations/index.php" style="color:var(--ct-muted);text-decoration:none;">Investigations</a> ›
          <span class="mono fw-600"><?= esc($inv['inv_reference']) ?></span>
        </div>
        <h2 style="margin:0;font-size:1.35rem;"><?= esc($inv['title']) ?></h2>
      </div>
      <div class="d-flex gap-1">
        <?php if ($canAddItem): ?>
        <a href="<?= $base ?>/items/add_item.php?investigation_id=<?= (int)$id ?>" class="ct-btn ct-btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Register Item for Case
        </a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <a href="<?= $base ?>/investigations/edit.php?id=<?= (int)$id ?>" class="ct-btn ct-btn-secondary">
          Edit Case
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Case Information Cards -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:20px;">
      
      <!-- Metadata summary -->
      <div class="ct-card">
        <div class="ct-card-header">Case Parameters</div>
        <div class="ct-card-body">
          <div class="mb-2">
            <div class="small text-muted">Case Reference</div>
            <div class="mono fw-600" style="font-size:1.1rem;color:var(--ct-accent);"><?= esc($inv['inv_reference']) ?></div>
          </div>

          <div class="mb-2">
            <div class="small text-muted">Case Status</div>
            <div style="margin-top:4px;"><?= invStatusBadgeLarge($inv['status']) ?></div>
          </div>

          <div class="mb-2">
            <div class="small text-muted">Department / Division</div>
            <div class="fw-600"><?= esc($inv['dept_name'] ?: '—') ?></div>
          </div>

          <div class="mb-2">
            <div class="small text-muted">Lead Investigator</div>
            <div class="fw-600">
              <?= esc($inv['lead_name'] ?: 'Unassigned') ?>
              <?php if (!empty($inv['lead_emp_id'])): ?>
              <span class="mono small text-muted">(<?= esc($inv['lead_emp_id']) ?>)</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-2">
            <div class="small text-muted">Investigation Period</div>
            <div>
              <strong><?= $inv['start_date'] ? date('d M Y', strtotime($inv['start_date'])) : '—' ?></strong>
              to
              <strong><?= $inv['end_date'] ? date('d M Y', strtotime($inv['end_date'])) : 'Ongoing' ?></strong>
            </div>
          </div>

          <div class="mb-1" style="border-top:1px solid var(--ct-border);padding-top:12px;">
            <div class="small text-muted">Registered By / Date</div>
            <div class="small">
              <?= esc($inv['created_by_name'] ?: 'System') ?> · <?= date('d M Y, H:i', strtotime($inv['created_at'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Description & Notes -->
      <div class="ct-card">
        <div class="ct-card-header">Scope & Description</div>
        <div class="ct-card-body" style="line-height:1.75;color:var(--ct-text);font-size:.9rem;">
          <?php if (!empty($inv['description'])): ?>
            <?= nl2br(esc($inv['description'])) ?>
          <?php else: ?>
            <p class="text-muted" style="font-style:italic;margin:0;">No case description or briefing notes provided.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Related Items -->
    <div class="ct-card">
      <div class="ct-card-header d-flex justify-between align-center">
        <div>
          <span>Controlled Items in This Case</span>
          <span class="badge bg-primary" style="margin-left:8px;"><?= count($items) ?></span>
        </div>
        <?php if ($canAddItem): ?>
        <a href="<?= $base ?>/items/add_item.php?investigation_id=<?= (int)$id ?>" class="ct-btn ct-btn-primary ct-btn-sm">
          + Add Item
        </a>
        <?php endif; ?>
      </div>

      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:130px;">Item Reference</th>
              <th>Item Name</th>
              <th style="width:120px;">Category</th>
              <th>Current Custodian</th>
              <th>Current Location</th>
              <th style="width:120px;text-align:center;">Status</th>
              <th style="width:110px;">Acquisition</th>
              <th style="width:100px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <a href="<?= $base ?>/items/item_details.php?id=<?= (int)$item['id'] ?>" class="ref-code" style="text-decoration:none;">
                  <?= esc($item['item_reference']) ?>
                </a>
              </td>
              <td class="fw-600">
                <a href="<?= $base ?>/items/item_details.php?id=<?= (int)$item['id'] ?>" style="color:var(--ct-text);text-decoration:none;">
                  <?= esc($item['item_name']) ?>
                </a>
              </td>
              <td class="small text-muted"><?= esc($item['category_name']) ?></td>
              <td class="small">
                <?= esc($item['custodian_name'] ?: '—') ?>
              </td>
              <td class="small text-muted"><?= esc($item['location_name'] ?: '—') ?></td>
              <td style="text-align:center;">
                <span class="badge bg-<?= esc($item['color_badge']) ?>">
                  <?= esc($item['status_name']) ?>
                </span>
              </td>
              <td class="small text-muted">
                <?= $item['acquisition_date'] ? date('d M Y', strtotime($item['acquisition_date'])) : '—' ?>
              </td>
              <td style="text-align:right;">
                <a href="<?= $base ?>/items/item_details.php?id=<?= (int)$item['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                  View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--ct-muted);padding:36px;">
                <div>No evidence or controlled items registered to this case yet.</div>
                <?php if ($canAddItem): ?>
                <div style="margin-top:10px;">
                  <a href="<?= $base ?>/items/add_item.php?investigation_id=<?= (int)$id ?>" class="ct-btn ct-btn-primary ct-btn-sm">
                    Register First Item
                  </a>
                </div>
                <?php endif; ?>
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
