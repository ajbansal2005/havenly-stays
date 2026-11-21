<?php
/**
 * StayHub User Wishlist / Saved Stays
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Fetch wishlisted stays
$sql = "SELECT l.*,
               COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) AS avg_rating,
               COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) AS review_count,
               (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image
        FROM wishlists w
        JOIN listings l ON w.listing_id = l.id
        WHERE w.user_id = :uid
        ORDER BY w.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':uid' => $userId]);
$wishlistItems = $stmt->fetchAll();

$pageTitle = 'My Wishlist';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Your Wishlist</h2>
            <p class="text-muted small mb-0">Stays and retreats you've saved for future adventures.</p>
        </div>
        <a href="<?= BASE_URL ?>/listings.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
            <i class="fa-solid fa-plus me-1"></i> Explore More Stays
        </a>
    </div>

    <?php if (!empty($wishlistItems)): ?>
        <div class="row g-4">
            <?php foreach ($wishlistItems as $stay): 
                $primaryImg = $stay['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800';
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                    <div class="stay-card h-100 position-relative">
                        <!-- Active Wishlist Heart (clicking toggles off) -->
                        <button type="button" class="wishlist-heart-btn active" data-listing-id="<?= (int)$stay['id'] ?>" title="Remove from wishlist">
                            <i class="fa-solid fa-heart"></i>
                        </button>

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
    <?php else: ?>
        <div class="text-center py-5">
            <div class="p-5 bg-light rounded-4 mx-auto" style="max-width: 500px;">
                <i class="fa-regular fa-heart fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">Your wishlist is empty</h4>
                <p class="text-muted small mb-4">As you search, tap the heart icon on any stay to save your favorite destinations here.</p>
                <a href="<?= BASE_URL ?>/listings.php" class="btn btn-danger rounded-pill px-4">Start Exploring</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

