<?php
// Forward to the complete User Management module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /users/index.php' . $qs);
exit;
