<?php
require_once __DIR__ . '/../../core/bootstrap.php';
// Forward to the complete Investigation Management module
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/view.php?id=' . $id);
exit;
