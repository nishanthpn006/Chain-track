<?php
// =============================================================================
// ChainTrack – Shared Helper Functions
// =============================================================================

/**
 * Escape output for safe HTML rendering.
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!function_exists('esc')) {
    function esc(mixed $value): string {
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

/**
 * Format a datetime string for display.
 */
function fmtDt(?string $dt): string {
    if (empty($dt)) return '—';
    return date(DT_FORMAT, strtotime($dt));
}

/**
 * Format a date string for display.
 */
function fmtDate(?string $d): string {
    if (empty($d)) return '—';
    return date(DATE_FORMAT, strtotime($d));
}

/**
 * Generate a unique item reference: EVD-YYYY-NNNNN
 */
function generateItemReference(): string {
    $pdo  = getDB();
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM items WHERE item_reference LIKE :prefix"
    );
    $stmt->execute([':prefix' => ITEM_REF_PREFIX . '-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    return sprintf('%s-%s-%05d', ITEM_REF_PREFIX, $year, $count + 1);
}

/**
 * Generate a unique transfer reference: TRF-YYYY-NNNNN
 */
function generateTransferReference(): string {
    $pdo  = getDB();
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM custody_transfers WHERE transfer_reference LIKE :prefix"
    );
    $stmt->execute([':prefix' => TRANSFER_REF_PREFIX . '-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    return sprintf('%s-%s-%05d', TRANSFER_REF_PREFIX, $year, $count + 1);
}

/**
 * Generate a unique investigation reference: INV-YYYY-NNN
 */
function generateInvReference(): string {
    $pdo  = getDB();
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM investigations WHERE inv_reference LIKE :prefix"
    );
    $stmt->execute([':prefix' => INV_REF_PREFIX . '-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    return sprintf('%s-%s-%03d', INV_REF_PREFIX, $year, $count + 1);
}

/**
 * Return Bootstrap badge HTML for a given color variant.
 */
function statusBadge(string $name, string $color): string {
    return '<span class="badge bg-' . e($color) . '">' . e($name) . '</span>';
}

/**
 * Return Bootstrap badge HTML for a transfer status.
 */
function transferBadge(string $status): string {
    $map = [
        'initiated' => ['label' => 'Initiated', 'color' => 'secondary'],
        'pending'   => ['label' => 'Pending Receipt', 'color' => 'warning'],
        'confirmed' => ['label' => 'Confirmed',  'color' => 'success'],
        'rejected'  => ['label' => 'Rejected',   'color' => 'danger'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    return '<span class="badge bg-' . $s['color'] . '">' . e($s['label']) . '</span>';
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Set a one-time flash message in session.
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Return GET/POST param safely or a default.
 */
function input(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Truncate a string to N characters.
 */
function truncate(string $str, int $len = 60): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . '…' : $str;
    }
    return strlen($str) > $len ? substr($str, 0, $len) . '…' : $str;
}

/**
 * Check if the current user has one of the given role slugs.
 */
function hasRole(string ...$roles): bool {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles, true);
}

/**
 * Get the current logged-in user ID.
 */
function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Get the current logged-in user role slug.
 */
function currentRole(): string {
    return $_SESSION['user_role'] ?? '';
}
