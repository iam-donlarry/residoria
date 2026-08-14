<?php
// config/constants.php - Residoria System Defines & Application Constants

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Residoria');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '3.3.0');
}

if (!defined('PRIMARY_COLOR')) {
    define('PRIMARY_COLOR', '#12B1B0');
}

if (!defined('DEFAULT_FONT')) {
    define('DEFAULT_FONT', 'Outfit');
}

// Security Defines
if (!defined('CSRF_TOKEN_KEY')) {
    define('CSRF_TOKEN_KEY', 'residoria_csrf_token');
}

// Session Timeout (30 minutes)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800);
}

// Base URL helper for robust asset resolution across all module depths
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = preg_replace('#/(modules|admin|api|ajax)(/.*)?$#i', '', $scriptDir);
    define('BASE_URL', rtrim($base, '/') . '/');
}
