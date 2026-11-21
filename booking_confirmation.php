<?php
/**
 * StayHub Booking Confirmation Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$code = trim($_GET['code'] ?? '');
if (empty($code)) {
    header('Location: ' . BASE_URL . '/my_bookings.php');
    exit;
}

$db = getDB();
$userId = getCurrentUserId();

$stmt = $db->prepare("
    SELECT b.*, l.title as listing_title, l.property_type, l.address as listing_address, l.city as listing_city, l.country as listing_country,
           u.name as host_name, u.email as host_email, u.phone as host_phone,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image
    FROM bookings b
    JOIN listings l ON b.listing_id = l.id
    JOIN users u ON l.host_id = u.id
    WHERE b.booking_code = :code AND b.guest_id = :gid
");
$stmt->execute([':code' => $code, ':gid' => $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('danger', 'Booking reservation not found.');
    header('Location: ' . BASE_URL . '/my_bookings.php');
    exit;
}

$pageTitle = 'Booking Confirmed - ' . $booking['booking_code'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card shadow-sm border-0 rounded-4 overflow-hidden p-4 p-md-5">
                
                <!-- Success Header -->
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 72px; height: 72px; font-size: 32px;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2 class="fw-bold">Reservation Confirmed!</h2>
                    <p class="text-muted">You're all set for your upcoming trip to <strong><?= e($booking['listing_city']) ?></strong>.</p>
                    <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                        Booking Code: <strong class="text-danger"><?= e($booking['booking_code']) ?></strong>
                    </span>
                </div>

                <!-- Stay Details Card -->
                <div class="card border rounded-4 overflow-hidden mb-4 bg-light">
                    <div class="row g-0">
                        <div class="col-md-5">
                            <img src="<?= e($booking['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=600') ?>" class="w-100 h-100 object-fit-cover" style="min-height: 180px;" alt="Stay">
                        </div>
                        <div class="col-md-7 p-4">
                            <span class="badge bg-danger text-uppercase mb-2" style="font-size: 11px;"><?= e($booking['property_type']) ?></span>
                            <h5 class="fw-bold mb-2"><?= e($booking['listing_title']) ?></h5>
                            <p class="text-muted small mb-3">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($booking['listing_address']) ?>, <?= e($booking['listing_city']) ?>
                            </p>
                            <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$booking['listing_id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill">
                                View Stay Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Key Dates & Pricing Breakdown -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Check-in</div>
                            <div class="fw-bold fs-5 text-dark"><?= date('D, M j, Y', strtotime($booking['check_in'])) ?></div>
                            <small class="text-muted">From 2:00 PM</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Check-out</div>
                            <div class="fw-bold fs-5 text-dark"><?= date('D, M j, Y', strtotime($booking['check_out'])) ?></div>
                            <small class="text-muted">Until 11:00 AM</small>
                        </div>
                    </div>
                </div>

                <table class="table table-bordered mb-4">
                    <tbody>
                        <tr>
                            <td class="text-muted">Total Nights</td>
                            <td class="fw-bold text-end"><?= (int)$booking['total_nights'] ?> night(s)</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Guests</td>
                            <td class="fw-bold text-end"><?= (int)$booking['guests_count'] ?> guest(s)</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Amount Paid</td>
                            <td class="fw-bold text-end text-success fs-6"><?= formatPrice($booking['total_price']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Razorpay Payment ID</td>
                            <td class="fw-bold text-end font-monospace small"><?= e($booking['razorpay_payment_id']) ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Host Contact -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <h6 class="fw-bold mb-1">Host Information</h6>
                    <div class="text-muted small">
                        <strong><?= e($booking['host_name']) ?></strong> &bull; <?= e($booking['host_email']) ?> &bull; <?= e($booking['host_phone'] ?? 'Via StayHub') ?>
                    </div>
                </div>

                <!-- Email confirmation alert -->
                <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                    <i class="fa-solid fa-envelope-circle-check fa-2x text-primary"></i>
                    <div class="small">
                        <strong>Email Confirmation Sent:</strong> An HTML booking receipt has been prepared and archived. You can inspect it directly inside the system's mail receipts log!
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-between">
                    <a href="<?= BASE_URL ?>/my_bookings.php" class="btn btn-outline-dark rounded-pill px-4">
                        <i class="fa-solid fa-suitcase me-2"></i> View My Bookings
                    </a>
                    <a href="<?= BASE_URL ?>/listings.php" class="btn btn-danger rounded-pill px-4">
                        Explore More Stays &rarr;
                    </a>
                </div>

            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

