<?php
/**
 * StayHub Production-Grade Application & Security Logger
 * Provides structured, leveled logging with daily rotation and security auditing.
 */

if (defined('STAYHUB_LOGGER_LOADED')) {
    return;
}
define('STAYHUB_LOGGER_LOADED', true);

require_once __DIR__ . '/../config/config.php';

class Logger {
    public const DEBUG    = 'DEBUG';
    public const INFO     = 'INFO';
    public const WARNING  = 'WARNING';
    public const ERROR    = 'ERROR';
    public const CRITICAL = 'CRITICAL';
    public const SECURITY = 'SECURITY';

    private static ?string $logDir = null;

    /**
     * Get and ensure storage log directory exists and is protected
     */
    public static function getLogDir(): string {
        if (self::$logDir === null) {
            self::$logDir = defined('STORAGE_LOGS_DIR') ? STORAGE_LOGS_DIR : dirname(__DIR__) . '/storage/logs/';
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
            // Ensure .htaccess protects the logs directory
            $htaccessFile = self::$logDir . '.htaccess';
            if (!file_exists($htaccessFile)) {
                @file_put_contents($htaccessFile, "# Deny all direct web access to logs\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
            }
        }
        return self::$logDir;
    }

    /**
     * Core logging function
     */
    public static function log(string $level, string $message, array $context = []): void {
        try {
            $dir = self::getLogDir();
            $date = date('Y-m-d');
            $timestamp = date('Y-m-d H:i:s');

            // Sanitize message to prevent log injection (newlines in message)
            $cleanMessage = str_replace(["\r", "\n"], ' ', trim($message));

            // Extract request context
            $ip = self::getClientIp();
            $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 'guest';
            $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

            $logData = [
                'timestamp' => $timestamp,
                'level'     => strtoupper($level),
                'ip'        => $ip,
                'user_id'   => $userId,
                'method'    => $method,
                'uri'       => $uri,
                'message'   => $cleanMessage
            ];

            if (!empty($context)) {
                // Remove sensitive keys from context if present
                $filteredContext = self::filterSensitiveData($context);
                $logData['context'] = $filteredContext;
            }

            $line = json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

            // Write to daily application log
            $appLogFile = $dir . "app-{$date}.log";
            @file_put_contents($appLogFile, $line, FILE_APPEND | LOCK_EX);

            // If security or error level, also append to dedicated log files
            if ($level === self::SECURITY) {
                @file_put_contents($dir . 'security.log', $line, FILE_APPEND | LOCK_EX);
            } elseif (in_array($level, [self::ERROR, self::CRITICAL], true)) {
                @file_put_contents($dir . 'error.log', $line, FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $t) {
            // Failsafe: Use PHP system error log if file write fails
            error_log("[StayHub Logger Failure] " . $t->getMessage());
        }
    }

    public static function debug(string $message, array $context = []): void {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            self::log(self::DEBUG, $message, $context);
        }
    }

    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function security(string $message, array $context = []): void {
        self::log(self::SECURITY, $message, $context);
    }

    /**
     * Log an exception with full stack trace and code location
     */
    public static function exception(\Throwable $e, array $context = []): void {
        $context['exception'] = [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => explode("\n", $e->getTraceAsString())
        ];
        self::log(self::ERROR, "Exception: " . $e->getMessage(), $context);
    }

    /**
     * Safely determine client IP behind reverse proxies/CDNs (Render, Vercel, Cloudflare)
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }

    /**
     * Filter out sensitive values (passwords, tokens, credit card, secrets) from log context
     */
    private static function filterSensitiveData(array $data): array {
        $sensitiveKeys = ['password', 'confirm_password', 'secret', 'token', 'csrf_token', 'razorpay_key_secret', 'card', 'cvv', 'credit_card'];
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::filterSensitiveData($value);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
