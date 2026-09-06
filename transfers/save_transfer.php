<?php
// =============================================================================
// ChainTrack — Save Transfer (POST Handler)
// transfers/save_transfer.php
//
// Execution Flow:
//  1. Reject non-POST.
//  2. Auth: requireRole (admin/investigator/custodian).
//  3. Server-side validation via validateInitiateTransfer() — 9 rules.
//  4. On fail: store errors in session, redirect back to form.
//  5. BEGIN TRANSACTION
//  6. INSERT custody_transfers  (status = 'pending')
//  7. Retrieve new transfer ID
//  8. UPDATE items.current_status_id → 'pending_transfer'
//     (current custodian is NOT changed yet — only after confirmation)
//  9. INSERT item_status_history
// 10. INSERT audit_logs
// 11. COMMIT — all or nothing
// 12. On any failure: ROLLBACK, redirect with error
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /transfers/initiate.php');
    exit;
}

requireRole('administrator', 'investigator', 'custodian');

$db   = getMySQL();
$uid  = sessionUserId();
$role = sessionUserRole();

// ─── Fail-back helper ─────────────────────────────────────────────────────────
function failBack(array $errors, array $post = []): never
{
    $_SESSION['form_errors']     = $errors;
    $_SESSION['form_repopulate'] = $post;
    header('Location: /transfers/initiate.php');
    exit;
}

// ─── Step 3: Validate ─────────────────────────────────────────────────────────
$errors = validateInitiateTransfer($db, $_POST, $uid, $role);
if (!empty($errors)) {
    failBack($errors, $_POST);
}

// ─── Collect sanitised values ─────────────────────────────────────────────────
$itemId     = (int)$_POST['item_id'];
$toUserId   = (int)$_POST['to_user_id'];
$toLocId    = (int)$_POST['to_location_id'];
$reason     = trim($_POST['reason']);

// Load current item state (needed for from_user / from_location / prev status)
$stmt = $db->prepare(
    "SELECT current_custodian_id, current_location_id, current_status_id FROM items WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fromUserId  = (int)($item['current_custodian_id'] ?? 0) ?: null;
$fromLocId   = (int)($item['current_location_id']  ?? 0) ?: null;
$prevStatId  = (int)($item['current_status_id']);

// Status IDs needed
$pendingTrfStatusId = getStatusIdBySlug($db, 'pending_transfer');
if (!$pendingTrfStatusId) {
    failBack(["System error: 'pending_transfer' status not found in database. Contact administrator."], $_POST);
}

// Generate unique transfer reference TRF-YYYY-NNNNN
$transferRef = generateTransferRef($db);

// IP / UA for audit
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// =============================================================================
// DATABASE TRANSACTION
// =============================================================================
$db->begin_transaction();

try {
    // ── Step 6: INSERT custody_transfers ─────────────────────────────────────
    $stmt = $db->prepare(
        "INSERT INTO custody_transfers
           (transfer_reference, item_id, from_user_id, to_user_id,
            from_location_id, to_location_id, transfer_status,
            reason, initiated_by, initiated_at)
         VALUES
           (?, ?, ?, ?,  ?, ?, 'pending',  ?, ?, NOW())"
    );
    // Types: s i i i  i i  s i
    $stmt->bind_param(
        'siiiissi',
        $transferRef,
        $itemId,
        $fromUserId,
        $toUserId,
        $fromLocId,
        $toLocId,
        $reason,
        $uid
    );
    $stmt->execute();
    $stmt->close();

    // ── Step 7: Get new transfer ID ───────────────────────────────────────────
    $newTransferId = (int)$db->insert_id;
    if ($newTransferId === 0) {
        throw new RuntimeException('Failed to create transfer record.');
    }

    // ── Step 8: UPDATE item status → pending_transfer ─────────────────────────
    //    Current custodian is intentionally NOT changed here.
    $stmt = $db->prepare(
        "UPDATE items SET current_status_id = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param('ii', $pendingTrfStatusId, $itemId);
    $stmt->execute();
    $stmt->close();

    // ── Step 9: INSERT item_status_history ────────────────────────────────────
    $histReason = "Transfer initiated to user ID {$toUserId}. Ref: {$transferRef}.";
    $stmt = $db->prepare(
        "INSERT INTO item_status_history
           (item_id, previous_status_id, new_status_id, changed_by,
            related_transfer_id, reason, changed_at)
         VALUES
           (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        'iiiiss',
        $itemId,
        $prevStatId,
        $pendingTrfStatusId,
        $uid,
        $newTransferId,
        $histReason
    );
    $stmt->execute();
    $stmt->close();

    // ── Step 10: INSERT audit_logs ────────────────────────────────────────────
    $auditDesc = "Transfer {$transferRef} initiated for item ID {$itemId}. "
               . "From user {$fromUserId} to user {$toUserId}. Reason: {$reason}";
    $stmt = $db->prepare(
        "INSERT INTO audit_logs
           (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
         VALUES
           (?, 'transfer.initiated', 'custody_transfer', ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('iisss', $uid, $newTransferId, $auditDesc, $ip, $ua);
    $stmt->execute();
    $stmt->close();

    // ── Step 11: COMMIT ───────────────────────────────────────────────────────
    $db->commit();

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => "Transfer <strong>{$transferRef}</strong> submitted. "
                   . "The recipient must confirm receipt to complete the transfer.",
    ];
    header("Location: /transfers/index.php");
    exit;

} catch (Throwable $e) {
    // ── Step 12: ROLLBACK ─────────────────────────────────────────────────────
    $db->rollback();
    error_log('[ChainTrack] save_transfer.php failed: ' . $e->getMessage()
            . ' | ' . $e->getFile() . ':' . $e->getLine());
    failBack([
        "A database error occurred and the transfer was not created. "
      . "All changes have been rolled back. Please try again."
    ], $_POST);
}
