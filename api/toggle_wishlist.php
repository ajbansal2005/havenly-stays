<?php
/**
 * API: Toggle Wishlist (Favorite / Unfavorite)
 * Requires authentication and CSRF token.
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
    echo json_encode(['success' => false, 'message' => 'Please log in to save stays.']);
    exit;
}

$userId = getCurrentUserId();
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$csrfToken = $_POST['csrf_token'] ?? null;

if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session security token.']);
    exit;
}

if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid listing.']);
    exit;
}

try {
    $db = getDB();

    // Verify listing exists
    $stmtListing = $db->prepare("SELECT id FROM listings WHERE id = :lid AND status = 'approved'");
    $stmtListing->execute([':lid' => $listingId]);
    if (!$stmtListing->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Listing not found or unavailable.']);
        exit;
    }

    $wishlisted = false;

    Database::transaction(function (PDO $db) use ($userId, $listingId, &$wishlisted) {
        $stmtCheck = $db->prepare("SELECT id FROM wishlists WHERE user_id = :uid AND listing_id = :lid");
        $stmtCheck->execute([':uid' => $userId, ':lid' => $listingId]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $stmtDel = $db->prepare("DELETE FROM wishlists WHERE id = :id");
            $stmtDel->execute([':id' => (int)$existing['id']]);
            $wishlisted = false;
        } else {
            $stmtIns = $db->prepare("INSERT INTO wishlists (user_id, listing_id) VALUES (:uid, :lid)");
            $stmtIns->execute([':uid' => $userId, ':lid' => $listingId]);
            $wishlisted = true;
        }
    });

    // Get updated total count for user
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = :uid");
    $stmtCount->execute([':uid' => $userId]);
    $totalCount = (int)$stmtCount->fetchColumn();

    Logger::info("Wishlist toggled", [
        'user_id'    => $userId,
        'listing_id' => $listingId,
        'wishlisted' => $wishlisted
    ]);

    echo json_encode([
        'success'     => true,
        'wishlisted'  => $wishlisted,
        'total_count' => $totalCount
    ]);
} catch (\Throwable $t) {
    Logger::error("API toggle_wishlist error: " . $t->getMessage(), [
        'user_id'    => $userId,
        'listing_id' => $listingId
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update wishlist.']);
}
