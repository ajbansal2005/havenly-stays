<?php
/**
 * StayHub Authentication & Authorization Helper Functions
 * Provides session security, CSRF protection with header support,
 * role authorization, rate limiting, and security event auditing.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function getCurrentUserId(): ?int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0;
}

function hasRole(string|array $roles): bool {
    if (!isLoggedIn()) return false;
    $rolesArray = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['user']['role'] ?? '';
    return in_array($userRole, $rolesArray, true);
}

function requireLogin(string $redirectTo = ''): void {
    if (!isLoggedIn()) {
        $rawTarget = $redirectTo ?: ($_SERVER['REQUEST_URI'] ?? '/');
        $safeTarget = safeRedirectUrl($rawTarget);
        setFlash('warning', 'Please log in to continue.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($safeTarget));
        exit;
    }
}

function requireRole(string|array $roles): void {
    requireLogin();
    $rolesArray = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['user']['role'] ?? '';

    if (!in_array($userRole, $rolesArray, true)) {
        $userId = getCurrentUserId();
        Logger::security("Unauthorized role access blocked: User {$userId} with role '{$userRole}' attempted to access route requiring: " . implode(',', $rolesArray));

        if (isJsonRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Forbidden',
                'message' => 'You do not have permission to perform this action.'
            ]);
            exit;
        }

        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo "<div class='container py-5 text-center'>"
            . "<div class='card shadow-sm p-5 mx-auto rounded-4 border-0' style='max-width: 500px;'>"
            . "<i class='fa-solid fa-lock text-danger fa-3x mb-3'></i>"
            . "<h3 class='fw-bold'>Access Denied</h3>"
            . "<p class='text-muted'>You do not have the required permissions to view this section.</p>"
            . "<a href='" . BASE_URL . "/' class='btn btn-danger rounded-pill px-4'>Return Home</a>"
            . "</div></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token = null): bool {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    // If token wasn't explicitly passed, inspect POST, GET, or HTTP request headers
    if (empty($token)) {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? ($_GET['csrf_token'] ?? null);
    }

    if (empty($token) || !is_string($token)) {
        Logger::security("CSRF verification failed: missing token.");
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        Logger::security("CSRF verification failed: token mismatch.");
    }
    return $valid;
}

function loginUser(array $user): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Prevent session fixation
        session_regenerate_id(true);
    }

    unset($user['password']); // Never store password hash in session
    $_SESSION['user'] = $user;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // Reset failed login tracking
    unset($_SESSION['login_failed_attempts'], $_SESSION['login_lockout_until']);

    Logger::security("User authentication successful", [
        'user_id' => $user['id'],
        'email'   => $user['email'],
        'role'    => $user['role']
    ]);
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $userId = getCurrentUserId();
        if ($userId) {
            Logger::security("User logged out", ['user_id' => $userId]);
        }
        unset($_SESSION['user'], $_SESSION['login_time']);
        // Regenerate session id safely so any subsequent flash messages persist cleanly
        session_regenerate_id(true);
    }
}

/**
 * In-memory / session-based rate limiter for brute-force prevention
 */
function checkRateLimit(string $actionKey, int $maxAttempts = 5, int $decaySeconds = 60): bool {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    $now = time();
    $rateData = $_SESSION['rate_limit'][$actionKey] ?? ['attempts' => 0, 'first_attempt' => $now];

    if ($now - $rateData['first_attempt'] > $decaySeconds) {
        // Reset window
        $_SESSION['rate_limit'][$actionKey] = ['attempts' => 1, 'first_attempt' => $now];
        return true;
    }

    $rateData['attempts']++;
    $_SESSION['rate_limit'][$actionKey] = $rateData;

    if ($rateData['attempts'] > $maxAttempts) {
        Logger::security("Rate limit exceeded for action: {$actionKey}", [
            'attempts' => $rateData['attempts'],
            'max'      => $maxAttempts
        ]);
        return false;
    }

    return true;
}
