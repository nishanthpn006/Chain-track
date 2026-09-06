<?php
// Forward to the complete Item Management module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /items/index.php' . $qs);
exit;
