<?php
/**
 * StayHub Admin - User Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$db = getDB();
$currentAdminId = getCurrentUserId();
$roleFilter = trim($_GET['role'] ?? 'all');

// Handle Role Change / Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if (verifyCsrfToken($csrf) && $targetUserId > 0) {
        if ($_POST['action'] === 'update_role') {
            $newRole = in_array($_POST['role'] ?? '', ['guest', 'host', 'admin']) ? $_POST['role'] : 'guest';
            $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute([':role' => $newRole, ':id' => $targetUserId]);
            setFlash('success', 'User role updated to ' . ucfirst($newRole));
        } elseif ($_POST['action'] === 'toggle_status') {
            if ($targetUserId === $currentAdminId) {
                setFlash('danger', 'You cannot suspend your own admin account.');
            } else {
                $db->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'active' END WHERE id = :id")->execute([':id' => $targetUserId]);
                setFlash('info', 'User account status toggled.');
            }
        }
    } else {
        setFlash('danger', 'Invalid security token.');
    }
    header('Location: ' . BASE_URL . '/admin/users.php?role=' . urlencode($roleFilter));
    exit;
}

$whereClause = "1=1";
$params = [];
if (in_array($roleFilter, ['guest', 'host', 'admin'], true)) {
    $whereClause = "u.role = :role";
    $params[':role'] = $roleFilter;
}

$sql = "SELECT u.*,
               COALESCE((SELECT COUNT(*) FROM listings WHERE host_id = u.id), 0) AS listings_count,
               COALESCE((SELECT COUNT(*) FROM bookings WHERE guest_id = u.id), 0) AS bookings_count
        FROM users u
        WHERE {$whereClause}
        ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users - Admin';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <span class="badge bg-danger mb-1 text-uppercase">Admin</span>
            <h2 class="fw-bold mb-1">User Management</h2>
            <p class="text-muted small mb-0">View registered users, modify permissions, or suspend accounts.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Filter by Role -->
    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm <?= $roleFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3">
            All Users
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=guest" class="btn btn-sm <?= $roleFilter === 'guest' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
            Guests
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=host" class="btn btn-sm <?= $roleFilter === 'host' ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3">
            Hosts
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=admin" class="btn btn-sm <?= $roleFilter === 'admin' ? 'btn-dark' : 'btn-outline-dark' ?> rounded-pill px-3">
            Admins
        </a>
    </div>

    <div class="card border rounded-4 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Activity</th>
                        <th>Joined</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $statusBadge = ($u['status'] === 'active') ? 'bg-success' : 'bg-danger';
                        $roleBadge = match($u['role']) {
                            'admin' => 'bg-dark',
                            'host'  => 'bg-danger',
                            default => 'bg-secondary'
                        };
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($u['avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100') ?>" class="rounded-circle object-fit-cover shadow-sm" width="40" height="40" alt="Avatar">
                                    <div>
                                        <div class="fw-bold"><?= e($u['name']) ?></div>
                                        <small class="text-muted"><?= e($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($u['phone'] ?? 'N/A') ?></td>
                            <td>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm d-inline-block w-auto rounded-pill" onchange="this.form.submit();">
                                        <option value="guest" <?= $u['role'] === 'guest' ? 'selected' : '' ?>>Guest</option>
                                        <option value="host" <?= $u['role'] === 'host' ? 'selected' : '' ?>>Host</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            </td>
                            <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td class="small">
                                <?php if ($u['role'] === 'host'): ?>
                                    <span><?= (int)$u['listings_count'] ?> listings</span>
                                <?php else: ?>
                                    <span><?= (int)$u['bookings_count'] ?> trips</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <?php if ($u['id'] !== $currentAdminId): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill px-3">
                                            <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

