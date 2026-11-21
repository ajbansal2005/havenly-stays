<?php
/**
 * StayHub Homepage
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();

// Fetch featured & approved stays
$sqlFeatured = "SELECT l.*,
                COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) AS avg_rating,
                COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) AS review_count,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image
                FROM listings l
                WHERE l.status = 'approved'
                ORDER BY l.featured DESC, l.id DESC
                LIMIT 8";
$stmtFeatured = $db->query($sqlFeatured);
$featuredStays = $stmtFeatured->fetchAll();

// Top cities with count
$sqlCities = "SELECT city, COUNT(*) as stay_count 
              FROM listings 
              WHERE status = 'approved' 
              GROUP BY city 
              ORDER BY stay_count DESC 
              LIMIT 6";
$stmtCities = $db->query($sqlCities);
$topCities = $stmtCities->fetchAll();

$pageTitle = 'Vacation Homes & Condo Rentals';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner & Floating Search Bar -->
<div class="container pt-4">
    <div class="hero-banner text-center d-flex flex-column justify-content-center align-items-center">
        <h1 class="display-4 fw-extrabold mb-3 text-white" style="letter-spacing: -1px; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
            Find your next unforgettable stay
        </h1>
        <p class="lead mb-4 text-white-50" style="max-width: 650px; text-shadow: 0 1px 4px rgba(0,0,0,0.5);">
            Discover luxury beachfront villas, tranquil mountain cabins, chic city penthouses and more.
        </p>
    </div>

    <!-- Floating Search Bar -->
    <div class="hero-search-box mx-auto" style="max-width: 880px;">
        <form action="<?= BASE_URL ?>/listings.php" method="GET" class="row g-2 align-items-center m-0">
            <!-- Where -->
            <div class="col-md-4 col-12 border-md-end py-1 px-3">
                <label class="form-label text-uppercase fw-bold text-dark mb-0" style="font-size: 11px;">Where</label>
                <input type="text" name="city" class="form-control border-0 p-0 shadow-none fw-semibold" placeholder="Search destinations (e.g. Goa, Mumbai)" autocomplete="off">
            </div>

            <!-- Check In & Out -->
            <div class="col-md-4 col-12 border-md-end py-1 px-3">
                <label class="form-label text-uppercase fw-bold text-dark mb-0" style="font-size: 11px;">When</label>
                <div class="d-flex align-items-center gap-1">
                    <input type="text" id="heroDateRange" class="form-control border-0 p-0 shadow-none fw-semibold" placeholder="Add dates">
                    <input type="hidden" name="check_in" id="heroCheckIn">
                    <input type="hidden" name="check_out" id="heroCheckOut">
                </div>
            </div>

            <!-- Who -->
            <div class="col-md-2 col-6 py-1 px-3">
                <label class="form-label text-uppercase fw-bold text-dark mb-0" style="font-size: 11px;">Who</label>
                <select name="guests" class="form-select border-0 p-0 shadow-none fw-semibold">
                    <option value="1">1 guest</option>
                    <option value="2">2 guests</option>
                    <option value="3">3 guests</option>
                    <option value="4">4 guests</option>
                    <option value="6">6+ guests</option>
                </select>
            </div>

            <!-- Search Button -->
            <div class="col-md-2 col-6 text-end">
                <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 px-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Search</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Property Type Category Filter Bar -->
<div class="container mt-5 pt-3">
    <div class="d-flex gap-4 overflow-x-auto pb-2 border-bottom category-scroll-container">
        <a href="<?= BASE_URL ?>/listings.php" class="category-filter-item active">
            <i class="fa-solid fa-fire"></i>
            <span>Trending</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Villa" class="category-filter-item">
            <i class="fa-solid fa-water-ladder"></i>
            <span>Villas</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Apartment" class="category-filter-item">
            <i class="fa-solid fa-building"></i>
            <span>Apartments</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Cabin" class="category-filter-item">
            <i class="fa-solid fa-tree"></i>
            <span>Cabins</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Heritage" class="category-filter-item">
            <i class="fa-solid fa-monument"></i>
            <span>Heritage</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Loft" class="category-filter-item">
            <i class="fa-solid fa-couch"></i>
            <span>Lofts</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php?property_type=Cottage" class="category-filter-item">
            <i class="fa-solid fa-house-chimney"></i>
            <span>Cottages</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php" class="category-filter-item">
            <i class="fa-solid fa-umbrella-beach"></i>
            <span>Beachfront</span>
        </a>
        <a href="<?= BASE_URL ?>/listings.php" class="category-filter-item">
            <i class="fa-solid fa-mountain-sun"></i>
            <span>Amazing Views</span>
        </a>
    </div>
</div>

<!-- Featured Stays Grid -->
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h3 class="fw-bold mb-1">Featured Stays Worldwide</h3>
            <p class="text-muted small mb-0">Handpicked retreats with top guest reviews and exceptional hospitality.</p>
        </div>
        <a href="<?= BASE_URL ?>/listings.php" class="fw-semibold text-danger text-decoration-none small">
            View all stays <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="row g-4">
        <?php foreach ($featuredStays as $stay): 
            $primaryImg = $stay['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800';
            $isFav = isWishlisted((int)$stay['id']);
        ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                <div class="stay-card h-100 position-relative">
                    <!-- Wishlist Toggle -->
                    <button type="button" class="wishlist-heart-btn <?= $isFav ? 'active' : '' ?>" data-listing-id="<?= (int)$stay['id'] ?>" title="Save to wishlist">
                        <i class="<?= $isFav ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                    </button>

                    <!-- Image with link -->
                    <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$stay['id'] ?>" class="text-decoration-none text-dark">
                        <div class="stay-image-wrapper mb-2">
                            <span class="stay-badge-type"><?= e($stay['property_type']) ?></span>
                            <img src="<?= e($primaryImg) ?>" alt="<?= e($stay['title']) ?>" loading="lazy">
                        </div>

                        <div class="d-flex justify-content-between align-items-start mt-2">
                            <h6 class="fw-bold mb-0 text-truncate" style="max-width: 75%;" title="<?= e($stay['title']) ?>">
                                <?= e($stay['title']) ?>
                            </h6>
                            <div class="small">
                                <?= renderRatingStars($stay['avg_rating'], $stay['review_count']) ?>
                            </div>
                        </div>

                        <div class="text-muted small mb-1">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= e($stay['city']) ?>, <?= e($stay['country']) ?>
                        </div>

                        <div class="text-muted small mb-2">
                            <?= (int)$stay['max_guests'] ?> guests &bull; <?= (int)$stay['bedrooms'] ?> bed(s) &bull; <?= (int)$stay['bathrooms'] ?> bath(s)
                        </div>

                        <div class="d-flex align-items-baseline gap-1">
                            <span class="fw-bold fs-6 text-dark"><?= formatPrice($stay['price_per_night']) ?></span>
                            <span class="text-muted small">night</span>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Popular Destinations Showcase -->
<div class="container py-4 my-3">
    <div class="bg-light rounded-4 p-4 p-md-5">
        <h4 class="fw-bold mb-1">Explore Popular Destinations</h4>
        <p class="text-muted small mb-4">Discover dream stays across our most loved travel hotspots.</p>

        <div class="row g-3">
            <?php 
            $cityPhotos = [
                'Goa' => 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?w=500&auto=format&fit=crop&q=80',
                'Mumbai' => 'https://images.unsplash.com/photo-1566552881560-0be862a7c445?w=500&auto=format&fit=crop&q=80',
                'Manali' => 'https://images.unsplash.com/photo-1626621341517-bbf3d9990a23?w=500&auto=format&fit=crop&q=80',
                'Jaipur' => 'https://images.unsplash.com/photo-1477587458883-47145ed94245?w=500&auto=format&fit=crop&q=80',
                'Bengaluru' => 'https://images.unsplash.com/photo-1596176530529-78163a4f7af2?w=500&auto=format&fit=crop&q=80',
                'Alleppey' => 'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?w=500&auto=format&fit=crop&q=80'
            ];
            foreach ($topCities as $c): 
                $cityName = $c['city'];
                $cityImg = $cityPhotos[$cityName] ?? 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?w=500';
            ?>
                <div class="col-lg-2 col-md-4 col-6">
                    <a href="<?= BASE_URL ?>/listings.php?city=<?= urlencode($cityName) ?>" class="text-decoration-none group-destination">
                        <div class="card border-0 rounded-4 overflow-hidden shadow-sm h-100 text-white position-relative" style="height: 140px;">
                            <img src="<?= e($cityImg) ?>" class="w-100 h-100 object-fit-cover" alt="<?= e($cityName) ?>">
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-25"></div>
                            <div class="position-absolute bottom-0 start-0 p-3 w-100" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
                                <h6 class="fw-bold mb-0 text-white"><?= e($cityName) ?></h6>
                                <span class="small text-white-50"><?= (int)$c['stay_count'] ?> stay(s)</span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Host CTA Banner -->
<div class="container py-4 mb-4">
    <div class="card border-0 rounded-4 overflow-hidden text-white" style="background: linear-gradient(135deg, #222222 0%, #3a3a3a 100%);">
        <div class="row align-items-center g-0 p-4 p-md-5">
            <div class="col-lg-7">
                <span class="badge bg-danger mb-2 text-uppercase px-2 py-1">Become a StayHub Host</span>
                <h2 class="display-6 fw-bold mb-3">Earn extra income by opening your doors to travelers</h2>
                <p class="text-white-50 mb-4" style="max-width: 500px;">
                    List your apartment, beach house, or cabin. Set your pricing, manage availability with our smart calendar, and welcome guests with confidence.
                </p>
                <a href="<?= BASE_URL ?>/register.php?role=host" class="btn btn-danger btn-lg rounded-pill px-4 fw-semibold">
                    Get Started Hosting
                </a>
            </div>
            <div class="col-lg-5 text-center mt-4 mt-lg-0 d-none d-lg-block">
                <img src="https://images.unsplash.com/photo-1556912172-45b7abe8b7e1?w=500&auto=format&fit=crop&q=80" class="img-fluid rounded-4 shadow" alt="Host your home" style="max-height: 280px;">
            </div>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
$(document).ready(function() {
    flatpickr('#heroDateRange', {
        mode: 'range',
        minDate: 'today',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'M j',
        onClose: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                $('#heroCheckIn').val(instance.formatDate(selectedDates[0], 'Y-m-d'));
                $('#heroCheckOut').val(instance.formatDate(selectedDates[1], 'Y-m-d'));
            }
        }
    });
});
</script>
";
include __DIR__ . '/includes/footer.php'; 
?>

