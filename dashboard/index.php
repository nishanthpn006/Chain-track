<?php
require_once __DIR__ . '/../core/bootstrap.php';
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/views/dashboard/index.php');
exit;
