<?php
/**
 * API: Submit Review and Rating After Stay
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review.']);
    exit;
}

$userId = getCurrentUserId();
$csrfToken = $_POST['csrf_token'] ?? '';
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = trim($_POST['comment'] ?? '');

if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh and try again.']);
    exit;
}

if ($listingId <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid rating (1-5 stars) and a review message.']);
    exit;
}

$db = getDB();
$today = date('Y-m-d');

// Verify that the guest actually stayed at this listing (completed or past check_out booking)
$sqlCheckStay = "SELECT id FROM bookings 
                 WHERE listing_id = :lid 
                   AND guest_id = :gid 
                   AND (booking_status = 'completed' OR (booking_status = 'confirmed' AND check_out <= :today))
                 LIMIT 1";

$stmtCheck = $db->prepare($sqlCheckStay);
$stmtCheck->execute([
    ':lid' => $listingId,
    ':gid' => $userId,
    ':today' => $today
]);
$eligibleBooking = $stmtCheck->fetch();

if (!$eligibleBooking) {
    echo json_encode([
        'success' => false, 
        'message' => 'You can only review a property after completing your stay.'
    ]);
    exit;
}

// Check if user already reviewed this listing
$stmtCheckReviewed = $db->prepare("SELECT id FROM reviews WHERE listing_id = :lid AND guest_id = :gid");
$stmtCheckReviewed->execute([':lid' => $listingId, ':gid' => $userId]);
if ($stmtCheckReviewed->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'You have already submitted a review for this stay. Thank you!'
    ]);
    exit;
}

// Insert review
$stmtIns = $db->prepare("INSERT INTO reviews (booking_id, listing_id, guest_id, rating, comment) VALUES (:bid, :lid, :gid, :rating, :comment)");
$stmtIns->execute([
    ':bid' => $eligibleBooking['id'],
    ':lid' => $listingId,
    ':gid' => $userId,
    ':rating' => $rating,
    ':comment' => $comment
]);

echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your review and rating have been posted.'
]);
