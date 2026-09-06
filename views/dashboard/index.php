<?php
// =============================================================================
// ChainTrack — Role-Based Dynamic Dashboard
// views/dashboard/index.php
// =============================================================================
require_once __DIR__ . '/../../core/bootstrap.php';
require_login();

$pdo    = getDB();
$userId = currentUserId();
$role   = currentRole();

// ── Role Labels & Helper Badge ───────────────────────────────────────────────
$roleLabels = [
    ROLE_ADMIN        => 'System Administrator',
    ROLE_INVESTIGATOR => 'Lead Investigator',
    ROLE_CUSTODIAN    => 'Evidence Custodian',
    ROLE_ANALYST      => 'Forensic Analyst',
    ROLE_AUDITOR      => 'Compliance Auditor'
];
$displayRole = $roleLabels[$role] ?? ucfirst($role);

function dashBadge(string $name, string $color = 'secondary'): string {
    return '<span class="badge bg-' . htmlspecialchars($color, ENT_QUOTES) . '">'
         . htmlspecialchars($name, ENT_QUOTES) . '</span>';
}

function dashTfrBadge(string $status): string {
    $colors = [
        'pending'   => 'warning',
        'confirmed' => 'success',
        'rejected'  => 'danger',
        'initiated' => 'secondary'
    ];
    $c = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst(htmlspecialchars($status, ENT_QUOTES)) . '</span>';
}

// ─────────────────────────────────────────────────────────────────────────────
// DATA QUERIES BY ROLE
// ─────────────────────────────────────────────────────────────────────────────

if ($role === ROLE_ADMIN) {
    // Administrator: High-level system overview
    $statInv = (int)$pdo->query("SELECT COUNT(*) FROM investigations")->fetchColumn();
    $statItems = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE is_archived = 0")->fetchColumn();
    $statUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $statPendingTfr = (int)$pdo->query("SELECT COUNT(*) FROM custody_transfers WHERE transfer_status = 'pending'")->fetchColumn();
    $statUnderExam = (int)$pdo->query(
        "SELECT COUNT(*) FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         WHERE ist.status_slug = 'under_examination' AND i.is_archived = 0"
    )->fetchColumn();
    $statArchived = (int)$pdo->query(
        "SELECT COUNT(*) FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         WHERE i.is_archived = 1 OR ist.status_slug IN ('archived', 'closed')"
    )->fetchColumn();

    // Recent Activity (Audit Log)
    $stmtAct = $pdo->query(
        "SELECT al.*, u.full_name AS actor_name 
         FROM audit_logs al 
         LEFT JOIN users u ON u.id = al.user_id 
         ORDER BY al.created_at DESC LIMIT 6"
    );
    $recentActivity = $stmtAct->fetchAll();

    // Recent Transfers
    $stmtTfr = $pdo->query(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user, tu.full_name AS to_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         JOIN users tu ON tu.id = ct.to_user_id 
         ORDER BY ct.initiated_at DESC LIMIT 6"
    );
    $recentTransfers = $stmtTfr->fetchAll();

    // Recent Item Registrations
    $stmtReg = $pdo->query(
        "SELECT i.*, ist.status_name, ist.color_badge, ic.cat_name, u.full_name AS reg_user 
         FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         JOIN item_categories ic ON ic.id = i.category_id 
         LEFT JOIN users u ON u.id = i.registered_by 
         ORDER BY i.created_at DESC LIMIT 6"
    );
    $recentRegistrations = $stmtReg->fetchAll();

} elseif ($role === ROLE_INVESTIGATOR) {
    // Investigator: Cases, held evidence, transfers
    $stmtInv = $pdo->prepare(
        "SELECT inv.*, d.dept_name,
                (SELECT COUNT(*) FROM items WHERE investigation_id = inv.id AND is_archived = 0) AS item_count
         FROM investigations inv 
         LEFT JOIN departments d ON d.id = inv.department_id 
         WHERE inv.lead_user_id = ? OR inv.created_by = ? 
         ORDER BY inv.created_at DESC"
    );
    $stmtInv->execute([$userId, $userId]);
    $myInvestigations = $stmtInv->fetchAll();

    // Items associated with accessible investigations
    $stmtItems = $pdo->prepare(
        "SELECT i.*, ist.status_name, ist.color_badge, inv.inv_reference, u.full_name AS custodian_name 
         FROM items i 
         JOIN investigations inv ON inv.id = i.investigation_id 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         LEFT JOIN users u ON u.id = i.current_custodian_id 
         WHERE inv.lead_user_id = ? OR inv.created_by = ? OR i.registered_by = ? 
         ORDER BY i.created_at DESC LIMIT 8"
    );
    $stmtItems->execute([$userId, $userId, $userId]);
    $accessibleItems = $stmtItems->fetchAll();

    // Items currently under my custody
    $stmtCustody = $pdo->prepare(
        "SELECT i.*, ist.status_name, ist.color_badge, l.location_name, inv.inv_reference 
         FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         JOIN investigations inv ON inv.id = i.investigation_id 
         LEFT JOIN locations l ON l.id = i.current_location_id 
         WHERE i.current_custodian_id = ? AND i.is_archived = 0 
         ORDER BY i.updated_at DESC"
    );
    $stmtCustody->execute([$userId]);
    $myCustodyItems = $stmtCustody->fetchAll();

    // Transfers initiated by me
    $stmtTfr = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, tu.full_name AS to_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         JOIN users tu ON tu.id = ct.to_user_id 
         WHERE ct.initiated_by = ? OR ct.from_user_id = ? 
         ORDER BY ct.initiated_at DESC LIMIT 6"
    );
    $stmtTfr->execute([$userId, $userId]);
    $myTransfers = $stmtTfr->fetchAll();

    // Pending incoming transfers (waiting for me to accept)
    $stmtInc = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user, tl.location_name AS dest_location 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         LEFT JOIN locations tl ON tl.id = ct.to_location_id 
         WHERE ct.to_user_id = ? AND ct.transfer_status = 'pending' 
         ORDER BY ct.initiated_at DESC"
    );
    $stmtInc->execute([$userId]);
    $incomingTransfers = $stmtInc->fetchAll();

} elseif ($role === ROLE_CUSTODIAN) {
    // Custodian: Vault items, incoming transfers, recent handovers
    $stmtCustody = $pdo->prepare(
        "SELECT i.*, ist.status_name, ist.color_badge, l.location_name, inv.inv_reference, ic.cat_name 
         FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         JOIN investigations inv ON inv.id = i.investigation_id 
         JOIN item_categories ic ON ic.id = i.category_id 
         LEFT JOIN locations l ON l.id = i.current_location_id 
         WHERE i.current_custodian_id = ? AND i.is_archived = 0 
         ORDER BY i.updated_at DESC"
    );
    $stmtCustody->execute([$userId]);
    $custodyItems = $stmtCustody->fetchAll();

    // Incoming transfers waiting confirmation
    $stmtInc = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user, tl.location_name AS dest_location 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         LEFT JOIN locations tl ON tl.id = ct.to_location_id 
         WHERE ct.to_user_id = ? AND ct.transfer_status = 'pending' 
         ORDER BY ct.initiated_at DESC"
    );
    $stmtInc->execute([$userId]);
    $incomingPending = $stmtInc->fetchAll();

    // Recently received items
    $stmtRec = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         WHERE ct.to_user_id = ? AND ct.transfer_status = 'confirmed' 
         ORDER BY ct.confirmed_at DESC LIMIT 6"
    );
    $stmtRec->execute([$userId]);
    $recentlyReceived = $stmtRec->fetchAll();

    // Items pending transfer (outgoing initiated by custodian)
    $stmtOut = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, tu.full_name AS to_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         JOIN users tu ON tu.id = ct.to_user_id 
         WHERE ct.from_user_id = ? AND ct.transfer_status = 'pending' 
         ORDER BY ct.initiated_at DESC"
    );
    $stmtOut->execute([$userId]);
    $outgoingPending = $stmtOut->fetchAll();

} elseif ($role === ROLE_ANALYST) {
    // Analyst: Assigned items, examination workflow
    $stmtCustody = $pdo->prepare(
        "SELECT i.*, ist.status_name, ist.color_badge, l.location_name, inv.inv_reference 
         FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         JOIN investigations inv ON inv.id = i.investigation_id 
         LEFT JOIN locations l ON l.id = i.current_location_id 
         WHERE i.current_custodian_id = ? AND i.is_archived = 0 
         ORDER BY i.updated_at DESC"
    );
    $stmtCustody->execute([$userId]);
    $assignedItems = $stmtCustody->fetchAll();

    // All Items currently under examination across lab
    $stmtExam = $pdo->query(
        "SELECT i.*, ist.status_name, ist.color_badge, u.full_name AS custodian_name, l.location_name, inv.inv_reference 
         FROM items i 
         JOIN item_statuses ist ON ist.id = i.current_status_id 
         JOIN investigations inv ON inv.id = i.investigation_id 
         LEFT JOIN users u ON u.id = i.current_custodian_id 
         LEFT JOIN locations l ON l.id = i.current_location_id 
         WHERE ist.status_slug = 'under_examination' AND i.is_archived = 0 
         ORDER BY i.updated_at DESC"
    );
    $underExamItems = $stmtExam->fetchAll();

    // Recently completed examinations
    $stmtDone = $pdo->query(
        "SELECT ish.*, i.item_reference, i.item_name, u.full_name AS examiner_name 
         FROM item_status_history ish 
         JOIN items i ON i.id = ish.item_id 
         JOIN item_statuses ist ON ist.id = ish.new_status_id 
         JOIN users u ON u.id = ish.changed_by 
         WHERE ist.status_slug = 'examination_complete' 
         ORDER BY ish.changed_at DESC LIMIT 6"
    );
    $recentlyCompleted = $stmtDone->fetchAll();

    // Incoming transfers pending acceptance
    $stmtInc = $pdo->prepare(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         WHERE ct.to_user_id = ? AND ct.transfer_status = 'pending' 
         ORDER BY ct.initiated_at DESC"
    );
    $stmtInc->execute([$userId]);
    $incomingPending = $stmtInc->fetchAll();

} elseif ($role === ROLE_AUDITOR) {
    // Auditor: Read-only governance and integrity
    $statInv = (int)$pdo->query("SELECT COUNT(*) FROM investigations")->fetchColumn();
    $statItems = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE is_archived = 0")->fetchColumn();
    $statTransfers = (int)$pdo->query("SELECT COUNT(*) FROM custody_transfers")->fetchColumn();
    $statAuditLogs = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

    // Recent Custody Activity
    $stmtAct = $pdo->query(
        "SELECT ct.*, i.item_reference, i.item_name, fu.full_name AS from_user, tu.full_name AS to_user 
         FROM custody_transfers ct 
         JOIN items i ON i.id = ct.item_id 
         LEFT JOIN users fu ON fu.id = ct.from_user_id 
         JOIN users tu ON tu.id = ct.to_user_id 
         ORDER BY ct.initiated_at DESC LIMIT 8"
    );
    $recentCustodyActivity = $stmtAct->fetchAll();

    // Recent System Audit Events
    $stmtAudit = $pdo->query(
        "SELECT al.*, u.full_name AS actor_name 
         FROM audit_logs al 
         LEFT JOIN users u ON u.id = al.user_id 
         ORDER BY al.created_at DESC LIMIT 8"
    );
    $recentAuditEvents = $stmtAudit->fetchAll();
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../layouts/header.php';
?>
<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<div id="ct-main">
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>
<div id="ct-content">

  <!-- Welcome banner -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h2 style="margin:0;font-size:1.4rem;">
        Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Officer', ENT_QUOTES) ?>
      </h2>
      <span class="small text-muted">
        Role: <strong style="color:var(--ct-accent);"><?= esc($displayRole) ?></strong> &nbsp;·&nbsp;
        Department: <strong><?= esc($_SESSION['user_dept'] ?? 'Central Investigation') ?></strong>
      </span>
    </div>
    <div class="d-flex gap-2">
      <?php if ($role !== ROLE_AUDITOR): ?>
      <a href="/items/add_item.php" class="ct-btn ct-btn-primary ct-btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Register Item
      </a>
      <a href="/transfers/initiate.php" class="ct-btn ct-btn-secondary ct-btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/>
        </svg>
        Initiate Transfer
      </a>
      <?php else: ?>
      <a href="/views/reports/index.php" class="ct-btn ct-btn-secondary ct-btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        View Reports
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       ROLE 1: ADMINISTRATOR DASHBOARD
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php if ($role === ROLE_ADMIN): ?>
  
  <div class="ct-stat-grid" style="grid-template-columns: repeat(6, 1fr);margin-bottom:24px;">
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(79,126,248,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f7ef8" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statInv ?></div>
      <div class="ct-stat-label">Total Investigations</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(46,204,113,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statItems ?></div>
      <div class="ct-stat-label">Controlled Items</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(52,152,219,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3498db" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statUsers ?></div>
      <div class="ct-stat-label">Active Users</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(243,156,18,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statPendingTfr ?></div>
      <div class="ct-stat-label">Pending Transfers</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(124,92,191,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statUnderExam ?></div>
      <div class="ct-stat-label">Under Examination</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(108,117,125,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8892aa" stroke-width="2"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statArchived ?></div>
      <div class="ct-stat-label">Archived / Closed</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <!-- Recent Item Registrations -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        Recent Item Registrations
        <a href="/items/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">All Items</a>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item Name</th><th>Category</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recentRegistrations as $it): ?>
            <tr>
              <td><a href="/items/item_details.php?id=<?= $it['id'] ?>" class="ref-code"><?= esc($it['item_reference']) ?></a></td>
              <td><?= esc(truncate($it['item_name'], 30)) ?></td>
              <td class="small text-muted"><?= esc($it['cat_name']) ?></td>
              <td><?= dashBadge($it['status_name'], $it['color_badge']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentRegistrations)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No items recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Transfers -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
        Recent Custody Transfers
        <a href="/transfers/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">All Transfers</a>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>From › To</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentTransfers as $tr): ?>
            <tr>
              <td><a href="/transfers/view.php?id=<?= $tr['id'] ?>" class="ref-code"><?= esc($tr['transfer_reference']) ?></a></td>
              <td class="small"><?= esc($tr['from_user'] ?? '—') ?> › <strong><?= esc($tr['to_user']) ?></strong></td>
              <td><?= dashTfrBadge($tr['transfer_status']) ?></td>
              <td class="small text-muted"><?= fmtDt($tr['initiated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTransfers)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No custody transfers recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent System Activity (Audit Log) -->
  <div class="ct-card">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Recent Security & Audit Trail
      <a href="/views/audit/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">Full Audit Log</a>
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead><tr><th>Timestamp</th><th>Action</th><th>Actor</th><th>IP Address</th><th>Details</th></tr></thead>
        <tbody>
          <?php foreach ($recentActivity as $al): ?>
          <tr>
            <td class="small text-muted"><?= fmtDt($al['created_at']) ?></td>
            <td><span class="badge bg-secondary font-mono"><?= esc($al['action']) ?></span></td>
            <td class="small font-medium"><?= esc($al['actor_name'] ?? 'System') ?></td>
            <td class="small mono text-muted"><?= esc($al['ip_address'] ?? '—') ?></td>
            <td class="small"><?= esc($al['description'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       ROLE 2: INVESTIGATOR DASHBOARD
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($role === ROLE_INVESTIGATOR): ?>

  <?php if (!empty($incomingTransfers)): ?>
  <div class="ct-alert ct-alert-warning mb-4" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <strong>Notice:</strong> You have <strong><?= count($incomingTransfers) ?></strong> incoming custody transfer(s) awaiting your formal confirmation.
    </div>
    <a href="/transfers/incoming.php" class="ct-btn ct-btn-primary ct-btn-sm">Review Pending Transfers</a>
  </div>
  <?php endif; ?>

  <div class="ct-stat-grid" style="grid-template-columns: repeat(4, 1fr);margin-bottom:24px;">
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(79,126,248,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f7ef8" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($myInvestigations) ?></div>
      <div class="ct-stat-label">My Investigations</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(46,204,113,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($myCustodyItems) ?></div>
      <div class="ct-stat-label">Items in My Custody</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(243,156,18,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($incomingTransfers) ?></div>
      <div class="ct-stat-label">Pending Incoming</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(124,92,191,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($myTransfers) ?></div>
      <div class="ct-stat-label">Transfers Initiated</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <!-- Items in My Custody -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
        Evidence in My Custody
        <span class="badge bg-secondary" style="margin-left:8px;"><?= count($myCustodyItems) ?></span>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item Name</th><th>Location</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($myCustodyItems as $ci): ?>
            <tr>
              <td><a href="/items/item_details.php?id=<?= $ci['id'] ?>" class="ref-code"><?= esc($ci['item_reference']) ?></a></td>
              <td><?= esc(truncate($ci['item_name'], 25)) ?></td>
              <td class="small text-muted"><?= esc($ci['location_name'] ?? '—') ?></td>
              <td>
                <a href="/transfers/initiate.php?item_id=<?= $ci['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:2px 8px;font-size:0.72rem;">Transfer</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($myCustodyItems)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No controlled items currently in your custody.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- My Investigations -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        My Assigned Cases
        <a href="/investigations/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">All Cases</a>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Case Ref</th><th>Title</th><th>Items</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($myInvestigations as $inv): ?>
            <tr>
              <td><a href="/investigations/view.php?id=<?= $inv['id'] ?>" class="ref-code"><?= esc($inv['inv_reference']) ?></a></td>
              <td><?= esc(truncate($inv['title'], 28)) ?></td>
              <td><span class="badge bg-secondary"><?= $inv['item_count'] ?></span></td>
              <td><span class="badge bg-<?= $inv['status'] === 'open' ? 'success' : 'secondary' ?>"><?= ucfirst(esc($inv['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($myInvestigations)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No investigations assigned yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Accessible Investigation Items -->
  <div class="ct-card">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
      Evidence in My Active Cases
      <a href="/items/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">All Items</a>
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead><tr><th>Reference</th><th>Item Name</th><th>Case</th><th>Current Custodian</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($accessibleItems as $it): ?>
          <tr>
            <td><a href="/items/item_details.php?id=<?= $it['id'] ?>" class="ref-code"><?= esc($it['item_reference']) ?></a></td>
            <td><?= esc(truncate($it['item_name'], 32)) ?></td>
            <td class="mono small"><?= esc($it['inv_reference']) ?></td>
            <td class="small"><?= esc($it['custodian_name'] ?? '—') ?></td>
            <td><?= dashBadge($it['status_name'], $it['color_badge']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($accessibleItems)): ?>
          <tr><td colspan="5" class="text-center text-muted p-4">No evidence items found for your cases.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       ROLE 3: EVIDENCE CUSTODIAN DASHBOARD
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($role === ROLE_CUSTODIAN): ?>

  <?php if (!empty($incomingPending)): ?>
  <div class="ct-alert ct-alert-warning mb-4" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <strong>Urgent Action Required:</strong> You have <strong><?= count($incomingPending) ?></strong> incoming transfer(s) awaiting vault receipt.
    </div>
    <a href="/transfers/incoming.php" class="ct-btn ct-btn-primary ct-btn-sm">Review & Receive</a>
  </div>
  <?php endif; ?>

  <div class="ct-stat-grid" style="grid-template-columns: repeat(4, 1fr);margin-bottom:24px;">
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(46,204,113,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($custodyItems) ?></div>
      <div class="ct-stat-label">Items in My Custody</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(243,156,18,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($incomingPending) ?></div>
      <div class="ct-stat-label">Incoming to Confirm</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(79,126,248,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f7ef8" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($outgoingPending) ?></div>
      <div class="ct-stat-label">Pending Outgoing</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(124,92,191,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($recentlyReceived) ?></div>
      <div class="ct-stat-label">Recently Received</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <!-- Items in My Custody -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
        Vault Items Currently in Custody
        <span class="badge bg-secondary" style="margin-left:8px;"><?= count($custodyItems) ?></span>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item Name</th><th>Location</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($custodyItems as $ci): ?>
            <tr>
              <td><a href="/items/item_details.php?id=<?= $ci['id'] ?>" class="ref-code"><?= esc($ci['item_reference']) ?></a></td>
              <td><?= esc(truncate($ci['item_name'], 28)) ?></td>
              <td class="small text-muted"><?= esc($ci['location_name'] ?? '—') ?></td>
              <td>
                <a href="/transfers/initiate.php?item_id=<?= $ci['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:2px 8px;font-size:0.72rem;">Transfer</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($custodyItems)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No items currently in custody.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Incoming Pending Transfers -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Incoming Transfers Awaiting Receipt
        <a href="/transfers/incoming.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">Pending Queue</a>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item</th><th>From</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($incomingPending as $ip): ?>
            <tr>
              <td><a href="/transfers/confirm.php?id=<?= $ip['id'] ?>&action=confirm" class="ref-code"><?= esc($ip['transfer_reference']) ?></a></td>
              <td><?= esc(truncate($ip['item_name'], 24)) ?></td>
              <td class="small"><?= esc($ip['from_user'] ?? '—') ?></td>
              <td>
                <a href="/transfers/confirm.php?id=<?= $ip['id'] ?>&action=confirm" class="ct-btn ct-btn-primary" style="padding:2px 8px;font-size:0.72rem;">Confirm</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($incomingPending)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No incoming transfers pending confirmation.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recently Received Items -->
  <div class="ct-card">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Recently Received Items
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead><tr><th>Transfer Ref</th><th>Item Ref</th><th>Item Name</th><th>Transferred From</th><th>Received Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentlyReceived as $rr): ?>
          <tr>
            <td><a href="/transfers/view.php?id=<?= $rr['id'] ?>" class="ref-code"><?= esc($rr['transfer_reference']) ?></a></td>
            <td><a href="/items/item_details.php?id=<?= $rr['item_id'] ?>" class="ref-code"><?= esc($rr['item_reference']) ?></a></td>
            <td><?= esc($rr['item_name']) ?></td>
            <td class="small"><?= esc($rr['from_user'] ?? '—') ?></td>
            <td class="small text-muted"><?= fmtDt($rr['confirmed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentlyReceived)): ?>
          <tr><td colspan="5" class="text-center text-muted p-4">No recently received transfers.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       ROLE 4: FORENSIC ANALYST DASHBOARD
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($role === ROLE_ANALYST): ?>

  <div class="ct-stat-grid" style="grid-template-columns: repeat(3, 1fr);margin-bottom:24px;">
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(46,204,113,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($assignedItems) ?></div>
      <div class="ct-stat-label">Assigned in My Custody</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(124,92,191,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($underExamItems) ?></div>
      <div class="ct-stat-label">Items Under Examination</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(79,126,248,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f7ef8" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="ct-stat-value"><?= count($recentlyCompleted) ?></div>
      <div class="ct-stat-label">Examinations Completed</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <!-- Items in My Custody (Ready for Analysis) -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
        Evidence in My Custody
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item Name</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($assignedItems as $ai): ?>
            <tr>
              <td><a href="/items/item_details.php?id=<?= $ai['id'] ?>" class="ref-code"><?= esc($ai['item_reference']) ?></a></td>
              <td><?= esc(truncate($ai['item_name'], 25)) ?></td>
              <td><?= dashBadge($ai['status_name'], $ai['color_badge']) ?></td>
              <td>
                <a href="/items/item_details.php?id=<?= $ai['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:2px 8px;font-size:0.72rem;">Manage Status</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($assignedItems)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No evidence currently in your custody.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Active Examinations Across Lab -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
        Active Examinations in Lab
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>Item Name</th><th>Custodian</th><th>Location</th></tr></thead>
          <tbody>
            <?php foreach ($underExamItems as $ue): ?>
            <tr>
              <td><a href="/items/item_details.php?id=<?= $ue['id'] ?>" class="ref-code"><?= esc($ue['item_reference']) ?></a></td>
              <td><?= esc(truncate($ue['item_name'], 25)) ?></td>
              <td class="small"><?= esc($ue['custodian_name'] ?? '—') ?></td>
              <td class="small text-muted"><?= esc($ue['location_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($underExamItems)): ?>
            <tr><td colspan="4" class="text-center text-muted p-4">No items currently under examination.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recently Completed Examinations -->
  <div class="ct-card">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Recently Completed Examinations
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead><tr><th>Item Reference</th><th>Item Name</th><th>Examiner</th><th>Completed Date</th><th>Findings / Remarks</th></tr></thead>
        <tbody>
          <?php foreach ($recentlyCompleted as $rc): ?>
          <tr>
            <td><a href="/items/item_details.php?id=<?= $rc['item_id'] ?>" class="ref-code"><?= esc($rc['item_reference']) ?></a></td>
            <td><?= esc($rc['item_name']) ?></td>
            <td class="small"><?= esc($rc['examiner_name']) ?></td>
            <td class="small text-muted"><?= fmtDt($rc['changed_at']) ?></td>
            <td class="small"><?= esc($rc['reason'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentlyCompleted)): ?>
          <tr><td colspan="5" class="text-center text-muted p-4">No completed examinations recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       ROLE 5: AUDITOR DASHBOARD (READ-ONLY)
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($role === ROLE_AUDITOR): ?>

  <div class="ct-stat-grid" style="grid-template-columns: repeat(4, 1fr);margin-bottom:24px;">
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(79,126,248,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f7ef8" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statInv ?></div>
      <div class="ct-stat-label">Total Investigations</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(46,204,113,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statItems ?></div>
      <div class="ct-stat-label">Controlled Items</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(243,156,18,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statTransfers ?></div>
      <div class="ct-stat-label">Custody Transfers</div>
    </div>
    <div class="ct-stat">
      <div class="ct-stat-icon" style="background:rgba(124,92,191,.15);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div class="ct-stat-value"><?= $statAuditLogs ?></div>
      <div class="ct-stat-label">Audit Log Entries</div>
    </div>
  </div>

  <!-- Audit Reports Direct Link Grid -->
  <div class="ct-card" style="margin-bottom:22px;background:var(--ct-surface2);">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Compliance & Audit Reports (Read-Only)
    </div>
    <div class="ct-card-body" style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;">
      <a href="/views/reports/index.php?tab=custody" class="ct-btn ct-btn-secondary" style="display:flex;flex-direction:column;align-items:flex-start;padding:14px;gap:6px;text-align:left;">
        <strong style="color:var(--ct-accent);">1. Chain-of-Custody</strong>
        <span class="small text-muted">Complete item lifecycle audit</span>
      </a>
      <a href="/views/reports/index.php?tab=investigation" class="ct-btn ct-btn-secondary" style="display:flex;flex-direction:column;align-items:flex-start;padding:14px;gap:6px;text-align:left;">
        <strong style="color:var(--ct-accent);">2. Case Items Report</strong>
        <span class="small text-muted">Evidence grouped by investigation</span>
      </a>
      <a href="/views/reports/index.php?tab=pending" class="ct-btn ct-btn-secondary" style="display:flex;flex-direction:column;align-items:flex-start;padding:14px;gap:6px;text-align:left;">
        <strong style="color:var(--ct-accent);">3. Pending Transfers</strong>
        <span class="small text-muted">Active in-transit transfer queue</span>
      </a>
      <a href="/views/reports/index.php?tab=status" class="ct-btn ct-btn-secondary" style="display:flex;flex-direction:column;align-items:flex-start;padding:14px;gap:6px;text-align:left;">
        <strong style="color:var(--ct-accent);">4. Item Status Report</strong>
        <span class="small text-muted">Inventory filter by status category</span>
      </a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;">
    <!-- Recent Custody Activity -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/></svg>
        Recent Custody Activity (Transfer Stream)
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Reference</th><th>From › To</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentCustodyActivity as $ca): ?>
            <tr>
              <td><a href="/transfers/view.php?id=<?= $ca['id'] ?>" class="ref-code"><?= esc($ca['transfer_reference']) ?></a></td>
              <td class="small"><?= esc($ca['from_user'] ?? '—') ?> › <?= esc($ca['to_user']) ?></td>
              <td><?= dashTfrBadge($ca['transfer_status']) ?></td>
              <td class="small text-muted"><?= fmtDt($ca['initiated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Audit Events -->
    <div class="ct-card">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        System Audit Events
        <a href="/views/audit/index.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="margin-left:auto;">All Logs</a>
      </div>
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead><tr><th>Timestamp</th><th>Action</th><th>Actor</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($recentAuditEvents as $ae): ?>
            <tr>
              <td class="small text-muted"><?= fmtDt($ae['created_at']) ?></td>
              <td><span class="badge bg-secondary font-mono"><?= esc($ae['action']) ?></span></td>
              <td class="small"><?= esc($ae['actor_name'] ?? 'System') ?></td>
              <td class="small"><?= esc($ae['description'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
