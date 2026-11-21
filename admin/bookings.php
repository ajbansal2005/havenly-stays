<?php
/**
 * StayHub Admin - View All Bookings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$db = getDB();
$statusFilter = trim($_GET['status'] ?? 'all');

$whereClause = "1=1";
$params = [];
if (in_array($statusFilter, ['confirmed', 'completed', 'cancelled', 'pending'], true)) {
    $whereClause = "b.booking_status = :status";
    $params[':status'] = $statusFilter;
}

$sql = "SELECT b.*, l.title as listing_title, l.city as listing_city,
               h.name as host_name, h.email as host_email,
               g.name as guest_name, g.email as guest_email, g.phone as guest_phone
        FROM bookings b
        JOIN listings l ON b.listing_id = l.id
        JOIN users h ON l.host_id = h.id
        JOIN users g ON b.guest_id = g.id
        WHERE {$whereClause}
        ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Total platform revenue for filtered list
$totalFilteredRevenue = 0;
foreach ($bookings as $b) {
    if ($b['payment_status'] === 'paid') {
        $totalFilteredRevenue += (float)$b['total_price'];
    }
}

$pageTitle = 'All Bookings - Admin';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <span class="badge bg-danger mb-1 text-uppercase">Admin</span>
            <h2 class="fw-bold mb-1">Platform Reservations</h2>
            <p class="text-muted small mb-0">Total gross bookings in current view: <strong class="text-success"><?= formatPrice($totalFilteredRevenue) ?></strong></p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Status Filter Tabs -->
    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2">
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3">
            All Bookings (<?= count($bookings) ?>)
        </a>
        <a href="<?= BASE_URL ?>/admin/bookings.php?status=confirmed" class="btn btn-sm <?= $statusFilter === 'confirmed' ? 'btn-success text-white' : 'btn-outline-success' ?> rounded-pill px-3">
            Confirmed
        </a>
        <a href="<?= BASE_URL ?>/admin/bookings.php?status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-secondary text-white' : 'btn-outline-secondary' ?> rounded-pill px-3">
            Completed
        </a>
        <a href="<?= BASE_URL ?>/admin/bookings.php?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-danger text-white' : 'btn-outline-danger' ?> rounded-pill px-3">
            Cancelled
        </a>
    </div>

    <?php if (!empty($bookings)): ?>
        <div class="card border rounded-4 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Stay / City</th>
                            <th>Host</th>
                            <th>Guest</th>
                            <th>Dates</th>
                            <th>Total</th>
                            <th>Payment ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): 
                            $statusBadge = match($b['booking_status']) {
                                'confirmed' => 'bg-success',
                                'completed' => 'bg-secondary',
                                'cancelled' => 'bg-danger',
                                default => 'bg-warning text-dark'
                            };
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace text-danger"><?= e($b['booking_code']) ?></td>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 180px;">
                                        <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$b['listing_id'] ?>" class="text-decoration-none text-dark">
                                            <?= e($b['listing_title']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted"><?= e($b['listing_city']) ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($b['host_name']) ?></div>
                                    <small class="text-muted"><?= e($b['host_email']) ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($b['guest_name']) ?></div>
                                    <small class="text-muted"><?= e($b['guest_email']) ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= date('M j', strtotime($b['check_in'])) ?> &ndash; <?= date('M j, Y', strtotime($b['check_out'])) ?></div>
                                    <small class="text-muted"><?= (int)$b['total_nights'] ?> nights &bull; <?= (int)$b['guests_count'] ?> guests</small>
                                </td>
                                <td class="fw-bold text-success"><?= formatPrice($b['total_price']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                                        <?= e($b['razorpay_payment_id'] ?: 'N/A') ?>
                                    </span>
                                </td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($b['booking_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="p-5 bg-light rounded-4 mx-auto" style="max-width: 500px;">
                <i class="fa-regular fa-receipt fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">No bookings found</h4>
                <p class="text-muted small mb-0">No reservations match the selected filter.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

