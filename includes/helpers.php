<?php
/**
 * StayHub Helper Utility Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function e(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatPrice(float|int|string $amount): string {
    $num = (float)$amount;
    return CURRENCY_SYMBOL . number_format($num, 0, '.', ',');
}

function calculateNights(string $checkIn, string $checkOut): int {
    $d1 = new DateTime($checkIn);
    $d2 = new DateTime($checkOut);
    $diff = $d1->diff($d2);
    return max(1, (int)$diff->days);
}

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = e($flash['type']);
        $msg = $flash['message']; // Can contain safe HTML or text
        echo "<div class='container mt-3'>
                <div class='alert alert-{$type} alert-dismissible fade show shadow-sm' role='alert'>
                    {$msg}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
              </div>";
    }
}

/**
 * Robust SQL Date Overlap Check
 * Prevents double bookings: Two date ranges [A, B] and [C, D] overlap if (A < D) AND (B > C)
 */
function checkDateOverlap(int $listingId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool {
    $db = getDB();
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE listing_id = :listing_id 
              AND booking_status IN ('confirmed', 'pending')
              AND (check_in < :check_out AND check_out > :check_in)";

    $params = [
        ':listing_id' => $listingId,
        ':check_in' => $checkIn,
        ':check_out' => $checkOut
    ];

    if ($excludeBookingId !== null) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeBookingId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();

    return $count > 0; // True if overlap exists (not available)
}

/**
 * Calculate full booking pricing breakdown
 */
function calculateBookingPrice(array $listing, string $checkIn, string $checkOut): array {
    $nights = calculateNights($checkIn, $checkOut);
    $pricePerNight = (float)$listing['price_per_night'];
    $subtotal = $pricePerNight * $nights;
    $cleaningFee = (float)($listing['cleaning_fee'] ?? 0);
    $serviceFee = (float)($listing['service_fee'] ?? 0);
    $total = $subtotal + $cleaningFee + $serviceFee;

    return [
        'nights' => $nights,
        'price_per_night' => $pricePerNight,
        'subtotal' => $subtotal,
        'cleaning_fee' => $cleaningFee,
        'service_fee' => $serviceFee,
        'total' => $total
    ];
}

/**
 * Check if a listing is wishlisted by the current user
 */
function isWishlisted(int $listingId, ?int $userId = null): bool {
    if (!$userId) {
        $userId = $_SESSION['user']['id'] ?? null;
    }
    if (!$userId) return false;

    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM wishlists WHERE user_id = :uid AND listing_id = :lid LIMIT 1");
    $stmt->execute([':uid' => $userId, ':lid' => $listingId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Render star ratings HTML
 */
function renderRatingStars(float|int $rating, int $reviewCount = 0): string {
    $rating = round((float)$rating, 1);
    $html = "<span class='d-inline-flex align-items-center gap-1'>";
    $html .= "<i class='fa-solid fa-star text-warning'></i>";
    $html .= "<strong>" . ($rating > 0 ? number_format($rating, 1) : 'New') . "</strong>";
    if ($reviewCount > 0) {
        $html .= "<span class='text-muted'>(" . $reviewCount . ")</span>";
    }
    $html .= "</span>";
    return $html;
}

/**
 * Handle listing image upload safely
 * Validates error code, file size, real MIME type, and image dimensions
 */
function uploadListingImage(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Enforce 5MB maximum file size limit
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return null;
    }

    // Verify actual image structure and dimensions (blocks polyglot scripts)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return null;
    }

    // Strictly whitelist MIME types using fileinfo extension
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        return null;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    // Never use client-supplied filename (prevents directory traversal & double-extension attacks)
    $fileName = 'stay_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = UPLOAD_DIR . $fileName;

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0644);
        return UPLOAD_URL . $fileName;
    }

    return null;
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

