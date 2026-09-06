<?php
// =============================================================================
// ChainTrack — Item Registration Form
// items/add_item.php
//
// Displays the HTML form for registering a new controlled item.
// All server-side save logic is in items/save_item.php.
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

// Only investigators and administrators may register items.
requireRole('administrator', 'investigator');

$db = getMySQL();

// ─── Populate form dropdowns ─────────────────────────────────────────────────
$categories    = getActiveCategories($db);
$investigations= getOpenInvestigations($db);
$custodians    = getCustodianCandidates($db);
$locations     = getActiveLocations($db);

// ─── Re-populate form values after a failed validation round-trip ────────────
//     save_item.php stores the rejected POST values and errors in the session
//     so the user does not have to retype everything.
$formValues = $_SESSION['form_repopulate'] ?? [];
$formErrors = $_SESSION['form_errors']     ?? [];
unset($_SESSION['form_repopulate'], $_SESSION['form_errors']);

if (empty($formValues['investigation_id'])) {
    if (!empty($_GET['investigation_id'])) {
        $formValues['investigation_id'] = (int)$_GET['investigation_id'];
    } elseif (!empty($_GET['inv'])) {
        $formValues['investigation_id'] = (int)$_GET['inv'];
    }
}

// ─── Flash messages from previous save attempt ───────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ─── Auto-generate a suggested reference number (user can override) ──────────
$suggestedRef = generateItemRef($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Item — ChainTrack</title>
  <meta name="description" content="Register a new controlled evidence item and record its initial custody assignment.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>

<!-- Reuse the existing ChainTrack shell layout -->
<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>

<div id="ct-main">

  <!-- Top bar -->
  <div id="ct-topbar">
    <span class="page-title">Register Controlled Item</span>
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
      <a href="/items/index.php" style="color:var(--ct-muted);text-decoration:none;">Items</a>
      <span>›</span>
      <span>Register New Controlled Item</span>
    </div>

    <!-- Validation error summary -->
    <?php if (!empty($formErrors)): ?>
    <div class="ct-alert ct-alert-danger" role="alert">
      <strong>Please correct the following errors before saving:</strong>
      <ul style="margin: 8px 0 0 18px; padding: 0;">
        <?php foreach ($formErrors as $err): ?>
          <li><?= esc($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="ct-card" style="max-width:900px;">
      <div class="ct-card-header">
        <!-- Icon -->
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        </svg>
        New Controlled Item Registration
        <span class="text-muted small" style="margin-left:auto;font-weight:400;">
          Fields marked <span style="color:var(--ct-danger);">*</span> are mandatory.
        </span>
      </div>

      <div class="ct-card-body">

        <!-- ╔═══════════════════════════════════════════════════════╗ -->
        <!-- ║  FORM — submits to items/save_item.php              ║ -->
        <!-- ╚═══════════════════════════════════════════════════════╝ -->
        <form method="POST" action="/items/save_item.php"
              id="item-registration-form"
              novalidate>

          <!-- CSRF token -->
          <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">

          <!-- ── Section 1: Item Information ──────────────────────────── -->
          <p class="ct-section-title">1. Item Information</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">

            <div class="ct-form-group">
              <label class="ct-label" for="item_reference">
                Reference Number <span style="color:var(--ct-danger);">*</span>
              </label>
              <input
                id="item_reference"
                name="item_reference"
                type="text"
                class="ct-input"
                required
                maxlength="100"
                value="<?= esc($formValues['item_reference'] ?? $suggestedRef) ?>"
                autocomplete="off"
                placeholder="e.g. EVD-2026-00001"
              >
              <p class="small text-muted" style="margin:4px 0 0;">Auto-generated. You may override it — must be unique.</p>
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="item_name">
                Item Name / Description <span style="color:var(--ct-danger);">*</span>
              </label>
              <input
                id="item_name"
                name="item_name"
                type="text"
                class="ct-input"
                required
                maxlength="255"
                value="<?= esc($formValues['item_name'] ?? '') ?>"
                placeholder="e.g. Black Samsung mobile phone"
              >
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="investigation_id">
                Investigation / Case <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="investigation_id" name="investigation_id" class="ct-select" required>
                <option value="">— Select an open investigation —</option>
                <?php foreach ($investigations as $inv): ?>
                <option
                  value="<?= esc($inv['id']) ?>"
                  <?= ($formValues['investigation_id'] ?? '') == $inv['id'] ? 'selected' : '' ?>
                >
                  <?= esc($inv['inv_reference']) ?> — <?= esc($inv['title']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="category_id">
                Category <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="category_id" name="category_id" class="ct-select" required>
                <option value="">— Select a category —</option>
                <?php foreach ($categories as $cat): ?>
                <option
                  value="<?= esc($cat['id']) ?>"
                  <?= ($formValues['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>
                >
                  <?= esc($cat['cat_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>

          <!-- ── Section B: Physical Details ─────────────────────────── -->
          <p class="ct-section-title" style="margin-top:20px;">B — Physical Details</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">

            <div class="ct-form-group">
              <label class="ct-label" for="acquisition_date">Acquisition / Seizure Date</label>
              <input
                id="acquisition_date"
                name="acquisition_date"
                type="date"
                class="ct-input"
                value="<?= esc($formValues['acquisition_date'] ?? '') ?>"
                max="<?= date('Y-m-d') ?>"
              >
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="acquisition_location">Original Collection Point</label>
              <input
                id="acquisition_location"
                name="acquisition_location"
                type="text"
                class="ct-input"
                maxlength="255"
                placeholder="Scene address, GPS, or description"
                value="<?= esc($formValues['acquisition_location'] ?? '') ?>"
              >
            </div>

            <div class="ct-form-group" style="grid-column:span 2;">
              <label class="ct-label" for="physical_description">Physical Description</label>
              <textarea
                id="physical_description"
                name="physical_description"
                class="ct-textarea"
                placeholder="Colour, make, model, serial number, dimensions, condition, markings…"
              ><?= esc($formValues['physical_description'] ?? '') ?></textarea>
            </div>

          </div>

          <!-- ── Section 2: Initial Custody Assignment ─────────────── -->
          <p class="ct-section-title" style="margin-top:20px;">
            2. Initial Custody Assignment
            <span class="text-muted small" style="font-weight:400;text-transform:none;letter-spacing:0;">
              &nbsp;— Permanent starting record in custody history.
            </span>
          </p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">

            <div class="ct-form-group">
              <label class="ct-label" for="initial_custodian_id">
                Initial Custodian <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="initial_custodian_id" name="initial_custodian_id" class="ct-select" required>
                <option value="">— Select custodian —</option>
                <?php foreach ($custodians as $u): ?>
                <option
                  value="<?= esc($u['id']) ?>"
                  <?= ($formValues['initial_custodian_id'] ?? '') == $u['id'] ? 'selected' : '' ?>
                >
                  <?= esc($u['full_name']) ?>
                  <?= $u['employee_id'] ? ' [' . esc($u['employee_id']) . ']' : '' ?>
                  — <?= esc($u['role_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label" for="initial_location_id">
                Initial Storage Location <span style="color:var(--ct-danger);">*</span>
              </label>
              <select id="initial_location_id" name="initial_location_id" class="ct-select" required>
                <option value="">— Select location —</option>
                <?php foreach ($locations as $loc): ?>
                <option
                  value="<?= esc($loc['id']) ?>"
                  <?= ($formValues['initial_location_id'] ?? '') == $loc['id'] ? 'selected' : '' ?>
                >
                  <?= esc($loc['location_name']) ?>
                  <small>(<?= esc($loc['location_type']) ?>)</small>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group" style="grid-column:span 2;">
              <label class="ct-label" for="custody_remarks">
                Initial Custody Remarks
                <span class="text-muted small" style="text-transform:none;letter-spacing:0;">(optional — recorded in custody history)</span>
              </label>
              <textarea
                id="custody_remarks"
                name="custody_remarks"
                class="ct-textarea"
                style="min-height:70px;"
                placeholder="Any notes relevant to why this custodian is receiving the item…"
              ><?= esc($formValues['custody_remarks'] ?? '') ?></textarea>
            </div>

          </div>

          <!-- ── Section 3: Additional Notes ─────────────────────────── -->
          <p class="ct-section-title" style="margin-top:20px;">3. Additional Notes</p>
          <div class="ct-form-group">
            <label class="ct-label" for="notes">Internal Notes (visible to authorised users)</label>
            <textarea
              id="notes"
              name="notes"
              class="ct-textarea"
              style="min-height:70px;"
            ><?= esc($formValues['notes'] ?? '') ?></textarea>
          </div>

          <!-- ── Action Buttons ───────────────────────────────────────── -->
          <div class="d-flex gap-2" style="margin-top:24px;padding-top:18px;border-top:1px solid var(--ct-border);">
            <button type="submit" id="btn-save" class="ct-btn ct-btn-primary">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
              </svg>
              Register Controlled Item
            </button>
            <a href="/items/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>

        </form>
      </div><!-- .ct-card-body -->
    </div><!-- .ct-card -->

  </div><!-- #ct-content -->
</div><!-- #ct-main -->
</div><!-- #ct-sidebar-wrap -->

<script>
// Prevent accidental double-submit
document.getElementById('item-registration-form').addEventListener('submit', function() {
  document.getElementById('btn-save').disabled = true;
  document.getElementById('btn-save').textContent = 'Saving…';
});
</script>

</body>
</html>
