<?php
// =============================================================================
// ChainTrack — Reports Module
// views/reports/index.php
// Accessible to Administrator and Auditor (Read-Only)
// =============================================================================
require_once __DIR__ . '/../../core/bootstrap.php';
require_role(ROLE_ADMIN, ROLE_AUDITOR);

$pdo = getDB();

// Determine active tab
$tab = input('tab', input('report', 'custody'));
if (!in_array($tab, ['custody', 'investigation', 'pending', 'status'], true)) {
    $tab = 'custody';
}

function repBadge(string $name, string $color = 'secondary'): string {
    return '<span class="badge bg-' . htmlspecialchars($color, ENT_QUOTES) . '">'
         . htmlspecialchars($name, ENT_QUOTES) . '</span>';
}

function repTfrBadge(string $status): string {
    $colors = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'initiated' => 'secondary'];
    $c = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst(htmlspecialchars($status, ENT_QUOTES)) . '</span>';
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. CHAIN-OF-CUSTODY REPORT DATA
// ─────────────────────────────────────────────────────────────────────────────
$allItems = $pdo->query(
    "SELECT id, item_reference, item_name 
     FROM items 
     ORDER BY item_reference ASC"
)->fetchAll();

$selectedItemId = (int)input('item_id', $allItems[0]['id'] ?? 0);
$selectedItem   = null;
$custodyEvents  = [];
$itemTransfers  = [];
$statusLogs     = [];

if ($selectedItemId > 0) {
    // Current item details
    $stmt = $pdo->prepare(
        "SELECT i.*, 
                inv.inv_reference, inv.title AS inv_title,
                ic.cat_name,
                ist.status_name, ist.color_badge,
                cu.full_name AS custodian_name, cu.employee_id AS custodian_emp_id,
                cl.location_name AS current_location_name,
                ru.full_name AS registered_by_name
         FROM items i
         JOIN investigations inv ON inv.id = i.investigation_id
         JOIN item_categories ic ON ic.id = i.category_id
         JOIN item_statuses ist  ON ist.id = i.current_status_id
         LEFT JOIN users cu      ON cu.id = i.current_custodian_id
         LEFT JOIN locations cl  ON cl.id = i.current_location_id
         LEFT JOIN users ru      ON ru.id = i.registered_by
         WHERE i.id = ?"
    );
    $stmt->execute([$selectedItemId]);
    $selectedItem = $stmt->fetch();

    if ($selectedItem) {
        // Custody history timeline (immutable facts)
        $stmtCh = $pdo->prepare(
            "SELECT ch.*,
                    fu.full_name AS from_user_name,
                    tu.full_name AS to_user_name,
                    fl.location_name AS from_loc_name,
                    tl.location_name AS to_loc_name,
                    ru.full_name AS recorded_by_name
             FROM custody_history ch
             LEFT JOIN users fu     ON fu.id = ch.from_user_id
             JOIN users tu          ON tu.id = ch.to_user_id
             LEFT JOIN locations fl ON fl.id = ch.from_location_id
             LEFT JOIN locations tl ON tl.id = ch.to_location_id
             LEFT JOIN users ru     ON ru.id = ch.recorded_by
             WHERE ch.item_id = ?
             ORDER BY ch.recorded_at ASC, ch.custody_history_id ASC"
        );
        $stmtCh->execute([$selectedItemId]);
        $custodyEvents = $stmtCh->fetchAll();

        // Custody transfer history
        $stmtTr = $pdo->prepare(
            "SELECT ct.*,
                    fu.full_name AS from_user_name,
                    tu.full_name AS to_user_name,
                    fl.location_name AS from_loc_name,
                    tl.location_name AS to_loc_name,
                    iu.full_name AS initiated_by_name,
                    cb.full_name AS confirmed_by_name
             FROM custody_transfers ct
             LEFT JOIN users fu     ON fu.id = ct.from_user_id
             JOIN users tu          ON tu.id = ct.to_user_id
             LEFT JOIN locations fl ON fl.id = ct.from_location_id
             LEFT JOIN locations tl ON tl.id = ct.to_location_id
             LEFT JOIN users iu     ON iu.id = ct.initiated_by
             LEFT JOIN users cb     ON cb.id = ct.confirmed_by
             WHERE ct.item_id = ?
             ORDER BY ct.initiated_at ASC"
        );
        $stmtTr->execute([$selectedItemId]);
        $itemTransfers = $stmtTr->fetchAll();

        // Status history
        $stmtSt = $pdo->prepare(
            "SELECT ish.*,
                    ps.status_name AS prev_status_name, ps.color_badge AS prev_color,
                    ns.status_name AS new_status_name, ns.color_badge AS new_color,
                    u.full_name AS changed_by_name
             FROM item_status_history ish
             LEFT JOIN item_statuses ps ON ps.id = ish.previous_status_id
             JOIN item_statuses ns      ON ns.id = ish.new_status_id
             JOIN users u               ON u.id = ish.changed_by
             WHERE ish.item_id = ?
             ORDER BY ish.changed_at ASC, ish.id ASC"
        );
        $stmtSt->execute([$selectedItemId]);
        $statusLogs = $stmtSt->fetchAll();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. INVESTIGATION ITEMS REPORT DATA
// ─────────────────────────────────────────────────────────────────────────────
$allInvestigations = $pdo->query(
    "SELECT id, inv_reference, title 
     FROM investigations 
     ORDER BY inv_reference ASC"
)->fetchAll();

$selectedInvId = (int)input('inv_id', $allInvestigations[0]['id'] ?? 0);
$selectedInv   = null;
$invItems      = [];

if ($selectedInvId > 0) {
    $stmtInv = $pdo->prepare(
        "SELECT inv.*, d.dept_name, u.full_name AS lead_name 
         FROM investigations inv 
         LEFT JOIN departments d ON d.id = inv.department_id 
         LEFT JOIN users u       ON u.id = inv.lead_user_id 
         WHERE inv.id = ?"
    );
    $stmtInv->execute([$selectedInvId]);
    $selectedInv = $stmtInv->fetch();

    if ($selectedInv) {
        $stmtItems = $pdo->prepare(
            "SELECT i.*, 
                    ic.cat_name, 
                    ist.status_name, ist.color_badge,
                    cu.full_name AS custodian_name,
                    cl.location_name
             FROM items i
             JOIN item_categories ic ON ic.id = i.category_id
             JOIN item_statuses ist  ON ist.id = i.current_status_id
             LEFT JOIN users cu      ON cu.id = i.current_custodian_id
             LEFT JOIN locations cl  ON cl.id = i.current_location_id
             WHERE i.investigation_id = ?
             ORDER BY i.item_reference ASC"
        );
        $stmtItems->execute([$selectedInvId]);
        $invItems = $stmtItems->fetchAll();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. PENDING TRANSFERS REPORT DATA
// ─────────────────────────────────────────────────────────────────────────────
$pendingTransfers = $pdo->query(
    "SELECT ct.*,
            i.item_reference, i.item_name,
            fu.full_name AS from_user_name,
            tu.full_name AS to_user_name,
            tl.location_name AS to_location_name,
            iu.full_name AS initiated_by_name
     FROM custody_transfers ct
     JOIN items i           ON i.id = ct.item_id
     LEFT JOIN users fu     ON fu.id = ct.from_user_id
     JOIN users tu          ON tu.id = ct.to_user_id
     LEFT JOIN locations tl ON tl.id = ct.to_location_id
     LEFT JOIN users iu     ON iu.id = ct.initiated_by
     WHERE ct.transfer_status = 'pending'
     ORDER BY ct.initiated_at ASC"
)->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// 4. ITEM STATUS REPORT DATA
// ─────────────────────────────────────────────────────────────────────────────
$allStatuses = $pdo->query(
    "SELECT id, status_name, status_slug, color_badge 
     FROM item_statuses 
     WHERE is_active = 1 
     ORDER BY sort_order ASC, status_name ASC"
)->fetchAll();

$statusFilter = input('status_id', 'all');
$statusSql = "SELECT i.*, 
                     inv.inv_reference, inv.title AS inv_title,
                     ic.cat_name,
                     ist.status_name, ist.color_badge,
                     cu.full_name AS custodian_name,
                     cl.location_name
              FROM items i
              JOIN investigations inv ON inv.id = i.investigation_id
              JOIN item_categories ic ON ic.id = i.category_id
              JOIN item_statuses ist  ON ist.id = i.current_status_id
              LEFT JOIN users cu      ON cu.id = i.current_custodian_id
              LEFT JOIN locations cl  ON cl.id = i.current_location_id";

$statusParams = [];
if ($statusFilter !== 'all' && (int)$statusFilter > 0) {
    $statusSql .= " WHERE i.current_status_id = ?";
    $statusParams[] = (int)$statusFilter;
}
$statusSql .= " ORDER BY ist.sort_order ASC, i.item_reference ASC";

$stmtStatusItems = $pdo->prepare($statusSql);
$stmtStatusItems->execute($statusParams);
$statusItems = $stmtStatusItems->fetchAll();

// Counts per status
$statusCounts = $pdo->query(
    "SELECT ist.id, ist.status_name, ist.color_badge, COUNT(i.id) AS cnt
     FROM item_statuses ist
     LEFT JOIN items i ON i.current_status_id = ist.id AND i.is_archived = 0
     GROUP BY ist.id
     ORDER BY ist.sort_order ASC"
)->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../layouts/header.php';
?>
<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<div id="ct-main">
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>
<div id="ct-content">

  <!-- Header and Print Button -->
  <div class="d-flex justify-between align-center mb-3">
    <div>
      <h2 style="margin:0;font-size:1.35rem;">Compliance & Chain-of-Custody Reports</h2>
      <span class="small text-muted">Auditable database-driven records for academic evaluation and legal oversight</span>
    </div>
    <div>
      <button type="button" class="ct-btn ct-btn-secondary ct-btn-sm" onclick="window.print();">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 6 2 18 2 18 9"></polyline>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
          <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        Print / Export PDF
      </button>
    </div>
  </div>

  <!-- Report Navigation Tabs -->
  <div class="d-flex gap-2 mb-4" style="border-bottom:1px solid var(--ct-border);padding-bottom:12px;">
    <a href="?tab=custody" class="ct-btn <?= $tab === 'custody' ? 'ct-btn-primary' : 'ct-btn-secondary' ?>">
      1. Chain-of-Custody Report
    </a>
    <a href="?tab=investigation" class="ct-btn <?= $tab === 'investigation' ? 'ct-btn-primary' : 'ct-btn-secondary' ?>">
      2. Case Items Report
    </a>
    <a href="?tab=pending" class="ct-btn <?= $tab === 'pending' ? 'ct-btn-primary' : 'ct-btn-secondary' ?>">
      3. Pending Transfers Report
    </a>
    <a href="?tab=status" class="ct-btn <?= $tab === 'status' ? 'ct-btn-primary' : 'ct-btn-secondary' ?>">
      4. Item Status Report
    </a>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       TAB 1: COMPLETE CHAIN-OF-CUSTODY REPORT
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php if ($tab === 'custody'): ?>
  
  <div class="ct-card mb-4">
    <div class="ct-card-header">Select Controlled Item to Audit</div>
    <div class="ct-card-body">
      <form method="GET" action="" style="display:flex;gap:14px;align-items:flex-end;">
        <input type="hidden" name="tab" value="custody">
        <div style="flex:1;">
          <label class="ct-form-label">Controlled Evidence Item</label>
          <select name="item_id" class="ct-form-select" onchange="this.form.submit()">
            <?php foreach ($allItems as $it): ?>
            <option value="<?= $it['id'] ?>" <?= $it['id'] == $selectedItemId ? 'selected' : '' ?>>
              <?= esc($it['item_reference']) ?> — <?= esc($it['item_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="ct-btn ct-btn-secondary">Generate Report</button>
      </form>
    </div>
  </div>

  <?php if ($selectedItem): ?>
  <!-- Item Overview Card -->
  <div class="ct-card mb-4" style="border-left:4px solid var(--ct-accent);">
    <div class="ct-card-header">
      <span>Evidence Dossier: <strong class="mono text-accent"><?= esc($selectedItem['item_reference']) ?></strong></span>
      <span class="badge bg-secondary" style="margin-left:auto;"><?= esc($selectedItem['cat_name']) ?></span>
    </div>
    <div class="ct-card-body">
      <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;">
        <div>
          <div class="ct-detail-label">Item Name</div>
          <div class="fw-600"><?= esc($selectedItem['item_name']) ?></div>
        </div>
        <div>
          <div class="ct-detail-label">Investigation Case</div>
          <div class="mono"><?= esc($selectedItem['inv_reference']) ?></div>
          <div class="small text-muted"><?= esc($selectedItem['inv_title']) ?></div>
        </div>
        <div>
          <div class="ct-detail-label">Current Custodian</div>
          <div class="fw-600"><?= esc($selectedItem['custodian_name'] ?? '—') ?></div>
          <?php if ($selectedItem['custodian_emp_id']): ?>
          <div class="small text-muted"><?= esc($selectedItem['custodian_emp_id']) ?></div>
          <?php endif; ?>
        </div>
        <div>
          <div class="ct-detail-label">Current Location & Status</div>
          <div><?= esc($selectedItem['current_location_name'] ?? '—') ?></div>
          <div><?= repBadge($selectedItem['status_name'], $selectedItem['color_badge']) ?></div>
        </div>
      </div>
      <?php if ($selectedItem['description'] || $selectedItem['physical_description']): ?>
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--ct-border);font-size:0.85rem;">
        <strong>Description:</strong> <?= esc($selectedItem['description'] ?: $selectedItem['physical_description']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 1. Custody History Timeline -->
  <div class="ct-card mb-4">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Chronological Chain of Custody Timeline (Permanent Audit Table: <code>custody_history</code>)
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Event #</th>
            <th>Date & Time</th>
            <th>Event Type</th>
            <th>Released By (Sender)</th>
            <th>Received By (Custodian)</th>
            <th>Vault / Location</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($custodyEvents as $idx => $ev): ?>
          <tr>
            <td class="mono font-semibold">#<?= $idx + 1 ?></td>
            <td class="small text-muted"><?= fmtDt($ev['recorded_at']) ?></td>
            <td>
              <?php if ($ev['action_type'] === 'initial_assignment'): ?>
                <span class="badge bg-success">Initial Registration</span>
              <?php else: ?>
                <span class="badge bg-primary">Custody Transfer</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= esc($ev['from_user_name'] ?? '— (Origin)') ?></td>
            <td class="fw-600"><?= esc($ev['to_user_name']) ?></td>
            <td class="small text-muted"><?= esc($ev['to_loc_name'] ?? '—') ?></td>
            <td class="small"><?= esc($ev['remarks'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($custodyEvents)): ?>
          <tr><td colspan="7" class="text-center text-muted p-4">No custody history records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2. Transfer History Table -->
  <div class="ct-card mb-4">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/></svg>
      Transfer Workflow History (<code>custody_transfers</code>)
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Transfer Ref</th>
            <th>Sender</th>
            <th>Receiver</th>
            <th>Routing (Location)</th>
            <th>Initiated Date</th>
            <th>Confirmed Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($itemTransfers as $tr): ?>
          <tr>
            <td class="ref-code"><?= esc($tr['transfer_reference']) ?></td>
            <td><?= esc($tr['from_user_name'] ?? '—') ?></td>
            <td class="fw-600"><?= esc($tr['to_user_name']) ?></td>
            <td class="small text-muted"><?= esc($tr['from_loc_name'] ?? '—') ?> › <?= esc($tr['to_loc_name'] ?? '—') ?></td>
            <td class="small"><?= fmtDt($tr['initiated_at']) ?></td>
            <td class="small"><?= fmtDt($tr['confirmed_at']) ?></td>
            <td><?= repTfrBadge($tr['transfer_status']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($itemTransfers)): ?>
          <tr><td colspan="7" class="text-center text-muted p-4">No transfer workflows initiated.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 3. Status Change History -->
  <div class="ct-card mb-4">
    <div class="ct-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Status Transition History (<code>item_status_history</code>)
    </div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Previous Status</th>
            <th>New Status</th>
            <th>Changed By</th>
            <th>Reason / Justification</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($statusLogs as $sl): ?>
          <tr>
            <td class="small text-muted"><?= fmtDt($sl['changed_at']) ?></td>
            <td><?= $sl['prev_status_name'] ? repBadge($sl['prev_status_name'], $sl['prev_color']) : '<span class="text-muted small">— None (Initial)</span>' ?></td>
            <td><?= repBadge($sl['new_status_name'], $sl['new_color']) ?></td>
            <td class="small"><?= esc($sl['changed_by_name']) ?></td>
            <td class="small"><?= esc($sl['reason'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($statusLogs)): ?>
          <tr><td colspan="5" class="text-center text-muted p-4">No status change records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       TAB 2: INVESTIGATION ITEMS REPORT
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'investigation'): ?>

  <div class="ct-card mb-4">
    <div class="ct-card-header">Select Investigation Case</div>
    <div class="ct-card-body">
      <form method="GET" action="" style="display:flex;gap:14px;align-items:flex-end;">
        <input type="hidden" name="tab" value="investigation">
        <div style="flex:1;">
          <label class="ct-form-label">Investigation</label>
          <select name="inv_id" class="ct-form-select" onchange="this.form.submit()">
            <?php foreach ($allInvestigations as $iv): ?>
            <option value="<?= $iv['id'] ?>" <?= $iv['id'] == $selectedInvId ? 'selected' : '' ?>>
              <?= esc($iv['inv_reference']) ?> — <?= esc($iv['title']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="ct-btn ct-btn-secondary">Generate Case Report</button>
      </form>
    </div>
  </div>

  <?php if ($selectedInv): ?>
  <div class="ct-card mb-4" style="border-left:4px solid var(--ct-accent2);">
    <div class="ct-card-header">
      <span>Case Summary: <strong class="mono text-accent"><?= esc($selectedInv['inv_reference']) ?></strong></span>
      <span class="badge bg-<?= $selectedInv['status'] === 'open' ? 'success' : 'secondary' ?>" style="margin-left:auto;">
        <?= ucfirst(esc($selectedInv['status'])) ?>
      </span>
    </div>
    <div class="ct-card-body">
      <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;">
        <div>
          <div class="ct-detail-label">Case Title</div>
          <div class="fw-600"><?= esc($selectedInv['title']) ?></div>
        </div>
        <div>
          <div class="ct-detail-label">Lead Investigator</div>
          <div><?= esc($selectedInv['lead_name'] ?? '—') ?></div>
        </div>
        <div>
          <div class="ct-detail-label">Department</div>
          <div><?= esc($selectedInv['dept_name'] ?? '—') ?></div>
        </div>
        <div>
          <div class="ct-detail-label">Total Linked Evidence Items</div>
          <div class="fw-600" style="font-size:1.1rem;color:var(--ct-accent);"><?= count($invItems) ?> item(s)</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ct-card">
    <div class="ct-card-header">Evidence Inventory Associated With Case</div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Item Reference</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Current Custodian</th>
            <th>Current Location</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invItems as $it): ?>
          <tr>
            <td><a href="/items/item_details.php?id=<?= $it['id'] ?>" class="ref-code"><?= esc($it['item_reference']) ?></a></td>
            <td><?= esc($it['item_name']) ?></td>
            <td class="small text-muted"><?= esc($it['cat_name']) ?></td>
            <td class="fw-600 small"><?= esc($it['custodian_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= esc($it['location_name'] ?? '—') ?></td>
            <td><?= repBadge($it['status_name'], $it['color_badge']) ?></td>
            <td>
              <a href="/items/item_details.php?id=<?= $it['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:2px 8px;font-size:0.75rem;">View Dossier</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($invItems)): ?>
          <tr><td colspan="7" class="text-center text-muted p-4">No evidence items registered under this investigation yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       TAB 3: PENDING TRANSFERS REPORT
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'pending'): ?>

  <div class="ct-card mb-4" style="border-left:4px solid var(--ct-warning);">
    <div class="ct-card-header">
      Active Custody Transfers in Transit / Awaiting Acceptance
      <span class="badge bg-warning" style="margin-left:auto;"><?= count($pendingTransfers) ?> Transfer(s) Pending</span>
    </div>
    <div class="ct-card-body small text-muted">
      This report tracks all evidence items currently in transit. The initiating custodian remains legally responsible until the intended recipient formally inspects and accepts custody.
    </div>
  </div>

  <div class="ct-card">
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Transfer Ref</th>
            <th>Item</th>
            <th>Sender (Current Custodian)</th>
            <th>Recipient (Pending)</th>
            <th>Destination Vault</th>
            <th>Initiated Date</th>
            <th>Reason / Justification</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingTransfers as $pt): ?>
          <tr>
            <td class="ref-code"><?= esc($pt['transfer_reference']) ?></td>
            <td>
              <a href="/items/item_details.php?id=<?= $pt['item_id'] ?>" class="ref-code" style="font-size:0.75rem;"><?= esc($pt['item_reference']) ?></a>
              <div class="small"><?= esc(truncate($pt['item_name'], 25)) ?></div>
            </td>
            <td class="small"><?= esc($pt['from_user_name'] ?? '—') ?></td>
            <td class="fw-600 small"><?= esc($pt['to_user_name']) ?></td>
            <td class="small text-muted"><?= esc($pt['to_location_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= fmtDt($pt['initiated_at']) ?></td>
            <td class="small"><?= esc(truncate($pt['reason'], 40)) ?></td>
            <td>
              <a href="/transfers/view.php?id=<?= $pt['id'] ?>" class="ct-btn ct-btn-secondary" style="padding:2px 8px;font-size:0.75rem;">Inspect</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pendingTransfers)): ?>
          <tr><td colspan="8" class="text-center text-muted p-4">No custody transfers currently pending in the system.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       TAB 4: ITEM STATUS REPORT
       ═══════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'status'): ?>

  <div class="ct-card mb-4">
    <div class="ct-card-header">Filter by Controlled Item Status</div>
    <div class="ct-card-body">
      <form method="GET" action="" style="display:flex;gap:14px;align-items:flex-end;">
        <input type="hidden" name="tab" value="status">
        <div style="flex:1;">
          <label class="ct-form-label">Lifecycle Status</label>
          <select name="status_id" class="ct-form-select" onchange="this.form.submit()">
            <option value="all">All Statuses (Full Inventory)</option>
            <?php foreach ($allStatuses as $st): ?>
            <option value="<?= $st['id'] ?>" <?= (string)$st['id'] === (string)$statusFilter ? 'selected' : '' ?>>
              <?= esc($st['status_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="ct-btn ct-btn-secondary">Apply Filter</button>
      </form>

      <!-- Status count pills -->
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid var(--ct-border);">
        <a href="?tab=status&status_id=all" class="badge bg-<?= $statusFilter === 'all' ? 'primary' : 'secondary' ?>" style="text-decoration:none;padding:6px 12px;">
          All: <?= count($statusItems) ?>
        </a>
        <?php foreach ($statusCounts as $sc): ?>
        <a href="?tab=status&status_id=<?= $sc['id'] ?>" class="badge bg-<?= (string)$sc['id'] === (string)$statusFilter ? 'primary' : 'secondary' ?>" style="text-decoration:none;padding:6px 12px;">
          <?= esc($sc['status_name']) ?>: <?= $sc['cnt'] ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="ct-card">
    <div class="ct-card-header">Item Status Inventory Overview (<?= count($statusItems) ?> items)</div>
    <div class="ct-table-wrap">
      <table class="ct-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Item Name</th>
            <th>Investigation</th>
            <th>Category</th>
            <th>Current Custodian</th>
            <th>Location</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($statusItems as $si): ?>
          <tr>
            <td><a href="/items/item_details.php?id=<?= $si['id'] ?>" class="ref-code"><?= esc($si['item_reference']) ?></a></td>
            <td><?= esc(truncate($si['item_name'], 30)) ?></td>
            <td class="mono small"><?= esc($si['inv_reference']) ?></td>
            <td class="small text-muted"><?= esc($si['cat_name']) ?></td>
            <td class="small fw-600"><?= esc($si['custodian_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= esc($si['location_name'] ?? '—') ?></td>
            <td><?= repBadge($si['status_name'], $si['color_badge']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($statusItems)): ?>
          <tr><td colspan="7" class="text-center text-muted p-4">No controlled items match this status filter.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
