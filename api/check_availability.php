<?php
/**
 * API: Check Listing Availability & Live Calculate Price
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$checkIn = trim($_POST['check_in'] ?? '');
$checkOut = trim($_POST['check_out'] ?? '');
$guestsCount = isset($_POST['guests_count']) ? (int)$_POST['guests_count'] : 1;

if ($listingId <= 0 || empty($checkIn) || empty($checkOut)) {
    echo json_encode([
        'available' => false,
        'message' => 'Please select valid check-in and check-out dates.'
    ]);
    exit;
}

$db = getDB();

// 1. Fetch listing details
$stmtListing = $db->prepare("SELECT * FROM listings WHERE id = :id AND status = 'approved'");
$stmtListing->execute([':id' => $listingId]);
$listing = $stmtListing->fetch();

if (!$listing) {
    echo json_encode([
        'available' => false,
        'message' => 'Listing not found or currently not available.'
    ]);
    exit;
}

// 2. Validate dates logic
$d1 = new DateTime($checkIn);
$d2 = new DateTime($checkOut);
$today = new DateTime('today');

if ($d1 < $today) {
    echo json_encode([
        'available' => false,
        'message' => 'Check-in date cannot be in the past.'
    ]);
    exit;
}

if ($d2 <= $d1) {
    echo json_encode([
        'available' => false,
        'message' => 'Check-out date must be after check-in date.'
    ]);
    exit;
}

// 3. Validate guest count
if ($guestsCount > (int)$listing['max_guests']) {
    echo json_encode([
        'available' => false,
        'message' => "This property accommodates a maximum of {$listing['max_guests']} guests."
    ]);
    exit;
}

// 4. SQL Double-Booking Check (Date Overlap)
$isOverlapping = checkDateOverlap($listingId, $checkIn, $checkOut);

if ($isOverlapping) {
    echo json_encode([
        'available' => false,
        'message' => 'Selected dates are already booked. Please choose other available dates on the calendar.'
    ]);
    exit;
}

// 5. Calculate Price Breakdown
$calc = calculateBookingPrice($listing, $checkIn, $checkOut);

echo json_encode([
    'available' => true,
    'calculation' => [
        'nights' => $calc['nights'],
        'price_per_night' => $calc['price_per_night'],
        'price_per_night_formatted' => formatPrice($calc['price_per_night']),
        'subtotal' => $calc['subtotal'],
        'subtotal_formatted' => formatPrice($calc['subtotal']),
        'cleaning_fee' => $calc['cleaning_fee'],
        'cleaning_fee_formatted' => formatPrice($calc['cleaning_fee']),
        'service_fee' => $calc['service_fee'],
        'service_fee_formatted' => formatPrice($calc['service_fee']),
        'total' => $calc['total'],
        'total_formatted' => formatPrice($calc['total'])
    ]
]);
