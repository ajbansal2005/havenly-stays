<?php
/**
 * API: Toggle Wishlist (Favorite / Unfavorite)
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to save stays.']);
    exit;
}

$userId = getCurrentUserId();
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$csrfToken = $_POST['csrf_token'] ?? '';

if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session security token.']);
    exit;
}

if ($listingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing.']);
    exit;
}

$db = getDB();

// Check if already wishlisted
$stmtCheck = $db->prepare("SELECT id FROM wishlists WHERE user_id = :uid AND listing_id = :lid");
$stmtCheck->execute([':uid' => $userId, ':lid' => $listingId]);
$existing = $stmtCheck->fetch();

$wishlisted = false;

if ($existing) {
    // Remove
    $stmtDel = $db->prepare("DELETE FROM wishlists WHERE id = :id");
    $stmtDel->execute([':id' => $existing['id']]);
    $wishlisted = false;
} else {
    // Insert
    $stmtIns = $db->prepare("INSERT INTO wishlists (user_id, listing_id) VALUES (:uid, :lid)");
    $stmtIns->execute([':uid' => $userId, ':lid' => $listingId]);
    $wishlisted = true;
}

// Get updated total count for user
$stmtCount = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = :uid");
$stmtCount->execute([':uid' => $userId]);
$totalCount = (int)$stmtCount->fetchColumn();

echo json_encode([
    'success' => true,
    'wishlisted' => $wishlisted,
    'total_count' => $totalCount
]);
