<?php
// =============================================================================
// ChainTrack — Session / Authentication Guard
// includes/auth_check.php
//
// Include at the very top of every protected page (before any HTML output).
// Verifies that:
//   1. A session is active.
//   2. The user is logged in (user_id and user_role set in session).
//   3. The session has not expired due to inactivity.
//   4. The caller has the required role (if $requiredRoles is provided).
//
// Usage:
//   // Any logged-in user:
//   require_once __DIR__ . '/../includes/auth_check.php';
//   requireLogin();
//
//   // Only investigators or administrators:
//   require_once __DIR__ . '/../includes/auth_check.php';
//   requireRole('administrator', 'investigator');
// =============================================================================

require_once __DIR__ . '/../config/app.php';

// Maximum inactive seconds before session expires (30 minutes)
const SESSION_TIMEOUT_SECONDS = 1800;

// Login page URL (absolute path from web root)
const LOGIN_URL = '/views/auth/login.php';

/**
 * Start or resume a secure PHP session.
 * Sets strict, HttpOnly, SameSite cookie flags.
 */
function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,          // expires when browser closes
            'path'     => '/',
            'secure'   => false,      // set true in production (HTTPS)
            'httponly' => true,       // JS cannot read the session cookie
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Regenerate the session ID periodically to prevent session fixation.
 */
function regenerateIfDue(): void
{
    $interval = 300; // regenerate every 5 minutes
    if (isset($_SESSION['last_regenerated'])) {
        if (time() - $_SESSION['last_regenerated'] > $interval) {
            session_regenerate_id(true);
            $_SESSION['last_regenerated'] = time();
        }
    } else {
        $_SESSION['last_regenerated'] = time();
    }
}

function getAuthBasePath(): string
{
    if (defined('BASE_PATH')) return BASE_PATH;
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(__DIR__ . '/..');
    if ($docRoot && $appRoot && $docRoot === $appRoot) {
        return '';
    } elseif ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        return rtrim(str_replace('\\', '/', substr($appRoot, strlen($docRoot))), '/');
    }
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/chaintrack') ? '/chaintrack' : '';
}

/**
 * Destroy the session and redirect to the login page.
 */
function forceLogout(string $reason = ''): never
{
    session_unset();
    session_destroy();
    $query = $reason ? '?reason=' . urlencode($reason) : '';
    header('Location: ' . getAuthBasePath() . '/views/auth/login.php' . $query);
    exit;
}

/**
 * Require the user to be logged in.
 * Checks session validity and inactivity timeout.
 * Redirects to login if the session is missing or expired.
 */
function requireLogin(): void
{
    ensureSession();

    // Must have logged-in session data
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        forceLogout('not_logged_in');
    }

    // Inactivity timeout check
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS) {
            forceLogout('session_expired');
        }
    }

    // Update last activity timestamp on every valid request
    $_SESSION['last_activity'] = time();

    regenerateIfDue();
}

/**
 * Require the logged-in user to have one of the specified role slugs.
 * Calls requireLogin() first, so the session is always validated.
 *
 * @param string ...$roles  e.g. 'administrator', 'investigator'
 */
function requireRole(string ...$roles): void
{
    requireLogin();

    if (!in_array($_SESSION['user_role'], $roles, true)) {
        // Forbidden — redirect to dashboard with an error flash
        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => 'Access denied. You do not have permission to view that page.',
        ];
        header('Location: ' . getAuthBasePath() . '/views/dashboard/index.php');
        exit;
    }
}

/**
 * Convenience wrapper — returns the current user ID from session.
 */
function sessionUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Convenience wrapper — returns the current user role slug from session.
 */
function sessionUserRole(): string
{
    return $_SESSION['user_role'] ?? '';
}
