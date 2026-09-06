<?php
// =============================================================================
// ChainTrack — Location Management: Add Location
// locations/add.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$errors = [];
$v = [
    'location_code' => '',
    'location_name' => '',
    'location_type' => 'storage',
    'parent_id'     => '',
    'department_id' => '',
    'description'   => '',
    'is_active'     => 1,
];

$validTypes = ['storage', 'lab', 'office', 'field', 'external', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['location_code'] = trim($_POST['location_code'] ?? '');
    $v['location_name'] = trim($_POST['location_name'] ?? '');
    $v['location_type'] = trim($_POST['location_type'] ?? 'storage');
    $v['parent_id']     = trim($_POST['parent_id'] ?? '');
    $v['department_id'] = trim($_POST['department_id'] ?? '');
    $v['description']   = trim($_POST['description'] ?? '');
    $v['is_active']     = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    validateRequired($v['location_name'], 'Location name', $errors);

    if (!in_array($v['location_type'], $validTypes, true)) {
        $errors[] = 'Invalid location type selected.';
    }

    if ($v['location_name'] !== '' && !isLocationNameUnique($db, $v['location_name'])) {
        $errors[] = "A location named \"{$v['location_name']}\" already exists.";
    }

    if ($v['location_code'] !== '' && !isLocationCodeUnique($db, $v['location_code'])) {
        $errors[] = "The location code \"{$v['location_code']}\" is already assigned.";
    }

    if (empty($errors)) {
        try {
            $code = $v['location_code'] !== '' ? $v['location_code'] : null;
            $parent = $v['parent_id'] !== '' ? (int)$v['parent_id'] : null;
            $dept = $v['department_id'] !== '' ? (int)$v['department_id'] : null;
            $desc = $v['description'] !== '' ? $v['description'] : null;
            $active = (int)$v['is_active'];

            $stmt = $db->prepare(
                "INSERT INTO locations
                   (location_code, location_name, location_type, parent_id, department_id, description, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssissi', $code, $v['location_name'], $v['location_type'], $parent, $dept, $desc, $active);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();

            logAudit($db, sessionUserId(), 'location.created', 'location', $newId, "Created location {$v['location_name']} (Type: {$v['location_type']})");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Location \"{$v['location_name']}\" added successfully.",
            ];
            header('Location: /locations/index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Dropdown options
$allLocations = $db->query("SELECT id, location_name, location_code FROM locations WHERE is_active = 1 ORDER BY location_name ASC")->fetch_all(MYSQLI_ASSOC);
$departments  = $db->query("SELECT id, dept_name, dept_code FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add Location';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Add new storage facility or examination location.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title"><?= esc($pageTitle) ?></span>
    <div class="topbar-right">
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
      <a href="/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="mb-2">
      <div class="small text-muted mb-1">
        <a href="/locations/index.php" style="color:var(--ct-muted);text-decoration:none;">Locations</a> › <span>Add</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Add New Storage Location</h2>
    </div>

    <div class="ct-card" style="max-width:720px;">
      <div class="ct-card-header">Location Details</div>
      <div class="ct-card-body">

        <?php if (!empty($errors)): ?>
        <div class="ct-alert ct-alert-danger mb-2">
          <ul style="margin:0;padding-left:18px;">
            <?php foreach ($errors as $err): ?>
            <li><?= esc($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="POST">
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Location Name <span style="color:var(--ct-danger);">*</span></label>
              <input type="text" name="location_name" class="ct-input" required maxlength="150"
                     placeholder="e.g. Evidence Vault A – Shelf 3" value="<?= esc($v['location_name']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Location Code</label>
              <input type="text" name="location_code" class="ct-input" maxlength="50"
                     placeholder="e.g. VLT-A-03" value="<?= esc($v['location_code']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Location Type <span style="color:var(--ct-danger);">*</span></label>
              <select name="location_type" class="ct-select" required>
                <?php foreach ($validTypes as $vt): ?>
                <option value="<?= $vt ?>" <?= $v['location_type'] === $vt ? 'selected' : '' ?>><?= ucfirst($vt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Status</label>
              <select name="is_active" class="ct-select">
                <option value="1" <?= (int)$v['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (int)$v['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Parent Facility (Optional)</label>
              <select name="parent_id" class="ct-select">
                <option value="">— Top-level Facility —</option>
                <?php foreach ($allLocations as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= (string)$v['parent_id'] === (string)$al['id'] ? 'selected' : '' ?>>
                  <?= esc($al['location_name']) ?> <?= $al['location_code'] ? '('.esc($al['location_code']).')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Assigned Department (Optional)</label>
              <select name="department_id" class="ct-select">
                <option value="">— General / Shared —</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= (int)$dept['id'] ?>" <?= (string)$v['department_id'] === (string)$dept['id'] ? 'selected' : '' ?>>
                  <?= esc($dept['dept_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="ct-form-group">
            <label class="ct-label">Description / Security Notes</label>
            <textarea name="description" class="ct-textarea" style="min-height:80px;"
                      placeholder="Access restrictions, vault combination holder, temperature controls…"><?= esc($v['description']) ?></textarea>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Save Location</button>
            <a href="/locations/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
