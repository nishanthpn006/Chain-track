<?php
// =============================================================================
// ChainTrack — Shared Functions (MySQLi edition)
// includes/functions.php
//
// All functions in this file use the MySQLi connection returned by getMySQL().
// These complement the PDO-based core/Helpers.php used by the original system.
// =============================================================================

require_once __DIR__ . '/../config/db_mysqli.php';

// ─── Output / XSS helpers ────────────────────────────────────────────────────

/**
 * Safely escape a value for HTML output.
 * Always use this function when rendering user-supplied data.
 */
function esc(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $str, ?string $encoding = null): int {
        return strlen($str);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $str, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length !== null ? substr($str, $start, $length) : substr($str, $start);
    }
}

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $str, int $start, int $width, string $trimmarker = '', ?string $encoding = null): string {
        if (strlen($str) <= $width) {
            return $str;
        }
        $markerLen = strlen($trimmarker);
        if ($width < $markerLen) {
            return substr($str, $start, $width);
        }
        return substr($str, $start, $width - $markerLen) . $trimmarker;
    }
}

// ─── Validation helpers ───────────────────────────────────────────────────────

/**
 * Validate that a required string field is not empty after trimming.
 *
 * @param  string $value     The raw input value.
 * @param  string $fieldName Human-readable field name (used in error message).
 * @param  array  &$errors   Error array to append any message to.
 * @return string            The trimmed value.
 */
function validateRequired(string $value, string $fieldName, array &$errors): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        $errors[] = "$fieldName is required and cannot be empty.";
    }
    return $trimmed;
}

/**
 * Validate that an integer ID is positive (> 0).
 *
 * @param  mixed  $value     Raw input (will be cast to int).
 * @param  string $fieldName Human-readable name for error messages.
 * @param  array  &$errors   Error array to append any message to.
 * @return int               The validated positive integer, or 0 on failure.
 */
function validatePositiveInt(mixed $value, string $fieldName, array &$errors): int
{
    $int = (int)$value;
    if ($int <= 0) {
        $errors[] = "$fieldName must be selected.";
    }
    return $int;
}

// ─── Database existence-check helpers ─────────────────────────────────────────

/**
 * Check that a row exists in $table with $column = $value AND is_active = 1.
 * Returns the full row as an associative array, or null if not found / inactive.
 *
 * @param  mysqli $db
 * @param  string $table    Table name (hard-coded in calling code — no user input).
 * @param  string $column   Column name to match.
 * @param  mixed  $value    The value to look up (bound via prepared statement).
 * @param  bool   $checkActive  Whether to also require is_active = 1.
 * @return array|null
 */
function dbFindActive(mysqli $db, string $table, string $column, mixed $value, bool $checkActive = true): ?array
{
    $activeClause = $checkActive ? ' AND is_active = 1' : '';
    $stmt = $db->prepare(
        "SELECT * FROM `{$table}` WHERE `{$column}` = ? {$activeClause} LIMIT 1"
    );
    $stmt->bind_param('s', $value);   // 's' works for both strings and integers in MySQLi
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Verify that an item_reference does not already exist in the items table.
 * Returns true if the reference is UNIQUE (safe to use), false if duplicate.
 */
function isItemReferenceUnique(mysqli $db, string $reference): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS cnt FROM items WHERE item_reference = ? LIMIT 1"
    );
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)$row['cnt']) === 0;
}

/**
 * Verify that the custodian user is active AND has a role that is allowed
 * to hold custody (investigator, custodian, analyst — not auditor/admin).
 *
 * Returns the full user row on success, or null on failure.
 */
function validateCustodianUser(mysqli $db, int $userId): ?array
{
    $stmt = $db->prepare(
        "SELECT u.id, u.full_name, u.is_active, r.role_slug
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.is_active = 1
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    // Auditor role may NOT hold physical custody of items.
    if ($row['role_slug'] === 'auditor') return null;

    return $row;
}

/**
 * Perform all server-side validations for the item registration form.
 *
 * Returns an array of human-readable error strings.
 * An empty array means validation passed.
 *
 * @param  mysqli $db
 * @param  array  $post   The raw $_POST data.
 * @param  int    $registrarId  The session user performing the registration.
 * @return array<string>
 */
function validateItemRegistration(mysqli $db, array $post, int $registrarId): array
{
    $errors = [];

    // ── Rule 1: Item name ───────────────────────────────────────────────────
    validateRequired($post['item_name'] ?? '', 'Item name', $errors);

    // ── Rule 2: Item reference is not empty ─────────────────────────────────
    $ref = trim($post['item_reference'] ?? '');
    if ($ref === '') {
        $errors[] = 'Item reference number is required.';
    } elseif (!isItemReferenceUnique($db, $ref)) {
        // ── Rule 3: Item reference is unique ─────────────────────────────────
        $errors[] = "Item reference number \"$ref\" already exists in the system. "
                  . "Each reference must be unique.";
    }

    // ── Rule 4: Investigation exists ────────────────────────────────────────
    $invId = (int)($post['investigation_id'] ?? 0);
    if ($invId <= 0) {
        $errors[] = 'An investigation / case must be selected.';
    } else {
        $inv = dbFindActive($db, 'investigations', 'id', $invId, false);
        if (!$inv) {
            $errors[] = 'The selected investigation does not exist.';
        } elseif ($inv['status'] === 'closed') {
            $errors[] = 'Items cannot be added to a closed investigation.';
        }
    }

    // ── Rule 5: Category exists and is active ───────────────────────────────
    $catId = (int)($post['category_id'] ?? 0);
    if ($catId <= 0) {
        $errors[] = 'A category must be selected.';
    } elseif (!dbFindActive($db, 'item_categories', 'id', $catId)) {
        $errors[] = 'The selected category is inactive or does not exist.';
    }

    // ── Rule 6: Initial custodian — active and allowed role ─────────────────
    $custodianId = (int)($post['initial_custodian_id'] ?? 0);
    if ($custodianId <= 0) {
        $errors[] = 'An initial custodian must be selected.';
    } else {
        $custodian = validateCustodianUser($db, $custodianId);
        if (!$custodian) {
            $errors[] = 'The selected custodian is inactive, does not exist, '
                      . 'or does not have a role that may hold item custody.';
        }
    }

    // ── Rule 7: Initial location exists and is active ───────────────────────
    $locationId = (int)($post['initial_location_id'] ?? 0);
    if ($locationId <= 0) {
        $errors[] = 'An initial storage location must be selected.';
    } elseif (!dbFindActive($db, 'locations', 'id', $locationId)) {
        $errors[] = 'The selected location is inactive or does not exist.';
    }

    // ── Rule 8: Session user is authorised to register items ────────────────
    $allowedRoles = ['administrator', 'investigator'];
    if (!in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
        $errors[] = 'Your account role does not have permission to register items.';
    }

    return $errors;
}

// ─── Reference generators (MySQLi-based) ──────────────────────────────────────

/**
 * Generate a guaranteed-unique item reference in the format EVD-YYYY-NNNNN.
 * Uses SELECT COUNT(*) to calculate the next sequence number.
 */
function generateItemRef(mysqli $db): string
{
    $year  = date('Y');
    $prefix = 'EVD-' . $year . '-%';
    $stmt  = $db->prepare(
        "SELECT COUNT(*) AS cnt FROM items WHERE item_reference LIKE ?"
    );
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return sprintf('EVD-%s-%05d', $year, $count + 1);
}

/**
 * Generate a guaranteed-unique transfer reference in the format TRF-YYYY-NNNNN.
 */
function generateTransferRef(mysqli $db): string
{
    $year   = date('Y');
    $prefix = 'TRF-' . $year . '-%';
    $stmt   = $db->prepare(
        "SELECT COUNT(*) AS cnt FROM custody_transfers WHERE transfer_reference LIKE ?"
    );
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return sprintf('TRF-%s-%05d', $year, $count + 1);
}

// ─── Custody history helpers ───────────────────────────────────────────────────

/**
 * Fetch the complete custody history for a given item in chronological order.
 *
 * Returns an array of rows with joined user / location names for display.
 *
 * @param  mysqli $db
 * @param  int    $itemId
 * @return array<array<string,mixed>>
 */
function getItemCustodyHistory(mysqli $db, int $itemId): array
{
    $stmt = $db->prepare(
        "SELECT
            ch.custody_history_id,
            ch.action_type,
            ch.remarks,
            ch.recorded_at,
            fu.full_name      AS from_user_name,
            fu.employee_id    AS from_employee_id,
            tu.full_name      AS to_user_name,
            tu.employee_id    AS to_employee_id,
            fl.location_name  AS from_location_name,
            tl.location_name  AS to_location_name,
            ru.full_name      AS recorded_by_name,
            ct.transfer_reference
         FROM custody_history ch
         LEFT JOIN users    fu ON fu.id = ch.from_user_id
         LEFT JOIN users    tu ON tu.id = ch.to_user_id
         LEFT JOIN locations fl ON fl.id = ch.from_location_id
         LEFT JOIN locations tl ON tl.id = ch.to_location_id
         LEFT JOIN users    ru ON ru.id = ch.recorded_by
         LEFT JOIN custody_transfers ct ON ct.id = ch.related_transfer_id
         WHERE ch.item_id = ?
         ORDER BY ch.recorded_at ASC, ch.custody_history_id ASC"
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Lookup helper — returns a flat assoc array of all active locations
 * keyed by id (used in form dropdowns).
 */
function getActiveLocations(mysqli $db): array
{
    $result = $db->query(
        "SELECT id, location_name, location_type FROM locations WHERE is_active = 1 ORDER BY location_name"
    );
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Lookup helper — returns all active item categories.
 */
function getActiveCategories(mysqli $db): array
{
    $result = $db->query(
        "SELECT id, cat_name FROM item_categories WHERE is_active = 1 ORDER BY cat_name"
    );
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Lookup helper — returns open investigations for dropdown.
 */
function getOpenInvestigations(mysqli $db): array
{
    $result = $db->query(
        "SELECT id, inv_reference, title FROM investigations WHERE status = 'open' ORDER BY inv_reference DESC"
    );
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Lookup helper — returns users who may hold custody (excludes auditors).
 */
function getCustodianCandidates(mysqli $db): array
{
    $result = $db->query(
        "SELECT u.id, u.full_name, u.employee_id, r.role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1
           AND r.role_slug NOT IN ('auditor')
         ORDER BY u.full_name"
    );
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Human-readable label for an action_type value.
 */
function actionTypeLabel(string $type): string
{
    return match ($type) {
        'initial_assignment' => 'Initial Assignment',
        'transfer'           => 'Custody Transfer',
        'returned'           => 'Returned',
        'archived'           => 'Archived',
        default              => ucfirst(str_replace('_', ' ', $type)),
    };
}

// =============================================================================
// TRANSFER MODULE FUNCTIONS  (appended for Step 33)
// =============================================================================

/**
 * Validate the "Initiate Transfer" form — all 9 business rules.
 * Returns array of human-readable error strings (empty = pass).
 */
function validateInitiateTransfer(mysqli $db, array $post, int $initiatorId, string $initiatorRole): array
{
    $errors = [];

    // Rule 1+2: must be logged in with permission — checked by requireRole() before calling this
    $allowedRoles = ['administrator', 'investigator', 'custodian'];
    if (!in_array($initiatorRole, $allowedRoles, true)) {
        $errors[] = 'Your role does not have permission to initiate transfers.';
        return $errors; // fatal — stop here
    }

    // Rule 3+4: item exists, not archived
    $itemId = (int)($post['item_id'] ?? 0);
    if ($itemId <= 0) {
        $errors[] = 'An item must be selected.';
    } else {
        $stmt = $db->prepare("SELECT id, item_name, current_custodian_id, is_archived FROM items WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            $errors[] = 'The selected item does not exist.';
        } elseif ($item['is_archived']) {
            $errors[] = 'Archived items cannot be transferred.';
        } else {
            // Rule 5: must be current custodian unless admin
            if ($initiatorRole !== 'administrator' && (int)$item['current_custodian_id'] !== $initiatorId) {
                $errors[] = 'You are not the current custodian of this item and cannot initiate its transfer.';
            }
        }
    }

    // Rule 6+7: receiving user exists, active, not same as sender
    $toUserId = (int)($post['to_user_id'] ?? 0);
    if ($toUserId <= 0) {
        $errors[] = 'A receiving user must be selected.';
    } else {
        $stmt = $db->prepare("SELECT id, full_name, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $toUserId);
        $stmt->execute();
        $toUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$toUser || !$toUser['is_active']) {
            $errors[] = 'The selected receiving user is inactive or does not exist.';
        } elseif ($toUserId === $initiatorId) {
            $errors[] = 'An item cannot be transferred to the same person. Select a different recipient.';
        }
    }

    // Rule 8: destination location exists and is active
    $locationId = (int)($post['to_location_id'] ?? 0);
    if ($locationId <= 0) {
        $errors[] = 'A destination location must be selected.';
    } elseif (!dbFindActive($db, 'locations', 'id', $locationId)) {
        $errors[] = 'The selected destination location is inactive or does not exist.';
    }

    // Rule 9: no active pending transfer for this item
    if ($itemId > 0 && empty($errors)) {
        $stmt = $db->prepare(
            "SELECT id FROM custody_transfers
             WHERE item_id = ? AND transfer_status = 'pending' LIMIT 1"
        );
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $errors[] = 'This item already has an active pending transfer. '
                      . 'The current transfer must be confirmed or rejected before a new one can be initiated.';
        }
    }

    // Reason is required
    if (empty(trim($post['reason'] ?? ''))) {
        $errors[] = 'A transfer reason or remarks field is required.';
    }

    return $errors;
}

/**
 * Fetch a single transfer row with all joined user/location/item data.
 */
function getTransferById(mysqli $db, int $transferId): ?array
{
    $stmt = $db->prepare(
        "SELECT
            ct.*,
            i.item_reference,
            i.item_name,
            i.current_custodian_id,
            fu.full_name      AS from_user_name,
            fu.employee_id    AS from_emp_id,
            tu.full_name      AS to_user_name,
            tu.employee_id    AS to_emp_id,
            iu.full_name      AS initiated_by_name,
            cu.full_name      AS confirmed_by_name,
            fl.location_name  AS from_location_name,
            tl.location_name  AS to_location_name
         FROM custody_transfers ct
         JOIN items i           ON i.id  = ct.item_id
         LEFT JOIN users fu     ON fu.id = ct.from_user_id
         JOIN users tu          ON tu.id = ct.to_user_id
         JOIN users iu          ON iu.id = ct.initiated_by
         LEFT JOIN users cu     ON cu.id = ct.confirmed_by
         LEFT JOIN locations fl ON fl.id = ct.from_location_id
         LEFT JOIN locations tl ON tl.id = ct.to_location_id
         WHERE ct.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $transferId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get all transfers visible to a given user (role-filtered).
 * Admins/Auditors: all. Others: transfers where they are sender or recipient.
 */
function getUserTransfers(mysqli $db, int $userId, string $role, string $statusFilter = ''): array
{
    $roleWhereAll  = in_array($role, ['administrator', 'auditor'], true);
    $statusClause  = $statusFilter ? " AND ct.transfer_status = '{$statusFilter}'" : '';
    $userClause    = $roleWhereAll ? '' : " AND (ct.from_user_id = {$userId} OR ct.to_user_id = {$userId})";

    $sql = "SELECT
                ct.id,
                ct.transfer_reference,
                ct.transfer_status,
                ct.reason,
                ct.initiated_at,
                ct.confirmed_at,
                i.item_reference,
                i.item_name,
                fu.full_name AS from_user_name,
                tu.full_name AS to_user_name,
                fl.location_name AS from_location_name,
                tl.location_name AS to_location_name
            FROM custody_transfers ct
            JOIN items i           ON i.id  = ct.item_id
            LEFT JOIN users fu     ON fu.id = ct.from_user_id
            JOIN users tu          ON tu.id = ct.to_user_id
            LEFT JOIN locations fl ON fl.id = ct.from_location_id
            LEFT JOIN locations tl ON tl.id = ct.to_location_id
            WHERE 1=1 {$userClause} {$statusClause}
            ORDER BY ct.initiated_at DESC";

    $result = $db->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all PENDING incoming transfers for the logged-in user.
 */
function getPendingIncoming(mysqli $db, int $userId): array
{
    $stmt = $db->prepare(
        "SELECT
            ct.id,
            ct.transfer_reference,
            ct.reason,
            ct.initiated_at,
            i.item_reference,
            i.item_name,
            i.id AS item_id,
            fu.full_name      AS from_user_name,
            fu.employee_id    AS from_emp_id,
            fl.location_name  AS from_location_name,
            tl.location_name  AS to_location_name
         FROM custody_transfers ct
         JOIN items i           ON i.id  = ct.item_id
         LEFT JOIN users fu     ON fu.id = ct.from_user_id
         LEFT JOIN locations fl ON fl.id = ct.from_location_id
         LEFT JOIN locations tl ON tl.id = ct.to_location_id
         WHERE ct.to_user_id = ?
           AND ct.transfer_status = 'pending'
         ORDER BY ct.initiated_at ASC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Get items available for transfer by the given user (items they currently hold).
 * Admins see all non-archived items; others see only their own.
 */
function getTransferableItems(mysqli $db, int $userId, string $role): array
{
    $whereUser = ($role === 'administrator')
        ? ''
        : " AND i.current_custodian_id = {$userId}";

    $sql = "SELECT
                i.id,
                i.item_reference,
                i.item_name,
                i.current_custodian_id,
                cu.full_name AS custodian_name,
                cl.location_name AS current_location_name,
                ist.status_name
            FROM items i
            LEFT JOIN users cu     ON cu.id = i.current_custodian_id
            LEFT JOIN locations cl ON cl.id = i.current_location_id
            JOIN item_statuses ist ON ist.id = i.current_status_id
            WHERE i.is_archived = 0 {$whereUser}
              AND i.current_status_id NOT IN (
                    SELECT id FROM item_statuses WHERE status_slug = 'pending_transfer'
                  )
            ORDER BY i.item_reference ASC";

    $result = $db->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Lookup a status ID by its slug.
 */
function getStatusIdBySlug(mysqli $db, string $slug): int
{
    $stmt = $db->prepare("SELECT id FROM item_statuses WHERE status_slug = ? LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}

/**
 * Transfer status badge HTML.
 */
function transferStatusBadge(string $status): string
{
    $map = [
        'pending'   => ['Pending Receipt',   'warning'],
        'confirmed' => ['Confirmed',          'success'],
        'rejected'  => ['Rejected',           'danger'],
        'initiated' => ['Initiated',          'secondary'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    $label = $s[0] ?? $s['label'];
    $color = $s[1] ?? $s['color'];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

// =============================================================================
// STEP 34 — AUDIT LOGGING HELPER (MySQLi)
// =============================================================================

/**
 * Record an audit log entry using MySQLi.
 * Never throws — audit failure must not abort a successful business action.
 */
function logAudit(
    mysqli $db,
    ?int $userId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt = $db->prepare(
            "INSERT INTO audit_logs
               (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('ississs', $userId, $action, $entityType, $entityId, $description, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('logAudit failed: ' . $e->getMessage());
    }
}

// =============================================================================
// STEP 34 — DEPARTMENT MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch all departments with item and user counts, optionally filtered by search.
 */
function getAllDepartments(mysqli $db, string $search = ''): array
{
    $whereClause = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $whereClause = 'WHERE d.dept_name LIKE ? OR d.dept_code LIKE ?';
        $like = '%' . $search . '%';
        $params = [$like, $like];
        $types = 'ss';
    }

    $sql = "SELECT d.*,
                   (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS user_count,
                   (SELECT COUNT(*) FROM investigations inv WHERE inv.department_id = d.id) AS investigation_count
            FROM departments d
            {$whereClause}
            ORDER BY d.dept_name ASC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Find department by ID.
 */
function getDepartmentById(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Check if a department name is unique.
 */
function isDepartmentNameUnique(mysqli $db, string $name, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM departments WHERE dept_name = ? AND id != ?");
    $stmt->bind_param('si', $name, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Check if a department code is unique.
 */
function isDepartmentCodeUnique(mysqli $db, string $code, int $excludeId = 0): bool
{
    if (trim($code) === '') return true;
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM departments WHERE dept_code = ? AND id != ?");
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Toggle active status of a department.
 */
function toggleDepartmentStatus(mysqli $db, int $id): bool
{
    $stmt = $db->prepare("UPDATE departments SET is_active = 1 - is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// =============================================================================
// STEP 34 — CATEGORY MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch all categories with item counts, optionally filtered by search.
 */
function getAllCategories(mysqli $db, string $search = ''): array
{
    $whereClause = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $whereClause = 'WHERE c.cat_name LIKE ?';
        $params = ['%' . $search . '%'];
        $types = 's';
    }

    $sql = "SELECT c.*,
                   (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count
            FROM item_categories c
            {$whereClause}
            ORDER BY c.cat_name ASC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Find category by ID.
 */
function getCategoryById(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM item_categories WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Check if category name is unique.
 */
function isCategoryNameUnique(mysqli $db, string $name, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM item_categories WHERE cat_name = ? AND id != ?");
    $stmt->bind_param('si', $name, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Toggle active status of a category.
 */
function toggleCategoryStatus(mysqli $db, int $id): bool
{
    $stmt = $db->prepare("UPDATE item_categories SET is_active = 1 - is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// =============================================================================
// STEP 34 — LOCATION MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch all locations with joined parent location and department names.
 */
function getAllLocations(mysqli $db, string $search = '', string $type = ''): array
{
    $where = ['1=1'];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(l.location_name LIKE ? OR l.location_code LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    if ($type !== '') {
        $where[] = 'l.location_type = ?';
        $params[] = $type;
        $types .= 's';
    }

    $wSql = implode(' AND ', $where);
    $sql = "SELECT l.*,
                   p.location_name AS parent_name,
                   d.dept_name,
                   (SELECT COUNT(*) FROM items i WHERE i.current_location_id = l.id) AS active_item_count
            FROM locations l
            LEFT JOIN locations p   ON p.id = l.parent_id
            LEFT JOIN departments d ON d.id = l.department_id
            WHERE {$wSql}
            ORDER BY l.location_name ASC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Find location by ID.
 */
function getLocationById(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare(
        "SELECT l.*, p.location_name AS parent_name, d.dept_name
         FROM locations l
         LEFT JOIN locations p   ON p.id = l.parent_id
         LEFT JOIN departments d ON d.id = l.department_id
         WHERE l.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Check if location code is unique.
 */
function isLocationCodeUnique(mysqli $db, string $code, int $excludeId = 0): bool
{
    if (trim($code) === '') return true;
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM locations WHERE location_code = ? AND id != ?");
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Check if location name is unique.
 */
function isLocationNameUnique(mysqli $db, string $name, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM locations WHERE location_name = ? AND id != ?");
    $stmt->bind_param('si', $name, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Toggle active status of a location.
 */
function toggleLocationStatus(mysqli $db, int $id): bool
{
    $stmt = $db->prepare("UPDATE locations SET is_active = 1 - is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// =============================================================================
// STEP 34 — USER MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch all users with joined role and department info.
 */
function getAllUsers(mysqli $db, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    $types = '';

    if (!empty($filters['search'])) {
        $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'ssss';
    }

    if (!empty($filters['role_id'])) {
        $where[] = 'u.role_id = ?';
        $params[] = (int)$filters['role_id'];
        $types .= 'i';
    }

    if (!empty($filters['department_id'])) {
        $where[] = 'u.department_id = ?';
        $params[] = (int)$filters['department_id'];
        $types .= 'i';
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $where[] = 'u.is_active = ?';
        $params[] = (int)$filters['is_active'];
        $types .= 'i';
    }

    $wSql = implode(' AND ', $where);
    $sql = "SELECT u.id, u.employee_id, u.full_name, u.email, u.username,
                   u.is_active, u.last_login_at, u.created_at, u.role_id, u.department_id,
                   r.role_name, r.role_slug, d.dept_name,
                   (SELECT COUNT(*) FROM items i WHERE i.current_custodian_id = u.id) AS held_items_count
            FROM users u
            JOIN roles r            ON r.id = u.role_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE {$wSql}
            ORDER BY u.full_name ASC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Fetch a single user by ID.
 */
function getUserById(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare(
        "SELECT u.*, r.role_name, r.role_slug, d.dept_name
         FROM users u
         JOIN roles r            ON r.id = u.role_id
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Check if username is unique.
 */
function isUsernameUnique(mysqli $db, string $username, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param('si', $username, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Check if email is unique.
 */
function isEmailUnique(mysqli $db, string $email, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Check if employee_id is unique.
 */
function isEmployeeIdUnique(mysqli $db, string $empId, int $excludeId = 0): bool
{
    if (trim($empId) === '') return true;
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE employee_id = ? AND id != ?");
    $stmt->bind_param('si', $empId, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Toggle active status of a user.
 */
function toggleUserStatus(mysqli $db, int $id): bool
{
    $stmt = $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Get all available roles.
 */
function getAllRoles(mysqli $db): array
{
    $res = $db->query("SELECT * FROM roles ORDER BY id ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// =============================================================================
// STEP 34 — INVESTIGATION MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch all investigations with lead name, department name, and item counts.
 */
function getAllInvestigations(mysqli $db, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    $types = '';

    if (!empty($filters['search'])) {
        $where[] = '(inv.inv_reference LIKE ? OR inv.title LIKE ? OR inv.description LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    if (!empty($filters['status'])) {
        $where[] = 'inv.status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['department_id'])) {
        $where[] = 'inv.department_id = ?';
        $params[] = (int)$filters['department_id'];
        $types .= 'i';
    }

    $wSql = implode(' AND ', $where);
    $sql = "SELECT inv.*,
                   d.dept_name,
                   lu.full_name AS lead_name,
                   cu.full_name AS created_by_name,
                   (SELECT COUNT(*) FROM items i WHERE i.investigation_id = inv.id) AS item_count
            FROM investigations inv
            LEFT JOIN departments d ON d.id = inv.department_id
            LEFT JOIN users lu      ON lu.id = inv.lead_user_id
            LEFT JOIN users cu      ON cu.id = inv.created_by
            WHERE {$wSql}
            ORDER BY inv.created_at DESC";

    if ($types !== '') {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $res = $db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Find investigation by ID.
 */
function getInvestigationById(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare(
        "SELECT inv.*,
                d.dept_name,
                d.dept_code,
                lu.full_name AS lead_name,
                lu.employee_id AS lead_emp_id,
                cu.full_name AS created_by_name
         FROM investigations inv
         LEFT JOIN departments d ON d.id = inv.department_id
         LEFT JOIN users lu      ON lu.id = inv.lead_user_id
         LEFT JOIN users cu      ON cu.id = inv.created_by
         WHERE inv.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch all items belonging to an investigation.
 */
function getInvestigationItems(mysqli $db, int $invId): array
{
    $stmt = $db->prepare(
        "SELECT i.id, i.item_reference, i.item_name, i.description,
                i.acquisition_date, i.is_archived,
                c.cat_name AS category_name,
                u.full_name AS custodian_name,
                u.employee_id AS custodian_emp_id,
                l.location_name,
                ist.status_name,
                ist.status_slug,
                ist.color_badge
         FROM items i
         JOIN item_categories c ON c.id = i.category_id
         LEFT JOIN users u      ON u.id = i.current_custodian_id
         LEFT JOIN locations l  ON l.id = i.current_location_id
         JOIN item_statuses ist ON ist.id = i.current_status_id
         WHERE i.investigation_id = ?
         ORDER BY i.created_at DESC"
    );
    $stmt->bind_param('i', $invId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Check if investigation reference is unique.
 */
function isInvestigationRefUnique(mysqli $db, string $ref, int $excludeId = 0): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM investigations WHERE inv_reference = ? AND id != ?");
    $stmt->bind_param('si', $ref, $excludeId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count === 0;
}

/**
 * Generate a unique investigation reference in format INV-YYYY-NNN.
 */
function generateInvestigationRef(mysqli $db): string
{
    $year   = date('Y');
    $prefix = 'INV-' . $year . '-%';
    $stmt   = $db->prepare("SELECT COUNT(*) AS cnt FROM investigations WHERE inv_reference LIKE ?");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    do {
        $count++;
        $candidate = sprintf('INV-%s-%03d', $year, $count);
    } while (!isInvestigationRefUnique($db, $candidate));

    return $candidate;
}

/**
 * Get active users eligible to lead investigations (investigators and admins).
 */
function getEligibleLeadUsers(mysqli $db): array
{
    $res = $db->query(
        "SELECT u.id, u.full_name, u.employee_id, r.role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1
           AND r.role_slug IN ('administrator', 'investigator')
         ORDER BY u.full_name ASC"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// =============================================================================
// STEP 35 — ITEM LISTING & STATUS MANAGEMENT HELPERS
// =============================================================================

/**
 * Fetch paginated items with comprehensive filters.
 */
function getAllItemsPaginated(mysqli $db, array $filters = [], int $page = 1, int $perPage = 20): array
{
    $where = ['1=1'];
    $params = [];
    $types = '';

    if (!empty($filters['search'])) {
        $where[] = '(i.item_reference LIKE ? OR i.item_name LIKE ? OR i.physical_description LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    if (!empty($filters['investigation_id'])) {
        $where[] = 'i.investigation_id = ?';
        $params[] = (int)$filters['investigation_id'];
        $types .= 'i';
    }

    if (!empty($filters['category_id'])) {
        $where[] = 'i.category_id = ?';
        $params[] = (int)$filters['category_id'];
        $types .= 'i';
    }

    if (!empty($filters['status_id'])) {
        $where[] = 'i.current_status_id = ?';
        $params[] = (int)$filters['status_id'];
        $types .= 'i';
    }

    if (!empty($filters['custodian_id'])) {
        $where[] = 'i.current_custodian_id = ?';
        $params[] = (int)$filters['custodian_id'];
        $types .= 'i';
    }

    if (!empty($filters['location_id'])) {
        $where[] = 'i.current_location_id = ?';
        $params[] = (int)$filters['location_id'];
        $types .= 'i';
    }

    if (!empty($filters['restrict_to_user'])) {
        $where[] = 'i.current_custodian_id = ?';
        $params[] = (int)$filters['restrict_to_user'];
        $types .= 'i';
    }

    if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
        $where[] = 'i.is_archived = ?';
        $params[] = (int)$filters['is_archived'];
        $types .= 'i';
    }

    $wSql = implode(' AND ', $where);

    // Count total
    $cntSql = "SELECT COUNT(*) AS total FROM items i WHERE {$wSql}";
    if ($types !== '') {
        $stmt = $db->prepare($cntSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $res = $db->query($cntSql);
        $total = (int)$res->fetch_assoc()['total'];
    }

    $page = max(1, $page);
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    // Fetch page rows
    $sql = "SELECT i.id, i.item_reference, i.item_name, i.physical_description,
                   i.acquisition_date, i.is_archived, i.created_at,
                   c.cat_name AS category_name,
                   inv.id AS investigation_id, inv.inv_reference, inv.title AS investigation_title,
                   u.id AS custodian_id, u.full_name AS custodian_name, u.employee_id AS custodian_emp_id,
                   l.id AS location_id, l.location_name,
                   ist.id AS status_id, ist.status_name, ist.status_slug, ist.color_badge
            FROM items i
            JOIN item_categories c   ON c.id   = i.category_id
            JOIN investigations inv  ON inv.id = i.investigation_id
            JOIN item_statuses ist   ON ist.id = i.current_status_id
            LEFT JOIN users u        ON u.id   = i.current_custodian_id
            LEFT JOIN locations l    ON l.id   = i.current_location_id
            WHERE {$wSql}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";

    $pageParams = $params;
    $pageParams[] = $perPage;
    $pageParams[] = $offset;
    $pageTypes = $types . 'ii';

    $stmt = $db->prepare($sql);
    $stmt->bind_param($pageTypes, ...$pageParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'total'   => $total,
        'page'    => $page,
        'perPage' => $perPage,
        'pages'   => $pages,
        'rows'    => $rows,
    ];
}

/**
 * Execute a controlled, transaction-safe item status update.
 */
function updateItemStatus(mysqli $db, int $itemId, int $newStatusId, int $userId, string $userRole, ?string $reason = null): array
{
    // Auditor can never update
    if ($userRole === 'auditor') {
        return ['success' => false, 'message' => 'Auditors have read-only access and cannot update item status.'];
    }

    // Load item
    $stmt = $db->prepare("SELECT i.*, ist.status_slug, ist.status_name FROM items i JOIN item_statuses ist ON ist.id = i.current_status_id WHERE i.id = ? LIMIT 1");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        return ['success' => false, 'message' => 'Item not found.'];
    }

    if ((int)$item['is_archived'] === 1) {
        return ['success' => false, 'message' => 'Cannot update status of an archived item.'];
    }

    // Check target status
    $stmt = $db->prepare("SELECT * FROM item_statuses WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('i', $newStatusId);
    $stmt->execute();
    $newStatus = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$newStatus) {
        return ['success' => false, 'message' => 'Invalid or inactive target status.'];
    }

    if ((int)$item['current_status_id'] === $newStatusId) {
        return ['success' => false, 'message' => 'Item is already in "' . $newStatus['status_name'] . '" status.'];
    }

    // Role-specific permission check
    if ($userRole !== 'administrator') {
        // Must either hold the item, or lead the investigation
        $isCustodian = ((int)$item['current_custodian_id'] === $userId);
        
        $chkInv = $db->prepare("SELECT lead_user_id FROM investigations WHERE id = ? LIMIT 1");
        $chkInv->bind_param('i', $item['investigation_id']);
        $chkInv->execute();
        $invLead = (int)($chkInv->get_result()->fetch_assoc()['lead_user_id'] ?? 0);
        $chkInv->close();
        
        $isLead = ($invLead === $userId);

        if (!$isCustodian && !$isLead) {
            return ['success' => false, 'message' => 'You do not have authorization to change status for this item.'];
        }

        // Analyst can only change to examination-related statuses
        if ($userRole === 'analyst' && !in_array($newStatus['status_slug'], ['under_examination', 'examination_complete'], true)) {
            return ['success' => false, 'message' => 'Analysts may only transition items to examination statuses.'];
        }
    }

    // Cannot manually set to 'pending_transfer' via status updater — that is managed by transfer workflow
    if ($newStatus['status_slug'] === 'pending_transfer') {
        return ['success' => false, 'message' => '"Pending Transfer" status is managed automatically through the Custody Transfer workflow.'];
    }

    // Begin atomic transaction
    $db->begin_transaction();
    try {
        $prevStatusId = (int)$item['current_status_id'];

        // 1. Update items current_status_id
        $stmt = $db->prepare("UPDATE items SET current_status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ii', $newStatusId, $itemId);
        $stmt->execute();
        $stmt->close();

        // 2. Insert item_status_history
        $histReason = $reason ? trim($reason) : 'Manual status update by ' . $userRole;
        $stmt = $db->prepare(
            "INSERT INTO item_status_history
               (item_id, previous_status_id, new_status_id, changed_by, reason, changed_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('iiiis', $itemId, $prevStatusId, $newStatusId, $userId, $histReason);
        $stmt->execute();
        $stmt->close();

        // 3. Insert audit_logs
        $auditDesc = "Status changed from \"{$item['status_name']}\" to \"{$newStatus['status_name']}\" for item {$item['item_reference']}. Reason: {$histReason}";
        logAudit($db, $userId, 'item.status_updated', 'item', $itemId, $auditDesc);

        $db->commit();
        return ['success' => true, 'message' => "Item status updated to \"{$newStatus['status_name']}\" successfully."];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Database error during status update: ' . $e->getMessage()];
    }
}


