<?php
/**
 * StayHub Host - Add New Listing
 * Includes interactive Leaflet map pin placement and multi-image uploads
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();
$error = '';

// Fetch all available amenities
$stmtAm = $db->query("SELECT * FROM amenities ORDER BY category ASC, name ASC");
$allAmenities = $stmtAm->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rawPropertyType = trim($_POST['property_type'] ?? 'Apartment');
    $propertyType = isValidPropertyType($rawPropertyType) ? $rawPropertyType : 'Apartment';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $latitude = (float)($_POST['latitude'] ?? 15.5996);
    $longitude = (float)($_POST['longitude'] ?? 73.7439);
    $pricePerNight = (float)($_POST['price_per_night'] ?? 0);
    $cleaningFee = max(0.0, (float)($_POST['cleaning_fee'] ?? 0));
    $serviceFee = max(0.0, (float)($_POST['service_fee'] ?? 0));
    $maxGuests = max(1, min(50, (int)($_POST['max_guests'] ?? 1)));
    $bedrooms = max(1, min(50, (int)($_POST['bedrooms'] ?? 1)));
    $beds = max(1, min(50, (int)($_POST['beds'] ?? 1)));
    $bathrooms = max(1, min(50, (int)($_POST['bathrooms'] ?? 1)));
    $selectedAmenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? array_map('intval', $_POST['amenities']) : [];
    $imageUrls = isset($_POST['image_urls']) && is_array($_POST['image_urls']) ? array_filter(array_map('trim', $_POST['image_urls'])) : [];

    // Latitude & Longitude bounds check
    if ($latitude < -90.0 || $latitude > 90.0) $latitude = 15.5996;
    if ($longitude < -180.0 || $longitude > 180.0) $longitude = 73.7439;

    if (!verifyCsrfToken($csrf)) {
        $error = 'Security session expired. Please try again.';
    } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
        $error = 'Title must be between 5 and 255 characters.';
    } elseif (mb_strlen($description) < 20 || mb_strlen($description) > 5000) {
        $error = 'Description must be between 20 and 5,000 characters.';
    } elseif (empty($city) || mb_strlen($city) > 100 || empty($address) || mb_strlen($address) > 255) {
        $error = 'Please provide valid address and city details.';
    } elseif ($pricePerNight <= 0 || $pricePerNight > 10000000) {
        $error = 'Please provide a valid nightly price between 1 and 10,000,000.';
    } else {
        try {
            $listingId = 0;
            Database::transaction(function (PDO $db) use (
                $hostId, $title, $description, $propertyType, $address, $city, $state, $country,
                $latitude, $longitude, $pricePerNight, $cleaningFee, $serviceFee,
                $maxGuests, $bedrooms, $beds, $bathrooms, $selectedAmenities, $imageUrls,
                &$listingId
            ) {
                $stmtIns = $db->prepare("
                    INSERT INTO listings (
                        host_id, title, description, property_type, address, city, state, country,
                        latitude, longitude, price_per_night, cleaning_fee, service_fee,
                        max_guests, bedrooms, beds, bathrooms, status
                    ) VALUES (
                        :hid, :title, :desc, :ptype, :addr, :city, :state, :country,
                        :lat, :lng, :price, :clean, :serv,
                        :guests, :beds_room, :beds, :baths, 'approved'
                    )
                ");
                $stmtIns->execute([
                    ':hid'       => $hostId,
                    ':title'     => $title,
                    ':desc'      => $description,
                    ':ptype'     => $propertyType,
                    ':addr'      => $address,
                    ':city'      => $city,
                    ':state'     => $state,
                    ':country'   => $country,
                    ':lat'       => $latitude,
                    ':lng'       => $longitude,
                    ':price'     => $pricePerNight,
                    ':clean'     => $cleaningFee,
                    ':serv'      => $serviceFee,
                    ':guests'    => $maxGuests,
                    ':beds_room' => $bedrooms,
                    ':beds'      => $beds,
                    ':baths'     => $bathrooms
                ]);

                $listingId = (int)$db->lastInsertId();

                // Insert selected amenities
                if (!empty($selectedAmenities)) {
                    $stmtLa = $db->prepare("INSERT INTO listing_amenities (listing_id, amenity_id) VALUES (:lid, :aid)");
                    foreach ($selectedAmenities as $aid) {
                        if ($aid > 0) {
                            $stmtLa->execute([':lid' => $listingId, ':aid' => $aid]);
                        }
                    }
                }

                // Handle File Uploads
                $hasPrimary = false;
                if (!empty($_FILES['listing_files']['name'][0])) {
                    $stmtImgIns = $db->prepare("INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (:lid, :url, :is_prim)");
                    $totalFiles = count($_FILES['listing_files']['name']);
                    for ($i = 0; $i < $totalFiles; $i++) {
                        if (isset($_FILES['listing_files']['error'][$i]) && $_FILES['listing_files']['error'][$i] === UPLOAD_ERR_OK) {
                            $singleFile = [
                                'name'     => $_FILES['listing_files']['name'][$i],
                                'type'     => $_FILES['listing_files']['type'][$i],
                                'tmp_name' => $_FILES['listing_files']['tmp_name'][$i],
                                'error'    => $_FILES['listing_files']['error'][$i],
                                'size'     => $_FILES['listing_files']['size'][$i]
                            ];
                            $uploadedUrl = uploadListingImage($singleFile);
                            if ($uploadedUrl) {
                                $isPrim = !$hasPrimary ? 1 : 0;
                                $stmtImgIns->execute([':lid' => $listingId, ':url' => $uploadedUrl, ':is_prim' => $isPrim]);
                                $hasPrimary = true;
                            }
                        }
                    }
                }

                // Insert Web Image URLs (validated to http/https only)
                if (!empty($imageUrls)) {
                    $stmtImgIns = $db->prepare("INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (:lid, :url, :is_prim)");
                    foreach ($imageUrls as $url) {
                        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url) && strlen($url) <= 500) {
                            $isPrim = !$hasPrimary ? 1 : 0;
                            $stmtImgIns->execute([':lid' => $listingId, ':url' => $url, ':is_prim' => $isPrim]);
                            $hasPrimary = true;
                        }
                    }
                }

                // Fallback default image if none provided
                if (!$hasPrimary) {
                    $stmtImgIns = $db->prepare("INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (:lid, :url, 1)");
                    $stmtImgIns->execute([':lid' => $listingId, ':url' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1200']);
                }
            });

            Logger::info("New listing created successfully", [
                'listing_id' => $listingId,
                'host_id'    => $hostId,
                'title'      => $title
            ]);

            setFlash('success', 'Stay <strong>' . e($title) . '</strong> created successfully and is now live!', true);
            header('Location: ' . BASE_URL . '/host/listings.php');
            exit;

        } catch (\Throwable $e) {
            Logger::error("Failed to create listing: " . $e->getMessage(), ['host_id' => $hostId]);
            $error = 'Failed to create stay. Please verify your inputs and try again.';
        }
    }
}

$pageTitle = 'Add New Stay - Host';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h2 class="fw-bold mb-1">Create a New Stay</h2>
                        <p class="text-muted small mb-0">Fill in the property details, amenities, pricing, and images.</p>
                    </div>
                    <a href="<?= BASE_URL ?>/host/listings.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                        Cancel
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- Basic Info -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-home me-2"></i> 1. Property Overview</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Listing Title *</label>
                            <input type="text" name="title" class="form-control rounded-3" required placeholder="e.g. Luxury Sunset Beach Villa with Infinity Pool">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Property Type *</label>
                            <select name="property_type" class="form-select rounded-3">
                                <option value="Apartment">Apartment</option>
                                <option value="Villa" selected>Villa</option>
                                <option value="Cabin">Cabin</option>
                                <option value="Heritage">Heritage Haveli</option>
                                <option value="Loft">Studio Loft</option>
                                <option value="Cottage">Cottage</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description *</label>
                            <textarea name="description" rows="4" class="form-control rounded-3" required placeholder="Describe the space, special features, views, and what makes your property unique..."></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Capacity & Rooms -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-bed me-2"></i> 2. Rooms and Capacity</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Max Guests</label>
                            <input type="number" name="max_guests" class="form-control rounded-3" min="1" max="50" value="4" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Bedrooms</label>
                            <input type="number" name="bedrooms" class="form-control rounded-3" min="1" max="20" value="2" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Beds</label>
                            <input type="number" name="beds" class="form-control rounded-3" min="1" max="30" value="2" required>
                        </div>
                        <div class="col-sm-3 col-6">
                            <label class="form-label small fw-semibold">Bathrooms</label>
                            <input type="number" name="bathrooms" class="form-control rounded-3" min="1" max="20" value="2" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Pricing & Fees -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-indian-rupee-sign me-2"></i> 3. Pricing</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Price per night (<?= CURRENCY_SYMBOL ?>) *</label>
                            <input type="number" name="price_per_night" class="form-control rounded-3" min="100" step="100" required placeholder="5000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Cleaning fee (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" name="cleaning_fee" class="form-control rounded-3" min="0" step="50" value="500">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Service fee (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" name="service_fee" class="form-control rounded-3" min="0" step="50" value="300">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Location with Leaflet Pin Picker -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-map-location-dot me-2"></i> 4. Location & Map Pin</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Street Address *</label>
                            <input type="text" name="address" id="inputAddress" class="form-control rounded-3" required placeholder="e.g. Vagator Beach Road, Anjuna">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">City *</label>
                            <input type="text" name="city" id="inputCity" class="form-control rounded-3" required placeholder="e.g. Goa">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">State / Region</label>
                            <input type="text" name="state" class="form-control rounded-3" placeholder="e.g. Goa">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Latitude</label>
                            <input type="number" name="latitude" id="inputLat" class="form-control rounded-3" step="0.0000001" value="15.5996" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Longitude</label>
                            <input type="number" name="longitude" id="inputLng" class="form-control rounded-3" step="0.0000001" value="73.7439" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted">Click or drag the marker anywhere on the map to set exact coordinates:</label>
                        <div id="pickerMap" style="height: 300px; border-radius: 16px; border: 1px solid #ddd;"></div>
                    </div>

                    <hr class="my-4">

                    <!-- Amenities -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-sparkles me-2"></i> 5. Amenities</h5>
                    <div class="row g-3 mb-4">
                        <?php foreach ($allAmenities as $am): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= (int)$am['id'] ?>" id="am_<?= (int)$am['id'] ?>">
                                    <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer" for="am_<?= (int)$am['id'] ?>">
                                        <i class="<?= e($am['icon']) ?> text-muted" style="width: 20px;"></i>
                                        <span class="small"><?= e($am['name']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-4">

                    <!-- Photo Uploads & Web URLs -->
                    <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-images me-2"></i> 6. Photos & Gallery</h5>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Upload Photo Files from Device</label>
                        <input type="file" name="listing_files[]" class="form-control rounded-3" multiple accept="image/*">
                        <div class="form-text small">Upload high-resolution JPG, PNG or WEBP photos (up to 5 images).</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Or Add Direct Photo URLs (Optional)</label>
                        <input type="url" name="image_urls[]" class="form-control rounded-3 mb-2" placeholder="https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1200">
                        <input type="url" name="image_urls[]" class="form-control rounded-3 mb-2" placeholder="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200">
                        <input type="url" name="image_urls[]" class="form-control rounded-3" placeholder="https://images.unsplash.com/photo-1613977257363-707ba9348227?w=1200">
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= BASE_URL ?>/host/listings.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold">Publish Stay</button>
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

