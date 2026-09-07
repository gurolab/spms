<?php

define('BASE_PATH', __DIR__ . '/');

// Load environment overrides if present
$envFile = dirname(__DIR__) . '/.env.php';
if (file_exists($envFile)) {
    $env = require $envFile;
} else {
    $env = [];
}

define('BASE_URL', $env['BASE_URL'] ?? getenv('BASE_URL') ?: 'https://spms.cotsu.live/');
define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'cotsu-spms');
define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?: 'cotsu-spms');
define('DB_PASS', $env['DB_PASS'] ?? getenv('DB_PASS') ?: '');

define('EMAIL_USER', $env['EMAIL_USER'] ?? getenv('EMAIL_USER') ?: '');
define('EMAIL_PASS', $env['EMAIL_PASS'] ?? getenv('EMAIL_PASS') ?: '');


//define('BASE_URL', $env['BASE_URL'] ?? getenv('BASE_URL') ?: 'http://localhost/spms.cotsu.live/');
//define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
//define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'spms');
//define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?: 'root');
//define('DB_PASS', $env['DB_PASS'] ?? getenv('DB_PASS') ?: '');
define('DB_CHARSET', $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

define('CSS_PATH', BASE_URL.'assets/css');
define('JS_PATH', BASE_URL.'assets/js');
define('IMG_PATH', BASE_URL.'assets/images');
define('ASSETS_PATH', BASE_URL.'assets');
define('CORE_PATH', BASE_URL.'core/');
define('ADMIN_PATH', BASE_URL.'admin/');

// Session hardening: set cookie flags and strict settings before session_start
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (parse_url(BASE_URL, PHP_URL_SCHEME) === 'https');

    // Enforce strict session behavior
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    // SameSite: Strict in prod (HTTPS), Lax in non-HTTPS dev
    $cookieParams = session_get_cookie_params();
    $sameSite = $isHttps ? 'Strict' : 'Lax';

    // Some SAPIs also respect this ini setting; harmless if unsupported
    @ini_set('session.cookie_samesite', $sameSite);

    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);

    session_start();
}

// Configurable email domain allowlist for login validation
if (!defined('ALLOWED_EMAIL_DOMAINS')) {
    define('ALLOWED_EMAIL_DOMAINS', ['gmail.com', 'cotsu.edu.ph']);
}