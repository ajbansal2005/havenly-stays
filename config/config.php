<?php
/**
 * StayHub - Application Configuration
 * Loads environment variables from .env file with secure defaults,
 * initializes session management, security headers, global error handling, and structured logging.
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
def('STORAGE_LOGS_DIR', ROOT_PATH . '/storage/logs/');

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

def('APP_ENV', env('APP_ENV', $isCloud ? 'production' : 'development'));
$isDebug = filter_var(env('APP_DEBUG', !$isCloud), FILTER_VALIDATE_BOOLEAN);
def('APP_DEBUG', $isDebug);

// Secure Session Cookie Settings (Configured before session_start)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || $isCloud;

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }
}

// Security HTTP Headers (Prevent Clickjacking, MIME-Sniffing, XSS, Info Leaks)
if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: camera=(), microphone=(), payment=(self)');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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

// Start Session with Inactivity Expiration (2 Hours)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();

    // Session inactivity timeout (7200 seconds / 2 hours)
    $maxInactivity = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxInactivity)) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
        @session_start();
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Your session expired due to inactivity. Please log in again.'
        ];
    }
    $_SESSION['last_activity'] = time();
}

// Load logger early
require_once __DIR__ . '/../includes/logger.php';

// Environment & Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

/**
 * Determine if current request expects a JSON response
 */
function isJsonRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($uri, '/api/')) {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json')) {
        return true;
    }
    $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return strtolower($xRequestedWith) === 'xmlhttprequest';
}

/**
 * Production-safe Global Exception Handler
 */
set_exception_handler(function (\Throwable $e): void {
    Logger::exception($e);

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (isJsonRequest()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $response = [
            'success' => false,
            'error'   => 'Internal Server Error',
            'message' => APP_DEBUG ? $e->getMessage() : 'An unexpected error occurred. Please try again later.'
        ];
        if (APP_DEBUG) {
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = explode("\n", $e->getTraceAsString());
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Web response
    if (APP_DEBUG) {
        echo "<div style='font-family:monospace;padding:24px;background:#fff0f0;border-left:5px solid #dc3545;margin:20px;border-radius:4px;'>";
        echo "<h3 style='color:#dc3545;margin-top:0;'>Fatal Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
        echo "<p><b>File:</b> " . htmlspecialchars($e->getFile()) . " on line <b>" . $e->getLine() . "</b></p>";
        echo "<pre style='background:#f8f9fa;padding:12px;overflow:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        $base = defined('BASE_URL') ? BASE_URL : '/';
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>500 - Server Error</title>"
           . "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>"
           . "</head><body class='bg-light d-flex align-items-center min-vh-100'><div class='container text-center py-5'>"
           . "<div class='card shadow-sm border-0 rounded-4 p-5 mx-auto' style='max-width:550px;'>"
           . "<div class='text-danger mb-3' style='font-size:4rem;'><i class='bi bi-exclamation-triangle'></i>500</div>"
           . "<h2 class='fw-bold mb-2'>Something went wrong</h2>"
           . "<p class='text-muted mb-4'>We encountered an unexpected error on our server. Our team has been automatically notified.</p>"
           . "<a href='{$base}/' class='btn btn-danger rounded-pill px-4'>Return to Homepage</a>"
           . "</div></div></body></html>";
    }
    exit;
});

/**
 * Global Error Handler: Convert critical PHP errors to ErrorException
 */
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // Only throw exception on severe errors; log warnings/notices
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    Logger::warning("PHP Notice/Warning: {$errstr}", [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ]);
    return true;
});

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

// Sanitize Currency Symbol so numbers or corrupted strings never overwrite it
$rawCurrency = (string)env('CURRENCY_SYMBOL', '₹');
if (empty($rawCurrency) || is_numeric($rawCurrency) || mb_strlen($rawCurrency) > 5) {
    $rawCurrency = '₹';
}
def('CURRENCY_SYMBOL', $rawCurrency);
def('CURRENCY_CODE', env('CURRENCY_CODE', 'INR'));

// Base URL calculation (with automatic detection for Render and Vercel domains)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
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

// Razorpay Test Credentials (Loaded from .env)
def('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID', ''));
def('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET', ''));
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
