<?php
/**
 * API: Submit Review and Rating After Stay
 * Requires authentication, CSRF verification, rate limiting, and verified completed stay.
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

// Enforce POST method
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. POST required.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review.']);
    exit;
}

$userId = getCurrentUserId();
$csrfToken = $_POST['csrf_token'] ?? null;
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$rawComment = trim($_POST['comment'] ?? '');

// CSRF verification
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security session expired. Please refresh the page and try again.']);
    exit;
}

// Rate limiting (max 10 reviews per hour per user)
if (!checkRateLimit('submit_review_' . $userId, 10, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'You are submitting reviews too quickly. Please wait a moment.']);
    exit;
}

// Input validation
if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid listing property.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a star rating between 1 and 5.']);
    exit;
}

$cleanComment = strip_tags($rawComment);
if (mb_strlen($cleanComment) < 5 || mb_strlen($cleanComment) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Review comment must be between 5 and 2,000 characters.']);
    exit;
}

try {
    $db = getDB();
    $today = date('Y-m-d');

    // 1. Verify that the guest actually completed a stay at this listing
    $sqlCheckStay = "SELECT id FROM bookings 
                     WHERE listing_id = :lid 
                       AND guest_id = :gid 
                       AND (booking_status = 'completed' OR (booking_status = 'confirmed' AND check_out <= :today))
                     ORDER BY check_out DESC
                     LIMIT 1";

    $stmtCheck = $db->prepare($sqlCheckStay);
    $stmtCheck->execute([
        ':lid'   => $listingId,
        ':gid'   => $userId,
        ':today' => $today
    ]);
    $eligibleBooking = $stmtCheck->fetch();

    if (!$eligibleBooking) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'You can only review a property after completing your stay.'
        ]);
        exit;
    }

    // 2. Prevent duplicate reviews by the same guest for this listing
    $stmtCheckReviewed = $db->prepare("SELECT id FROM reviews WHERE listing_id = :lid AND guest_id = :gid LIMIT 1");
    $stmtCheckReviewed->execute([':lid' => $listingId, ':gid' => $userId]);
    if ($stmtCheckReviewed->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You have already submitted a review for this stay. Thank you!'
        ]);
        exit;
    }

    // 3. Insert review inside transaction
    Database::transaction(function (PDO $db) use ($eligibleBooking, $listingId, $userId, $rating, $cleanComment) {
        $stmtIns = $db->prepare("
            INSERT INTO reviews (booking_id, listing_id, guest_id, rating, comment) 
            VALUES (:bid, :lid, :gid, :rating, :comment)
        ");
        $stmtIns->execute([
            ':bid'     => (int)$eligibleBooking['id'],
            ':lid'     => $listingId,
            ':gid'     => $userId,
            ':rating'  => $rating,
            ':comment' => $cleanComment
        ]);
    });

    Logger::info("Review submitted successfully", [
        'listing_id' => $listingId,
        'guest_id'   => $userId,
        'rating'     => $rating
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your review and rating have been posted.'
    ]);
} catch (\Throwable $t) {
    Logger::error("API submit_review error: " . $t->getMessage(), [
        'listing_id' => $listingId,
        'guest_id'   => $userId
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving your review. Please try again.'
    ]);
}
