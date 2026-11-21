<?php
/**
 * StayHub Host Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();

// 1. Host Stats
// Total Listings
$stmtListings = $db->prepare("SELECT COUNT(*) FROM listings WHERE host_id = :hid");
$stmtListings->execute([':hid' => $hostId]);
$totalListings = (int)$stmtListings->fetchColumn();

// Total Bookings & Total Earnings
$stmtEarnings = $db->prepare("
    SELECT COUNT(*) as total_bookings,
           COALESCE(SUM(b.total_price), 0) as total_revenue
    FROM bookings b
    JOIN listings l ON b.listing_id = l.id
    WHERE l.host_id = :hid AND b.booking_status IN ('confirmed', 'completed')
");
$stmtEarnings->execute([':hid' => $hostId]);
$statRow = $stmtEarnings->fetch();
$totalBookings = (int)$statRow['total_bookings'];
$totalEarnings = (float)$statRow['total_revenue'];

// Active upcoming bookings
$today = date('Y-m-d');
$stmtActive = $db->prepare("
    SELECT COUNT(*) 
    FROM bookings b 
    JOIN listings l ON b.listing_id = l.id 
    WHERE l.host_id = :hid AND b.booking_status = 'confirmed' AND b.check_in >= :today
");
$stmtActive->execute([':hid' => $hostId, ':today' => $today]);
$activeBookings = (int)$stmtActive->fetchColumn();

// Recent Reservations
$stmtRecent = $db->prepare("
    SELECT b.*, l.title as listing_title, u.name as guest_name, u.email as guest_email, u.phone as guest_phone
    FROM bookings b
    JOIN listings l ON b.listing_id = l.id
    JOIN users u ON b.guest_id = u.id
    WHERE l.host_id = :hid
    ORDER BY b.created_at DESC
    LIMIT 6
");
$stmtRecent->execute([':hid' => $hostId]);
$recentReservations = $stmtRecent->fetchAll();

$pageTitle = 'Host Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    
    <!-- Top Welcome Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <span class="badge bg-danger mb-1 text-uppercase">Host Mode</span>
            <h2 class="fw-bold mb-0">Welcome, <?= e($_SESSION['user']['name']) ?></h2>
            <p class="text-muted small mb-0">Track performance, manage listings, and view guest bookings.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/host/listings.php" class="btn btn-outline-dark rounded-pill">
                <i class="fa-solid fa-hotel me-1"></i> My Listings
            </a>
            <a href="<?= BASE_URL ?>/host/add_listing.php" class="btn btn-danger rounded-pill">
                <i class="fa-solid fa-plus me-1"></i> Add New Stay
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-5">
        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Earnings</span>
                    <div class="rounded-circle bg-light text-success p-2">
                        <i class="fa-solid fa-coins fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-success"><?= formatPrice($totalEarnings) ?></h3>
                <small class="text-muted">From confirmed stays</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Listings</span>
                    <div class="rounded-circle bg-light text-primary p-2">
                        <i class="fa-solid fa-house-laptop fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $totalListings ?></h3>
                <small class="text-muted">Properties managed by you</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Bookings</span>
                    <div class="rounded-circle bg-light text-danger p-2">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $totalBookings ?></h3>
                <small class="text-muted">Lifetime reservations</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Upcoming Stays</span>
                    <div class="rounded-circle bg-light text-info p-2">
                        <i class="fa-solid fa-plane-arrival fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $activeBookings ?></h3>
                <small class="text-muted">Guests arriving soon</small>
            </div>
        </div>
    </div>

    <!-- Recent Guest Reservations -->
    <div class="card border rounded-4 shadow-sm overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Recent Guest Reservations</h5>
            <a href="<?= BASE_URL ?>/host/bookings.php" class="text-danger fw-semibold text-decoration-none small">
                View all reservations &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-4">Booking Code</th>
                        <th>Listing</th>
                        <th>Guest</th>
                        <th>Dates</th>
                        <th>Guests</th>
                        <th>Payout</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentReservations)): ?>
                        <?php foreach ($recentReservations as $res): 
                            $statusBadge = match($res['booking_status']) {
                                'confirmed' => 'bg-success',
                                'completed' => 'bg-secondary',
                                'cancelled' => 'bg-danger',
                                default => 'bg-warning text-dark'
                            };
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold text-danger"><?= e($res['booking_code']) ?></td>
                                <td class="fw-semibold text-truncate" style="max-width: 220px;"><?= e($res['listing_title']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= e($res['guest_name']) ?></div>
                                    <small class="text-muted"><?= e($res['guest_email']) ?></small>
                                </td>
                                <td>
                                    <small><?= date('M j', strtotime($res['check_in'])) ?> &ndash; <?= date('M j, Y', strtotime($res['check_out'])) ?></small>
                                </td>
                                <td><?= (int)$res['guests_count'] ?> guest(s)</td>
                                <td class="fw-bold text-dark"><?= formatPrice($res['total_price']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($res['booking_status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$res['listing_id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill">View Stay</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                No guest bookings yet. When travelers book your stays, they'll appear here!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

