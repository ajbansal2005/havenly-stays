<?php
/**
 * StayHub User Registration
 * Validates role whitelisting, password complexity, email uniqueness, CSRF, and rate limiting.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$selectedRole = in_array(($_GET['role'] ?? ''), ['guest', 'host'], true) ? $_GET['role'] : 'guest';
$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limit registration attempts per IP
    if (!checkRateLimit('register_' . Logger::getClientIp(), 6, 300)) {
        $error = 'Too many registration attempts from your network. Please wait a few minutes before trying again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $rawPhone = trim($_POST['phone'] ?? '');
        $phone = substr(preg_replace('/[^0-9+\-\s()]/', '', $rawPhone), 0, 30);
        $role = in_array(($_POST['role'] ?? ''), ['guest', 'host'], true) ? $_POST['role'] : 'guest';
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $csrf = $_POST['csrf_token'] ?? null;

        if (!verifyCsrfToken($csrf)) {
            $error = 'Security session expired. Please refresh the page and try again.';
        } elseif (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill out all required fields.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Name must be between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (strlen($password) > 72) {
            $error = 'Password cannot exceed 72 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $db = getDB();

                // Check if email already exists
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmtCheck->execute([':email' => $email]);
                if ($stmtCheck->fetch()) {
                    $error = 'An account with this email address already exists.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $defaultAvatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&auto=format&fit=crop&q=80';

                    $newUserId = 0;
                    Database::transaction(function (PDO $db) use ($name, $email, $hashedPassword, $phone, $role, $defaultAvatar, &$newUserId) {
                        $stmtIns = $db->prepare("
                            INSERT INTO users (name, email, password, phone, role, avatar, status) 
                            VALUES (:name, :email, :password, :phone, :role, :avatar, 'active')
                        ");
                        $stmtIns->execute([
                            ':name'     => $name,
                            ':email'    => $email,
                            ':password' => $hashedPassword,
                            ':phone'    => $phone,
                            ':role'     => $role,
                            ':avatar'   => $defaultAvatar
                        ]);
                        $newUserId = (int)$db->lastInsertId();
                    });

                    $stmtUser = $db->prepare("SELECT * FROM users WHERE id = :id");
                    $stmtUser->execute([':id' => $newUserId]);
                    $newUser = $stmtUser->fetch();

                    if ($newUser) {
                        loginUser($newUser);
                        Logger::security("New account registered", ['user_id' => $newUserId, 'email' => $email, 'role' => $role]);
                        setFlash('success', 'Welcome to StayHub, ' . e($name) . '! Your account has been created.');

                        if ($role === 'host') {
                            header('Location: ' . BASE_URL . '/host/dashboard.php');
                        } else {
                            header('Location: ' . BASE_URL . '/');
                        }
                        exit;
                    }
                }
            } catch (\Throwable $t) {
                Logger::error("Registration system error: " . $t->getMessage(), ['email' => $email]);
                $error = 'An error occurred while creating your account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign Up';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-md-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width: 48px; height: 48px; font-size: 24px;">
                        <i class="fa-solid fa-house-chimney-window"></i>
                    </div>
                    <h3 class="fw-bold">Create your account</h3>
                    <p class="text-muted small">Join StayHub to book unique stays or host your place.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- Role Selector Radio -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-2">I want to:</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="card p-3 border text-center h-100 cursor-pointer role-option-card">
                                    <input type="radio" name="role" value="guest" class="form-check-input visually-hidden" <?= $selectedRole === 'guest' ? 'checked' : '' ?>>
                                    <i class="fa-solid fa-compass fa-2x mb-2 text-danger"></i>
                                    <div class="fw-bold small">Explore & Book</div>
                                    <div class="text-muted" style="font-size: 11px;">I am a Guest</div>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="card p-3 border text-center h-100 cursor-pointer role-option-card">
                                    <input type="radio" name="role" value="host" class="form-check-input visually-hidden" <?= $selectedRole === 'host' ? 'checked' : '' ?>>
                                    <i class="fa-solid fa-house-user fa-2x mb-2 text-primary"></i>
                                    <div class="fw-bold small">Host my Property</div>
                                    <div class="text-muted" style="font-size: 11px;">I am a Host</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control rounded-3 py-2" required placeholder="John Doe" value="<?= e($name) ?>" maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email address</label>
                        <input type="email" name="email" class="form-control rounded-3 py-2" required placeholder="name@example.com" value="<?= e($email) ?>" maxlength="150">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number (optional)</label>
                        <input type="tel" name="phone" class="form-control rounded-3 py-2" placeholder="+91 98765 43210" value="<?= e($phone) ?>" maxlength="30">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control rounded-3 py-2" required placeholder="At least 6 characters" minlength="6" maxlength="72">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control rounded-3 py-2" required placeholder="Re-type password" minlength="6" maxlength="72">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 rounded-3 py-2 fw-semibold mb-3">
                        Create Account
                    </button>
                </form>

                <div class="text-center small text-muted">
                    Already have an account? 
                    <a href="<?= BASE_URL ?>/login.php" class="text-danger fw-semibold text-decoration-none">Log in</a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
