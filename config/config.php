<?php
/**
 * StayHub - Application Configuration
 * Loads environment variables from .env file with secure defaults.
 */

// Include Guard: Prevent duplicate loading and constant redefinition warnings
if (defined('STAYHUB_CONFIG_LOADED')) {
    return;
}
define('STAYHUB_CONFIG_LOADED', true);

// Safe constant definition helper
if (!function_exists('def')) {
    function def(string $name, mixed $val): void {
        if (!defined($name)) {
            define($name, $val);
        }
    }
}

// Define Root Path
def('ROOT_PATH', dirname(__DIR__));

/**
 * Lightweight .env file parser & loader
 */
function loadEnv(string $filePath): void {
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip enclosing quotes (double or single)
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Populate $_ENV, $_SERVER and putenv if not already set
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

/**
 * Retrieve environment variable with default fallback and type conversion
 */
function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($val === false || $val === null || $val === '') {
        return $default;
    }

    // Convert boolean string representations
    $lower = strtolower((string)$val);
    if ($lower === 'true' || $lower === '(true)') {
        return true;
    }
    if ($lower === 'false' || $lower === '(false)') {
        return false;
    }
    if ($lower === 'null' || $lower === '(null)') {
        return null;
    }

    return $val;
}

// Load local .env if present (Render/Vercel inject variables directly into process env)
loadEnv(ROOT_PATH . '/.env');

// Detect serverless & cloud container environments
$isVercel = !empty(env('VERCEL')) || !empty($_SERVER['VERCEL']);
$isRender = !empty(env('RENDER')) || !empty($_SERVER['RENDER']);
$isCloud  = $isRender || $isVercel || (env('APP_ENV') === 'production');

// Secure Session Cookie Settings (Configured before session_start)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || $isCloud;

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }
}

// Security HTTP Headers (Prevent Clickjacking, MIME-Sniffing, XSS)
if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Parse cloud database connection string if DATABASE_URL is provided (e.g. Render / Railway / Aiven)
$dbUrl = env('DATABASE_URL');
$parsedDb = [];
if (!empty($dbUrl)) {
    $dbParts = parse_url($dbUrl);
    if ($dbParts !== false) {
        $parsedDb['host'] = $dbParts['host'] ?? null;
        $parsedDb['port'] = $dbParts['port'] ?? null;
        $parsedDb['user'] = $dbParts['user'] ?? null;
        $parsedDb['pass'] = $dbParts['pass'] ?? null;
        $parsedDb['name'] = isset($dbParts['path']) ? ltrim($dbParts['path'], '/') : null;
    }
}

// Session Directory Configuration (Vercel has read-only filesystem outside /tmp)
if ($isVercel) {
    $sessionDir = sys_get_temp_dir() . '/stayhub_sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }
    @session_save_path($sessionDir);

    // If using SQLite fallback on Vercel, copy it to /tmp
    $tempSqlite = sys_get_temp_dir() . '/airbnb.sqlite';
    if (!file_exists($tempSqlite) && file_exists(ROOT_PATH . '/database/airbnb.sqlite')) {
        @copy(ROOT_PATH . '/database/airbnb.sqlite', $tempSqlite);
    }
    def('SQLITE_PATH', file_exists($tempSqlite) ? $tempSqlite : ROOT_PATH . '/database/airbnb.sqlite');
} else {
    $sessionDir = ROOT_PATH . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0777, true);
    }
    if (is_dir($sessionDir)) {
        @session_save_path($sessionDir);
    }
    def('SQLITE_PATH', ROOT_PATH . '/database/airbnb.sqlite');
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Environment & Error reporting (Strictly suppress display_errors in production and cloud to prevent broken layouts)
$isDebug = filter_var(env('APP_DEBUG', !$isCloud), FILTER_VALIDATE_BOOLEAN);
if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Database Configuration (Loaded from .env or parsed cloud URL)
def('DB_TYPE', env('DB_TYPE', 'mysql')); // 'mysql' or 'sqlite'
def('DB_HOST', $parsedDb['host'] ?? env('DB_HOST', '127.0.0.1'));
def('DB_PORT', (string)($parsedDb['port'] ?? env('DB_PORT', '3306')));
def('DB_NAME', $parsedDb['name'] ?? env('DB_NAME', 'airbnb_db'));
def('DB_USER', $parsedDb['user'] ?? env('DB_USER', 'root'));
def('DB_PASS', $parsedDb['pass'] ?? env('DB_PASS', ''));

// App Info
def('APP_NAME', env('APP_NAME', 'StayHub'));
def('APP_TAGLINE', env('APP_TAGLINE', 'Find and book unique stays around the world'));
def('CURRENCY_SYMBOL', env('CURRENCY_SYMBOL', '₹'));
def('CURRENCY_CODE', env('CURRENCY_CODE', 'INR'));

// Base URL calculation (with automatic detection for Render and Vercel domains)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || $isCloud;

$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($protocol . $host . $scriptName, '/');
// Strip subfolders like /host or /admin or /api from base URL if current script is inside them
$baseUrl = preg_replace('/(\/host|\/admin|\/api|\/database)$/', '', $baseUrl);

if (!empty($_SERVER['RENDER_EXTERNAL_URL'])) {
    $baseUrl = rtrim($_SERVER['RENDER_EXTERNAL_URL'], '/');
    if (!str_starts_with($baseUrl, 'http')) {
        $baseUrl = 'https://' . $baseUrl;
    }
} elseif (!empty($_SERVER['RENDER_EXTERNAL_HOSTNAME'])) {
    $baseUrl = 'https://' . rtrim($_SERVER['RENDER_EXTERNAL_HOSTNAME'], '/');
} elseif (!empty($_SERVER['VERCEL_URL'])) {
    $baseUrl = 'https://' . rtrim($_SERVER['VERCEL_URL'], '/');
}
def('BASE_URL', $baseUrl);

// Uploads & Paths
def('UPLOAD_DIR', ROOT_PATH . '/uploads/listings/');
def('UPLOAD_URL', BASE_URL . '/uploads/listings/');
def('STORAGE_LOGS_DIR', ROOT_PATH . '/storage/logs/');

// Razorpay Test Credentials (Loaded from .env)
def('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID', 'rzp_test_1DP5mmOlF5G5ag'));
def('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET', 'test_secret_key_123456'));
def('RAZORPAY_TEST_MODE', env('RAZORPAY_TEST_MODE', true));

// Mail Configuration (Loaded from .env)
def('MAIL_ENABLED', env('MAIL_ENABLED', true));
def('SMTP_HOST', env('SMTP_HOST', 'sandbox.smtp.mailtrap.io'));
def('SMTP_PORT', (int)env('SMTP_PORT', 2525));
def('SMTP_USER', env('SMTP_USER', ''));
def('SMTP_PASS', env('SMTP_PASS', ''));
def('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'no-reply@stayhub.test'));
def('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'StayHub Bookings'));

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));
