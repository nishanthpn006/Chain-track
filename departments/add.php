<?php
// =============================================================================
// ChainTrack — Department Management: Add Department
// departments/add.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$errors = [];
$v = [
    'dept_code'   => '',
    'dept_name'   => '',
    'description' => '',
    'is_active'   => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['dept_code']   = trim($_POST['dept_code'] ?? '');
    $v['dept_name']   = trim($_POST['dept_name'] ?? '');
    $v['description'] = trim($_POST['description'] ?? '');
    $v['is_active']   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    // Validation
    validateRequired($v['dept_name'], 'Department name', $errors);

    if ($v['dept_name'] !== '' && !isDepartmentNameUnique($db, $v['dept_name'])) {
        $errors[] = "A department named \"{$v['dept_name']}\" already exists.";
    }

    if ($v['dept_code'] !== '' && !isDepartmentCodeUnique($db, $v['dept_code'])) {
        $errors[] = "The department code \"{$v['dept_code']}\" is already assigned to another department.";
    }

    if (empty($errors)) {
        try {
            $code = $v['dept_code'] !== '' ? $v['dept_code'] : null;
            $desc = $v['description'] !== '' ? $v['description'] : null;
            $active = (int)$v['is_active'];

            $stmt = $db->prepare(
                "INSERT INTO departments (dept_code, dept_name, description, is_active, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssi', $code, $v['dept_name'], $desc, $active);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();

            logAudit($db, sessionUserId(), 'department.created', 'department', $newId, "Created department {$v['dept_name']} ({$v['dept_code']})");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Department \"{$v['dept_name']}\" added successfully.",
            ];
            header('Location: /departments/index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Department';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Add new investigative department.">
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
        <a href="/departments/index.php" style="color:var(--ct-muted);text-decoration:none;">Departments</a> › <span>Add</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Add New Department</h2>
    </div>

    <div class="ct-card" style="max-width:680px;">
      <div class="ct-card-header">Department Details</div>
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
          <div class="ct-form-group">
            <label class="ct-label">Department Name <span style="color:var(--ct-danger);">*</span></label>
            <input type="text" name="dept_name" class="ct-input" required maxlength="150"
                   placeholder="e.g. Cyber Crime Investigation Division" value="<?= esc($v['dept_name']) ?>">
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Department Code</label>
              <input type="text" name="dept_code" class="ct-input" maxlength="30"
                     placeholder="e.g. CCID" value="<?= esc($v['dept_code']) ?>">
              <span class="small text-muted">Short identifier (optional, must be unique)</span>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Status</label>
              <select name="is_active" class="ct-select">
                <option value="1" <?= (int)$v['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (int)$v['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>
          </div>

          <div class="ct-form-group">
            <label class="ct-label">Description</label>
            <textarea name="description" class="ct-textarea" style="min-height:90px;"
                      placeholder="Operational role, scope, or jurisdiction of this unit…"><?= esc($v['description']) ?></textarea>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Save Department</button>
            <a href="/departments/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
