<?php
/**
 * StayHub Guest Bookings Management
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$today = date('Y-m-d');

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('danger', 'Security session expired.');
    } else {
        $stmtCancel = $db->prepare("
            UPDATE bookings 
            SET booking_status = 'cancelled' 
            WHERE id = :id AND guest_id = :gid AND booking_status = 'confirmed' AND check_in > :today
        ");
        $stmtCancel->execute([':id' => $bookingId, ':gid' => $userId, ':today' => $today]);

        if ($stmtCancel->rowCount() > 0) {
            setFlash('success', 'Your booking reservation has been cancelled.');
        } else {
            setFlash('danger', 'Unable to cancel booking. It may have already started or been cancelled.');
        }
    }
    header('Location: ' . BASE_URL . '/my_bookings.php');
    exit;
}

// Fetch all bookings for this user
$sql = "SELECT b.*, l.title as listing_title, l.property_type, l.address as listing_address, l.city as listing_city,
               u.name as host_name, u.email as host_email, u.phone as host_phone,
               (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image,
               (SELECT id FROM reviews WHERE booking_id = b.id OR (listing_id = b.listing_id AND guest_id = b.guest_id) LIMIT 1) as existing_review_id
        FROM bookings b
        JOIN listings l ON b.listing_id = l.id
        JOIN users u ON l.host_id = u.id
        WHERE b.guest_id = :gid
        ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':gid' => $userId]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Trips & Bookings';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Trips</h2>
            <p class="text-muted small mb-0">View all your reservations, past trips, and receipts.</p>
        </div>
        <a href="<?= BASE_URL ?>/listings.php" class="btn btn-danger btn-sm rounded-pill px-3">
            <i class="fa-solid fa-magnifying-glass me-1"></i> Book Another Stay
        </a>
    </div>

    <?php if (!empty($bookings)): ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($bookings as $b): 
                $isUpcoming = ($b['booking_status'] === 'confirmed' && $b['check_in'] > $today);
                $isCompleted = ($b['booking_status'] === 'completed' || ($b['booking_status'] === 'confirmed' && $b['check_out'] <= $today));
                $isCancelled = ($b['booking_status'] === 'cancelled');

                $statusBadgeClass = match($b['booking_status']) {
                    'confirmed' => ($isCompleted ? 'bg-secondary' : 'bg-success'),
                    'completed' => 'bg-secondary',
                    'cancelled' => 'bg-danger',
                    default => 'bg-warning text-dark'
                };
                $statusText = match($b['booking_status']) {
                    'confirmed' => ($isCompleted ? 'Completed Stay' : 'Upcoming Confirmed'),
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    default => 'Pending'
                };
            ?>
                <div class="card border rounded-4 shadow-sm overflow-hidden p-0">
                    <div class="row g-0">
                        <!-- Image -->
                        <div class="col-lg-3 col-md-4">
                            <img src="<?= e($b['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=500') ?>" class="w-100 h-100 object-fit-cover" style="min-height: 200px;" alt="Stay">
                        </div>

                        <!-- Details -->
                        <div class="col-lg-9 col-md-8 p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                    <span class="badge <?= $statusBadgeClass ?> text-uppercase px-2 py-1" style="font-size: 11px;">
                                        <?= $statusText ?>
                                    </span>
                                    <span class="text-muted small">
                                        Booking Code: <strong class="text-dark"><?= e($b['booking_code']) ?></strong>
                                    </span>
                                </div>

                                <h5 class="fw-bold mb-1">
                                    <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$b['listing_id'] ?>" class="text-decoration-none text-dark">
                                        <?= e($b['listing_title']) ?>
                                    </a>
                                </h5>

                                <p class="text-muted small mb-3">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($b['listing_address']) ?>, <?= e($b['listing_city']) ?>
                                </p>

                                <div class="row g-2 mb-3 small bg-light p-3 rounded-3">
                                    <div class="col-sm-3 col-6">
                                        <div class="text-muted">Check-in:</div>
                                        <strong><?= date('M j, Y', strtotime($b['check_in'])) ?></strong>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="text-muted">Check-out:</div>
                                        <strong><?= date('M j, Y', strtotime($b['check_out'])) ?></strong>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="text-muted">Guests:</div>
                                        <strong><?= (int)$b['guests_count'] ?> guest(s) (<?= (int)$b['total_nights'] ?> nights)</strong>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="text-muted">Total Paid:</div>
                                        <strong class="text-danger"><?= formatPrice($b['total_price']) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions Row -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                                <div class="small text-muted">
                                    Host: <strong><?= e($b['host_name']) ?></strong> &bull; <?= e($b['host_email']) ?>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= BASE_URL ?>/booking_confirmation.php?code=<?= urlencode($b['booking_code']) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                        <i class="fa-solid fa-receipt me-1"></i> View Receipt
                                    </a>

                                    <?php if ($isUpcoming): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                                <i class="fa-solid fa-ban me-1"></i> Cancel Trip
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isCompleted && empty($b['existing_review_id'])): ?>
                                        <button class="btn btn-danger btn-sm rounded-pill px-3 btn-open-review-modal" 
                                                data-listing-id="<?= (int)$b['listing_id'] ?>" 
                                                data-booking-id="<?= (int)$b['id'] ?>"
                                                data-listing-title="<?= e($b['listing_title']) ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#guestReviewModal">
                                            <i class="fa-solid fa-star me-1"></i> Review Stay
                                        </button>
                                    <?php elseif (!empty($b['existing_review_id'])): ?>
                                        <span class="badge bg-light text-muted border py-2 px-3 rounded-pill">
                                            <i class="fa-solid fa-check text-success me-1"></i> Reviewed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="p-5 bg-light rounded-4 mx-auto" style="max-width: 500px;">
                <i class="fa-solid fa-suitcase-rolling fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">No trips booked yet</h4>
                <p class="text-muted small mb-4">When you book a stay, your itinerary and trip details will be waiting for you right here.</p>
                <a href="<?= BASE_URL ?>/listings.php" class="btn btn-danger rounded-pill px-4">Find a Place to Stay</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Review Modal for My Bookings -->
<div class="modal fade" id="guestReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="reviewModalTitle">Review Your Stay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="myBookingsReviewForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="listing_id" id="modalListingId" value="">
                    <input type="hidden" name="booking_id" id="modalBookingId" value="">

                    <div class="mb-3 text-center">
                        <label class="form-label fw-bold d-block mb-2">Overall Rating</label>
                        <div class="d-inline-flex gap-2 fs-3">
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <input type="radio" name="rating" id="mstar_<?= $r ?>" value="<?= $r ?>" class="btn-check" <?= $r === 5 ? 'checked' : '' ?>>
                                <label for="mstar_<?= $r ?>" class="cursor-pointer text-warning"><i class="fa-solid fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">How was your stay?</label>
                        <textarea name="comment" rows="4" class="form-control rounded-3" required placeholder="Tell other travelers about your stay experience, cleanliness, communication, and location."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4" id="btnSubmitReview">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
$(document).ready(function() {
    $('.btn-open-review-modal').on('click', function() {
        const lid = $(this).data('listing-id');
        const bid = $(this).data('booking-id');
        const title = $(this).data('listing-title');
        $('#modalListingId').val(lid);
        $('#modalBookingId').val(bid);
        $('#reviewModalTitle').text('Review Stay at ' + title);
    });

    $('#myBookingsReviewForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#btnSubmitReview');
        btn.prop('disabled', true).html('<span class=\"spinner-border spinner-border-sm me-1\"></span> Submitting...');

        $.ajax({
            url: window.APP_CONFIG.baseUrl + '/api/submit_review.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).text('Submit Review');
                if (res.success) {
                    window.showToast(res.message, 'success');
                    $('#guestReviewModal').modal('hide');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    window.showToast(res.message, 'danger');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Submit Review');
                window.showToast('Error communicating with server', 'danger');
            }
        });
    });
});
</script>
";
include __DIR__ . '/includes/footer.php'; 
?>

