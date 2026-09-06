<?php
require_once __DIR__ . '/../../core/bootstrap.php';
// Forward to the complete Investigation Management module
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/investigations/add.php');
exit;
