<?php
/**
 * API: Get Booked Date Ranges for a Listing (to block on Flatpickr calendar)
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if ($listingId <= 0) {
    echo json_encode(['success' => false, 'ranges' => []]);
    exit;
}

$db = getDB();
$today = date('Y-m-d');

// Fetch bookings that are confirmed or pending and check_out is today or later
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
    $ranges[] = [
        'from' => $b['check_in'],
        'to' => $b['check_out']
    ];
}

echo json_encode([
    'success' => true,
    'listing_id' => $listingId,
    'ranges' => $ranges
]);
