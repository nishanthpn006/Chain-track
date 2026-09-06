<?php
// =============================================================================
// ChainTrack — Confirm or Reject a Transfer
// transfers/confirm.php
//
// Handles BOTH confirm and reject GET/POST actions.
// GET  ?id=N&action=confirm  → shows confirmation page
// GET  ?id=N&action=reject   → shows rejection page (with reason textarea)
// POST (action=confirm)      → executes CONFIRM transaction
// POST (action=reject)       → executes REJECT transaction
//
// CONFIRM transaction (6 steps inside 1 atomic block):
//   1. UPDATE custody_transfers  → status='confirmed', confirmed_by, confirmed_at
//   2. UPDATE items              → current_custodian_id, current_location_id, current_status_id
//   3. INSERT custody_history    → action_type='transfer' (permanent record)
//   4. INSERT item_status_history
//   5. INSERT audit_logs
//   6. COMMIT
//
// REJECT transaction (4 steps):
//   1. UPDATE custody_transfers  → status='rejected', rejection_reason
//   2. UPDATE items              → revert current_status_id to 'in_storage'
//   3. INSERT item_status_history
//   4. INSERT audit_logs → COMMIT
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = getMySQL();
$uid        = sessionUserId();
$role       = sessionUserRole();
$transferId = (int)($_GET['id'] ?? $_POST['transfer_id'] ?? 0);
$action     = $_GET['action'] ?? $_POST['action'] ?? 'confirm';

// ─── Helper: redirect with message ───────────────────────────────────────────
function flashRedirect(string $type, string $msg, string $url): never
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header("Location: {$url}");
    exit;
}

if ($transferId <= 0) {
    flashRedirect('danger', 'Invalid transfer ID.', '/transfers/incoming.php');
}

// ─── Load transfer ────────────────────────────────────────────────────────────
$transfer = getTransferById($db, $transferId);

if (!$transfer) {
    flashRedirect('danger', 'Transfer not found.', '/transfers/incoming.php');
}

// ─── Security: only the intended recipient (or admin) can act ────────────────
if ($role !== 'administrator' && (int)$transfer['to_user_id'] !== $uid) {
    flashRedirect('danger',
        'Access denied. Only the intended recipient can confirm or reject this transfer.',
        '/transfers/incoming.php');
}

// ─── Guard: transfer must still be pending ───────────────────────────────────
if ($transfer['transfer_status'] !== 'pending') {
    $label = ucfirst($transfer['transfer_status']);
    flashRedirect('warning',
        "This transfer is already <strong>{$label}</strong> and cannot be actioned again.",
        '/transfers/incoming.php');
}

// =============================================================================
// HANDLE POST (actual transaction execution)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim($_POST['action'] ?? 'confirm');
    $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // ── CONFIRM ───────────────────────────────────────────────────────────────
    if ($action === 'confirm') {

        // Status IDs
        $inStorageId = getStatusIdBySlug($db, 'in_storage');
        if (!$inStorageId) {
            flashRedirect('danger', "System error: 'in_storage' status not found.", '/transfers/incoming.php');
        }
        $prevStatusId = (int)$transfer['current_custodian_id']; // pre-load via separate query below

        // Get item's current status for history
        $s = $db->prepare("SELECT current_status_id FROM items WHERE id = ? LIMIT 1");
        $s->bind_param('i', $transfer['item_id']);
        $s->execute();
        $prevStatusId = (int)$s->get_result()->fetch_assoc()['current_status_id'];
        $s->close();

        $db->begin_transaction();
        try {
            // Step 1 — UPDATE custody_transfers
            $s = $db->prepare(
                "UPDATE custody_transfers
                 SET transfer_status = 'confirmed',
                     confirmed_by    = ?,
                     confirmed_at    = NOW()
                 WHERE id = ? AND transfer_status = 'pending'"
            );
            $s->bind_param('ii', $uid, $transferId);
            $s->execute();
            if ($s->affected_rows === 0) {
                throw new RuntimeException('Transfer was already actioned by another session.');
            }
            $s->close();

            // Step 2 — UPDATE items (current custodian NOW changes)
            $toUserId = (int)$transfer['to_user_id'];
            $toLocId  = (int)($transfer['to_location_id'] ?? 0) ?: null;

            $s = $db->prepare(
                "UPDATE items
                 SET current_custodian_id = ?,
                     current_location_id  = ?,
                     current_status_id    = ?,
                     updated_at           = NOW()
                 WHERE id = ?"
            );
            $s->bind_param('iiii', $toUserId, $toLocId, $inStorageId, $transfer['item_id']);
            $s->execute();
            $s->close();

            // Step 3 — INSERT custody_history (permanent record)
            $fromUserId = (int)($transfer['from_user_id'] ?? 0) ?: null;
            $fromLocId  = (int)($transfer['from_location_id'] ?? 0) ?: null;
            $histRemark = "Transfer confirmed. Ref: {$transfer['transfer_reference']}. "
                        . "Reason: {$transfer['reason']}";

            $s = $db->prepare(
                "INSERT INTO custody_history
                   (item_id, from_user_id, to_user_id, from_location_id, to_location_id,
                    action_type, remarks, related_transfer_id, recorded_by, recorded_at)
                 VALUES
                   (?, ?, ?, ?, ?, 'transfer', ?, ?, ?, NOW())"
            );
            $s->bind_param(
                'iiiiisii',
                $transfer['item_id'],
                $fromUserId,
                $toUserId,
                $fromLocId,
                $toLocId,
                $histRemark,
                $transferId,
                $uid
            );
            $s->execute();
            $s->close();

            // Step 4 — INSERT item_status_history
            $shReason = "Transfer {$transfer['transfer_reference']} confirmed. Custody updated to user ID {$toUserId}.";
            $s = $db->prepare(
                "INSERT INTO item_status_history
                   (item_id, previous_status_id, new_status_id, changed_by,
                    related_transfer_id, reason, changed_at)
                 VALUES
                   (?, ?, ?, ?, ?, ?, NOW())"
            );
            $s->bind_param(
                'iiiiis',
                $transfer['item_id'],
                $prevStatusId,
                $inStorageId,
                $uid,
                $transferId,
                $shReason
            );
            $s->execute();
            $s->close();

            // Step 5 — INSERT audit_logs
            $auditDesc = "Transfer {$transfer['transfer_reference']} confirmed by user ID {$uid}. "
                       . "Item ID {$transfer['item_id']} custody transferred from user {$fromUserId} to {$toUserId}.";
            $s = $db->prepare(
                "INSERT INTO audit_logs
                   (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                 VALUES
                   (?, 'transfer.confirmed', 'custody_transfer', ?, ?, ?, ?, NOW())"
            );
            $s->bind_param('iisss', $uid, $transferId, $auditDesc, $ip, $ua);
            $s->execute();
            $s->close();

            // Step 6 — COMMIT
            $db->commit();

            flashRedirect('success',
                "Transfer <strong>{$transfer['transfer_reference']}</strong> confirmed. "
              . "You are now the custodian of item <strong>{$transfer['item_reference']}</strong>.",
                '/items/item_details.php?id=' . $transfer['item_id']);

        } catch (Throwable $e) {
            $db->rollback();
            error_log('[ChainTrack] confirm.php CONFIRM failed: ' . $e->getMessage()
                    . ' | ' . $e->getFile() . ':' . $e->getLine());
            flashRedirect('danger',
                'A database error occurred. The transfer was not confirmed. '
              . 'All changes have been rolled back. Please try again.',
                '/transfers/incoming.php');
        }
    }

    // ── REJECT ────────────────────────────────────────────────────────────────
    if ($action === 'reject') {

        $rejectReason = trim($_POST['reject_reason'] ?? '');
        if ($rejectReason === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'A rejection reason is required.'];
            header("Location: /transfers/confirm.php?id={$transferId}&action=reject");
            exit;
        }

        // Revert status: pending_transfer → in_storage
        $inStorageId = getStatusIdBySlug($db, 'in_storage');

        // Get current item status
        $s = $db->prepare("SELECT current_status_id FROM items WHERE id = ? LIMIT 1");
        $s->bind_param('i', $transfer['item_id']);
        $s->execute();
        $prevStatusId = (int)$s->get_result()->fetch_assoc()['current_status_id'];
        $s->close();

        $db->begin_transaction();
        try {
            // 1 — UPDATE custody_transfers
            $s = $db->prepare(
                "UPDATE custody_transfers
                 SET transfer_status = 'rejected', rejection_reason = ?
                 WHERE id = ? AND transfer_status = 'pending'"
            );
            $s->bind_param('si', $rejectReason, $transferId);
            $s->execute();
            if ($s->affected_rows === 0) {
                throw new RuntimeException('Transfer was already actioned.');
            }
            $s->close();

            // 2 — Revert item status to in_storage (custodian stays the same)
            $s = $db->prepare(
                "UPDATE items SET current_status_id = ?, updated_at = NOW() WHERE id = ?"
            );
            $s->bind_param('ii', $inStorageId, $transfer['item_id']);
            $s->execute();
            $s->close();

            // 3 — INSERT item_status_history
            $shReason = "Transfer {$transfer['transfer_reference']} rejected. Reason: {$rejectReason}";
            $s = $db->prepare(
                "INSERT INTO item_status_history
                   (item_id, previous_status_id, new_status_id, changed_by,
                    related_transfer_id, reason, changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $s->bind_param('iiiiis',
                $transfer['item_id'], $prevStatusId, $inStorageId, $uid, $transferId, $shReason
            );
            $s->execute();
            $s->close();

            // 4 — INSERT audit_logs
            $auditDesc = "Transfer {$transfer['transfer_reference']} rejected by user ID {$uid}. "
                       . "Reason: {$rejectReason}";
            $s = $db->prepare(
                "INSERT INTO audit_logs
                   (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                 VALUES (?, 'transfer.rejected', 'custody_transfer', ?, ?, ?, ?, NOW())"
            );
            $s->bind_param('iisss', $uid, $transferId, $auditDesc, $ip, $ua);
            $s->execute();
            $s->close();

            $db->commit();

            flashRedirect('info',
                "Transfer <strong>{$transfer['transfer_reference']}</strong> has been rejected. "
              . "The item remains with its current custodian.",
                '/transfers/index.php');

        } catch (Throwable $e) {
            $db->rollback();
            error_log('[ChainTrack] confirm.php REJECT failed: ' . $e->getMessage());
            flashRedirect('danger',
                'A database error occurred. The rejection was not saved. Please try again.',
                '/transfers/incoming.php');
        }
    }

    // Unknown action — just redirect
    header('Location: /transfers/incoming.php');
    exit;
}

// =============================================================================
// GET — Show the confirm / reject UI page
// =============================================================================

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function fmtDtC(?string $dt): string {
    return $dt ? date('d M Y, g:i A', strtotime($dt)) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $action === 'reject' ? 'Reject' : 'Confirm' ?> Transfer — ChainTrack</title>
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">
      <?= $action === 'reject' ? 'Reject Transfer' : 'Confirm Receipt of Transfer' ?>
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

    <!-- Breadcrumb -->
    <div class="d-flex align-center gap-1 mb-2 text-muted small">
      <a href="/transfers/incoming.php" style="color:var(--ct-muted);text-decoration:none;">Incoming</a>
      <span>›</span>
      <span class="mono"><?= esc($transfer['transfer_reference']) ?></span>
    </div>

    <div class="ct-card" style="max-width:800px;">
      <div class="ct-card-header">
        <span class="mono text-accent"><?= esc($transfer['transfer_reference']) ?></span>
        <span class="badge bg-warning" style="margin-left:8px;">Pending Receipt</span>
        <span class="small text-muted" style="margin-left:auto;">
          Initiated: <?= fmtDtC($transfer['initiated_at']) ?>
        </span>
      </div>
      <div class="ct-card-body">

        <!-- Transfer details -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 24px;margin-bottom:22px;">
          <div>
            <div class="ct-detail-label">Item</div>
            <div class="fw-600">
              <a href="/items/item_details.php?id=<?= esc($transfer['item_id']) ?>"
                 class="ref-code"><?= esc($transfer['item_reference']) ?></a>
            </div>
            <div class="small text-muted"><?= esc($transfer['item_name']) ?></div>
          </div>
          <div>
            <div class="ct-detail-label">Initiated By</div>
            <div class="fw-600"><?= esc($transfer['initiated_by_name']) ?></div>
          </div>
          <div>
            <div class="ct-detail-label">From (Sender)</div>
            <div class="fw-600"><?= esc($transfer['from_user_name'] ?? '—') ?></div>
            <?php if ($transfer['from_emp_id']): ?>
            <div class="small text-muted"><?= esc($transfer['from_emp_id']) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <div class="ct-detail-label">To (Recipient — You)</div>
            <div class="fw-600"><?= esc($transfer['to_user_name']) ?></div>
            <?php if ($transfer['to_emp_id']): ?>
            <div class="small text-muted"><?= esc($transfer['to_emp_id']) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <div class="ct-detail-label">From Location</div>
            <div><?= esc($transfer['from_location_name'] ?? '—') ?></div>
          </div>
          <div>
            <div class="ct-detail-label">Destination Location</div>
            <div class="fw-600"><?= esc($transfer['to_location_name'] ?? '—') ?></div>
          </div>
          <div style="grid-column:span 2;">
            <div class="ct-detail-label">Transfer Reason</div>
            <div class="small" style="line-height:1.6;"><?= esc($transfer['reason']) ?></div>
          </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--ct-border);margin:0 0 20px;">

        <?php if ($action === 'reject'): ?>
        <!-- ── REJECT FORM ──────────────────────────────────────────────── -->
        <div class="ct-alert ct-alert-warning" style="margin-bottom:18px;">
          <strong>Rejecting this transfer</strong> will keep the item with its current custodian.
          The transfer status will be updated to <em>Rejected</em>. This action cannot be undone.
        </div>
        <form method="POST" action="/transfers/confirm.php">
          <input type="hidden" name="transfer_id" value="<?= esc($transferId) ?>">
          <input type="hidden" name="action" value="reject">
          <div class="ct-form-group">
            <label class="ct-label" for="reject_reason">
              Rejection Reason <span style="color:var(--ct-danger);">*</span>
            </label>
            <textarea id="reject_reason" name="reject_reason" class="ct-textarea" required
                      placeholder="State why you are rejecting this transfer…"></textarea>
          </div>
          <div class="d-flex gap-2" style="margin-top:16px;">
            <button type="submit" class="ct-btn ct-btn-danger">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
              Confirm Rejection
            </button>
            <a href="/transfers/incoming.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

        <?php else: ?>
        <!-- ── CONFIRM FORM ──────────────────────────────────────────────── -->
        <div class="ct-alert ct-alert-info" style="margin-bottom:18px;">
          <strong>By confirming receipt</strong>, you acknowledge physical receipt of this item
          and accept full custody responsibility.
          Your user account will become the new current custodian.
        </div>
        <form method="POST" action="/transfers/confirm.php" id="confirm-form">
          <input type="hidden" name="transfer_id" value="<?= esc($transferId) ?>">
          <input type="hidden" name="action" value="confirm">
          <div class="d-flex gap-2" style="margin-top:4px;">
            <button type="submit" id="btn-confirm" class="ct-btn ct-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              ✓ Confirm Receipt
            </button>
            <a href="/transfers/confirm.php?id=<?= esc($transferId) ?>&action=reject"
               class="ct-btn ct-btn-danger">
              Reject Transfer
            </a>
            <a href="/transfers/incoming.php" class="ct-btn ct-btn-secondary">
              Back
            </a>
          </div>
        </form>
        <?php endif; ?>

      </div>
    </div>

  </div>
</div>
</div>

<script>
const cf = document.getElementById('confirm-form');
if (cf) cf.addEventListener('submit', function() {
  document.getElementById('btn-confirm').disabled = true;
  document.getElementById('btn-confirm').textContent = 'Processing…';
});
</script>

</body>
</html>
