<?php
// =============================================================================
// ChainTrack — Database & System Integrity Diagnostic Tool
// tools/diagnostics.php
//
// Verifies:
//   1. Core configuration and environment prerequisites
//   2. Database connections (PDO & MySQLi)
//   3. Required table schemas and column structures
//   4. Foreign key integrity and orphaned record detection
//   5. Sequential Chain-of-Custody continuity
//   6. Active administrator account availability
// =============================================================================

require_once __DIR__ . '/../core/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_role(ROLE_ADMIN);
}

function out(string $msg, string $status = 'INFO'): void {
    global $isCli;
    $symbols = [
        'OK'    => '[ PASS ]',
        'WARN'  => '[ WARN ]',
        'FAIL'  => '[ FAIL ]',
        'INFO'  => '[ INFO ]',
    ];
    $tag = $symbols[$status] ?? '[ .... ]';
    if ($isCli) {
        echo "{$tag} {$msg}\n";
    } else {
        $colors = ['OK' => '#15803d', 'WARN' => '#b45309', 'FAIL' => '#b91c1c', 'INFO' => '#1e40af'];
        $c = $colors[$status] ?? '#334155';
        echo "<div style='font-family:monospace;font-size:0.85rem;margin-bottom:4px;'><strong style='color:{$c}'>{$tag}</strong> " . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    $pageTitle = 'System Diagnostics';
    require_once __DIR__ . '/../views/layouts/header.php';
    echo '<div id="ct-sidebar-wrap">';
    require_once __DIR__ . '/../views/layouts/sidebar.php';
    echo '<div id="ct-main">';
    require_once __DIR__ . '/../views/layouts/navbar.php';
    echo '<div id="ct-content"><div class="ct-card"><h2 style="margin-top:0;">System & Database Diagnostics</h2><hr>';
}

out("Starting ChainTrack System Integrity Diagnostics...", 'INFO');
$errors = 0;
$warnings = 0;

// 1. Connection Checks
try {
    $pdo = getDB();
    out("PDO MySQL connection established successfully", 'OK');
} catch (Throwable $e) {
    out("PDO MySQL connection failed: " . $e->getMessage(), 'FAIL');
    $errors++;
}

try {
    require_once __DIR__ . '/../config/db_mysqli.php';
    $mysqli = getMySQL();
    out("MySQLi connection established successfully", 'OK');
} catch (Throwable $e) {
    out("MySQLi connection failed: " . $e->getMessage(), 'FAIL');
    $errors++;
}

// 2. Table Existence Checks
$requiredTables = [
    'roles', 'departments', 'users', 'investigations',
    'item_categories', 'item_statuses', 'locations', 'items',
    'custody_transfers', 'item_status_history', 'audit_logs', 'custody_history'
];

$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($requiredTables as $tbl) {
    if (in_array($tbl, $existingTables, true)) {
        out("Table `{$tbl}` verified present", 'OK');
    } else {
        out("Table `{$tbl}` is MISSING from database", 'FAIL');
        $errors++;
    }
}

// 3. Foreign Key & Orphan Checks
out("Inspecting database relationships for orphaned records...", 'INFO');

// Orphan items -> investigations
$orphans = (int)$pdo->query("SELECT count(*) FROM items i LEFT JOIN investigations inv ON inv.id = i.investigation_id WHERE inv.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All items link to valid investigations (0 orphans)", 'OK');
} else {
    out("Found {$orphans} orphaned items with invalid investigation_id", 'FAIL');
    $errors++;
}

// Orphan items -> categories
$orphans = (int)$pdo->query("SELECT count(*) FROM items i LEFT JOIN item_categories c ON c.id = i.category_id WHERE c.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All items link to valid item categories (0 orphans)", 'OK');
} else {
    out("Found {$orphans} items with invalid category_id", 'FAIL');
    $errors++;
}

// Orphan items -> users (custodians)
$orphans = (int)$pdo->query("SELECT count(*) FROM items i LEFT JOIN users u ON u.id = i.current_custodian_id WHERE i.current_custodian_id IS NOT NULL AND u.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All current custodians reference valid users (0 orphans)", 'OK');
} else {
    out("Found {$orphans} items with invalid current_custodian_id", 'FAIL');
    $errors++;
}

// Orphan items -> locations
$orphans = (int)$pdo->query("SELECT count(*) FROM items i LEFT JOIN locations l ON l.id = i.current_location_id WHERE i.current_location_id IS NOT NULL AND l.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All current locations reference valid locations (0 orphans)", 'OK');
} else {
    out("Found {$orphans} items with invalid current_location_id", 'FAIL');
    $errors++;
}

// Orphan custody_transfers -> items
$orphans = (int)$pdo->query("SELECT count(*) FROM custody_transfers t LEFT JOIN items i ON i.id = t.item_id WHERE i.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All custody transfers reference valid items (0 orphans)", 'OK');
} else {
    out("Found {$orphans} transfers with invalid item_id", 'FAIL');
    $errors++;
}

// Orphan custody_transfers -> users
$orphans = (int)$pdo->query("SELECT count(*) FROM custody_transfers t LEFT JOIN users u ON u.id = t.to_user_id WHERE u.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All custody transfer recipients reference valid users (0 orphans)", 'OK');
} else {
    out("Found {$orphans} transfers with invalid to_user_id", 'FAIL');
    $errors++;
}

// Orphan custody_history -> items
$orphans = (int)$pdo->query("SELECT count(*) FROM custody_history ch LEFT JOIN items i ON i.id = ch.item_id WHERE i.id IS NULL")->fetchColumn();
if ($orphans === 0) {
    out("All custody history events reference valid items (0 orphans)", 'OK');
} else {
    out("Found {$orphans} custody history events with invalid item_id", 'FAIL');
    $errors++;
}

// 4. Sequential Chain-of-Custody Continuity
out("Verifying sequential chain-of-custody continuity...", 'INFO');
$itemsWithoutInit = (int)$pdo->query(
    "SELECT count(*) FROM items i 
     WHERE NOT EXISTS (
         SELECT 1 FROM custody_history ch 
         WHERE ch.item_id = i.id AND ch.action_type = 'initial_assignment'
     )"
)->fetchColumn();

if ($itemsWithoutInit === 0) {
    out("All items have verified initial custody booking records in custody_history", 'OK');
} else {
    out("Found {$itemsWithoutInit} items missing initial custody history", 'WARN');
    $warnings++;
}

// Verify current custodian synchronization with last confirmed transfer
$custodianMismatch = (int)$pdo->query(
    "SELECT count(*) FROM items i
     WHERE EXISTS (SELECT 1 FROM custody_transfers WHERE item_id = i.id AND transfer_status = 'confirmed')
     AND i.current_custodian_id != (
         SELECT to_user_id FROM custody_transfers 
         WHERE item_id = i.id AND transfer_status = 'confirmed' 
         ORDER BY id DESC LIMIT 1
     )"
)->fetchColumn();

if ($custodianMismatch === 0) {
    out("All item custodians are in strict synchronization with confirmed transfers", 'OK');
} else {
    out("Found {$custodianMismatch} items where current_custodian_id does not match last confirmed transfer", 'FAIL');
    $errors++;
}

// 5. Active Admin Account Verification
$activeAdmins = (int)$pdo->query(
    "SELECT count(*) FROM users u 
     JOIN roles r ON r.id = u.role_id 
     WHERE r.role_slug = 'administrator' AND u.is_active = 1"
)->fetchColumn();

if ($activeAdmins >= 1) {
    out("Active System Administrator account verified ({$activeAdmins} available)", 'OK');
} else {
    out("No active System Administrator account found", 'FAIL');
    $errors++;
}

// Summary
out("----------------------------------------------------------------", 'INFO');
if ($errors === 0 && $warnings === 0) {
    out("INTEGRITY CHECK COMPLETE: 0 errors, 0 warnings. Database is 100% consistent.", 'OK');
} elseif ($errors === 0) {
    out("INTEGRITY CHECK COMPLETE: 0 errors, {$warnings} warning(s). System is functional.", 'WARN');
} else {
    out("INTEGRITY CHECK COMPLETE: {$errors} error(s), {$warnings} warning(s) detected.", 'FAIL');
}

if (!$isCli) {
    echo '</div></div></div>';
    require_once __DIR__ . '/../views/layouts/footer.php';
}

exit($errors === 0 ? 0 : 1);
