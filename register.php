<?php
/**
 * StayHub User Registration
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$selectedRole = in_array(($_GET['role'] ?? ''), ['guest', 'host']) ? $_GET['role'] : 'guest';
$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array(($_POST['role'] ?? ''), ['guest', 'host']) ? $_POST['role'] : 'guest';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Security session expired. Please try again.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        // Check if email already exists
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = :email");
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $defaultAvatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&auto=format&fit=crop&q=80';

            $stmtIns = $db->prepare("INSERT INTO users (name, email, password, phone, role, avatar, status) VALUES (:name, :email, :password, :phone, :role, :avatar, 'active')");
            $stmtIns->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':phone' => $phone,
                ':role' => $role,
                ':avatar' => $defaultAvatar
            ]);

            $newUserId = (int)$db->lastInsertId();
            $stmtUser = $db->prepare("SELECT * FROM users WHERE id = :id");
            $stmtUser->execute([':id' => $newUserId]);
            $newUser = $stmtUser->fetch();

            loginUser($newUser);
            setFlash('success', 'Welcome to StayHub, ' . e($name) . '! Your account has been created.');

            if ($role === 'host') {
                header('Location: ' . BASE_URL . '/host/dashboard.php');
            } else {
                header('Location: ' . BASE_URL . '/');
            }
            exit;
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
                        <input type="text" name="name" class="form-control rounded-3 py-2" required placeholder="John Doe" value="<?= e($name) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email address</label>
                        <input type="email" name="email" class="form-control rounded-3 py-2" required placeholder="name@example.com" value="<?= e($email) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number (optional)</label>
                        <input type="tel" name="phone" class="form-control rounded-3 py-2" placeholder="+91 98765 43210" value="<?= e($phone) ?>">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control rounded-3 py-2" required placeholder="Min. 6 chars">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control rounded-3 py-2" required placeholder="Re-enter password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 rounded-3 py-2 fw-semibold">
                        Complete Registration
                    </button>
                </form>

                <div class="text-center mt-4 pt-2 border-top">
                    <p class="small text-muted mb-0">Already have an account? <a href="<?= BASE_URL ?>/login.php" class="fw-bold text-danger text-decoration-none">Log in</a></p>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.role-option-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.role-option-card').forEach(c => c.classList.remove('border-danger', 'bg-light'));
        this.classList.add('border-danger', 'bg-light');
    });
});
// Initial state
const activeRadio = document.querySelector('input[name="role"]:checked');
if (activeRadio) {
    activeRadio.closest('.role-option-card').classList.add('border-danger', 'bg-light');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

