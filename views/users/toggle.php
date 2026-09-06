<?php
// Forward to the complete User Management module
$id = (int)($_GET['id'] ?? 0);
header('Location: /users/toggle_status.php?id=' . $id);
exit;
