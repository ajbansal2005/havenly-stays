<?php
/**
 * StayHub User Login
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$redirect = $_GET['redirect'] ?? (BASE_URL . '/');

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$email = '';

// Handle quick demo login click
if (isset($_GET['demo'])) {
    $demoRole = $_GET['demo'];
    $demoEmails = [
        'guest' => 'guest@stayhub.test',
        'host'  => 'host@stayhub.test',
        'admin' => 'admin@stayhub.test'
    ];
    if (isset($demoEmails[$demoRole])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND status = 'active'");
        $stmt->execute([':email' => $demoEmails[$demoRole]]);
        $user = $stmt->fetch();
        if ($user) {
            loginUser($user);
            setFlash('success', 'Logged in as <strong>' . e($user['name']) . '</strong> (' . ucfirst($user['role']) . ')');
            if ($user['role'] === 'admin') {
                header('Location: ' . BASE_URL . '/admin/dashboard.php');
            } elseif ($user['role'] === 'host') {
                header('Location: ' . BASE_URL . '/host/dashboard.php');
            } else {
                header('Location: ' . $redirect);
            }
            exit;
        }
    }
}

// Check Brute-Force Rate Limiting
$lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;
if ($lockoutUntil > time()) {
    $remaining = ceil(($lockoutUntil - time()) / 60);
    $error = "Too many failed login attempts. Account access is temporarily locked for security. Please try again in {$remaining} minute(s).";
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($lockoutUntil > time()) {
        // Reject immediately if in lockout period
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verifyCsrfToken($csrf)) {
            $error = 'Security session expired. Please try again.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been suspended. Please contact platform support.';
                } else {
                    // Reset failed login counter on success
                    unset($_SESSION['login_failed_attempts'], $_SESSION['login_lockout_until']);

                    loginUser($user);
                    setFlash('success', 'Welcome back, ' . e($user['name']) . '!');
                    
                    // Redirect by role if default target
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

                if ($failedCount >= 5) {
                    $_SESSION['login_lockout_until'] = time() + 180; // 3 minute lock
                    $error = 'Too many failed login attempts. For security reasons, please wait 3 minutes before trying again.';
                } else {
                    $remainingAttempts = 5 - $failedCount;
                    $error = "Invalid email or password. ({$remainingAttempts} attempt(s) remaining before security lockout).";
                }
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

                    <button type="submit" class="btn btn-danger w-100 rounded-3 py-2 fw-semibold mt-2">
                        Log In
                    </button>
                </form>

                <div class="text-center mt-4 pt-2 border-top">
                    <p class="small text-muted mb-0">Don't have an account? <a href="<?= BASE_URL ?>/register.php" class="fw-bold text-danger text-decoration-none">Sign up</a></p>
                </div>

                <!-- 1-Click Demo Login for Interviewers / Testers -->
                <div class="mt-4 p-3 bg-light rounded-3 border">
                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size: 11px;">
                        <i class="fa-solid fa-bolt text-warning me-1"></i> 1-Click Demo Login
                    </div>
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/login.php?demo=guest&redirect=<?= urlencode($redirect) ?>" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-user me-2"></i> Demo Guest (Ajay)</span>
                            <span class="badge bg-light text-dark border">guest123</span>
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?demo=host&redirect=<?= urlencode($redirect) ?>" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-house-user me-2"></i> Demo Host (Rohit)</span>
                            <span class="badge bg-light text-dark border">host123</span>
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?demo=admin&redirect=<?= urlencode($redirect) ?>" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-shield-halved me-2"></i> Demo Admin</span>
                            <span class="badge bg-light text-dark border">admin123</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

