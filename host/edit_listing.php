<?php
/**
 * StayHub Host - Edit Listing
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Verify ownership or admin role
$stmtCheck = $db->prepare("SELECT * FROM listings WHERE id = :id");
$stmtCheck->execute([':id' => $listingId]);
$listing = $stmtCheck->fetch();

if (!$listing || ($listing['host_id'] !== $hostId && !hasRole('admin'))) {
    setFlash('danger', 'You do not have permission to edit this listing.');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

// Fetch all amenities
$stmtAm = $db->query("SELECT * FROM amenities ORDER BY category ASC, name ASC");
$allAmenities = $stmtAm->fetchAll();

// Fetch currently selected amenities
$stmtCurAm = $db->prepare("SELECT amenity_id FROM listing_amenities WHERE listing_id = :lid");
$stmtCurAm->execute([':lid' => $listingId]);
$currentAmenities = $stmtCurAm->fetchAll(PDO::FETCH_COLUMN);

// Fetch current images
$stmtImgs = $db->prepare("SELECT * FROM listing_images WHERE listing_id = :lid ORDER BY is_primary DESC, id ASC");
$stmtImgs->execute([':lid' => $listingId]);
$images = $stmtImgs->fetchAll();

// Handle Image Delete Action
if (isset($_GET['delete_image_id']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $delImgId = (int)$_GET['delete_image_id'];
    $stmtDelImg = $db->prepare("DELETE FROM listing_images WHERE id = :id AND listing_id = :lid");
    $stmtDelImg->execute([':id' => $delImgId, ':lid' => $listingId]);
    setFlash('info', 'Image removed.');
    header('Location: ' . BASE_URL . '/host/edit_listing.php?id=' . $listingId);
    exit;
}

// Handle Set Primary Image Action
if (isset($_GET['set_primary_id']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $primId = (int)$_GET['set_primary_id'];
    $db->prepare("UPDATE listing_images SET is_primary = 0 WHERE listing_id = :lid")->execute([':lid' => $listingId]);
    $db->prepare("UPDATE listing_images SET is_primary = 1 WHERE id = :id AND listing_id = :lid")->execute([':id' => $primId, ':lid' => $listingId]);
    setFlash('success', 'Cover photo updated.');
    header('Location: ' . BASE_URL . '/host/edit_listing.php?id=' . $listingId);
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $propertyType = trim($_POST['property_type'] ?? 'Apartment');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $latitude = (float)($_POST['latitude'] ?? $listing['latitude']);
    $longitude = (float)($_POST['longitude'] ?? $listing['longitude']);
    $pricePerNight = (float)($_POST['price_per_night'] ?? 0);
    $cleaningFee = (float)($_POST['cleaning_fee'] ?? 0);
    $serviceFee = (float)($_POST['service_fee'] ?? 0);
    $maxGuests = max(1, (int)($_POST['max_guests'] ?? 1));
    $bedrooms = max(1, (int)($_POST['bedrooms'] ?? 1));
    $beds = max(1, (int)($_POST['beds'] ?? 1));
    $bathrooms = max(1, (int)($_POST['bathrooms'] ?? 1));
    $selectedAmenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? array_map('intval', $_POST['amenities']) : [];
    $newImageUrls = isset($_POST['image_urls']) && is_array($_POST['image_urls']) ? array_filter(array_map('trim', $_POST['image_urls'])) : [];

    if (!verifyCsrfToken($csrf)) {
        $error = 'Security session expired. Please try again.';
    } elseif (empty($title) || empty($description) || empty($city) || empty($address) || $pricePerNight <= 0) {
        $error = 'Please fill out all required fields with valid pricing.';
    } else {
        try {
            $stmtUp = $db->prepare("
                UPDATE listings SET
                    title = :title, description = :desc, property_type = :ptype,
                    address = :addr, city = :city, state = :state, country = :country,
                    latitude = :lat, longitude = :lng, price_per_night = :price,
                    cleaning_fee = :clean, service_fee = :serv,
                    max_guests = :guests, bedrooms = :beds_room, beds = :beds, bathrooms = :baths
                WHERE id = :id
            ");
            $stmtUp->execute([
                ':title' => $title,
                ':desc' => $description,
                ':ptype' => $propertyType,
                ':addr' => $address,
                ':city' => $city,
                ':state' => $state,
                ':country' => $country,
                ':lat' => $latitude,
                ':lng' => $longitude,
                ':price' => $pricePerNight,
                ':clean' => $cleaningFee,
                ':serv' => $serviceFee,
                ':guests' => $maxGuests,
                ':beds_room' => $bedrooms,
                ':beds' => $beds,
                ':baths' => $bathrooms,
                ':id' => $listingId
            ]);

            // Sync amenities
            $db->prepare("DELETE FROM listing_amenities WHERE listing_id = :lid")->execute([':lid' => $listingId]);
            if (!empty($selectedAmenities)) {
                $stmtLa = $db->prepare("INSERT INTO listing_amenities (listing_id, amenity_id) VALUES (:lid, :aid)");
                foreach ($selectedAmenities as $aid) {
                    $stmtLa->execute([':lid' => $listingId, ':aid' => $aid]);
                }
            }

            // Upload new files
            if (!empty($_FILES['listing_files']['name'][0])) {
                $stmtImgIns = $db->prepare("INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (:lid, :url, 0)");
                $totalFiles = count($_FILES['listing_files']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    if ($_FILES['listing_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name' => $_FILES['listing_files']['name'][$i],
                            'type' => $_FILES['listing_files']['type'][$i],
                            'tmp_name' => $_FILES['listing_files']['tmp_name'][$i],
                            'error' => $_FILES['listing_files']['error'][$i],
                            'size' => $_FILES['listing_files']['size'][$i]
                        ];
                        $uploadedUrl = uploadListingImage($singleFile);
                        if ($uploadedUrl) {
                            $stmtImgIns->execute([':lid' => $listingId, ':url' => $uploadedUrl]);
                        }
                    }
                }
            }

            // Add new image URLs (validated to http/https only)
            if (!empty($newImageUrls)) {
                $stmtImgIns = $db->prepare("INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (:lid, :url, 0)");
                foreach ($newImageUrls as $url) {
                    if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url)) {
                        $stmtImgIns->execute([':lid' => $listingId, ':url' => $url]);
                    }
                }
            }

            setFlash('success', 'Listing updated successfully!');
            header('Location: ' . BASE_URL . '/host/edit_listing.php?id=' . $listingId);
            exit;

        } catch (Exception $e) {
            $error = 'Failed to update stay: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Stay - ' . $listing['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h2 class="fw-bold mb-1">Edit Stay Details</h2>
                        <p class="text-muted small mb-0"><?= e($listing['title']) ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/listing_detail.php?id=<?= $listingId ?>" class="btn btn-outline-dark rounded-pill btn-sm">
                            <i class="fa-solid fa-eye me-1"></i> View Live
                        </a>
                        <a href="<?= BASE_URL ?>/host/listings.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                            Back
                        </a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- Overview -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-home me-2"></i> 1. Property Overview</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Listing Title *</label>
                            <input type="text" name="title" class="form-control rounded-3" required value="<?= e($listing['title']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Property Type *</label>
                            <select name="property_type" class="form-select rounded-3">
                                <?php foreach (['Villa', 'Apartment', 'Cabin', 'Heritage', 'Loft', 'Cottage'] as $pt): ?>
                                    <option value="<?= $pt ?>" <?= $listing['property_type'] === $pt ? 'selected' : '' ?>><?= $pt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description *</label>
                            <textarea name="description" rows="4" class="form-control rounded-3" required><?= e($listing['description']) ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Rooms -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-bed me-2"></i> 2. Rooms and Capacity</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Max Guests</label>
                            <input type="number" name="max_guests" class="form-control rounded-3" min="1" max="50" value="<?= (int)$listing['max_guests'] ?>" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Bedrooms</label>
                            <input type="number" name="bedrooms" class="form-control rounded-3" min="1" max="20" value="<?= (int)$listing['bedrooms'] ?>" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Beds</label>
                            <input type="number" name="beds" class="form-control rounded-3" min="1" max="30" value="<?= (int)$listing['beds'] ?>" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Bathrooms</label>
                            <input type="number" name="bathrooms" class="form-control rounded-3" min="1" max="20" value="<?= (int)$listing['bathrooms'] ?>" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Pricing -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-indian-rupee-sign me-2"></i> 3. Pricing</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Price per night (<?= CURRENCY_SYMBOL ?>) *</label>
                            <input type="number" name="price_per_night" class="form-control rounded-3" min="100" step="100" required value="<?= (float)$listing['price_per_night'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Cleaning fee (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" name="cleaning_fee" class="form-control rounded-3" min="0" step="50" value="<?= (float)$listing['cleaning_fee'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Service fee (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" name="service_fee" class="form-control rounded-3" min="0" step="50" value="<?= (float)$listing['service_fee'] ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Location with Leaflet Picker -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-map-location-dot me-2"></i> 4. Location & Map Pin</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Street Address *</label>
                            <input type="text" name="address" class="form-control rounded-3" required value="<?= e($listing['address']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">City *</label>
                            <input type="text" name="city" class="form-control rounded-3" required value="<?= e($listing['city']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">State</label>
                            <input type="text" name="state" class="form-control rounded-3" value="<?= e($listing['state'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Latitude</label>
                            <input type="number" name="latitude" id="inputLat" class="form-control rounded-3" step="0.0000001" value="<?= (float)$listing['latitude'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Longitude</label>
                            <input type="number" name="longitude" id="inputLng" class="form-control rounded-3" step="0.0000001" value="<?= (float)$listing['longitude'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div id="pickerMap" style="height: 300px; border-radius: 16px; border: 1px solid #ddd;"></div>
                    </div>

                    <hr class="my-4">

                    <!-- Amenities -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-sparkles me-2"></i> 5. Amenities</h5>
                    <div class="row g-3 mb-4">
                        <?php foreach ($allAmenities as $am): 
                            $isChecked = in_array((int)$am['id'], $currentAmenities, true);
                        ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= (int)$am['id'] ?>" id="am_<?= (int)$am['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                    <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer" for="am_<?= (int)$am['id'] ?>">
                                        <i class="<?= e($am['icon']) ?> text-muted" style="width: 20px;"></i>
                                        <span class="small"><?= e($am['name']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-4">

                    <!-- Current Photos & Management -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-images me-2"></i> 6. Manage Photos</h5>
                    
                    <div class="row g-3 mb-4">
                        <?php foreach ($images as $img): ?>
                            <div class="col-md-3 col-6">
                                <div class="card border rounded-3 overflow-hidden position-relative h-100 shadow-sm">
                                    <img src="<?= e($img['image_url']) ?>" class="w-100 object-fit-cover" style="height: 140px;" alt="Photo">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="badge bg-danger position-absolute top-0 start-0 m-2">Cover Photo</span>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/host/edit_listing.php?id=<?= $listingId ?>&set_primary_id=<?= (int)$img['id'] ?>&csrf_token=<?= csrfToken() ?>" class="badge bg-dark bg-opacity-75 text-white position-absolute top-0 start-0 m-2 text-decoration-none">
                                            Make Cover
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?= BASE_URL ?>/host/edit_listing.php?id=<?= $listingId ?>&delete_image_id=<?= (int)$img['id'] ?>&csrf_token=<?= csrfToken() ?>" 
                                       class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle p-1" 
                                       style="width: 28px; height: 28px;" 
                                       onclick="return confirm('Delete this photo?');">
                                        <i class="fa-solid fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Add New Photos -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Upload More Photos</label>
                        <input type="file" name="listing_files[]" class="form-control rounded-3" multiple accept="image/*">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Add Photo Web URL</label>
                        <input type="url" name="image_urls[]" class="form-control rounded-3" placeholder="https://images.unsplash.com/photo-...">
                    </div>

                    <!-- Save Changes -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= BASE_URL ?>/host/listings.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold">Save All Changes</button>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
$(document).ready(function() {
    let lat = parseFloat($('#inputLat').val()) || 15.5996;
    let lng = parseFloat($('#inputLng').val()) || 73.7439;

    const pickerMap = L.map('pickerMap').setView([lat, lng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(pickerMap);

    const pin = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);

    function updateCoords(pos) {
        $('#inputLat').val(pos.lat.toFixed(6));
        $('#inputLng').val(pos.lng.toFixed(6));
    }

    pin.on('dragend', function(e) {
        updateCoords(e.target.getLatLng());
    });

    pickerMap.on('click', function(e) {
        pin.setLatLng(e.latlng);
        updateCoords(e.latlng);
    });
});
</script>
";
include __DIR__ . '/../includes/footer.php'; 
?>

