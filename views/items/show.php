<?php
// Forward to the complete Item Details module
$id = (int)($_GET['id'] ?? 0);
header('Location: /items/item_details.php?id=' . $id);
exit;
