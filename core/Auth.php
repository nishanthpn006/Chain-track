<?php
// =============================================================================
// ChainTrack – Authentication Core
// =============================================================================

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // set true in production with HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_role']  = $user['role_slug'];
    $_SESSION['role_id']    = $user['role_id'];
    $_SESSION['user_dept']  = $user['user_dept'] ?? 'Central Investigation';
    $_SESSION['last_active'] = time();
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function checkSessionTimeout(): void {
    if (isLoggedIn()) {
        if (time() - ($_SESSION['last_active'] ?? 0) > SESSION_TIMEOUT) {
            logoutUser();
            startSecureSession();
            setFlash('warning', 'Your session has expired. Please log in again.');
            redirect((defined('BASE_PATH') ? BASE_PATH : '') . '/views/auth/login.php');
        }
        $_SESSION['last_active'] = time();
    }
}

/**
 * Attempt to log in a user by username + password.
 * Returns the user array on success, or false on failure.
 */
function attemptLogin(string $username, string $password): array|false {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.email, u.password_hash, u.is_active,
                u.role_id, r.role_slug, d.dept_name AS user_dept
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.username = :username
         LIMIT 1"
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    // Update last_login_at
    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
        ->execute([':id' => $user['id']]);

    return $user;
}

