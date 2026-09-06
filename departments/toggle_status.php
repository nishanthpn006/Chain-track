<?php
// =============================================================================
// ChainTrack — Department Management: Toggle Status
// departments/toggle_status.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid department ID.'];
    header('Location: /departments/index.php');
    exit;
}

$dept = getDepartmentById($db, $id);
if (!$dept) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Department not found.'];
    header('Location: /departments/index.php');
    exit;
}

$newStatus = (int)$dept['is_active'] === 1 ? 0 : 1;
$statusText = $newStatus === 1 ? 'activated' : 'deactivated';

toggleDepartmentStatus($db, $id);

logAudit(
    $db,
    sessionUserId(),
    'department.status_changed',
    'department',
    $id,
    "Department {$dept['dept_name']} {$statusText} (ID: {$id})"
);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => "Department \"{$dept['dept_name']}\" has been {$statusText}.",
];

header('Location: /departments/index.php');
exit;
