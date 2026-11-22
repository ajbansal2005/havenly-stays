<?php
/**
 * StayHub User Profile Page
 * Validates profile fields, avatar URLs (XSS prevention), and CSRF tokens.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/logger.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();
$userId = (int)$user['id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $rawPhone = trim($_POST['phone'] ?? '');
    $phone = substr(preg_replace('/[^0-9+\-\s()]/', '', $rawPhone), 0, 30);
    $rawBio = trim($_POST['bio'] ?? '');
    $bio = mb_substr(strip_tags($rawBio), 0, 1000);
    $avatar = trim($_POST['avatar'] ?? '');
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken($csrf)) {
        $error = 'Security session expired. Please try again.';
    } elseif (empty($name)) {
        $error = 'Name cannot be empty.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Name must be between 2 and 100 characters.';
    } elseif (!empty($avatar) && (!filter_var($avatar, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $avatar) || strlen($avatar) > 500)) {
        $error = 'Avatar must be a valid secure web URL starting with http:// or https:// (max 500 characters).';
    } else {
        try {
            Database::transaction(function (PDO $db) use ($name, $phone, $bio, $avatar, $userId) {
                $stmtUpdate = $db->prepare("UPDATE users SET name = :name, phone = :phone, bio = :bio, avatar = :avatar WHERE id = :id");
                $stmtUpdate->execute([
                    ':name'   => $name,
                    ':phone'  => $phone,
                    ':bio'    => $bio,
                    ':avatar' => $avatar,
                    ':id'     => $userId
                ]);
            });

            // Refresh user in session
            $stmtFresh = $db->prepare("SELECT * FROM users WHERE id = :id");
            $stmtFresh->execute([':id' => $userId]);
            $refreshed = $stmtFresh->fetch();
            if ($refreshed) {
                loginUser($refreshed);
                $user = $refreshed;
            }

            Logger::info("User profile updated", ['user_id' => $userId]);
            $success = 'Profile updated successfully!';
        } catch (\Throwable $t) {
            Logger::error("Profile update failed: " . $t->getMessage(), ['user_id' => $userId]);
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
                <div class="d-flex align-items-center gap-4 mb-4 pb-4 border-bottom">
                    <img src="<?= e($user['avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200') ?>" class="rounded-circle object-fit-cover shadow-sm" width="90" height="90" alt="Avatar">
                    <div>
                        <h4 class="fw-bold mb-1"><?= e($user['name']) ?></h4>
                        <div class="text-muted small mb-2"><?= e($user['email']) ?></div>
                        <span class="badge bg-danger text-uppercase px-2 py-1" style="font-size: 11px;">
                            <?= e($user['role']) ?> Account
                        </span>
                        <span class="text-muted small ms-2">Member since <?= isValidDate($user['created_at'] ?? '', 'Y-m-d H:i:s') ? date('M Y', strtotime($user['created_at'])) : 'recently' ?></span>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 small"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-3" required value="<?= e($user['name']) ?>" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="tel" name="phone" class="form-control rounded-3" value="<?= e($user['phone'] ?? '') ?>" placeholder="+91 98765 43210" maxlength="30">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Avatar Image URL</label>
                        <input type="url" name="avatar" class="form-control rounded-3" value="<?= e($user['avatar'] ?? '') ?>" placeholder="https://example.com/photo.jpg" maxlength="500">
                        <div class="form-text small">Provide a direct web link to an image (http or https).</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">About Me (Bio)</label>
                        <textarea name="bio" rows="3" class="form-control rounded-3" placeholder="Tell hosts or guests a little about yourself..." maxlength="1000"><?= e($user['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2">
                        <a href="<?= BASE_URL ?>/" class="btn btn-outline-secondary rounded-3 px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
