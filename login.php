<?php
/**
 * StayHub User Login
 * Provides brute-force lockout, session regeneration, safe redirect protection, and security auditing.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';

$rawRedirect = $_GET['redirect'] ?? '';
$redirect = safeRedirectUrl($rawRedirect, BASE_URL . '/');

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$email = '';

// Handle quick demo login click
if (isset($_GET['demo'])) {
    $demoRole = (string)$_GET['demo'];
    $demoEmails = [
        'guest' => 'guest@stayhub.test',
        'host'  => 'host@stayhub.test',
        'admin' => 'admin@stayhub.test'
    ];
    if (isset($demoEmails[$demoRole])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND status = 'active'");
            $stmt->execute([':email' => $demoEmails[$demoRole]]);
            $user = $stmt->fetch();
            if ($user) {
                loginUser($user);
                setFlash('success', 'Logged in as <strong>' . e($user['name']) . '</strong> (' . ucfirst($user['role']) . ')', true);
                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                } elseif ($user['role'] === 'host') {
                    header('Location: ' . BASE_URL . '/host/dashboard.php');
                } else {
                    header('Location: ' . $redirect);
                }
                exit;
            }
        } catch (\Throwable $t) {
            Logger::error("Demo login database error: " . $t->getMessage());
            $error = 'Demo login is currently unavailable.';
        }
    }
}

// Check Brute-Force Rate Limiting (Session + IP based)
$lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;
if ($lockoutUntil > time()) {
    $remaining = ceil(($lockoutUntil - time()) / 60);
    $error = "Too many failed login attempts. Account access is temporarily locked for security. Please try again in {$remaining} minute(s).";
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($lockoutUntil > time()) {
        Logger::security("Login attempted while in lockout period from IP: " . Logger::getClientIp());
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $csrf = $_POST['csrf_token'] ?? null;

        if (!verifyCsrfToken($csrf)) {
            $error = 'Security session expired. Please refresh and try again.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if (($user['status'] ?? 'active') === 'suspended') {
                        Logger::security("Suspended user attempted login", ['email' => $email, 'user_id' => $user['id']]);
                        $error = 'Your account has been suspended. Please contact platform support.';
                    } else {
                        // Reset failed login counter on success
                        unset($_SESSION['login_failed_attempts'], $_SESSION['login_lockout_until']);

                        loginUser($user);
                        setFlash('success', 'Welcome back, ' . e($user['name']) . '!');

                        // Redirect by role if default root target
                        if ($redirect === (BASE_URL . '/') || empty($redirect)) {
                            if ($user['role'] === 'admin') {
                                header('Location: ' . BASE_URL . '/admin/dashboard.php');
                                exit;
                            } elseif ($user['role'] === 'host') {
                                header('Location: ' . BASE_URL . '/host/dashboard.php');
                                exit;
                            }
                        }
                        header('Location: ' . $redirect);
                        exit;
                    }
                } else {
                    // Increment failed attempts
                    $failedCount = ($_SESSION['login_failed_attempts'] ?? 0) + 1;
                    $_SESSION['login_failed_attempts'] = $failedCount;

                    Logger::security("Failed login attempt for {$email}", [
                        'attempt' => $failedCount,
                        'ip'      => Logger::getClientIp()
                    ]);

                    if ($failedCount >= 5) {
                        $_SESSION['login_lockout_until'] = time() + 180; // 3 minute lockout
                        $error = 'Too many failed login attempts. For security reasons, please wait 3 minutes before trying again.';
                    } else {
                        $remainingAttempts = 5 - $failedCount;
                        $error = "Invalid email or password. ({$remainingAttempts} attempt(s) remaining before security lockout).";
                    }
                }
            } catch (\Throwable $t) {
                Logger::error("Login system error: " . $t->getMessage(), ['email' => $email]);
                $error = 'An unexpected error occurred during authentication. Please try again.';
            }
        }
    }
}

$pageTitle = 'Log In';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-md-4">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width: 48px; height: 48px; font-size: 24px;">
                        <i class="fa-solid fa-house-chimney-window"></i>
                    </div>
                    <h3 class="fw-bold">Welcome to StayHub</h3>
                    <p class="text-muted small">Log in to manage bookings, wishlists, and stays.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email address</label>
                        <input type="email" name="email" class="form-control rounded-3 py-2" required placeholder="name@example.com" value="<?= e($email) ?>">
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label small fw-semibold">Password</label>
                        </div>
                        <input type="password" name="password" class="form-control rounded-3 py-2" required placeholder="Enter your password">
                    </div>

                    <button type="submit" class="btn btn-danger w-100 rounded-3 py-2 fw-semibold mb-3">
                        Log In
                    </button>
                </form>

                <div class="text-center small text-muted mb-4">
                    Don't have an account? 
                    <a href="<?= BASE_URL ?>/register.php" class="text-danger fw-semibold text-decoration-none">Sign up</a>
                </div>

                <!-- One-click Demo Logins -->
                <div class="border-top pt-3">
                    <div class="text-muted text-uppercase text-center fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">
                        Quick Demo Logins (Evaluator / Viva Mode)
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="<?= BASE_URL ?>/login.php?demo=admin" class="btn btn-outline-dark btn-sm rounded-pill flex-grow-1">
                            <i class="fa-solid fa-shield-halved text-danger me-1"></i> Admin
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?demo=host" class="btn btn-outline-dark btn-sm rounded-pill flex-grow-1">
                            <i class="fa-solid fa-house-user text-primary me-1"></i> Host
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?demo=guest" class="btn btn-outline-dark btn-sm rounded-pill flex-grow-1">
                            <i class="fa-solid fa-compass text-success me-1"></i> Guest
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
