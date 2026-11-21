<?php
/**
 * StayHub Host - Delete Listing Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole(['host', 'admin']);

$db = getDB();
$hostId = getCurrentUserId();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf_token'] ?? '';

if (!verifyCsrfToken($csrf)) {
    setFlash('danger', 'Security token invalid.');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

$stmtCheck = $db->prepare("SELECT * FROM listings WHERE id = :id");
$stmtCheck->execute([':id' => $listingId]);
$listing = $stmtCheck->fetch();

if (!$listing || ($listing['host_id'] !== $hostId && !hasRole('admin'))) {
    setFlash('danger', 'You do not have permission to delete this listing.');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

// Check for active upcoming bookings
$today = date('Y-m-d');
$stmtActive = $db->prepare("SELECT COUNT(*) FROM bookings WHERE listing_id = :lid AND booking_status = 'confirmed' AND check_out >= :today");
$stmtActive->execute([':lid' => $listingId, ':today' => $today]);
$activeCount = (int)$stmtActive->fetchColumn();

if ($activeCount > 0) {
    setFlash('danger', 'Cannot delete this property because it has ' . $activeCount . ' active upcoming guest reservation(s).');
    header('Location: ' . BASE_URL . '/host/listings.php');
    exit;
}

// Safe to delete
$stmtDel = $db->prepare("DELETE FROM listings WHERE id = :id");
$stmtDel->execute([':id' => $listingId]);

setFlash('success', 'Listing deleted successfully.');
header('Location: ' . BASE_URL . '/host/listings.php');
exit;

