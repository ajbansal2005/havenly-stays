<?php
/**
 * StayHub Admin - Listing Approvals & Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$db = getDB();
$adminId = (int)getCurrentUserId();
$statusFilter = trim($_GET['status'] ?? 'all');

// Handle Actions (Approve, Reject, Toggle Featured, Delete)
$action = $_REQUEST['action'] ?? null;
$targetId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$csrf = $_REQUEST['csrf_token'] ?? null;

if ($action && $targetId > 0) {
    $allowedActions = ['approve', 'reject', 'toggle_featured', 'delete'];

    if (!verifyCsrfToken($csrf)) {
        Logger::security("CSRF mismatch on admin listing action", ['admin_id' => $adminId, 'action' => $action]);
        setFlash('danger', 'Invalid security token.');
    } elseif (!in_array($action, $allowedActions, true)) {
        setFlash('danger', 'Invalid action specified.');
    } else {
        try {
            if ($action === 'approve') {
                $db->prepare("UPDATE listings SET status = 'approved' WHERE id = :id")->execute([':id' => $targetId]);
                Logger::security("Listing approved by admin", ['admin_id' => $adminId, 'listing_id' => $targetId]);
                setFlash('success', 'Listing approved successfully!');
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE listings SET status = 'rejected' WHERE id = :id")->execute([':id' => $targetId]);
                Logger::security("Listing rejected by admin", ['admin_id' => $adminId, 'listing_id' => $targetId]);
                setFlash('warning', 'Listing has been rejected.');
            } elseif ($action === 'toggle_featured') {
                $db->prepare("UPDATE listings SET featured = CASE WHEN featured = 1 THEN 0 ELSE 1 END WHERE id = :id")->execute([':id' => $targetId]);
                Logger::security("Listing featured status toggled by admin", ['admin_id' => $adminId, 'listing_id' => $targetId]);
                setFlash('info', 'Featured status updated.');
            } elseif ($action === 'delete') {
                Database::transaction(function (PDO $db) use ($targetId) {
                    $db->prepare("DELETE FROM listings WHERE id = :id")->execute([':id' => $targetId]);
                });
                Logger::security("Listing permanently deleted by admin", ['admin_id' => $adminId, 'listing_id' => $targetId]);
                setFlash('danger', 'Listing permanently deleted.');
            }
        } catch (\Throwable $t) {
            Logger::error("Admin listing action failed: " . $t->getMessage(), ['action' => $action, 'listing_id' => $targetId]);
            setFlash('danger', 'Failed to perform requested action on listing.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/listings.php?status=' . urlencode($statusFilter));
    exit;
}

$whereClause = "1=1";
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $whereClause = "l.status = :status";
    $params[':status'] = $statusFilter;
}

$listings = [];
try {
    $sql = "SELECT l.*, u.name as host_name, u.email as host_email,
                   (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image,
                   COALESCE((SELECT COUNT(*) FROM bookings WHERE listing_id = l.id), 0) AS booking_count
            FROM listings l
            JOIN users u ON l.host_id = u.id
            WHERE {$whereClause}
            ORDER BY l.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
} catch (\Throwable $t) {
    Logger::error("Failed to load admin listings: " . $t->getMessage());
    setFlash('danger', 'Failed to load listings.');
}

$pageTitle = 'Manage Listings - Admin';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <span class="badge bg-danger mb-1 text-uppercase">Admin</span>
            <h2 class="fw-bold mb-1">Listings & Approvals</h2>
            <p class="text-muted small mb-0">Review pending host submissions, approve or reject stays.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Status Filter Tabs -->
    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2">
        <a href="<?= BASE_URL ?>/admin/listings.php" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3">
            All Listings
        </a>
        <a href="<?= BASE_URL ?>/admin/listings.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?> rounded-pill px-3">
            Pending Approvals
        </a>
        <a href="<?= BASE_URL ?>/admin/listings.php?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success text-white' : 'btn-outline-success' ?> rounded-pill px-3">
            Approved & Live
        </a>
        <a href="<?= BASE_URL ?>/admin/listings.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger text-white' : 'btn-outline-danger' ?> rounded-pill px-3">
            Rejected
        </a>
    </div>

    <?php if (!empty($listings)): ?>
        <div class="card border rounded-4 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Listing</th>
                            <th>Host</th>
                            <th>City</th>
                            <th>Nightly Rate</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $l): 
                            $statusBadge = match($l['status']) {
                                'approved' => 'bg-success',
                                'pending' => 'bg-warning text-dark',
                                'rejected' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            $img = $l['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=200';
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($img) ?>" class="rounded-3 object-fit-cover shadow-sm" width="55" height="55" alt="Listing">
                                        <div>
                                            <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$l['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($l['title']) ?>
                                            </a>
                                            <div class="text-muted small"><?= e($l['property_type']) ?> &bull; <?= (int)$l['booking_count'] ?> bookings</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($l['host_name']) ?></div>
                                    <small class="text-muted"><?= e($l['host_email']) ?></small>
                                </td>
                                <td><?= e($l['city']) ?></td>
                                <td class="fw-bold"><?= formatPrice($l['price_per_night']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($l['status']) ?></span></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/listings.php?action=toggle_featured&id=<?= (int)$l['id'] ?>&csrf_token=<?= csrfToken() ?>&status=<?= urlencode($statusFilter) ?>" 
                                       class="badge <?= $l['featured'] ? 'bg-danger' : 'bg-light text-muted border' ?> text-decoration-none" title="Click to toggle featured">
                                        <?= $l['featured'] ? '<i class="fa-solid fa-star me-1"></i> Featured' : 'Normal' ?>
                                    </a>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$l['id'] ?>" class="btn btn-outline-dark" title="View Detail">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>

                                        <?php if ($l['status'] !== 'approved'): ?>
                                            <a href="<?= BASE_URL ?>/admin/listings.php?action=approve&id=<?= (int)$l['id'] ?>&csrf_token=<?= csrfToken() ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-success" title="Approve">
                                                <i class="fa-solid fa-check"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($l['status'] !== 'rejected'): ?>
                                            <a href="<?= BASE_URL ?>/admin/listings.php?action=reject&id=<?= (int)$l['id'] ?>&csrf_token=<?= csrfToken() ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-outline-warning text-dark" title="Reject">
                                                <i class="fa-solid fa-ban"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?= BASE_URL ?>/admin/listings.php?action=delete&id=<?= (int)$l['id'] ?>&csrf_token=<?= csrfToken() ?>&status=<?= urlencode($statusFilter) ?>" 
                                           class="btn btn-outline-danger" 
                                           title="Delete Listing" 
                                           onclick="return confirm('Permanently delete this listing?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="p-5 bg-light rounded-4 mx-auto" style="max-width: 500px;">
                <i class="fa-solid fa-check-double fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">No listings found</h4>
                <p class="text-muted small mb-0">No listings matching the current filter.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

