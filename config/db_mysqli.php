<?php
// =============================================================================
// ChainTrack — MySQLi Database Connection
// config/db_mysqli.php
//
// Provides a shared, lazily-initialised MySQLi connection for all Step-32+
// files that explicitly use MySQLi (as opposed to the PDO connection used
// by the original core/bootstrap.php).
//
// Usage in any file:
//   require_once __DIR__ . '/../config/db_mysqli.php';
//   $db = getMySQL();
// =============================================================================

// Enable MySQLi exceptions so every failure throws mysqli_sql_exception.
// This lets us use try/catch the same way we would with PDO.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------------------------
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'chaintrack');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

// ---------------------------------------------------------------------------
// Singleton factory — returns the same connection on every call.
// ---------------------------------------------------------------------------
function getMySQL(): mysqli
{
    static $mysqli = null;   // stored across calls within the same request

    if ($mysqli === null) {
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

            // All communication with the server uses UTF-8.
            $mysqli->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            // Log the real error but never expose it to the browser.
            error_log('[ChainTrack] MySQLi connection failed: ' . $e->getMessage());
            die('<h2 style="font-family:sans-serif;color:#c0392b;">
                    Database connection error. Please contact the system administrator.
                 </h2>');
        }
    }

    return $mysqli;
}
