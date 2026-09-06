<?php
// =============================================================================
// ChainTrack — User Management: Toggle Status
// users/toggle_status.php
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

if ($id === sessionUserId()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security restriction: You cannot deactivate your own account.'];
    header('Location: /users/index.php');
    exit;
}

$user = getUserById($db, $id);
if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User not found.'];
    header('Location: /users/index.php');
    exit;
}

$newStatus = (int)$user['is_active'] === 1 ? 0 : 1;
$statusText = $newStatus === 1 ? 'activated' : 'deactivated';

toggleUserStatus($db, $id);

logAudit(
    $db,
    sessionUserId(),
    'user.status_changed',
    'user',
    $id,
    "User {$user['username']} ({$user['full_name']}) {$statusText} (ID: {$id})"
);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => "User \"{$user['full_name']}\" ({$user['username']}) has been {$statusText}.",
];

header('Location: /users/index.php');
exit;
