<?php
/**
 * API: Check Listing Availability & Live Calculate Price
 * Validates date ranges, guest limits, and prevents double bookings.
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

// Enforce POST method
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['available' => false, 'message' => 'Method not allowed. POST required.']);
    exit;
}

try {
    $listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guestsCount = isset($_POST['guests_count']) ? max(1, (int)$_POST['guests_count']) : 1;

    // Validate inputs
    if ($listingId <= 0 || !isValidDate($checkIn) || !isValidDate($checkOut)) {
        http_response_code(400);
        echo json_encode([
            'available' => false,
            'message'   => 'Please select valid check-in and check-out dates.'
        ]);
        exit;
    }

    $db = getDB();

    // 1. Fetch listing details
    $stmtListing = $db->prepare("SELECT * FROM listings WHERE id = :id AND status = 'approved'");
    $stmtListing->execute([':id' => $listingId]);
    $listing = $stmtListing->fetch();

    if (!$listing) {
        http_response_code(404);
        echo json_encode([
            'available' => false,
            'message'   => 'Listing not found or currently not available.'
        ]);
        exit;
    }

    // 2. Validate dates logic
    $d1 = parseDate($checkIn);
    $d2 = parseDate($checkOut);
    $today = new DateTime('today');

    if (!$d1 || !$d2) {
        http_response_code(400);
        echo json_encode([
            'available' => false,
            'message'   => 'Invalid date format provided.'
        ]);
        exit;
    }

    if ($d1 < $today) {
        echo json_encode([
            'available' => false,
            'message'   => 'Check-in date cannot be in the past.'
        ]);
        exit;
    }

    if ($d2 <= $d1) {
        echo json_encode([
            'available' => false,
            'message'   => 'Check-out date must be after check-in date.'
        ]);
        exit;
    }

    $stayDays = $d1->diff($d2)->days;
    if ($stayDays > 180) {
        echo json_encode([
            'available' => false,
            'message'   => 'Maximum reservation length is 180 consecutive days.'
        ]);
        exit;
    }

    // 3. Validate guest count
    $maxGuests = (int)($listing['max_guests'] ?? 1);
    if ($guestsCount > $maxGuests) {
        echo json_encode([
            'available' => false,
            'message'   => "This property accommodates a maximum of {$maxGuests} guest(s)."
        ]);
        exit;
    }

    // 4. SQL Double-Booking Check (Date Overlap)
    $isOverlapping = checkDateOverlap($listingId, $checkIn, $checkOut);

    if ($isOverlapping) {
        echo json_encode([
            'available' => false,
            'message'   => 'Selected dates are already booked. Please choose other available dates on the calendar.'
        ]);
        exit;
    }

    // 5. Calculate Price Breakdown
    $calc = calculateBookingPrice($listing, $checkIn, $checkOut);

    echo json_encode([
        'available'   => true,
        'calculation' => [
            'nights'                    => $calc['nights'],
            'price_per_night'           => $calc['price_per_night'],
            'price_per_night_formatted' => formatPrice($calc['price_per_night']),
            'subtotal'                  => $calc['subtotal'],
            'subtotal_formatted'        => formatPrice($calc['subtotal']),
            'cleaning_fee'              => $calc['cleaning_fee'],
            'cleaning_fee_formatted'    => formatPrice($calc['cleaning_fee']),
            'service_fee'               => $calc['service_fee'],
            'service_fee_formatted'     => formatPrice($calc['service_fee']),
            'total'                     => $calc['total'],
            'total_formatted'           => formatPrice($calc['total'])
        ]
    ]);
} catch (\Throwable $t) {
    Logger::error("API check_availability error: " . $t->getMessage(), [
        'listing_id' => $_POST['listing_id'] ?? null,
        'check_in'   => $_POST['check_in'] ?? null,
        'check_out'  => $_POST['check_out'] ?? null
    ]);
    http_response_code(500);
    echo json_encode([
        'available' => false,
        'message'   => 'An error occurred while checking property availability. Please try again.'
    ]);
}
