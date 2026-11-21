<?php
/**
 * StayHub Listing Detail Page
 * Features Airbnb-style photo grid, reviews, amenities, Leaflet map, and interactive booking widget.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listingId <= 0) {
    header('Location: ' . BASE_URL . '/listings.php');
    exit;
}

$db = getDB();
$currentUser = getCurrentUser();
$userId = $currentUser['id'] ?? null;

// Fetch listing
$stmtListing = $db->prepare("
    SELECT l.*, 
           u.name AS host_name, u.avatar AS host_avatar, u.bio AS host_bio, u.created_at AS host_joined,
           COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) AS avg_rating,
           COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) AS review_count
    FROM listings l
    JOIN users u ON l.host_id = u.id
    WHERE l.id = :id
");
$stmtListing->execute([':id' => $listingId]);
$listing = $stmtListing->fetch();

if (!$listing) {
    setFlash('danger', 'Listing not found.');
    header('Location: ' . BASE_URL . '/listings.php');
    exit;
}

// Check approval status: only host or admin can view pending/rejected listings
if ($listing['status'] !== 'approved' && (!isLoggedIn() || ($listing['host_id'] !== $userId && !hasRole('admin')))) {
    setFlash('warning', 'This listing is currently pending approval.');
    header('Location: ' . BASE_URL . '/listings.php');
    exit;
}

// Fetch images
$stmtImgs = $db->prepare("SELECT * FROM listing_images WHERE listing_id = :lid ORDER BY is_primary DESC, id ASC");
$stmtImgs->execute([':lid' => $listingId]);
$images = $stmtImgs->fetchAll();

// Fallback images if none exists
if (empty($images)) {
    $images = [
        ['image_url' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1200', 'is_primary' => 1]
    ];
}

// Fetch amenities
$stmtAm = $db->prepare("
    SELECT a.* FROM amenities a
    JOIN listing_amenities la ON a.id = la.amenity_id
    WHERE la.listing_id = :lid
    ORDER BY a.category ASC, a.name ASC
");
$stmtAm->execute([':lid' => $listingId]);
$amenities = $stmtAm->fetchAll();

// Fetch reviews
$stmtRev = $db->prepare("
    SELECT r.*, u.name as guest_name, u.avatar as guest_avatar
    FROM reviews r
    JOIN users u ON r.guest_id = u.id
    WHERE r.listing_id = :lid
    ORDER BY r.created_at DESC
");
$stmtRev->execute([':lid' => $listingId]);
$reviews = $stmtRev->fetchAll();

// Check if current user is eligible to write a review
$canReview = false;
$eligibleBookingId = null;
if (isLoggedIn() && hasRole('guest')) {
    $today = date('Y-m-d');
    $stmtEligible = $db->prepare("
        SELECT id FROM bookings 
        WHERE listing_id = :lid 
          AND guest_id = :gid 
          AND (booking_status = 'completed' OR (booking_status = 'confirmed' AND check_out <= :today))
        LIMIT 1
    ");
    $stmtEligible->execute([':lid' => $listingId, ':gid' => $userId, ':today' => $today]);
    $bookingCheck = $stmtEligible->fetch();

    if ($bookingCheck) {
        // Check if already reviewed
        $stmtRevDone = $db->prepare("SELECT 1 FROM reviews WHERE listing_id = :lid AND guest_id = :gid");
        $stmtRevDone->execute([':lid' => $listingId, ':gid' => $userId]);
        if (!$stmtRevDone->fetch()) {
            $canReview = true;
            $eligibleBookingId = (int)$bookingCheck['id'];
        }
    }
}

$isFav = isWishlisted($listingId, $userId);
$pageTitle = $listing['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Title & Top Meta Header -->
    <div class="mb-3">
        <h2 class="fw-bold mb-2"><?= e($listing['title']) ?></h2>
        <div class="d-flex flex-wrap justify-content-between align-items-center text-muted small gap-2">
            <div class="d-flex align-items-center gap-3">
                <span>
                    <?= renderRatingStars($listing['avg_rating'], $listing['review_count']) ?>
                </span>
                <span>&bull;</span>
                <span class="text-decoration-underline fw-semibold text-dark">
                    <?= e($listing['city']) ?>, <?= e($listing['country']) ?>
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-dark btn-sm rounded-pill wishlist-heart-btn position-static <?= $isFav ? 'active' : '' ?>" data-listing-id="<?= $listingId ?>">
                    <i class="<?= $isFav ? 'fa-solid' : 'fa-regular' ?> fa-heart me-1"></i> Save
                </button>
            </div>
        </div>
    </div>

    <!-- Airbnb-style Photo Gallery Grid -->
    <div class="row g-2 detail-gallery mb-5">
        <!-- Main Large Photo (Left 6 cols) -->
        <div class="col-md-6 col-12 h-100">
            <img src="<?= e($images[0]['image_url']) ?>" class="w-100 h-100 gallery-main-img" alt="Primary view" data-bs-toggle="modal" data-bs-target="#galleryModal">
        </div>

        <!-- 4 Secondary Photos (Right 6 cols in 2x2 grid) -->
        <div class="col-md-6 col-12 d-none d-md-block h-100">
            <div class="row g-2 h-100">
                <?php for ($i = 1; $i <= 4; $i++): 
                    $subImg = $images[$i]['image_url'] ?? $images[0]['image_url'];
                ?>
                    <div class="col-6">
                        <img src="<?= e($subImg) ?>" class="w-100 gallery-sub-img rounded-3" alt="Stay gallery <?= $i ?>" data-bs-toggle="modal" data-bs-target="#galleryModal">
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Main Content & Sticky Booking Widget Layout -->
    <div class="row g-5">
        
        <!-- Left: Stay Details -->
        <div class="col-lg-7 col-xl-8">
            
            <!-- Specs & Host info -->
            <div class="d-flex justify-content-between align-items-center pb-4 border-bottom">
                <div>
                    <h4 class="fw-bold mb-1">
                        <?= e($listing['property_type']) ?> hosted by <?= e($listing['host_name']) ?>
                    </h4>
                    <div class="text-muted">
                        <?= (int)$listing['max_guests'] ?> guests &bull; <?= (int)$listing['bedrooms'] ?> bedroom(s) &bull; <?= (int)$listing['beds'] ?> bed(s) &bull; <?= (int)$listing['bathrooms'] ?> bath(s)
                    </div>
                </div>
                <img src="<?= e($listing['host_avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200') ?>" class="rounded-circle object-fit-cover shadow-sm" width="60" height="60" alt="Host Avatar">
            </div>

            <!-- Key Features / Highlights -->
            <div class="py-4 border-bottom">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <i class="fa-solid fa-award fa-xl text-danger"></i>
                    <div>
                        <div class="fw-bold">Experienced Superhost</div>
                        <div class="text-muted small">Superhosts are experienced, highly rated hosts committed to great stays.</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <i class="fa-solid fa-key fa-xl text-danger"></i>
                    <div>
                        <div class="fw-bold">Self check-in</div>
                        <div class="text-muted small">Check yourself in effortlessly with the smart keypad.</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <i class="fa-regular fa-calendar-xmark fa-xl text-danger"></i>
                    <div>
                        <div class="fw-bold">Free cancellation for 48 hours</div>
                        <div class="text-muted small">Lock in your dates with flexibility.</div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="py-4 border-bottom">
                <h5 class="fw-bold mb-3">About this space</h5>
                <p class="text-secondary lh-lg" style="white-space: pre-line;"><?= e($listing['description']) ?></p>
            </div>

            <!-- Amenities -->
            <div class="py-4 border-bottom">
                <h5 class="fw-bold mb-3">What this place offers</h5>
                <div class="row g-3">
                    <?php if (!empty($amenities)): ?>
                        <?php foreach ($amenities as $am): ?>
                            <div class="col-md-6 col-12 d-flex align-items-center gap-3">
                                <i class="<?= e($am['icon']) ?> text-dark fa-lg" style="width: 24px;"></i>
                                <span><?= e($am['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Standard amenities provided.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Location with Leaflet Map -->
            <div class="py-4 border-bottom">
                <h5 class="fw-bold mb-2">Where you'll be</h5>
                <p class="text-muted small mb-3">
                    <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($listing['address']) ?>, <?= e($listing['city']) ?>, <?= e($listing['state'] ?? '') ?>, <?= e($listing['country']) ?>
                </p>
                <div id="propertyDetailMap" 
                     class="stay-map-container" 
                     style="height: 380px; min-height: 380px;" 
                     data-lat="<?= (float)$listing['latitude'] ?>" 
                     data-lng="<?= (float)$listing['longitude'] ?>" 
                     data-title="<?= e($listing['title']) ?>">
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-star text-warning me-1"></i>
                        <?= number_format((float)$listing['avg_rating'], 1) ?> &bull; <?= (int)$listing['review_count'] ?> review(s)
                    </h5>
                    <?php if ($canReview): ?>
                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#writeReviewModal">
                            <i class="fa-solid fa-pen me-1"></i> Write a Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($reviews)): ?>
                    <div class="row g-4">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="col-md-6 col-12">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="<?= e($rev['guest_avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200') ?>" class="rounded-circle object-fit-cover" width="45" height="45" alt="Guest">
                                    <div>
                                        <div class="fw-bold"><?= e($rev['guest_name']) ?></div>
                                        <div class="text-muted" style="font-size: 12px;"><?= date('F Y', strtotime($rev['created_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="mb-2 text-warning small">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-<?= $s <= (int)$rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-secondary small mb-0"><?= e($rev['comment']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 bg-light rounded-4 text-center">
                        <i class="fa-regular fa-star fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0 small">No reviews yet for this property. Be the first guest to leave one!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: Sticky Booking Widget -->
        <div class="col-lg-5 col-xl-4">
            <div class="card p-4 booking-widget-card bg-white">
                <div class="d-flex justify-content-between align-items-baseline mb-4">
                    <div>
                        <span class="fs-3 fw-bold"><?= formatPrice($listing['price_per_night']) ?></span>
                        <span class="text-muted"> / night</span>
                    </div>
                    <div class="small">
                        <?= renderRatingStars($listing['avg_rating'], $listing['review_count']) ?>
                    </div>
                </div>

                <!-- Booking Form -->
                <form id="bookingForm" action="<?= BASE_URL ?>/book.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="listing_id" id="bookingListingId" value="<?= $listingId ?>">
                    <input type="hidden" name="check_in" id="bookingCheckIn" value="">
                    <input type="hidden" name="check_out" id="bookingCheckOut" value="">
                    <input type="hidden" name="total_price" id="bookingTotalPrice" value="0">

                    <div class="border rounded-3 overflow-hidden mb-3">
                        <div class="p-2 border-bottom bg-light">
                            <label class="form-label text-uppercase fw-bold text-muted mb-0" style="font-size: 10px;">Check-in / Check-out</label>
                            <input type="text" id="bookingDatesInput" class="form-control border-0 p-0 shadow-none bg-transparent fw-semibold" placeholder="Select dates on calendar">
                        </div>
                        <div class="p-2 bg-light">
                            <label class="form-label text-uppercase fw-bold text-muted mb-0" style="font-size: 10px;">Guests</label>
                            <select name="guests_count" id="bookingGuests" class="form-select border-0 p-0 shadow-none bg-transparent fw-semibold">
                                <?php for ($g = 1; $g <= (int)$listing['max_guests']; $g++): ?>
                                    <option value="<?= $g ?>"><?= $g ?> guest<?= $g > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Live Price Computation Box -->
                    <div id="priceCalculationBox" class="mb-4">
                        <div class="text-muted small text-center py-2">Select check-in and check-out dates to view price calculation.</div>
                    </div>

                    <button type="submit" id="btnReserve" class="btn btn-danger w-100 py-3 rounded-3 fw-bold fs-6 shadow-sm mb-2" disabled>
                        Check Availability
                    </button>

                    <div class="text-center">
                        <small class="text-muted">You won't be charged yet &bull; Protected by Razorpay</small>
                    </div>
                </form>

                <hr class="my-4">

                <!-- Host Guarantee / Highlights -->
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="fa-solid fa-shield-halved text-danger fa-lg"></i>
                    <span><strong>StayCover:</strong> Every booking includes free protection from Host cancellations, listing inaccuracies, and check-in issues.</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Full Gallery Modal -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Photo Tour</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <?php foreach ($images as $img): ?>
                        <div class="col-md-6 col-12">
                            <img src="<?= e($img['image_url']) ?>" class="img-fluid rounded-3 w-100 object-fit-cover shadow-sm" style="max-height: 400px;" alt="Photo">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Write Review Modal -->
<?php if ($canReview): ?>
<div class="modal fade" id="writeReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Review Your Stay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                    <input type="hidden" name="booking_id" value="<?= $eligibleBookingId ?>">

                    <div class="mb-3 text-center">
                        <label class="form-label fw-bold d-block mb-2">Overall Rating</label>
                        <div class="star-rating d-inline-flex gap-2 fs-3">
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <input type="radio" name="rating" id="star_<?= $r ?>" value="<?= $r ?>" class="btn-check" <?= $r === 5 ? 'checked' : '' ?>>
                                <label for="star_<?= $r ?>" class="cursor-pointer text-warning"><i class="fa-solid fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Your Experience</label>
                        <textarea name="comment" rows="4" class="form-control rounded-3" required placeholder="What was great about this stay? How was the host? What could be improved?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
$extraScripts = '
<script src="' . BASE_URL . '/assets/js/booking.js"></script>
';
include __DIR__ . '/includes/footer.php'; 
?>

