<?php
// =============================================================================
// ChainTrack — User Management: Add User
// users/add.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$errors = [];
$v = [
    'full_name'        => '',
    'username'         => '',
    'email'            => '',
    'employee_id'      => '',
    'role_id'          => '',
    'department_id'    => '',
    'password'         => '',
    'password_confirm' => '',
    'is_active'        => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['full_name']        = trim($_POST['full_name'] ?? '');
    $v['username']         = trim($_POST['username'] ?? '');
    $v['email']            = trim($_POST['email'] ?? '');
    $v['employee_id']      = trim($_POST['employee_id'] ?? '');
    $v['role_id']          = (int)($_POST['role_id'] ?? 0);
    $v['department_id']    = trim($_POST['department_id'] ?? '');
    $v['password']         = $_POST['password'] ?? '';
    $v['password_confirm'] = $_POST['password_confirm'] ?? '';
    $v['is_active']        = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    validateRequired($v['full_name'], 'Full name', $errors);
    validateRequired($v['username'], 'Username', $errors);
    validateRequired($v['email'], 'Email address', $errors);

    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($v['role_id'] <= 0) {
        $errors[] = 'A system role must be selected.';
    }

    if ($v['username'] !== '' && !isUsernameUnique($db, $v['username'])) {
        $errors[] = "The username \"{$v['username']}\" is already taken.";
    }

    if ($v['email'] !== '' && !isEmailUnique($db, $v['email'])) {
        $errors[] = "The email \"{$v['email']}\" is already registered to another user.";
    }

    if ($v['employee_id'] !== '' && !isEmployeeIdUnique($db, $v['employee_id'])) {
        $errors[] = "The badge / employee ID \"{$v['employee_id']}\" is already assigned.";
    }

    if (strlen($v['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters in length.';
    }

    if ($v['password'] !== $v['password_confirm']) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        try {
            $phash = password_hash($v['password'], PASSWORD_BCRYPT);
            $empId = $v['employee_id'] !== '' ? $v['employee_id'] : null;
            $dept  = $v['department_id'] !== '' ? (int)$v['department_id'] : null;
            $createdBy = sessionUserId();
            $active = (int)$v['is_active'];

            $stmt = $db->prepare(
                "INSERT INTO users
                   (employee_id, full_name, email, username, password_hash, role_id, department_id, is_active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssssiiii', $empId, $v['full_name'], $v['email'], $v['username'], $phash, $v['role_id'], $dept, $active, $createdBy);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();

            logAudit($db, $createdBy, 'user.created', 'user', $newId, "Created user {$v['full_name']} ({$v['username']}) with role ID {$v['role_id']}");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "User \"{$v['full_name']}\" ({$v['username']}) created successfully.",
            ];
            header('Location: /users/index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$roles       = getAllRoles($db);
$departments = $db->query("SELECT id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Add new authorized user to ChainTrack.">
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
        <a href="/users/index.php" style="color:var(--ct-muted);text-decoration:none;">Users</a> › <span>Add</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Add New Authorized User</h2>
    </div>

    <div class="ct-card" style="max-width:760px;">
      <div class="ct-card-header">Account Details & Credentials</div>
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
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Full Name <span style="color:var(--ct-danger);">*</span></label>
              <input type="text" name="full_name" class="ct-input" required maxlength="150"
                     placeholder="e.g. Inspector Alex Morgan" value="<?= esc($v['full_name']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Badge / Employee ID</label>
              <input type="text" name="employee_id" class="ct-input" maxlength="50"
                     placeholder="e.g. BDG-9021" value="<?= esc($v['employee_id']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Username <span style="color:var(--ct-danger);">*</span></label>
              <input type="text" name="username" class="ct-input" required maxlength="80"
                     placeholder="e.g. alex.morgan" value="<?= esc($v['username']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Email Address <span style="color:var(--ct-danger);">*</span></label>
              <input type="email" name="email" class="ct-input" required maxlength="150"
                     placeholder="e.g. alex.morgan@investigation.gov" value="<?= esc($v['email']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">System Role <span style="color:var(--ct-danger);">*</span></label>
              <select name="role_id" class="ct-select" required>
                <option value="">— Select Role —</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= (int)$v['role_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                  <?= esc($r['role_name']) ?> (<?= esc($r['role_slug']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Department</label>
              <select name="department_id" class="ct-select">
                <option value="">— Select Department —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (string)$v['department_id'] === (string)$d['id'] ? 'selected' : '' ?>>
                  <?= esc($d['dept_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Password <span style="color:var(--ct-danger);">*</span></label>
              <input type="password" name="password" class="ct-input" required minlength="8"
                     placeholder="Minimum 8 characters">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Confirm Password <span style="color:var(--ct-danger);">*</span></label>
              <input type="password" name="password_confirm" class="ct-input" required minlength="8"
                     placeholder="Re-enter password">
            </div>
          </div>

          <div class="ct-form-group" style="max-width:240px;">
            <label class="ct-label">Initial Status</label>
            <select name="is_active" class="ct-select">
              <option value="1" <?= (int)$v['is_active'] === 1 ? 'selected' : '' ?>>Active (Can Login)</option>
              <option value="0" <?= (int)$v['is_active'] === 0 ? 'selected' : '' ?>>Inactive (Blocked)</option>
            </select>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Create User</button>
            <a href="/users/index.php" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
