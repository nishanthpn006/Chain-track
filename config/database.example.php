<?php
// =============================================================================
// ChainTrack – Database Connection Configuration Template
// Copy this file to config/database.php and enter your local credentials.
// =============================================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'chaintrack');
define('DB_USER',    'root');
define('DB_PASS',    'YOUR_LOCAL_MYSQL_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose DB details to end users
            error_log('ChainTrack DB connection failed: ' . $e->getMessage());
            die('<h2 style="font-family:sans-serif;color:#c0392b">Database connection error. Please contact the system administrator.</h2>');
        }
    }
    return $pdo;
}
