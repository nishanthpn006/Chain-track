<?php
// =============================================================================
// ChainTrack — View Transfer Details
// transfers/view.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = getMySQL();
$uid        = sessionUserId();
$role       = sessionUserRole();
$transferId = (int)($_GET['id'] ?? 0);

if ($transferId <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid transfer ID.'];
    header('Location: /transfers/index.php');
    exit;
}

$transfer = getTransferById($db, $transferId);
if (!$transfer) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Transfer record not found.'];
    header('Location: /transfers/index.php');
    exit;
}

$pageTitle = 'Transfer Dossier: ' . $transfer['transfer_reference'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function trBadge(string $status): string {
    $colors = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'initiated' => 'secondary'];
    $c = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst(htmlspecialchars($status, ENT_QUOTES)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">
      Transfer Details — <span class="mono text-accent"><?= esc($transfer['transfer_reference']) ?></span>
    </span>
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

    <div class="d-flex align-center gap-1 mb-2 text-muted small">
      <a href="/transfers/index.php" style="color:var(--ct-muted);text-decoration:none;">Custody Transfers</a>
      <span>›</span>
      <span class="mono"><?= esc($transfer['transfer_reference']) ?></span>
    </div>

    <div class="d-flex justify-between align-center mb-3">
      <div>
        <h2 style="margin:0;font-size:1.35rem;">Transfer Record</h2>
        <span class="mono text-accent"><?= esc($transfer['transfer_reference']) ?></span>
        &nbsp;<?= trBadge($transfer['transfer_status']) ?>
      </div>
      <div class="d-flex gap-2">
        <a href="/items/item_details.php?id=<?= (int)$transfer['item_id'] ?>" class="ct-btn ct-btn-secondary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          </svg>
          View Evidence Item
        </a>
        <?php if ($transfer['transfer_status'] === 'pending' && ($role === 'administrator' || (int)$transfer['to_user_id'] === $uid)): ?>
        <a href="/transfers/confirm.php?id=<?= (int)$transfer['id'] ?>&action=confirm" class="ct-btn ct-btn-primary">
          Review & Confirm
        </a>
        <a href="/transfers/confirm.php?id=<?= (int)$transfer['id'] ?>&action=reject" class="ct-btn ct-btn-danger">
          Reject
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Overview Card -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
      <div class="ct-card">
        <div class="ct-card-header">Transfer Information</div>
        <div class="ct-card-body">
          <div class="ct-detail-grid">
            <div class="ct-detail-item">
              <div class="ct-detail-label">Status</div>
              <div class="ct-detail-value"><?= trBadge($transfer['transfer_status']) ?></div>
            </div>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Initiated Date & Time</div>
              <div class="ct-detail-value small"><?= date('d M Y, g:i A', strtotime($transfer['initiated_at'])) ?></div>
            </div>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Transfer Initiated By</div>
              <div class="ct-detail-value fw-600"><?= esc($transfer['initiated_by_name']) ?></div>
            </div>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Confirmed / Resolved Date</div>
              <div class="ct-detail-value small">
                <?= $transfer['confirmed_at'] ? date('d M Y, g:i A', strtotime($transfer['confirmed_at'])) : '— Pending Resolution' ?>
              </div>
            </div>
            <?php if (!empty($transfer['confirmed_by_name'])): ?>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Confirmed By</div>
              <div class="ct-detail-value fw-600"><?= esc($transfer['confirmed_by_name']) ?></div>
            </div>
            <?php endif; ?>
            <div class="ct-detail-item" style="grid-column:span 2;">
              <div class="ct-detail-label">Official Reason / Purpose</div>
              <div class="small" style="line-height:1.6;margin-top:4px;"><?= nl2br(esc($transfer['reason'])) ?></div>
            </div>
            <?php if (!empty($transfer['rejection_reason'])): ?>
            <div class="ct-detail-item" style="grid-column:span 2;color:var(--ct-danger);">
              <div class="ct-detail-label" style="color:var(--ct-danger);">Rejection Explanation</div>
              <div class="small" style="line-height:1.6;margin-top:4px;"><?= nl2br(esc($transfer['rejection_reason'])) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Item Snapshot -->
      <div class="ct-card">
        <div class="ct-card-header">Evidence Subject & Custody Route</div>
        <div class="ct-card-body">
          <div class="ct-detail-grid">
            <div class="ct-detail-item" style="grid-column:span 2;">
              <div class="ct-detail-label">Item Subject</div>
              <div class="fw-600"><?= esc($transfer['item_name']) ?></div>
              <div class="mono text-accent small"><?= esc($transfer['item_reference']) ?></div>
            </div>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Releasing Custodian (From)</div>
              <div class="fw-600"><?= esc($transfer['from_user_name'] ?? '—') ?></div>
              <div class="small text-muted"><?= esc($transfer['from_loc_name'] ?? '—') ?></div>
            </div>
            <div class="ct-detail-item">
              <div class="ct-detail-label">Intended Recipient (To)</div>
              <div class="fw-600"><?= esc($transfer['to_user_name']) ?></div>
              <div class="small text-muted"><?= esc($transfer['to_loc_name'] ?? '—') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- #ct-content -->
</div><!-- #ct-main -->
</div><!-- #ct-sidebar-wrap -->

</body>
</html>
