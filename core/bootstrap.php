<?php
// core/bootstrap.php -- included at the top of EVERY page
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
// Auto-load models
spl_autoload_register(function(string $class) {
    $file = __DIR__ . '/../models/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});
startSecureSession();
