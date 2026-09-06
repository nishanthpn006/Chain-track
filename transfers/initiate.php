<?php
// =============================================================================
// ChainTrack — Initiate Custody Transfer (Form)
// transfers/initiate.php
//
// Shows the form for initiating a transfer.
// All save logic is in transfers/save_transfer.php.
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator', 'investigator', 'custodian');

$db   = getMySQL();
$uid  = sessionUserId();
$role = sessionUserRole();

// ─── Populate dropdowns ───────────────────────────────────────────────────────
$transferableItems = getTransferableItems($db, $uid, $role);
$recipients        = getCustodianCandidates($db);
$locations         = getActiveLocations($db);

// ─── Pre-select item if arriving from item_details.php?item_id=N ────────────
$preItemId = (int)($_GET['item_id'] ?? 0);

// ─── Error repopulation ───────────────────────────────────────────────────────
$formValues = $_SESSION['form_repopulate'] ?? [];
$formErrors = $_SESSION['form_errors']     ?? [];
unset($_SESSION['form_repopulate'], $_SESSION['form_errors']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ─── If a pre-item was requested, load its detail for the info card ──────────
$preItem = null;
$selectedItemId = (int)($formValues['item_id'] ?? $preItemId);
if ($selectedItemId > 0) {
    $s = $db->prepare(
        "SELECT i.id, i.item_reference, i.item_name, i.current_custodian_id,
                cu.full_name AS custodian_name, cl.location_name AS current_location_name,
                ist.status_name, ist.color_badge
         FROM items i
         LEFT JOIN users cu     ON cu.id = i.current_custodian_id
         LEFT JOIN locations cl ON cl.id = i.current_location_id
         JOIN item_statuses ist ON ist.id = i.current_status_id
         WHERE i.id = ? LIMIT 1"
    );
    $s->bind_param('i', $selectedItemId);
    $s->execute();
    $preItem = $s->get_result()->fetch_assoc();
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Initiate Transfer — ChainTrack</title>
  <meta name="description" content="Transfer a controlled item to another authorized user.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">Initiate Custody Transfer</span>
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
    </div>
  </div>

  <div id="ct-content">

    <!-- Breadcrumb -->
    <div class="d-flex align-center gap-1 mb-2 text-muted small">
      <a href="/transfers/index.php" style="color:var(--ct-muted);text-decoration:none;">Transfers</a>
      <span>›</span><span>Initiate Transfer</span>
    </div>

    <!-- Validation errors -->
    <?php if (!empty($formErrors)): ?>
    <div class="ct-alert ct-alert-danger" role="alert">
      <strong>Please correct the following before continuing:</strong>
      <ul style="margin:8px 0 0 18px;padding:0;">
        <?php foreach ($formErrors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="ct-card" style="max-width:860px;">
      <div class="ct-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="17 1 21 5 17 9"/>
          <path d="M3 11V9a4 4 0 014-4h14"/>
          <polyline points="7 23 3 19 7 15"/>
          <path d="M21 13v2a4 4 0 01-4 4H3"/>
        </svg>
        Transfer Details
        <span class="text-muted small" style="margin-left:auto;font-weight:400;">
          Transfer does <strong>not</strong> take effect until the recipient confirms receipt.
        </span>
      </div>

      <div class="ct-card-body">

        <!-- Pre-selected item info card -->
        <?php if ($preItem): ?>
        <div style="background:rgba(79,126,248,.08);border:1px solid rgba(79,126,248,.25);
                    border-radius:10px;padding:14px 18px;margin-bottom:22px;">
          <div class="small text-muted" style="margin-bottom:4px;">ITEM TO BE TRANSFERRED</div>
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
              <span class="mono text-accent fw-600"><?= esc($preItem['item_reference']) ?></span>
              &nbsp;—&nbsp;
              <strong><?= esc($preItem['item_name']) ?></strong>
            </div>
            <div class="small text-muted">
              Current custodian: <strong><?= esc($preItem['custodian_name'] ?? '—') ?></strong>
            </div>
            <div class="small text-muted">
              Location: <?= esc($preItem['current_location_name'] ?? '—') ?>
            </div>
            <span class="badge bg-<?= esc($preItem['color_badge']) ?>">
              <?= esc($preItem['status_name']) ?>
            </span>
          </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="/transfers/save_transfer.php"
              id="transfer-form" novalidate>

          <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">

          <!-- ── Section A: Item ─────────────────────────────────────────── -->
          <p class="ct-section-title">A — Select Item</p>
          <div class="ct-form-group">
            <label class="ct-label" for="item_id">
              Item to Transfer <span style="color:var(--ct-danger);">*</span>
            </label>
            <?php if (empty($transferableItems)): ?>
              <div class="ct-alert ct-alert-warning" style="margin-top:4px;">
                You have no items available for transfer.
                Items that are already pending a transfer are excluded.
              </div>
              <input type="hidden" name="item_id" value="0">
            <?php else: ?>
            <select id="item_id" name="item_id" class="ct-select" required>
              <option value="">— Select an item —</option>
              <?php foreach ($transferableItems as $it): ?>
              <option value="<?= esc($it['id']) ?>"
                <?= ($formValues['item_id'] ?? $preItemId) == $it['id'] ? 'selected' : '' ?>>
                <?= esc($it['item_reference']) ?> — <?= esc($it['item_name']) ?>
                (<?= esc($it['custodian_name'] ?? 'Unassigned') ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </div>

          <!-- ── Section B: Recipient & Destination ────────────────────── -->
          <p class="ct-section-title" style="margin-top:20px;">B — Recipient & Destination</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">

            <div class="ct-form-group">
              <label class="ct-label" for="to_user_id">
                Transfer To (Recipient) <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="to_user_id" name="to_user_id" class="ct-select" required>
                <option value="">— Select recipient —</option>
                <?php foreach ($recipients as $u):
                  // Cannot transfer to yourself
                  if ((int)$u['id'] === $uid) continue;
                ?>
                <option value="<?= esc($u['id']) ?>"
                  <?= ($formValues['to_user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                  <?= esc($u['full_name']) ?>
                  <?= $u['employee_id'] ? ' [' . esc($u['employee_id']) . ']' : '' ?>
                  — <?= esc($u['role_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="to_location_id">
                Destination Location <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="to_location_id" name="to_location_id" class="ct-select" required>
                <option value="">— Select destination —</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= esc($loc['id']) ?>"
                  <?= ($formValues['to_location_id'] ?? '') == $loc['id'] ? 'selected' : '' ?>>
                  <?= esc($loc['location_name']) ?> (<?= esc($loc['location_type']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>

          <!-- ── Section C: Reason ─────────────────────────────────────── -->
          <p class="ct-section-title" style="margin-top:20px;">C — Transfer Reason</p>
          <div class="ct-form-group">
            <label class="ct-label" for="reason">
              Reason / Remarks <span style="color:var(--ct-danger);">*</span>
            </label>
            <textarea id="reason" name="reason" class="ct-textarea" required
                      placeholder="State the justification for this transfer (e.g. forensic examination, court submission, secure storage…)"
            ><?= esc($formValues['reason'] ?? '') ?></textarea>
          </div>

          <!-- Business rule notice -->
          <div style="background:rgba(255,193,7,.08);border:1px solid rgba(255,193,7,.3);
                      border-radius:8px;padding:12px 16px;font-size:.8rem;color:var(--ct-muted);margin-bottom:20px;">
            <strong style="color:var(--ct-warning);">⚠ Important:</strong>
            The current custodian <strong>does not change</strong> when this transfer is initiated.
            Custody will only update after the recipient confirms receipt.
          </div>

          <!-- Actions -->
          <div class="d-flex gap-2" style="padding-top:16px;border-top:1px solid var(--ct-border);">
            <button type="submit" id="btn-submit" class="ct-btn ct-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
              Submit Transfer Request
            </button>
            <a href="/transfers/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>

        </form>
      </div>
    </div>

  </div><!-- #ct-content -->
</div><!-- #ct-main -->
</div><!-- sidebar-wrap -->

<script>
document.getElementById('transfer-form').addEventListener('submit', function() {
  document.getElementById('btn-submit').disabled = true;
  document.getElementById('btn-submit').textContent = 'Submitting…';
});
</script>

</body>
</html>
