<?php
/**
 * StayHub Host - View & Manage Guest Reservations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();
$statusFilter = trim($_GET['status'] ?? 'all');

$whereClauses = ["l.host_id = :hid"];
$params = [':hid' => $hostId];

if (in_array($statusFilter, ['confirmed', 'completed', 'cancelled', 'pending'], true)) {
    $whereClauses[] = "b.booking_status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = implode(' AND ', $whereClauses);

$sql = "SELECT b.*, l.title as listing_title, l.city as listing_city,
               u.name as guest_name, u.email as guest_email, u.phone as guest_phone, u.avatar as guest_avatar
        FROM bookings b
        JOIN listings l ON b.listing_id = l.id
        JOIN users u ON b.guest_id = u.id
        WHERE {$whereSql}
        ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Guest Reservations - Host';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">Guest Reservations</h2>
            <p class="text-muted small mb-0">Manage incoming and past bookings across your properties.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/host/dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Pills -->
    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2">
        <a href="<?= BASE_URL ?>/host/bookings.php" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3">
            All Reservations
        </a>
        <a href="<?= BASE_URL ?>/host/bookings.php?status=confirmed" class="btn btn-sm <?= $statusFilter === 'confirmed' ? 'btn-success text-white' : 'btn-outline-success' ?> rounded-pill px-3">
            Confirmed
        </a>
        <a href="<?= BASE_URL ?>/host/bookings.php?status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-secondary text-white' : 'btn-outline-secondary' ?> rounded-pill px-3">
            Completed
        </a>
        <a href="<?= BASE_URL ?>/host/bookings.php?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-danger text-white' : 'btn-outline-danger' ?> rounded-pill px-3">
            Cancelled
        </a>
    </div>

    <?php if (!empty($bookings)): ?>
        <div class="card border rounded-4 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Booking Code</th>
                            <th>Stay / Property</th>
                            <th>Guest Contact</th>
                            <th>Dates</th>
                            <th>Guests</th>
                            <th>Payout</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Receipt</th>
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
                                    <div class="fw-semibold text-truncate" style="max-width: 200px;"><?= e($b['listing_title']) ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i> <?= e($b['listing_city']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($b['guest_name']) ?></div>
                                    <small class="text-muted"><?= e($b['guest_email']) ?> &bull; <?= e($b['guest_phone'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= date('M j', strtotime($b['check_in'])) ?> &ndash; <?= date('M j, Y', strtotime($b['check_out'])) ?></div>
                                    <small class="text-muted"><?= (int)$b['total_nights'] ?> night(s)</small>
                                </td>
                                <td><?= (int)$b['guests_count'] ?></td>
                                <td class="fw-bold text-success"><?= formatPrice($b['total_price']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($b['booking_status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$b['listing_id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill">
                                        View Stay
                                    </a>
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
                <i class="fa-regular fa-calendar-xmark fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">No reservations found</h4>
                <p class="text-muted small mb-0">No bookings match the selected status filter.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

