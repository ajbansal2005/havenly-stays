<?php
/**
 * API: Get Booked Date Ranges for a Listing (to block on Flatpickr calendar)
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID.', 'ranges' => []]);
    exit;
}

try {
    $db = getDB();
    $today = date('Y-m-d');

    // Fetch confirmed or pending bookings where check_out is today or later
    $sql = "SELECT check_in, check_out FROM bookings 
            WHERE listing_id = :lid 
              AND booking_status IN ('confirmed', 'pending')
              AND check_out >= :today
            ORDER BY check_in ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([':lid' => $listingId, ':today' => $today]);
    $bookings = $stmt->fetchAll();

    $ranges = [];
    foreach ($bookings as $b) {
        if (isValidDate($b['check_in']) && isValidDate($b['check_out'])) {
            $ranges[] = [
                'from' => $b['check_in'],
                'to'   => $b['check_out']
            ];
        }
    }

    echo json_encode([
        'success'    => true,
        'listing_id' => $listingId,
        'ranges'     => $ranges
    ]);
} catch (\Throwable $t) {
    Logger::error("API get_booked_dates error: " . $t->getMessage(), ['listing_id' => $listingId]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load booked dates.', 'ranges' => []]);
}
