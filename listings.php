<?php
/**
 * StayHub Explore & Search Stays
 * Live AJAX Filtering & Leaflet Map Integration
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();

// Initial query params passed via GET
$initCity = trim($_GET['city'] ?? '');
$initCheckIn = trim($_GET['check_in'] ?? '');
$initCheckOut = trim($_GET['check_out'] ?? '');
$initGuests = trim($_GET['guests'] ?? '');
$initPropertyType = trim($_GET['property_type'] ?? '');

// Fetch all available amenities for filter sidebar
$stmtAm = $db->query("SELECT * FROM amenities ORDER BY category ASC, name ASC");
$allAmenities = $stmtAm->fetchAll();

$pageTitle = 'Explore Stays & Rentals';
include __DIR__ . '/includes/header.php';
?>

<!-- Filter & Search Controls Header -->
<div class="bg-white border-bottom py-3 sticky-top" style="top: 71px; z-index: 1020;">
    <div class="container-fluid px-lg-5">
        <div class="row g-2 align-items-center">
            
            <!-- City Search -->
            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="fa-solid fa-location-dot text-danger"></i></span>
                    <input type="text" id="filterCity" class="form-control bg-light border-start-0 rounded-end-pill" placeholder="Destination or city..." value="<?= e($initCity) ?>">
                </div>
            </div>

            <!-- Date Range Flatpickr -->
            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="fa-regular fa-calendar-days text-muted"></i></span>
                    <input type="text" id="filterDateRange" class="form-control bg-light border-start-0 rounded-end-pill" placeholder="Check in & Check out">
                    <input type="hidden" id="filterCheckIn" value="<?= e($initCheckIn) ?>">
                    <input type="hidden" id="filterCheckOut" value="<?= e($initCheckOut) ?>">
                </div>
            </div>

            <!-- Guests -->
            <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                <select id="filterGuests" class="form-select rounded-pill bg-light">
                    <option value="">Any guests</option>
                    <option value="1" <?= $initGuests === '1' ? 'selected' : '' ?>>1 Guest</option>
                    <option value="2" <?= $initGuests === '2' ? 'selected' : '' ?>>2 Guests</option>
                    <option value="3" <?= $initGuests === '3' ? 'selected' : '' ?>>3 Guests</option>
                    <option value="4" <?= $initGuests === '4' ? 'selected' : '' ?>>4 Guests</option>
                    <option value="6" <?= $initGuests === '6' ? 'selected' : '' ?>>6+ Guests</option>
                </select>
            </div>

            <!-- Filter Modal Trigger -->
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-6">
                <button type="button" class="btn btn-outline-dark rounded-pill w-100 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#advancedFiltersModal">
                    <i class="fa-solid fa-sliders"></i>
                    <span>All Filters</span>
                </button>
            </div>

            <!-- Sort selector -->
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12">
                <select id="filterSort" class="form-select rounded-pill bg-light">
                    <option value="newest">Sort: Recommended</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="rating_desc">Highest Rated</option>
                </select>
            </div>

        </div>
    </div>
</div>

<!-- Main Split Container: Listings & Interactive Map -->
<div class="container-fluid px-lg-5 py-4">
    <div class="row g-4">
        
        <!-- Left: Listings Column -->
        <div class="col-lg-7 col-xl-7" id="listingsColumn">
            <!-- Results Bar -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0" id="resultsCount">Searching stays...</h5>
                    <small class="text-muted">Live search powered by AJAX & OpenStreetMap</small>
                </div>
                <div id="searchLoadingSpinner" class="spinner-border spinner-border-sm text-danger d-none" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Dynamic Listings Container -->
            <div class="row g-3" id="listingsContainer">
                <!-- Injected via assets/js/search.js -->
            </div>
        </div>

        <!-- Right: Map Column -->
        <div class="col-lg-5 col-xl-5 d-none d-lg-block" id="mapColumn">
            <div class="position-sticky" style="top: 155px; height: calc(100vh - 180px);">
                <div id="searchMap" class="stay-map-container w-100 h-100"></div>
            </div>
        </div>

    </div>
</div>

<!-- Floating Map Toggle Button for Mobile Screens -->
<div class="position-fixed bottom-0 start-50 translate-middle-x mb-4 d-lg-none" style="z-index: 1040;">
    <button id="toggleMapBtn" class="btn btn-dark rounded-pill px-4 py-2 shadow-lg d-flex align-items-center gap-2">
        <i class="fa-solid fa-map"></i>
        <span>Show Map</span>
    </button>
</div>

<!-- Advanced Filters Modal -->
<div class="modal fade" id="advancedFiltersModal" tabindex="-1" aria-labelledby="filtersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="filtersModalLabel">Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <!-- Price Range Slider -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold mb-0">Max Price per night</label>
                        <span class="badge bg-danger fs-6" id="priceDisplay"><?= CURRENCY_SYMBOL ?>30,000</span>
                    </div>
                    <input type="range" class="form-range" min="1000" max="30000" step="500" value="30000" id="filterMaxPrice">
                    <div class="d-flex justify-content-between text-muted small">
                        <span><?= CURRENCY_SYMBOL ?>1,000</span>
                        <span><?= CURRENCY_SYMBOL ?>15,000</span>
                        <span><?= CURRENCY_SYMBOL ?>30,000+</span>
                    </div>
                </div>

                <hr>

                <!-- Property Types -->
                <div class="mb-4">
                    <label class="form-label fw-bold mb-3">Property Type</label>
                    <div class="d-flex flex-wrap gap-2">
                        <label class="btn btn-outline-secondary rounded-pill btn-sm">
                            <input type="radio" name="filterPropertyType" value="" class="btn-check" <?= empty($initPropertyType) ? 'checked' : '' ?>>
                            All Types
                        </label>
                        <?php foreach (['Villa', 'Apartment', 'Cabin', 'Heritage', 'Loft', 'Cottage'] as $pt): ?>
                            <label class="btn btn-outline-secondary rounded-pill btn-sm">
                                <input type="radio" name="filterPropertyType" value="<?= $pt ?>" class="btn-check" <?= $initPropertyType === $pt ? 'checked' : '' ?>>
                                <?= $pt ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr>

                <!-- Amenities Checkboxes -->
                <div class="mb-2">
                    <label class="form-label fw-bold mb-3">Amenities</label>
                    <div class="row g-3">
                        <?php foreach ($allAmenities as $am): ?>
                            <div class="col-md-6 col-12">
                                <div class="form-check">
                                    <input class="form-check-input filter-amenity-checkbox" type="checkbox" value="<?= (int)$am['id'] ?>" id="am_<?= (int)$am['id'] ?>">
                                    <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer" for="am_<?= (int)$am['id'] ?>">
                                        <i class="<?= e($am['icon']) ?> text-muted" style="width: 20px;"></i>
                                        <span><?= e($am['name']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top d-flex justify-content-between">
                <button type="button" class="btn btn-link text-dark fw-semibold text-decoration-none" id="btnResetFilters">Clear all</button>
                <button type="button" class="btn btn-danger rounded-pill px-4" data-bs-dismiss="modal">Show Results</button>
            </div>
        </div>
    </div>
</div>

<?php 
$extraScripts = '
<script src="' . BASE_URL . '/assets/js/search.js"></script>
';
include __DIR__ . '/includes/footer.php'; 
?>

