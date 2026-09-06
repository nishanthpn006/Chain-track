<?php
// =============================================================================
// ChainTrack — Item Category Management: Toggle Status
// categories/toggle_status.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid category ID.'];
    header('Location: /categories/index.php');
    exit;
}

$cat = getCategoryById($db, $id);
if (!$cat) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Category not found.'];
    header('Location: /categories/index.php');
    exit;
}

$newStatus = (int)$cat['is_active'] === 1 ? 0 : 1;
$statusText = $newStatus === 1 ? 'activated' : 'deactivated';

toggleCategoryStatus($db, $id);

logAudit(
    $db,
    sessionUserId(),
    'category.status_changed',
    'item_category',
    $id,
    "Category {$cat['cat_name']} {$statusText} (ID: {$id})"
);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => "Category \"{$cat['cat_name']}\" has been {$statusText}.",
];

header('Location: /categories/index.php');
exit;
