<?php
/**
 * Database Connection Handler (PDO)
 * Supports MySQL by default, with automatic SQLite fallback for portable offline demos.
 * Includes transaction management, robust error handling, and structured logging.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // 1. Try MySQL first
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            // Enable SSL for Cloud Hosted MySQL (TiDB Cloud, Aiven, PlanetScale, Railway)
            $caPaths = [
                '/etc/ssl/certs/ca-certificates.crt', // Standard Linux / Debian (Render Docker)
                '/etc/pki/tls/certs/ca-bundle.crt',   // RedHat / CentOS
                '/etc/ssl/ca-bundle.pem',             // Alpine
                'C:/xampp/apache/bin/curl-ca-bundle.crt', // Windows XAMPP
                'C:/xampp/sendmail/curl-ca-bundle.crt'
            ];
            foreach ($caPaths as $ca) {
                if (file_exists($ca)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                    break;
                }
            }
            if (!isset($options[PDO::MYSQL_ATTR_SSL_CA]) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') && env('DB_SSL_VERIFY', false) === false) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::$activeDriver = 'mysql';
            return self::$instance;
        } catch (PDOException $e) {
            Logger::warning("MySQL connection failed, checking SQLite fallback: " . $e->getMessage());

            // 2. If MySQL failed, check if SQLite database is available
            if (defined('SQLITE_PATH') && file_exists(SQLITE_PATH)) {
                try {
                    $dsn = "sqlite:" . SQLITE_PATH;
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ];
                    self::$instance = new PDO($dsn, null, null, $options);
                    // Enable SQLite foreign keys
                    self::$instance->exec("PRAGMA foreign_keys = ON;");
                    self::$activeDriver = 'sqlite';
                    Logger::info("Connected to SQLite fallback database successfully.");
                    return self::$instance;
                } catch (PDOException $sqle) {
                    Logger::error("SQLite fallback also failed: " . $sqle->getMessage());
                }
            }

            // 3. Log critical connection error
            Logger::critical("Database Connection Failed: Neither MySQL nor SQLite available.", [
                'mysql_error' => $e->getMessage(),
                'db_host' => DB_HOST,
                'db_name' => DB_NAME
            ]);

            // Handle API requests with clean JSON
            if (isJsonRequest()) {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'success' => false,
                    'error'   => 'Database Connection Error',
                    'message' => APP_DEBUG ? $e->getMessage() : 'Database service is temporarily unavailable.'
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            // Handle Web requests with user-friendly setup prompt
            if (!headers_sent()) {
                http_response_code(500);
            }
            $errorDetail = APP_DEBUG ? "<p>Error: <b>" . htmlspecialchars($e->getMessage()) . "</b></p>" : "";
            die("<div style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:24px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:8px;'>"
                . "<h3 style='margin-top:0;'>Database Connection Failed</h3>"
                . "<p>Could not connect to the database server.</p>"
                . $errorDetail
                . "<p>To initialize the database automatically, run the installer:</p>"
                . "<p><a href='" . BASE_URL . "/database/setup.php' style='display:inline-block;padding:10px 18px;background:#0d6efd;color:white;text-decoration:none;border-radius:4px;'>Run Database Installer (setup.php)</a></p>"
                . "</div>");
        }
    }

    public static function getDriver(): string {
        return self::$activeDriver;
    }

    /**
     * Execute a callback inside a database transaction
     */
    public static function transaction(callable $callback): mixed {
        $db = self::getConnection();
        $isTopLevel = !$db->inTransaction();

        if ($isTopLevel) {
            $db->beginTransaction();
        }

        try {
            $result = $callback($db);
            if ($isTopLevel && $db->inTransaction()) {
                $db->commit();
            }
            return $result;
        } catch (\Throwable $t) {
            if ($isTopLevel && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error("Transaction rolled back due to exception: " . $t->getMessage(), [
                'exception' => get_class($t),
                'file' => $t->getFile(),
                'line' => $t->getLine()
            ]);
            throw $t;
        }
    }

    public static function inTransaction(): bool {
        return self::$instance !== null && self::$instance->inTransaction();
    }
}

// Global convenience function
function getDB(): PDO {
    return Database::getConnection();
}
