<?php
// Forward to the complete Custody Transfer view module
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /transfers/view.php' . $qs);
exit;
