<?php
// =============================================================================
// ChainTrack — Item Detail Page with Custody History Timeline
// items/item_details.php
//
// Displays:
//   • Current state snapshot (status, custodian, location)
//   • Full item metadata
//   • COMPLETE chronological custody history from custody_history table
//   • Status-change history from item_status_history table
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db     = getMySQL();
$itemId = (int)($_GET['id'] ?? 0);

if ($itemId <= 0) {
    header('Location: /views/items/index.php');
    exit;
}

// ─── Load item with all joined data ─────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT
        i.id,
        i.item_reference,
        i.item_name,
        i.physical_description,
        i.acquisition_date,
        i.acquisition_location,
        i.notes,
        i.is_archived,
        i.created_at,
        i.current_custodian_id,
        i.current_location_id,
        i.current_status_id,
        ist.status_name,
        ist.color_badge,
        inv.inv_reference,
        inv.title          AS investigation_title,
        inv.id             AS investigation_id,
        ic.cat_name        AS category_name,
        cu.full_name       AS current_custodian_name,
        cu.employee_id     AS custodian_employee_id,
        cl.location_name   AS current_location_name,
        rb.full_name       AS registered_by_name
     FROM items i
     JOIN item_statuses ist     ON ist.id = i.current_status_id
     JOIN investigations inv    ON inv.id = i.investigation_id
     JOIN item_categories ic    ON ic.id  = i.category_id
     LEFT JOIN users cu         ON cu.id  = i.current_custodian_id
     LEFT JOIN locations cl     ON cl.id  = i.current_location_id
     LEFT JOIN users rb         ON rb.id  = i.registered_by
     WHERE i.id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'Item not found.'];
    header('Location: /items/index.php');
    exit;
}

// Role-based visibility
$role   = sessionUserRole();
$userId = sessionUserId();

if (!in_array($role, ['administrator', 'auditor'], true)) {
    $isCustodian = ((int)$item['current_custodian_id'] === $userId);
    
    // Check if user is lead investigator of this case
    $chkInv = $db->prepare("SELECT lead_user_id FROM investigations WHERE id = ? LIMIT 1");
    $chkInv->bind_param('i', $item['investigation_id']);
    $chkInv->execute();
    $leadUserId = (int)($chkInv->get_result()->fetch_assoc()['lead_user_id'] ?? 0);
    $chkInv->close();
    
    $isLead = ($leadUserId === $userId);

    if (!$isCustodian && !$isLead) {
        $_SESSION['flash'] = ['type'=>'danger','message'=>'You do not have access to view this item.'];
        header('Location: /items/index.php');
        exit;
    }
}

// ─── Handle status update POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $newStatusId = (int)($_POST['new_status_id'] ?? 0);
    $reason      = trim($_POST['reason'] ?? '');

    $result = updateItemStatus($db, $itemId, $newStatusId, $userId, $role, $reason);

    $_SESSION['flash'] = [
        'type'    => $result['success'] ? 'success' : 'danger',
        'message' => $result['message'],
    ];
    header('Location: /items/item_details.php?id=' . $itemId);
    exit;
}

// ─── Status update eligibility ──────────────────────────────────────────────
$canUpdateStatus = false;
if ((int)$item['is_archived'] === 0 && $item['status_name'] !== 'Pending Transfer') {
    if ($role === 'administrator') {
        $canUpdateStatus = true;
    } elseif ($role !== 'auditor') {
        $isCustodian = ((int)$item['current_custodian_id'] === $userId);
        $canUpdateStatus = $isCustodian || (isset($isLead) && $isLead);
    }
}

// ─── Load active status options for dropdown ────────────────────────────────
$availableStatuses = $db->query(
    "SELECT id, status_name, status_slug FROM item_statuses WHERE is_active = 1 AND status_slug != 'pending_transfer' ORDER BY sort_order ASC, status_name ASC"
)->fetch_all(MYSQLI_ASSOC);

// ─── Load the complete custody history ───────────────────────────────────────
$custodyHistory = getItemCustodyHistory($db, $itemId);

// ─── Load any active PENDING transfer for this item ──────────────────────────
$pendingStmt = $db->prepare(
    "SELECT ct.id, ct.transfer_reference, ct.transfer_status, ct.reason, ct.initiated_at,
            fu.full_name AS from_user_name, tu.full_name AS to_user_name,
            fl.location_name AS from_loc_name, tl.location_name AS to_loc_name,
            iu.full_name AS initiated_by_name
     FROM custody_transfers ct
     LEFT JOIN users fu     ON fu.id = ct.from_user_id
     JOIN users tu          ON tu.id = ct.to_user_id
     JOIN users iu          ON iu.id = ct.initiated_by
     LEFT JOIN locations fl ON fl.id = ct.from_location_id
     LEFT JOIN locations tl ON tl.id = ct.to_location_id
     WHERE ct.item_id = ? AND ct.transfer_status = 'pending'
     LIMIT 1"
);
$pendingStmt->bind_param('i', $itemId);
$pendingStmt->execute();
$pendingTransfer = $pendingStmt->get_result()->fetch_assoc();
$pendingStmt->close();

// ─── Load all custody transfers for this item ────────────────────────────────
$allTransfersStmt = $db->prepare(
    "SELECT ct.*,
            fu.full_name AS from_user_name, tu.full_name AS to_user_name,
            fl.location_name AS from_loc_name, tl.location_name AS to_loc_name
     FROM custody_transfers ct
     LEFT JOIN users fu     ON fu.id = ct.from_user_id
     JOIN users tu          ON tu.id = ct.to_user_id
     LEFT JOIN locations fl ON fl.id = ct.from_location_id
     LEFT JOIN locations tl ON tl.id = ct.to_location_id
     WHERE ct.item_id = ?
     ORDER BY ct.initiated_at DESC"
);
$allTransfersStmt->bind_param('i', $itemId);
$allTransfersStmt->execute();
$allTransfers = $allTransfersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allTransfersStmt->close();

// ─── Load the status-change history ──────────────────────────────────────────
$statusHistStmt = $db->prepare(
    "SELECT
        ish.changed_at,
        ish.reason,
        ps.status_name   AS prev_status_name,
        ps.color_badge   AS prev_color,
        ns.status_name   AS new_status_name,
        ns.color_badge   AS new_color,
        u.full_name      AS changed_by_name
     FROM item_status_history ish
     LEFT JOIN item_statuses ps ON ps.id = ish.previous_status_id
     JOIN  item_statuses ns     ON ns.id = ish.new_status_id
     JOIN  users u              ON u.id  = ish.changed_by
     WHERE ish.item_id = ?
     ORDER BY ish.changed_at ASC, ish.id ASC"
);
$statusHistStmt->bind_param('i', $itemId);
$statusHistStmt->execute();
$statusHistory = $statusHistStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$statusHistStmt->close();

// ─── Load audit logs for Admin / Auditor ─────────────────────────────────────
$auditLogs = [];
if (in_array($role, ['administrator', 'auditor'], true)) {
    $auditStmt = $db->prepare(
        "SELECT al.*, u.full_name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE al.entity_type = 'item' AND al.entity_id = ?
         ORDER BY al.created_at DESC
         LIMIT 25"
    );
    $auditStmt->bind_param('i', $itemId);
    $auditStmt->execute();
    $auditLogs = $auditStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $auditStmt->close();
}

// ─── Flash message ────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ─── Helper: render a coloured status badge ───────────────────────────────────
function badge(string $name, string $color): string {
    return '<span class="badge bg-' . htmlspecialchars($color, ENT_QUOTES) . '">'
         . htmlspecialchars($name, ENT_QUOTES) . '</span>';
}

// ─── Helper: format datetime for display ─────────────────────────────────────
function fmtDtLocal(?string $dt): string {
    if (empty($dt)) return '—';
    return date('d M Y, g:i A', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($item['item_reference']) ?> — ChainTrack</title>
  <meta name="description" content="Custody history and full details for item <?= esc($item['item_reference']) ?>">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <!-- Top bar -->
  <div id="ct-topbar">
    <span class="page-title">
      Item Detail — <span class="mono text-accent"><?= esc($item['item_reference']) ?></span>
    </span>
    <div class="topbar-right">
      <?php if ($flash): ?>
      <div class="ct-alert ct-alert-<?= esc($flash['type']) ?>" style="margin:0;padding:8px 14px;font-size:.8rem;">
        <?= $flash['message'] /* may contain <strong> — intentional */ ?>
      </div>
      <?php endif; ?>
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
    </div>
  </div>

  <div id="ct-content">

    <!-- Back Navigation -->
    <div class="mb-3">
      <a href="/items/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="text-decoration:none;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Items
      </a>
    </div>

    <!-- ── Dossier Header ─────────────────────────────────────────────────── -->
    <div class="ct-card mb-3" style="padding:20px 24px;border-left:4px solid var(--ct-accent);">
      <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:14px;">
        <div>
          <div class="d-flex align-center gap-2 mb-1">
            <span class="mono fw-600" style="font-size:1.15rem;color:var(--ct-accent);letter-spacing:0.04em;">
              <?= esc($item['item_reference']) ?>
            </span>
            <?= badge($item['status_name'], $item['color_badge']) ?>
            <?php if ($item['is_archived']): ?>
            <span class="badge bg-secondary">Archived</span>
            <?php endif; ?>
          </div>
          <h2 style="margin:0 0 6px 0;font-size:1.4rem;font-weight:700;color:var(--ct-text);">
            <?= esc($item['item_name']) ?>
          </h2>
          <div class="small text-muted" style="display:flex;gap:14px;flex-wrap:wrap;">
            <span>Case: <a href="/investigations/view.php?id=<?= esc($item['investigation_id']) ?>" class="ref-code" style="font-weight:600;"><?= esc($item['inv_reference']) ?></a> (<?= esc($item['investigation_title']) ?>)</span>
            <span>&bull;</span>
            <span>Category: <strong style="color:var(--ct-text);"><?= esc($item['category_name']) ?></strong></span>
          </div>
        </div>

        <div class="d-flex gap-2" style="flex-wrap:wrap;">
          <?php 
          $canInitiateTransfer = !$item['is_archived'] 
            && ($item['status_name'] ?? '') !== 'Pending Transfer'
            && ($role === 'administrator' || (int)$userId === (int)($item['current_custodian_id'] ?? 0));
          ?>
          <?php if ($canInitiateTransfer): ?>
          <a href="/transfers/initiate.php?item_id=<?= $itemId ?>" class="ct-btn ct-btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="17 1 21 5 17 9"/>
              <path d="M3 11V9a4 4 0 014-4h14"/>
            </svg>
            Initiate Custody Transfer
          </a>
          <?php endif; ?>
          <?php if (in_array($role, ['administrator', 'auditor'], true)): ?>
          <a href="/reports/index.php?report=custody&item_id=<?= $itemId ?>" class="ct-btn ct-btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            Custody Report
          </a>
          <?php endif; ?>
          <button type="button" class="ct-btn ct-btn-secondary" onclick="window.print();">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Dossier
          </button>
        </div>
      </div>
    </div>

    <!-- ── Current Information Dossier Card ──────────────────────────────── -->
    <div class="ct-card mb-3">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        Current Information & Registration Record
      </div>
      <div class="ct-card-body">
        <div class="ct-detail-grid mb-3">
          <div class="ct-detail-item">
            <div class="ct-detail-label">Current Legal Custodian</div>
            <div class="ct-detail-value fw-600"><?= esc($item['current_custodian_name'] ?? '— Unassigned') ?></div>
            <?php if ($item['custodian_employee_id']): ?>
            <div class="small text-muted">ID: <?= esc($item['custodian_employee_id']) ?></div>
            <?php endif; ?>
          </div>
          <div class="ct-detail-item">
            <div class="ct-detail-label">Current Storage Location</div>
            <div class="ct-detail-value"><?= esc($item['current_location_name'] ?? '— Unknown Location') ?></div>
          </div>
          <div class="ct-detail-item">
            <div class="ct-detail-label">Registered By</div>
            <div class="ct-detail-value"><?= esc($item['registered_by_name'] ?? '— System Officer') ?></div>
          </div>
          <div class="ct-detail-item">
            <div class="ct-detail-label">Registration Date & Time</div>
            <div class="ct-detail-value small"><?= fmtDtLocal($item['created_at']) ?></div>
          </div>
          <?php if ($item['acquisition_date']): ?>
          <div class="ct-detail-item">
            <div class="ct-detail-label">Original Acquisition Date</div>
            <div class="ct-detail-value"><?= esc(date('d M Y', strtotime($item['acquisition_date']))) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($item['acquisition_location']): ?>
          <div class="ct-detail-item">
            <div class="ct-detail-label">Original Collection Point</div>
            <div class="ct-detail-value"><?= esc($item['acquisition_location']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($item['physical_description'])): ?>
        <div style="border-top:1px solid var(--ct-border);padding-top:14px;">
          <div class="ct-detail-label">Physical Description & Condition Marks</div>
          <div class="small text-secondary" style="line-height:1.6;">
            <?= nl2br(esc($item['physical_description'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         CUSTODY HISTORY TIMELINE
         This is the core section — every custody event in chronological order.
         The first row is always the Initial Assignment with from=NULL.
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="ct-card mb-2">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="17 1 21 5 17 9"/>
          <path d="M3 11V9a4 4 0 014-4h14"/>
          <polyline points="7 23 3 19 7 15"/>
          <path d="M21 13v2a4 4 0 01-4 4H3"/>
        </svg>
        Custody History — Complete Chain of Custody
        <span class="badge bg-primary" style="margin-left:8px;"><?= count($custodyHistory) ?> record(s)</span>
      </div>

      <div class="ct-card-body">

        <?php if (empty($custodyHistory)): ?>
          <p class="text-muted">No custody records found for this item.</p>
        <?php else: ?>

        <!-- Timeline view -->
        <div class="ct-timeline">
          <?php foreach ($custodyHistory as $idx => $event): ?>

          <?php
          $isInitial = ($event['action_type'] === 'initial_assignment');
          $dotColor  = $isInitial ? 'var(--ct-accent)' : 'var(--ct-success)';
          $actionLabel = actionTypeLabel($event['action_type']);
          ?>
          <div class="ct-timeline-item" style="--dot-color:<?= $dotColor ?>;">

            <!-- Event timestamp and sequence number -->
            <div class="ct-timeline-time">
              <span style="color:var(--ct-muted);font-size:.7rem;font-weight:700;">
                EVENT #<?= $idx + 1 ?> &nbsp;·&nbsp;
              </span>
              <?= fmtDtLocal($event['recorded_at']) ?>
              &nbsp;·&nbsp;
              <span style="background:<?= $isInitial ? 'rgba(79,126,248,.2)' : 'rgba(46,204,113,.15)' ?>;
                           color:<?= $isInitial ? 'var(--ct-accent)' : 'var(--ct-success)' ?>;
                           padding:2px 8px;border-radius:99px;font-size:.68rem;font-weight:700;">
                <?= esc($actionLabel) ?>
              </span>
            </div>

            <div class="ct-timeline-body">

              <?php if ($isInitial): ?>
              <!-- ── Initial Assignment event ───────────────────────────── -->
              <div class="ct-timeline-title">
                Item Entered ChainTrack — Initial Custody Assigned
              </div>
              <div class="ct-timeline-meta" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                <div>
                  <div class="ct-detail-label">From (Previous Custodian)</div>
                  <div class="small" style="color:var(--ct-muted);font-style:italic;">
                    — None (item is being registered for the first time)
                  </div>
                </div>
                <div>
                  <div class="ct-detail-label">To (Initial Custodian)</div>
                  <div class="fw-600"><?= esc($event['to_user_name'] ?? '—') ?></div>
                  <?php if ($event['to_employee_id']): ?>
                  <div class="small text-muted"><?= esc($event['to_employee_id']) ?></div>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="ct-detail-label">From Location</div>
                  <div class="small" style="color:var(--ct-muted);font-style:italic;">— None</div>
                </div>
                <div>
                  <div class="ct-detail-label">Initial Storage Location</div>
                  <div><?= esc($event['to_location_name'] ?? '—') ?></div>
                </div>
              </div>

              <?php else: ?>
              <!-- ── Transfer / other custody event ─────────────────────── -->
              <div class="ct-timeline-title">
                Custody Transfer
                <?php if ($event['transfer_reference']): ?>
                <span class="mono text-accent" style="font-size:.8rem;margin-left:6px;">
                  <?= esc($event['transfer_reference']) ?>
                </span>
                <?php endif; ?>
              </div>
              <div class="ct-timeline-meta" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                <div>
                  <div class="ct-detail-label">From (Previous Custodian)</div>
                  <div class="fw-600"><?= esc($event['from_user_name'] ?? '—') ?></div>
                  <?php if ($event['from_employee_id']): ?>
                  <div class="small text-muted"><?= esc($event['from_employee_id']) ?></div>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="ct-detail-label">To (New Custodian)</div>
                  <div class="fw-600"><?= esc($event['to_user_name'] ?? '—') ?></div>
                  <?php if ($event['to_employee_id']): ?>
                  <div class="small text-muted"><?= esc($event['to_employee_id']) ?></div>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="ct-detail-label">From Location</div>
                  <div><?= esc($event['from_location_name'] ?? '—') ?></div>
                </div>
                <div>
                  <div class="ct-detail-label">To Location</div>
                  <div><?= esc($event['to_location_name'] ?? '—') ?></div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Remarks (shown for all event types) -->
              <?php if ($event['remarks']): ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--ct-border);">
                <div class="ct-detail-label">Remarks</div>
                <div class="small" style="line-height:1.6;"><?= esc($event['remarks']) ?></div>
              </div>
              <?php endif; ?>

              <!-- Recorded by -->
              <div class="small text-muted" style="margin-top:8px;">
                Recorded by: <strong><?= esc($event['recorded_by_name'] ?? '—') ?></strong>
              </div>

            </div><!-- .ct-timeline-body -->
          </div><!-- .ct-timeline-item -->

          <?php endforeach; ?>

          <!-- ── PENDING TRANSFER: shown AFTER all confirmed history ──────── -->
          <?php if ($pendingTransfer): ?>
          <div class="ct-timeline-item" style="--dot-color:var(--ct-warning);">
            <div class="ct-timeline-time">
              <span style="color:var(--ct-muted);font-size:.7rem;font-weight:700;">
                EVENT #<?= count($custodyHistory) + 1 ?> &nbsp;·&nbsp;
              </span>
              <?= fmtDtLocal($pendingTransfer['initiated_at']) ?>
              &nbsp;·&nbsp;
              <span style="background:rgba(255,193,7,.18);color:var(--ct-warning);
                           padding:2px 8px;border-radius:99px;font-size:.68rem;font-weight:700;">
                Transfer Initiated — Awaiting Confirmation
              </span>
            </div>
            <div class="ct-timeline-body">
              <div class="ct-timeline-title">
                Transfer Pending
                <span class="mono text-accent" style="font-size:.8rem;margin-left:6px;">
                  <?= esc($pendingTransfer['transfer_reference']) ?>
                </span>
              </div>
              <div class="ct-timeline-meta" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                <div>
                  <div class="ct-detail-label">From (Current Custodian)</div>
                  <div class="fw-600"><?= esc($pendingTransfer['from_user_name'] ?? '—') ?></div>
                </div>
                <div>
                  <div class="ct-detail-label">To (Recipient — Not Yet Confirmed)</div>
                  <div class="fw-600"><?= esc($pendingTransfer['to_user_name']) ?></div>
                </div>
                <div>
                  <div class="ct-detail-label">From Location</div>
                  <div><?= esc($pendingTransfer['from_loc_name'] ?? '—') ?></div>
                </div>
                <div>
                  <div class="ct-detail-label">Destination Location</div>
                  <div><?= esc($pendingTransfer['to_loc_name'] ?? '—') ?></div>
                </div>
              </div>
              <?php if ($pendingTransfer['reason']): ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--ct-border);">
                <div class="ct-detail-label">Transfer Reason</div>
                <div class="small" style="line-height:1.6;"><?= esc($pendingTransfer['reason']) ?></div>
              </div>
              <?php endif; ?>
              <div style="margin-top:12px;">
                <a href="/transfers/confirm.php?id=<?= esc($pendingTransfer['id']) ?>&action=confirm"
                   class="ct-btn ct-btn-primary" style="font-size:.75rem;padding:5px 12px;">
                  Review Transfer
                </a>
              </div>
              <div class="small text-muted" style="margin-top:8px;">
                Initiated by: <strong><?= esc($pendingTransfer['initiated_by_name']) ?></strong>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- .ct-timeline -->

        <?php endif; ?>

      </div><!-- .ct-card-body -->
    </div><!-- .ct-card -->

    <!-- ── Status-Change History ──────────────────────────────────────────── -->
    <div class="ct-card" style="margin-bottom:22px;">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
        Status History
        <span class="badge bg-secondary" style="margin-left:8px;"><?= count($statusHistory) ?> record(s)</span>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th>Date / Time</th>
              <th>Previous Status</th>
              <th>New Status</th>
              <th>Changed By</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($statusHistory as $sh): ?>
            <tr>
              <td class="small text-muted"><?= fmtDtLocal($sh['changed_at']) ?></td>
              <td>
                <?php if ($sh['prev_status_name']): ?>
                  <?= badge($sh['prev_status_name'], $sh['prev_color']) ?>
                <?php else: ?>
                  <span class="text-muted small" style="font-style:italic;">— None (initial)</span>
                <?php endif; ?>
              </td>
              <td><?= badge($ns = $sh['new_status_name'], $sh['new_color']) ?></td>
              <td class="small"><?= esc($sh['changed_by_name']) ?></td>
              <td class="small text-muted"><?= esc($sh['reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($statusHistory)): ?>
            <tr>
              <td colspan="5" style="text-align:center;color:var(--ct-muted);padding:24px;">
                No status records found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Status Update Form (Part B) ────────────────────────────────────── -->
    <?php if ($canUpdateStatus && !empty($allowedStatuses)): ?>
    <div class="ct-card" style="margin-bottom:22px;border-left:4px solid var(--ct-accent);">
      <div class="ct-card-header" style="color:var(--ct-accent);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
        Update Item Status
      </div>
      <div class="ct-card-body">
        <?php if ($updateError): ?>
        <div class="ct-alert ct-alert-danger mb-3">
          <?= esc($updateError) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/items/item_details.php?id=<?= $itemId ?>">
          <input type="hidden" name="action" value="update_status">
          <div style="display:grid;grid-template-columns:1fr 2fr auto;gap:14px;align-items:end;">
            <div class="ct-form-group" style="margin-bottom:0;">
              <label class="ct-form-label" for="new_status_id">Target Status <span class="text-danger">*</span></label>
              <select name="new_status_id" id="new_status_id" class="ct-form-select" required>
                <option value="">— Select New Status —</option>
                <?php foreach ($allowedStatuses as $st): ?>
                  <?php if ((int)$st['id'] !== (int)$item['current_status_id']): ?>
                    <option value="<?= (int)$st['id'] ?>" <?= (isset($_POST['new_status_id']) && (int)$_POST['new_status_id'] === (int)$st['id']) ? 'selected' : '' ?>>
                      <?= esc($st['status_name']) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ct-form-group" style="margin-bottom:0;">
              <label class="ct-form-label" for="status_reason">Reason / Remarks <span class="text-danger">*</span></label>
              <input type="text" name="status_reason" id="status_reason" class="ct-form-input" 
                     placeholder="State the official justification for this status update..." 
                     value="<?= esc($_POST['status_reason'] ?? '') ?>" required>
            </div>
            <div>
              <button type="submit" class="ct-btn ct-btn-primary" onclick="return confirm('Are you sure you want to update the status of this controlled item?');">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Apply Status
              </button>
            </div>
          </div>
          <div class="small text-muted mt-2">
            Status updates are recorded in the permanent audit trail and immutable status history.
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Transfer History ────────────────────────────────────────────────── -->
    <div class="ct-card" style="margin-bottom:22px;">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="17 1 21 5 17 9"/>
          <path d="M3 11V9a4 4 0 014-4h14"/>
          <polyline points="7 23 3 19 7 15"/>
          <path d="M21 13v2a4 4 0 01-4 4H3"/>
        </svg>
        Transfer History
        <span class="badge bg-secondary" style="margin-left:8px;"><?= count($allTransfers) ?> record(s)</span>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>From Custodian</th>
              <th>To Recipient</th>
              <th>Locations (From › To)</th>
              <th>Initiated Date</th>
              <th>Received Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allTransfers as $tr): ?>
            <tr>
              <td class="mono font-semibold text-accent"><?= esc($tr['transfer_reference']) ?></td>
              <td><?= esc($tr['from_user_name'] ?? '—') ?></td>
              <td class="fw-600"><?= esc($tr['to_user_name']) ?></td>
              <td class="small text-muted">
                <?= esc($tr['from_loc_name'] ?? '—') ?> › <?= esc($tr['to_loc_name'] ?? '—') ?>
              </td>
              <td class="small"><?= fmtDtLocal($tr['initiated_at']) ?></td>
              <td class="small"><?= fmtDtLocal($tr['confirmed_at']) ?></td>
              <td>
                <?php
                $stBadge = 'secondary';
                if ($tr['transfer_status'] === 'confirmed') $stBadge = 'success';
                elseif ($tr['transfer_status'] === 'pending') $stBadge = 'warning';
                elseif ($tr['transfer_status'] === 'rejected') $stBadge = 'danger';
                ?>
                <span class="badge bg-<?= $stBadge ?>"><?= ucfirst(esc($tr['transfer_status'])) ?></span>
              </td>
              <td>
                <a href="/transfers/view.php?id=<?= (int)$tr['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:3px 8px;font-size:0.75rem;">
                  View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($allTransfers)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--ct-muted);padding:24px;">
                No custody transfers have been initiated for this item yet.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Audit Information (Admin / Auditor Only) ────────────────────────── -->
    <?php if (!empty($auditLogs)): ?>
    <div class="ct-card" style="margin-bottom:22px;">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        System Audit Trail (Security & Integrity Verification)
        <span class="badge bg-secondary" style="margin-left:8px;"><?= count($auditLogs) ?> event(s)</span>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Action</th>
              <th>Actor</th>
              <th>IP Address</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($auditLogs as $al): ?>
            <tr>
              <td class="small text-muted"><?= fmtDtLocal($al['created_at']) ?></td>
              <td><span class="badge bg-secondary font-mono"><?= esc($al['action']) ?></span></td>
              <td class="small font-medium"><?= esc($al['actor_name'] ?? 'System') ?></td>
              <td class="small mono text-muted"><?= esc($al['ip_address'] ?? '—') ?></td>
              <td class="small"><?= esc($al['details'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- #ct-content -->
</div><!-- #ct-main -->
</div><!-- #ct-sidebar-wrap -->

</body>
</html>
