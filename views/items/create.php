<?php
// Forward to the complete Item Registration module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /items/add_item.php' . $qs);
exit;
