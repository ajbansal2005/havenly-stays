<?php
/**
 * StayHub Host - Manage Listings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();

// Fetch host listings with bookings count & primary image
$sql = "SELECT l.*,
               COALESCE((SELECT COUNT(*) FROM bookings WHERE listing_id = l.id), 0) AS total_bookings,
               COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) AS avg_rating,
               (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image
        FROM listings l
        WHERE l.host_id = :hid
        ORDER BY l.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([':hid' => $hostId]);
$listings = $stmt->fetchAll();

$pageTitle = 'My Listings - Host';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">My Properties</h2>
            <p class="text-muted small mb-0">View, update, or remove your listings.</p>
        </div>
        <a href="<?= BASE_URL ?>/host/add_listing.php" class="btn btn-danger rounded-pill px-4">
            <i class="fa-solid fa-plus me-1"></i> Add New Stay
        </a>
    </div>

    <?php if (!empty($listings)): ?>
        <div class="card border rounded-4 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Listing</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Price / Night</th>
                            <th>Status</th>
                            <th>Bookings</th>
                            <th>Rating</th>
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
                                        <img src="<?= e($img) ?>" class="rounded-3 object-fit-cover shadow-sm" width="60" height="60" alt="Listing">
                                        <div>
                                            <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$l['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($l['title']) ?>
                                            </a>
                                            <div class="text-muted small"><?= (int)$l['bedrooms'] ?> bed &bull; <?= (int)$l['bathrooms'] ?> bath &bull; <?= (int)$l['max_guests'] ?> guests</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($l['property_type']) ?></span></td>
                                <td><?= e($l['city']) ?></td>
                                <td class="fw-bold"><?= formatPrice($l['price_per_night']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($l['status']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= (int)$l['total_bookings'] ?></span></td>
                                <td><?= renderRatingStars($l['avg_rating']) ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= (int)$l['id'] ?>" class="btn btn-outline-dark" title="View Preview">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/host/edit_listing.php?id=<?= (int)$l['id'] ?>" class="btn btn-outline-primary" title="Edit Listing">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/host/delete_listing.php?id=<?= (int)$l['id'] ?>&csrf_token=<?= csrfToken() ?>" 
                                           class="btn btn-outline-danger" 
                                           title="Delete Listing" 
                                           onclick="return confirm('Are you sure you want to permanently delete this listing?');">
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
                <i class="fa-solid fa-hotel fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold mb-2">No listings yet</h4>
                <p class="text-muted small mb-4">Start earning by listing your first home, apartment, or villa on StayHub today.</p>
                <a href="<?= BASE_URL ?>/host/add_listing.php" class="btn btn-danger rounded-pill px-4">Create Your First Listing</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

