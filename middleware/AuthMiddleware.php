<?php
// =============================================================================
// ChainTrack – Authorization Middleware
// Call require_login() at the top of every protected page.
// Call require_role('administrator','custodian') etc. for role-specific pages.
// =============================================================================

function require_login(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to access this page.');
        $p = defined('BASE_PATH') ? BASE_PATH : '';
        redirect($p . '/views/auth/login.php');
    }
    checkSessionTimeout();
}

function require_role(string ...$roles): void {
    require_login();
    if (!hasRole(...$roles)) {
        setFlash('danger', 'You do not have permission to access that page.');
        $p = defined('BASE_PATH') ? BASE_PATH : '';
        redirect($p . '/views/dashboard/index.php');
    }
}

/**
 * Check if the current user can initiate/confirm a transfer for an item.
 * Auditors can never perform transfers.
 */
function require_transfer_permission(): void {
    require_login();
    if (hasRole(ROLE_AUDITOR)) {
        setFlash('danger', 'Auditors cannot initiate or confirm transfers.');
        $p = defined('BASE_PATH') ? BASE_PATH : '';
        redirect($p . '/views/dashboard/index.php');
    }
}
