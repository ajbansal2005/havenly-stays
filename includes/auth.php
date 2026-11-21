<?php
/**
 * StayHub Authentication & Authorization Helper Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function getCurrentUserId(): ?int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user']['id']);
}

function hasRole(string $role): bool {
    if (!isLoggedIn()) return false;
    return ($_SESSION['user']['role'] ?? '') === $role;
}

function requireLogin(string $redirectTo = ''): void {
    if (!isLoggedIn()) {
        $target = $redirectTo ?: ($_SERVER['REQUEST_URI'] ?? '/');
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

function requireRole($roles): void {
    requireLogin();
    $rolesArray = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['user']['role'] ?? '';

    if (!in_array($userRole, $rolesArray, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo "<div class='container py-5 text-center'>"
            . "<div class='card shadow-sm p-5 mx-auto' style='max-width: 500px;'>"
            . "<i class='fa-solid fa-lock text-danger fa-3x mb-3'></i>"
            . "<h3 class='fw-bold'>Access Denied</h3>"
            . "<p class='text-muted'>You do not have permission to view this section.</p>"
            . "<a href='" . BASE_URL . "/' class='btn btn-primary'>Return Home</a>"
            . "</div></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function loginUser(array $user): void {
    // Regenerate session id to prevent session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    unset($user['password']); // Never store hashed password in session
    $_SESSION['user'] = $user;
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['user']);
        session_destroy();
    }
}

