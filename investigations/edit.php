<?php
// =============================================================================
// ChainTrack — Investigation Management: Edit Investigation
// investigations/edit.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator', 'investigator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid investigation ID.'];
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/index.php');
    exit;
}

$inv = getInvestigationById($db, $id);
if (!$inv) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Investigation not found.'];
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/index.php');
    exit;
}

$errors = [];
$v = [
    'title'         => $inv['title'] ?? '',
    'description'   => $inv['description'] ?? '',
    'department_id' => $inv['department_id'] ? (string)$inv['department_id'] : '',
    'lead_user_id'  => $inv['lead_user_id'] ? (string)$inv['lead_user_id'] : '',
    'start_date'    => $inv['start_date'] ?? '',
    'end_date'      => $inv['end_date'] ?? '',
    'status'        => $inv['status'] ?? 'open',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['title']         = trim($_POST['title'] ?? '');
    $v['description']   = trim($_POST['description'] ?? '');
    $v['department_id'] = trim($_POST['department_id'] ?? '');
    $v['lead_user_id']  = trim($_POST['lead_user_id'] ?? '');
    $v['start_date']    = trim($_POST['start_date'] ?? '');
    $v['end_date']      = trim($_POST['end_date'] ?? '');
    $v['status']        = trim($_POST['status'] ?? 'open');

    validateRequired($v['title'], 'Case title', $errors);
    validateRequired($v['start_date'], 'Start date', $errors);

    if (!in_array($v['status'], ['open', 'closed', 'archived'], true)) {
        $errors[] = 'Invalid case status selected.';
    }

    if ($v['start_date'] !== '' && !strtotime($v['start_date'])) {
        $errors[] = 'Invalid start date format.';
    }

    if ($v['end_date'] !== '') {
        if (!strtotime($v['end_date'])) {
            $errors[] = 'Invalid end date format.';
        } elseif (strtotime($v['end_date']) < strtotime($v['start_date'])) {
            $errors[] = 'End date cannot be prior to start date.';
        }
    }

    if (empty($errors)) {
        try {
            $dept = $v['department_id'] !== '' ? (int)$v['department_id'] : null;
            $lead = $v['lead_user_id'] !== '' ? (int)$v['lead_user_id'] : null;
            $endDate = $v['end_date'] !== '' ? $v['end_date'] : null;
            $desc = $v['description'] !== '' ? $v['description'] : null;

            $stmt = $db->prepare(
                "UPDATE investigations
                 SET title = ?, description = ?, department_id = ?, lead_user_id = ?,
                     start_date = ?, end_date = ?, status = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ssiisssi', $v['title'], $desc, $dept, $lead, $v['start_date'], $endDate, $v['status'], $id);
            $stmt->execute();
            $stmt->close();

            logAudit($db, sessionUserId(), 'investigation.updated', 'investigation', $id, "Updated investigation {$inv['inv_reference']}: {$v['title']} (Status: {$v['status']})");

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Investigation \"{$inv['inv_reference']}\" updated successfully.",
            ];
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/view.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$departments = $db->query("SELECT id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetch_all(MYSQLI_ASSOC);
$leadUsers   = getEligibleLeadUsers($db);

$base = defined('BASE_PATH') ? BASE_PATH : '';
$pageTitle = 'Edit Investigation: ' . $inv['inv_reference'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Edit investigative case file.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title">Edit Investigation</span>
    <div class="topbar-right">
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
      <a href="<?= $base ?>/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="mb-2">
      <div class="small text-muted mb-1">
        <a href="<?= $base ?>/investigations/index.php" style="color:var(--ct-muted);text-decoration:none;">Investigations</a> ›
        <a href="<?= $base ?>/investigations/view.php?id=<?= (int)$id ?>" style="color:var(--ct-muted);text-decoration:none;"><?= esc($inv['inv_reference']) ?></a> ›
        <span>Edit</span>
      </div>
      <h2 style="margin:0;font-size:1.25rem;">Edit Investigation: <?= esc($inv['inv_reference']) ?></h2>
    </div>

    <div class="ct-card" style="max-width:760px;">
      <div class="ct-card-header">Case Details (<?= esc($inv['inv_reference']) ?>)</div>
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
          <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Case Reference</label>
              <input type="text" class="ct-input mono" disabled value="<?= esc($inv['inv_reference']) ?>" style="background:var(--ct-bg-alt);cursor:not-allowed;">
              <span class="small text-muted">Immutable reference</span>
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Case Title <span style="color:var(--ct-danger);">*</span></label>
              <input type="text" name="title" class="ct-input" required maxlength="255"
                     value="<?= esc($v['title']) ?>">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
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

            <div class="ct-form-group">
              <label class="ct-label">Lead Investigator</label>
              <select name="lead_user_id" class="ct-select">
                <option value="">— Select Lead —</option>
                <?php foreach ($leadUsers as $lu): ?>
                <option value="<?= (int)$lu['id'] ?>" <?= (string)$v['lead_user_id'] === (string)$lu['id'] ? 'selected' : '' ?>>
                  <?= esc($lu['full_name']) ?> (<?= esc($lu['role_name']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
            <div class="ct-form-group">
              <label class="ct-label">Start Date <span style="color:var(--ct-danger);">*</span></label>
              <input type="date" name="start_date" class="ct-input" required value="<?= esc($v['start_date']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">End Date</label>
              <input type="date" name="end_date" class="ct-input" value="<?= esc($v['end_date']) ?>">
            </div>

            <div class="ct-form-group">
              <label class="ct-label">Status</label>
              <select name="status" class="ct-select">
                <option value="open" <?= $v['status'] === 'open' ? 'selected' : '' ?>>Open (Active)</option>
                <option value="closed" <?= $v['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="archived" <?= $v['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
              </select>
            </div>
          </div>

          <div class="ct-form-group">
            <label class="ct-label">Case Description & Objectives</label>
            <textarea name="description" class="ct-textarea" style="min-height:90px;"><?= esc($v['description']) ?></textarea>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="ct-btn ct-btn-primary">Update Case</button>
            <a href="<?= $base ?>/investigations/view.php?id=<?= (int)$id ?>" class="ct-btn ct-btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
