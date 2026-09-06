<?php
// =============================================================================
// ChainTrack — User Management: Edit User
// users/edit.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid user ID.'];
    header('Location: /users/index.php');
    exit;
}

$user = getUserById($db, $id);
if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User not found.'];
    header('Location: /users/index.php');
    exit;
}

$currentUid = sessionUserId();
$errors = [];
$v = [
    'full_name'        => $user['full_name'] ?? '',
    'username'         => $user['username'] ?? '',
    'email'            => $user['email'] ?? '',
    'employee_id'      => $user['employee_id'] ?? '',
    'role_id'          => (int)$user['role_id'],
    'department_id'    => $user['department_id'] ? (string)$user['department_id'] : '',
    'is_active'        => (int)$user['is_active'],
    'password'         => '',
    'password_confirm' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['full_name']        = trim($_POST['full_name'] ?? '');
    $v['email']            = trim($_POST['email'] ?? '');
    $v['employee_id']      = trim($_POST['employee_id'] ?? '');
    $v['role_id']          = (int)($_POST['role_id'] ?? 0);
    $v['department_id']    = trim($_POST['department_id'] ?? '');
    $v['is_active']        = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $v['password']         = $_POST['password'] ?? '';
    $v['password_confirm'] = $_POST['password_confirm'] ?? '';

    validateRequired($v['full_name'], 'Full name', $errors);
    validateRequired($v['email'], 'Email address', $errors);

    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($v['role_id'] <= 0) {
        $errors[] = 'A system role must be selected.';
    }

    if ($v['email'] !== '' && !isEmailUnique($db, $v['email'], $id)) {
        $errors[] = "The email \"{$v['email']}\" is already registered to another user.";
    }

    if ($v['employee_id'] !== '' && !isEmployeeIdUnique($db, $v['employee_id'], $id)) {
        $errors[] = "The badge / employee ID \"{$v['employee_id']}\" is already assigned.";
    }

    // Self protection: cannot deactivate self
    if ($id === $currentUid && (int)$v['is_active'] === 0) {
        $errors[] = 'You cannot deactivate your own account.';
        $v['is_active'] = 1;
    }

    // Optional password change
    if ($v['password'] !== '') {
        if (strlen($v['password']) < 8) {
            $errors[] = 'New password must be at least 8 characters in length.';
        }
        if ($v['password'] !== $v['password_confirm']) {
            $errors[] = 'New password confirmation does not match.';
        }
    }

    if (empty($errors)) {
        try {
            $empId = $v['employee_id'] !== '' ? $v['employee_id'] : null;
            $dept  = $v['department_id'] !== '' ? (int)$v['department_id'] : null;
            $active = (int)$v['is_active'];

            if ($v['password'] !== '') {
                $phash = password_hash($v['password'], PASSWORD_BCRYPT);
                $stmt = $db->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, employee_id = ?, role_id = ?,
                         department_id = ?, is_active = ?, password_hash = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssiisi i', $v['full_name'], $v['email'], $empId, $v['role_id'], $dept, $active, $phash, $id);
                // Correct types: 'sssiisi' (7 params) + 'i' (id) -> 'sssiisii'
                $types = 'sssiisii';
                $stmt = $db->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, employee_id = ?, role_id = ?,
                         department_id = ?, is_active = ?, password_hash = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssiisii', $v['full_name'], $v['email'], $empId, $v['role_id'], $dept, $active, $phash, $id);
            } else {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, employee_id = ?, role_id = ?,
                         department_id = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssiisi', $v['full_name'], $v['email'], $empId, $v['role_id'], $dept, $active, $id);
            }

            $stmt->execute();
            $stmt->close();

            logAudit($db, sessionUserId(), 'user.updated', 'user', $id, "Updated user {$v['full_name']} (ID: {$id})");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "User \"{$v['full_name']}\" updated successfully.",
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

$pageTitle = 'Edit User: ' . $user['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Edit user profile, role, and credentials.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">Edit User</span>
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
        <a href="/users/index.php" style="color:var(--ct-muted);text-decoration:none;">Users</a> › <span>Edit</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Edit User: <?= esc($user['full_name']) ?></h2>
    </div>

    <div class="ct-card" style="max-width:760px;">
      <div class="ct-card-header">
        <span>Account Profile (ID: #<?= (int)$id ?> — Username: <strong class="mono"><?= esc($user['username']) ?></strong>)</span>
      </div>
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
                     value="<?= esc($v['full_name']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Badge / Employee ID</label>
              <input type="text" name="employee_id" class="ct-input" maxlength="50"
                     value="<?= esc($v['employee_id']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Username</label>
              <input type="text" class="ct-input" disabled value="<?= esc($user['username']) ?>" style="background:var(--ct-bg-alt);cursor:not-allowed;">
              <span class="small text-muted">Username cannot be changed</span>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Email Address <span style="color:var(--ct-danger);">*</span></label>
              <input type="email" name="email" class="ct-input" required maxlength="150"
                     value="<?= esc($v['email']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">System Role <span style="color:var(--ct-danger);">*</span></label>
              <select name="role_id" class="ct-select" required>
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

          <div class="ct-form-group" style="max-width:240px;">
            <label class="ct-label">Status</label>
            <select name="is_active" class="ct-select" <?= $id === $currentUid ? 'disabled' : '' ?>>
              <option value="1" <?= (int)$v['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= (int)$v['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
            <?php if ($id === $currentUid): ?>
            <input type="hidden" name="is_active" value="1">
            <span class="small text-muted">You cannot deactivate your own account</span>
            <?php endif; ?>
          </div>

          <hr style="border:0;border-top:1px solid var(--ct-border);margin:20px 0;">

          <h3 style="font-size:1rem;margin-bottom:12px;">Reset Password <span class="small text-muted" style="font-weight:normal;">(leave blank to keep current password)</span></h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">New Password</label>
              <input type="password" name="password" class="ct-input" minlength="8" autocomplete="new-password"
                     placeholder="Enter new password (min 8 chars)">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Confirm New Password</label>
              <input type="password" name="password_confirm" class="ct-input" minlength="8" autocomplete="new-password"
                     placeholder="Re-enter new password">
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Save Changes</button>
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
