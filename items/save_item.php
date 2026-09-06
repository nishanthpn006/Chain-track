<?php
// =============================================================================
// ChainTrack — Item Registration Save Handler
// items/save_item.php
//
// Accepts the POST from items/add_item.php.
//
// Execution flow
// ──────────────
//  1.  Validate the request method (POST only).
//  2.  Start a secure session and verify the user is logged-in and authorised.
//  3.  Run all server-side validation via validateItemRegistration().
//  4.  If validation fails → store errors + repopulate values in session →
//      redirect back to add_item.php.  No DB write occurs.
//  5.  Open a MySQLi transaction.
//  6.  INSERT the item row into `items`.
//  7.  Retrieve the auto-generated item_id with $db->insert_id.
//  8.  INSERT the initial custody record into `custody_history`
//      (action_type = 'initial_assignment', from_user_id = NULL).
//  9.  INSERT the initial status-history record into `item_status_history`
//      (previous_status_id = NULL — no prior status exists).
// 10.  INSERT an audit-log entry into `audit_logs`.
// 11.  COMMIT the transaction.
// 12.  On any failure → ROLLBACK → re-display form with error.
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

// ─── Step 1: Reject non-POST requests ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /items/add_item.php');
    exit;
}

// ─── Step 2: Session + role check ────────────────────────────────────────────
requireRole('administrator', 'investigator');

$db            = getMySQL();
$registrarId   = sessionUserId();    // logged-in user who is performing the registration
$registrarRole = sessionUserRole();

// ─── Helper: redirect back to the form with errors ────────────────────────────
function failBack(array $errors, array $postValues): never
{
    $_SESSION['form_errors']     = $errors;
    $_SESSION['form_repopulate'] = $postValues;
    header('Location: /items/add_item.php');
    exit;
}

// ─── Step 3: Server-side validation ─────────────────────────────────────────
$errors = validateItemRegistration($db, $_POST, $registrarId);

if (!empty($errors)) {
    failBack($errors, $_POST);
}

// ─── Collect and sanitise the validated POST values ──────────────────────────
$itemReference       = trim($_POST['item_reference']);
$itemName            = trim($_POST['item_name']);
$investigationId     = (int)$_POST['investigation_id'];
$categoryId          = (int)$_POST['category_id'];
$initialCustodianId  = (int)$_POST['initial_custodian_id'];
$initialLocationId   = (int)$_POST['initial_location_id'];
$acquisitionDate     = !empty(trim($_POST['acquisition_date']))     ? trim($_POST['acquisition_date'])     : null;
$acquisitionLocation = !empty(trim($_POST['acquisition_location'])) ? trim($_POST['acquisition_location']) : null;
$physicalDescription = !empty(trim($_POST['physical_description'])) ? trim($_POST['physical_description']) : null;
$custodyRemarks      = !empty(trim($_POST['custody_remarks']))      ? trim($_POST['custody_remarks'])      : null;
$notes               = !empty(trim($_POST['notes']))                ? trim($_POST['notes'])                : null;

// ─── Retrieve the "Registered" status ID ──────────────────────────────────────
$statusStmt = $db->prepare(
    "SELECT id FROM item_statuses WHERE status_slug = 'registered' LIMIT 1"
);
$statusStmt->execute();
$statusRow = $statusStmt->get_result()->fetch_assoc();
$statusStmt->close();

if (!$statusRow) {
    failBack(["System error: The 'registered' status does not exist in the database. "
            . "Please contact the administrator."], $_POST);
}
$registeredStatusId = (int)$statusRow['id'];

// =============================================================================
// ──────────────────────────  DATABASE TRANSACTION  ───────────────────────────
// =============================================================================
// Every write below is inside a single transaction.
// If ANY step fails an exception is thrown (because mysqli_report is STRICT),
// caught in the catch block, and the transaction is rolled back.
// No partial data will ever remain in the database.
// =============================================================================

// ─── Step 5: Open transaction ────────────────────────────────────────────────
$db->begin_transaction();

try {

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 6 — INSERT the new item into `items`
    // ─────────────────────────────────────────────────────────────────────────
    // Stores the current snapshot (custodian, location, status).
    // History (full timeline) lives in custody_history + item_status_history.
    // ─────────────────────────────────────────────────────────────────────────
    $insertItem = $db->prepare(
        "INSERT INTO items
           (item_reference,
            investigation_id,
            category_id,
            item_name,
            physical_description,
            acquisition_date,
            acquisition_location,
            registered_by,
            current_custodian_id,
            current_location_id,
            current_status_id,
            notes,
            is_archived,
            created_at,
            updated_at)
         VALUES
           (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())"
    );

    // Bind all 12 parameters.
    // Types:  s  i  i  s  s  s  s  i  i  i  i  s
    $insertItem->bind_param(
        'siissssiiiis',
        $itemReference,
        $investigationId,
        $categoryId,
        $itemName,
        $physicalDescription,
        $acquisitionDate,
        $acquisitionLocation,
        $registrarId,
        $initialCustodianId,
        $initialLocationId,
        $registeredStatusId,
        $notes
    );
    $insertItem->execute();
    $insertItem->close();

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 7 — Retrieve the auto-incremented item ID
    // ─────────────────────────────────────────────────────────────────────────
    // $db->insert_id is safe here: it returns the ID from the LAST INSERT on
    // THIS connection — no race condition because we are inside a transaction.
    // ─────────────────────────────────────────────────────────────────────────
    $newItemId = (int)$db->insert_id;

    if ($newItemId === 0) {
        throw new RuntimeException(
            'Failed to retrieve the new item ID after INSERT. Registration aborted.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 8 — INSERT the Initial Custody Assignment into `custody_history`
    //
    //  action_type  = 'initial_assignment'
    //  from_user_id = NULL  (there is no previous custodian — the item is new)
    //  to_user_id   = the selected initial custodian
    //  from_location_id = NULL (no previous location)
    //  to_location_id   = the selected initial storage location
    //  recorded_by  = the officer performing the registration (session user)
    //
    // This is the FIRST and permanently-anchored event in the custody timeline.
    // ─────────────────────────────────────────────────────────────────────────
    $insertHistory = $db->prepare(
        "INSERT INTO custody_history
           (item_id,
            from_user_id,
            to_user_id,
            from_location_id,
            to_location_id,
            action_type,
            remarks,
            related_transfer_id,
            recorded_by,
            recorded_at)
         VALUES
           (?,
            NULL,
            ?,
            NULL,
            ?,
            'initial_assignment',
            ?,
            NULL,
            ?,
            NOW())"
    );

    // Types:  i(item_id)  i(to_user)  i(to_loc)  s(remarks)  i(recorded_by)
    $insertHistory->bind_param(
        'iiisi',
        $newItemId,
        $initialCustodianId,
        $initialLocationId,
        $custodyRemarks,
        $registrarId
    );
    $insertHistory->execute();
    $insertHistory->close();

    $newHistoryId = (int)$db->insert_id;

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 9 — INSERT the initial record into `item_status_history`
    //
    //  previous_status_id = NULL  (no prior status — item is brand new)
    //  new_status_id      = id of the 'registered' status
    //  related_transfer_id = NULL (not triggered by a transfer event)
    // ─────────────────────────────────────────────────────────────────────────
    $insertStatusHistory = $db->prepare(
        "INSERT INTO item_status_history
           (item_id,
            previous_status_id,
            new_status_id,
            changed_by,
            related_transfer_id,
            reason,
            changed_at)
         VALUES
           (?, NULL, ?, ?, NULL, 'Item registered and initial custody assigned.', NOW())"
    );

    // Types:  i(item_id)  i(new_status)  i(changed_by)
    $insertStatusHistory->bind_param(
        'iii',
        $newItemId,
        $registeredStatusId,
        $registrarId
    );
    $insertStatusHistory->execute();
    $insertStatusHistory->close();

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 10 — Write an audit-log entry
    //
    // The audit_logs table is the system-wide event log (immutable).
    // action format: 'entity.event'
    // ─────────────────────────────────────────────────────────────────────────
    $ipAddress  = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $auditDesc  = "Item registered: {$itemReference} — {$itemName}. "
                . "Initial custody assigned to user ID {$initialCustodianId} "
                . "at location ID {$initialLocationId}.";

    $insertAudit = $db->prepare(
        "INSERT INTO audit_logs
           (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
         VALUES
           (?, 'item.created', 'item', ?, ?, ?, ?, NOW())"
    );

    // Types:  i(user)  i(entity_id)  s(desc)  s(ip)  s(ua)
    $insertAudit->bind_param(
        'iisss',
        $registrarId,
        $newItemId,
        $auditDesc,
        $ipAddress,
        $userAgent
    );
    $insertAudit->execute();
    $insertAudit->close();

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 11 — COMMIT
    //
    // All four INSERTs (item + custody_history + item_status_history + audit)
    // are committed as a single atomic unit.  Either ALL succeed or NONE do.
    // ─────────────────────────────────────────────────────────────────────────
    $db->commit();

    // Success — redirect to the item detail page
    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => "Item <strong>{$itemReference}</strong> registered successfully. "
                   . "Initial custody assignment has been recorded.",
    ];
    header("Location: /items/item_details.php?id={$newItemId}");
    exit;

} catch (Throwable $e) {

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 12 — ROLLBACK on any failure
    //
    // mysqli_sql_exception is thrown by the driver for DB errors (because we
    // enabled MYSQLI_REPORT_STRICT above).  RuntimeException covers our own
    // checks (e.g. insert_id === 0).
    //
    // Rolling back guarantees that no partial records remain:
    //   • If the custody_history INSERT fails after the item INSERT,
    //     the item row is also removed.
    //   • The database stays consistent.
    // ─────────────────────────────────────────────────────────────────────────
    $db->rollback();

    // Log the full technical error for the developer — never show it to the user.
    error_log('[ChainTrack] save_item.php transaction failed: ' . $e->getMessage()
            . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());

    failBack(
        ["A database error occurred and the item could not be saved. "
       . "All changes have been rolled back. Please try again or contact the administrator."],
        $_POST
    );
}
