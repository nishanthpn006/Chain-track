<?php
// Forward to the complete Custody Transfer initiation module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /transfers/initiate.php' . $qs);
exit;
