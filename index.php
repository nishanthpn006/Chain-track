<?php
// =============================================================================
// ChainTrack — Root Application Router
// Directs requests to Dashboard (if authenticated) or Login
// Also seamlessly routes legacy /chaintrack/ paths on PHP dev server
// =============================================================================
require_once __DIR__ . '/core/bootstrap.php';

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// If request has /chaintrack/ prefix while running at document root (php -S -t .)
if (BASE_PATH === '' && str_starts_with($reqPath, '/chaintrack/')) {
    $target = substr($reqPath, strlen('/chaintrack'));
    $localFile = __DIR__ . $target;
    
    // Serve static assets directly with proper content-type
    if (is_file($localFile) && !str_ends_with($localFile, '.php')) {
        $ext = strtolower(pathinfo($localFile, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($localFile);
        exit;
    }

    // Redirect PHP requests to the clean path without /chaintrack
    $query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $target . $query, true, 302);
    exit;
}

// Normal root landing redirect
if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/views/dashboard/index.php');
} else {
    header('Location: ' . BASE_PATH . '/views/auth/login.php');
}
exit;
