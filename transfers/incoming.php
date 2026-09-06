<?php
// =============================================================================
// ChainTrack — Incoming Pending Transfers
// transfers/incoming.php
//
// Shows all PENDING transfers where the logged-in user is the TO (recipient).
// Only transfers addressed to the current user are ever shown here.
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db   = getMySQL();
$uid  = sessionUserId();

$incoming = getPendingIncoming($db, $uid);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function fmtDtL(?string $dt): string {
    return $dt ? date('d M Y, g:i A', strtotime($dt)) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Incoming Transfers — ChainTrack</title>
  <meta name="description" content="Review and confirm pending custody transfers addressed to you.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">
      Incoming Transfers
      <?php if (!empty($incoming)): ?>
      <span class="badge bg-warning" style="margin-left:6px;font-size:.7rem;vertical-align:middle;">
        <?= count($incoming) ?>
      </span>
      <?php endif; ?>
    </span>
    <div class="topbar-right">
      <?php if ($flash): ?>
      <div class="ct-alert ct-alert-<?= esc($flash['type']) ?>" style="margin:0;padding:8px 14px;font-size:.8rem;">
        <?= $flash['message'] ?>
      </div>
      <?php endif; ?>
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
    </div>
  </div>

  <div id="ct-content">

    <div class="d-flex align-center gap-1 mb-2 text-muted small">
      <a href="/transfers/index.php" style="color:var(--ct-muted);text-decoration:none;">Transfers</a>
      <span>›</span><span>Incoming (Pending Receipt)</span>
    </div>

    <?php if (empty($incoming)): ?>

    <div class="ct-card">
      <div class="ct-card-body" style="text-align:center;padding:48px 20px;">
        <h3 style="margin:0 0 8px;font-size:0.95rem;color:var(--ct-text);font-weight:600;">No Pending Transfers</h3>
        <p class="text-muted small" style="margin:0 0 16px;">
          No incoming transfers currently require your inspection or confirmation.
        </p>
        <a href="/transfers/index.php" class="ct-btn ct-btn-secondary ct-btn-sm">
          View All Custody Transfers
        </a>
      </div>
    </div>

    <?php else: ?>

    <div class="small text-muted mb-2">
      The following items have been transferred to you and are awaiting your confirmation.
      <strong>Custody does not change until you confirm receipt.</strong>
    </div>

    <?php foreach ($incoming as $t): ?>
    <div class="ct-card" style="margin-bottom:18px;">
      <div class="ct-card-header" style="justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div class="d-flex align-center gap-2">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="17 1 21 5 17 9"/>
            <path d="M3 11V9a4 4 0 014-4h14"/>
          </svg>
          <span class="mono text-accent"><?= esc($t['transfer_reference']) ?></span>
          <span class="badge bg-warning">Pending Receipt</span>
        </div>
        <span class="small text-muted">Initiated: <?= fmtDtL($t['initiated_at']) ?></span>
      </div>

      <div class="ct-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">

          <div>
            <div class="ct-detail-label">Item</div>
            <div class="fw-600">
              <a href="/items/item_details.php?id=<?= esc($t['item_id']) ?>"
                 class="ref-code"><?= esc($t['item_reference']) ?></a>
            </div>
            <div class="small text-muted"><?= esc($t['item_name']) ?></div>
          </div>

          <div>
            <div class="ct-detail-label">Transferred From</div>
            <div class="fw-600"><?= esc($t['from_user_name'] ?? '—') ?></div>
            <?php if ($t['from_emp_id']): ?>
            <div class="small text-muted"><?= esc($t['from_emp_id']) ?></div>
            <?php endif; ?>
          </div>

          <div>
            <div class="ct-detail-label">From Location</div>
            <div><?= esc($t['from_location_name'] ?? '—') ?></div>
          </div>

          <div>
            <div class="ct-detail-label">To Location (Your Destination)</div>
            <div class="fw-600"><?= esc($t['to_location_name'] ?? '—') ?></div>
          </div>

        </div>

        <?php if ($t['reason']): ?>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--ct-border);">
          <div class="ct-detail-label">Transfer Reason</div>
          <div class="small" style="line-height:1.6;"><?= esc($t['reason']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="d-flex gap-2" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--ct-border);">
          <a href="/transfers/confirm.php?id=<?= esc($t['id']) ?>&action=confirm"
             class="ct-btn ct-btn-primary"
             data-confirm="Confirm receipt of '<?= esc(addslashes($t['item_name'])) ?>'? This will make you the current custodian.">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Confirm Receipt
          </a>
          <a href="/transfers/confirm.php?id=<?= esc($t['id']) ?>&action=reject"
             class="ct-btn ct-btn-danger"
             data-confirm="Reject this transfer? The item will remain with the current custodian.">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            Reject Transfer
          </a>
          <a href="/items/item_details.php?id=<?= esc($t['item_id']) ?>"
             class="ct-btn ct-btn-secondary">
            View Item Details
          </a>
        </div>

      </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

  </div><!-- #ct-content -->
</div>
</div>

<script src="/public/js/chaintrack.js"></script>
</body>
</html>
