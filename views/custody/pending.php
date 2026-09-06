<?php
// Forward to the complete Incoming Transfers queue module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /transfers/incoming.php' . $qs);
exit;
