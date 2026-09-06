<?php
require_once __DIR__ . '/../../core/bootstrap.php';
// Forward to the complete Investigation Management module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/index.php' . $qs);
exit;
