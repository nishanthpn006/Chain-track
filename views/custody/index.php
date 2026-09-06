<?php
// Forward to the complete Custody Transfer module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /transfers/index.php' . $qs);
exit;
