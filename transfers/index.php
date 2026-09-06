<?php
// =============================================================================
// ChainTrack — Transfer Index (All Transfers)
// transfers/index.php
//
// Role-filtered list of custody transfers.
// Admins / Auditors: see all.
// Others: see only transfers where they are sender or recipient.
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db   = getMySQL();
$uid  = sessionUserId();
$role = sessionUserRole();

// ─── Status filter ────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['pending', 'confirmed', 'rejected', ''];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$transfers = getUserTransfers($db, $uid, $role, $statusFilter);

// Pending incoming count (for badge)
$pendingCount = 0;
foreach ($transfers as $t) {
    if ($t['transfer_status'] === 'pending'
        && (int)($t['to_user_id'] ?? 0) === $uid) {
        $pendingCount++;
    }
}
// Simpler approach — just query directly
$pc = $db->prepare("SELECT COUNT(*) AS c FROM custody_transfers WHERE to_user_id = ? AND transfer_status = 'pending'");
$pc->bind_param('i', $uid);
$pc->execute();
$pendingCount = (int)$pc->get_result()->fetch_assoc()['c'];
$pc->close();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function fmtDtI(?string $dt): string {
    return $dt ? date('d M Y, g:i A', strtotime($dt)) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Custody Transfers — ChainTrack</title>
  <meta name="description" content="View and manage custody transfer records in ChainTrack.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">Custody Transfers</span>
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

    <!-- ── Header row ─────────────────────────────────────────────────────── -->
    <div class="d-flex justify-between align-center mb-2">
      <div>
        <?php if ($pendingCount > 0): ?>
        <a href="/transfers/incoming.php" class="ct-btn ct-btn-warning">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="17 1 21 5 17 9"/>
            <path d="M3 11V9a4 4 0 014-4h14"/>
          </svg>
          Incoming Transfers
          <span class="badge bg-dark" style="margin-left:4px;"><?= $pendingCount ?></span>
        </a>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <?php if (in_array($role, ['administrator', 'investigator', 'custodian'], true)): ?>
        <a href="/transfers/initiate.php" class="ct-btn ct-btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Initiate Transfer
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Status filter tabs ─────────────────────────────────────────────── -->
    <div class="d-flex gap-1 mb-2">
      <?php
      $tabs = [
          ''          => 'All',
          'pending'   => 'Pending',
          'confirmed' => 'Confirmed',
          'rejected'  => 'Rejected',
      ];
      foreach ($tabs as $val => $label):
          $active = ($statusFilter === $val) ? 'ct-btn-primary' : 'ct-btn-secondary';
          $url    = '/transfers/index.php' . ($val ? "?status={$val}" : '');
      ?>
      <a href="<?= esc($url) ?>" class="ct-btn <?= $active ?>" style="padding:5px 14px;font-size:.8rem;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ── Transfers table ────────────────────────────────────────────────── -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Item</th>
              <th>From</th>
              <th>To</th>
              <th>Destination</th>
              <th>Status</th>
              <th>Initiated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transfers)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:32px;color:var(--ct-muted);">
                No transfers found<?= $statusFilter ? " with status <strong>{$statusFilter}</strong>" : '' ?>.
              </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($transfers as $t): ?>
            <tr>
              <td class="mono" style="font-size:.8rem;">
                <?= esc($t['transfer_reference']) ?>
              </td>
              <td>
                <div class="fw-600 small">
                  <a href="/items/item_details.php?id=<?= esc($t['item_id'] ?? '') ?>"
                     class="ref-code" style="text-decoration:none;">
                     <?= esc($t['item_reference']) ?>
                  </a>
                </div>
                <div class="small text-muted"><?= esc(mb_substr($t['item_name'], 0, 40)) ?></div>
              </td>
              <td class="small"><?= esc($t['from_user_name'] ?? '—') ?></td>
              <td class="small fw-600"><?= esc($t['to_user_name']) ?></td>
              <td class="small"><?= esc($t['to_location_name'] ?? '—') ?></td>
              <td><?= transferStatusBadge($t['transfer_status']) ?></td>
              <td class="small text-muted"><?= fmtDtI($t['initiated_at']) ?></td>
              <td>
                <?php if ($t['transfer_status'] === 'pending'): ?>
                  <a href="/transfers/confirm.php?id=<?= esc($t['id']) ?>&action=confirm"
                     class="ct-btn ct-btn-primary" style="padding:4px 10px;font-size:.75rem;">
                    Review
                  </a>
                <?php else: ?>
                  <span class="small text-muted"><?= fmtDtI($t['confirmed_at']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
