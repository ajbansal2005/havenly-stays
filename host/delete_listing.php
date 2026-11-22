<?php
/**
 * StayHub Host - Delete Listing Handler
 * Verifies listing ownership, checks for active upcoming guest reservations,
 * performs atomic deletion inside a database transaction, and cleans up uploaded files.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = (int)getCurrentUserId();
$listingId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$csrf = $_REQUEST['csrf_token'] ?? null;

if (!verifyCsrfToken($csrf)) {
    Logger::security("CSRF token verification failed on delete listing attempt", ['listing_id' => $listingId, 'host_id' => $hostId]);
    setFlash('danger', 'Security token invalid. Please try again.');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

if ($listingId <= 0) {
    setFlash('danger', 'Invalid listing ID.');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

try {
    $stmtCheck = $db->prepare("SELECT * FROM listings WHERE id = :id");
    $stmtCheck->execute([':id' => $listingId]);
    $listing = $stmtCheck->fetch();

    if (!$listing || ((int)$listing['host_id'] !== $hostId && !hasRole('admin'))) {
        Logger::security("Unauthorized delete attempt on listing {$listingId} by user {$hostId}");
        setFlash('danger', 'You do not have permission to delete this listing.');
        header('Location: ' . BASE_URL . '/host/listings.php');
        exit;
    }

    // Check for active or upcoming bookings
    $today = date('Y-m-d');
    $stmtActive = $db->prepare("SELECT COUNT(*) FROM bookings WHERE listing_id = :lid AND booking_status = 'confirmed' AND check_out >= :today");
    $stmtActive->execute([':lid' => $listingId, ':today' => $today]);
    $activeCount = (int)$stmtActive->fetchColumn();

    if ($activeCount > 0) {
        setFlash('danger', 'Cannot delete this property because it has ' . $activeCount . ' active or upcoming guest reservation(s).');
        header('Location: ' . BASE_URL . '/host/listings.php');
        exit;
    }

    // Fetch images to delete local files
    $stmtImages = $db->prepare("SELECT image_url FROM listing_images WHERE listing_id = :lid");
    $stmtImages->execute([':lid' => $listingId]);
    $images = $stmtImages->fetchAll();

    // Atomic deletion
    Database::transaction(function (PDO $db) use ($listingId) {
        $stmtDel = $db->prepare("DELETE FROM listings WHERE id = :id");
        $stmtDel->execute([':id' => $listingId]);
    });

    // Clean up local uploaded image files
    foreach ($images as $img) {
        $url = $img['image_url'] ?? '';
        if (str_contains($url, '/uploads/listings/')) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $filePath = UPLOAD_DIR . $filename;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    Logger::info("Listing deleted successfully", ['listing_id' => $listingId, 'host_id' => $hostId, 'title' => $listing['title']]);
    setFlash('success', 'Listing deleted successfully.');
} catch (\Throwable $t) {
    Logger::error("Error deleting listing: " . $t->getMessage(), ['listing_id' => $listingId]);
    setFlash('danger', 'An error occurred while deleting the listing.');
}

header('Location: ' . BASE_URL . '/host/listings.php');
exit;
