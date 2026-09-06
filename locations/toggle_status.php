<?php
// =============================================================================
// ChainTrack — Location Management: Toggle Status
// locations/toggle_status.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid location ID.'];
    header('Location: /locations/index.php');
    exit;
}

$loc = getLocationById($db, $id);
if (!$loc) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Location not found.'];
    header('Location: /locations/index.php');
    exit;
}

$newStatus = (int)$loc['is_active'] === 1 ? 0 : 1;
$statusText = $newStatus === 1 ? 'activated' : 'deactivated';

toggleLocationStatus($db, $id);

logAudit(
    $db,
    sessionUserId(),
    'location.status_changed',
    'location',
    $id,
    "Location {$loc['location_name']} {$statusText} (ID: {$id})"
);

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => "Location \"{$loc['location_name']}\" has been {$statusText}.",
];

header('Location: /locations/index.php');
exit;
