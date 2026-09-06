<?php
// =============================================================================
// ChainTrack – Application Configuration
// =============================================================================

define('APP_NAME',    'ChainTrack');
define('APP_VERSION', '1.0.0');

// Dynamic base path: empty string '' when served at document root (e.g. php -S localhost:8000 -t .),
// or '/chaintrack' when served from a subfolder (e.g. XAMPP htdocs).
if (!defined('BASE_PATH')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(__DIR__ . '/..');
    if ($docRoot && $appRoot && $docRoot === $appRoot) {
        define('BASE_PATH', '');
    } elseif ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $sub = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        define('BASE_PATH', rtrim($sub, '/'));
    } else {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        define('BASE_PATH', str_starts_with($uri, '/chaintrack') ? '/chaintrack' : '');
    }
}
if (!defined('BASE_URL')) {
    define('BASE_URL', BASE_PATH);
}

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Item reference prefix (EVD-YYYY-NNNNN)
define('ITEM_REF_PREFIX',     'EVD');
// Transfer reference prefix (TRF-YYYY-NNNNN)
define('TRANSFER_REF_PREFIX', 'TRF');
// Investigation reference prefix
define('INV_REF_PREFIX',      'INV');

// Role slugs – use these constants everywhere, never raw strings
define('ROLE_ADMIN',       'administrator');
define('ROLE_INVESTIGATOR','investigator');
define('ROLE_CUSTODIAN',   'custodian');
define('ROLE_ANALYST',     'analyst');
define('ROLE_AUDITOR',     'auditor');

// Transfer statuses
define('TRANSFER_INITIATED', 'initiated');
define('TRANSFER_PENDING',   'pending');
define('TRANSFER_CONFIRMED', 'confirmed');
define('TRANSFER_REJECTED',  'rejected');

// Item status slugs
define('STATUS_REGISTERED',   'registered');
define('STATUS_IN_STORAGE',   'in_storage');
define('STATUS_PENDING_TRF',  'pending_transfer');
define('STATUS_IN_TRANSIT',   'in_transit');
define('STATUS_RECEIVED',     'received');
define('STATUS_EXAMINING',    'under_examination');
define('STATUS_EXAM_DONE',    'examination_complete');
define('STATUS_RETURNED',     'returned_storage');
define('STATUS_ARCHIVED',     'archived');
define('STATUS_CLOSED',       'closed');

// Date/time formats
define('DT_FORMAT',  'd M Y, H:i');
define('DATE_FORMAT','d M Y');
