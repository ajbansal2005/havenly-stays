<?php
/**
 * StayHub Admin Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

$db = getDB();

// 1. Stats
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGuests = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'guest'")->fetchColumn();
$totalHosts = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'host'")->fetchColumn();

$totalListings = (int)$db->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$pendingListings = (int)$db->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
$approvedListings = (int)$db->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();

$totalBookings = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();

// Recent Pending Listings
$stmtPending = $db->query("
    SELECT l.*, u.name as host_name, u.email as host_email 
    FROM listings l 
    JOIN users u ON l.host_id = u.id 
    WHERE l.status = 'pending' 
    ORDER BY l.created_at DESC 
    LIMIT 5
");
$pendingList = $stmtPending->fetchAll();

// Recent Bookings
$stmtBookings = $db->query("
    SELECT b.*, l.title as listing_title, u.name as guest_name 
    FROM bookings b 
    JOIN listings l ON b.listing_id = l.id 
    JOIN users u ON b.guest_id = u.id 
    ORDER BY b.created_at DESC 
    LIMIT 6
");
$recentBookings = $stmtBookings->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <span class="badge bg-danger mb-1 text-uppercase">Admin Portal</span>
            <h2 class="fw-bold mb-0">Platform Overview</h2>
            <p class="text-muted small mb-0">Monitor ecosystem health, review pending approvals, and manage members.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/admin/listings.php" class="btn btn-outline-dark rounded-pill btn-sm">
                <i class="fa-solid fa-list-check me-1"></i> Listings Approvals (<?= $pendingListings ?>)
            </a>
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-dark rounded-pill btn-sm">
                <i class="fa-solid fa-users-gear me-1"></i> Manage Users
            </a>
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-danger rounded-pill btn-sm">
                <i class="fa-solid fa-receipt me-1"></i> All Bookings
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-3 mb-5">
        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Platform Revenue</span>
                    <div class="rounded-circle bg-light text-success p-2">
                        <i class="fa-solid fa-sack-dollar fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-success"><?= formatPrice($totalRevenue) ?></h3>
                <small class="text-muted">Total Gross Bookings</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Users</span>
                    <div class="rounded-circle bg-light text-primary p-2">
                        <i class="fa-solid fa-users fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $totalUsers ?></h3>
                <small class="text-muted"><?= $totalGuests ?> Guests &bull; <?= $totalHosts ?> Hosts</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Listings</span>
                    <div class="rounded-circle bg-light text-warning p-2">
                        <i class="fa-solid fa-house-circle-check fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $totalListings ?></h3>
                <small class="text-muted"><?= $approvedListings ?> Approved &bull; <strong class="text-danger"><?= $pendingListings ?> Pending</strong></small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-4 rounded-4 border shadow-sm bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reservations</span>
                    <div class="rounded-circle bg-light text-danger p-2">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0"><?= $totalBookings ?></h3>
                <small class="text-muted">Across all destinations</small>
            </div>
        </div>
    </div>

    <!-- Pending Listings Section -->
    <?php if ($pendingListings > 0): ?>
        <div class="card border-warning border rounded-4 shadow-sm mb-5 overflow-hidden">
            <div class="card-header bg-warning bg-opacity-10 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-bell text-warning me-2"></i> Listings Awaiting Approval (<?= $pendingListings ?>)
                </h5>
                <a href="<?= BASE_URL ?>/admin/listings.php?status=pending" class="btn btn-warning btn-sm rounded-pill fw-bold">
                    Review All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-4">Title</th>
                            <th>Host</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th class="text-end pe-4">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingList as $pl): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= e($pl['title']) ?></td>
                                <td><?= e($pl['host_name']) ?> <small class="text-muted">(<?= e($pl['host_email']) ?>)</small></td>
                                <td><?= e($pl['city']) ?></td>
                                <td><?= formatPrice($pl['price_per_night']) ?></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/listings.php?action=approve&id=<?= (int)$pl['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-success btn-sm rounded-pill me-1">Approve</a>
                                    <a href="<?= BASE_URL ?>/admin/listings.php?action=reject&id=<?= (int)$pl['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-outline-danger btn-sm rounded-pill">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Global Bookings -->
    <div class="card border rounded-4 shadow-sm overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Recent Platform Bookings</h5>
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="text-danger fw-semibold text-decoration-none small">
                View all bookings &rarr;
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Stay</th>
                        <th>Guest</th>
                        <th>Dates</th>
                        <th>Total</th>
                        <th>Payment Status</th>
                        <th>Booking Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBookings as $rb): ?>
                        <tr>
                            <td class="ps-4 fw-bold font-monospace text-danger"><?= e($rb['booking_code']) ?></td>
                            <td class="fw-semibold"><?= e($rb['listing_title']) ?></td>
                            <td><?= e($rb['guest_name']) ?></td>
                            <td><?= date('M j', strtotime($rb['check_in'])) ?> &ndash; <?= date('M j, Y', strtotime($rb['check_out'])) ?></td>
                            <td class="fw-bold text-success"><?= formatPrice($rb['total_price']) ?></td>
                            <td><span class="badge bg-success"><?= ucfirst($rb['payment_status']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= ucfirst($rb['booking_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

