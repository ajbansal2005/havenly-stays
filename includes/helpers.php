<?php
/**
 * StayHub Helper Utility Functions
 * Production-ready validation, pricing calculation, concurrency checks,
 * safe flash messaging, image handling, and redirect sanitization.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

/**
 * Safe HTML escaping (prevents XSS across all output)
 */
function e(mixed $str): string {
    if ($str === null) {
        return '';
    }
    if (is_array($str) || is_object($str)) {
        return htmlspecialchars(json_encode($str), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency amount with currency symbol
 */
function formatPrice(float|int|string $amount): string {
    $num = is_numeric($amount) ? (float)$amount : 0.0;
    $symbol = defined('CURRENCY_SYMBOL') && !is_numeric(CURRENCY_SYMBOL) && mb_strlen(CURRENCY_SYMBOL) <= 5 ? CURRENCY_SYMBOL : '₹';
    return $symbol . number_format(max(0, $num), 0, '.', ',');
}

/**
 * Strict date format and calendar validity check (e.g. rejects 2026-02-31)
 */
function isValidDate(string $date, string $format = 'Y-m-d'): bool {
    if (empty($date) || strlen($date) > 20) {
        return false;
    }
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Safe Date parser that never throws uncaught exceptions
 */
function parseDate(string $date, string $format = 'Y-m-d'): ?DateTime {
    if (!isValidDate($date, $format)) {
        return null;
    }
    try {
        return new DateTime($date);
    } catch (\Throwable $t) {
        return null;
    }
}

/**
 * Calculate total nights between two date strings safely
 */
function calculateNights(string $checkIn, string $checkOut): int {
    $d1 = parseDate($checkIn);
    $d2 = parseDate($checkOut);
    if (!$d1 || !$d2 || $d2 <= $d1) {
        return 1;
    }
    $diff = $d1->diff($d2);
    return max(1, min(365, (int)$diff->days));
}

/**
 * Safe Open Redirect Prevention
 * Ensures redirect URL is internal to the application
 */
function safeRedirectUrl(?string $url, string $default = ''): string {
    $fallback = !empty($default) ? $default : (BASE_URL . '/');
    if (empty($url)) {
        return $fallback;
    }

    $url = trim($url);

    // Reject protocol-relative URLs (e.g., //evil.com) and backslash tricks (e.g., /\evil.com)
    if (str_starts_with($url, '//') || str_starts_with($url, '/\\') || str_starts_with($url, '\\')) {
        return $fallback;
    }

    // Reject non-http(s) schemes like javascript:, data:, etc.
    if (preg_match('/^[a-z0-9+.-]+:/i', $url)) {
        // If absolute URL, ensure it starts with BASE_URL
        if (str_starts_with($url, BASE_URL)) {
            return $url;
        }
        return $fallback;
    }

    // Relative path starting with single slash
    if (str_starts_with($url, '/')) {
        // Build absolute URL from BASE_URL origin or path
        $parts = parse_url(BASE_URL);
        $schemeHost = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $schemeHost . $url;
    }

    return $fallback;
}

/**
 * Flash message handling with XSS protection
 */
function setFlash(string $type, string $message, bool $isSafeHtml = false): void {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    $flashType = in_array($type, $allowedTypes, true) ? $type : 'info';

    $_SESSION['flash'] = [
        'type'        => $flashType,
        'message'     => $message,
        'is_safe_html' => $isSafeHtml
    ];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
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
        $msg = !empty($flash['is_safe_html']) ? $flash['message'] : e($flash['message']);
        echo "<div class='container mt-3'>
                <div class='alert alert-{$type} alert-dismissible fade show shadow-sm' role='alert'>
                    {$msg}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
              </div>";
    }
}

/**
 * Concurrency-Safe SQL Date Overlap Check
 * Prevents double bookings: Two date ranges [A, B] and [C, D] overlap if (A < D) AND (B > C)
 * Under MySQL inside a transaction, supports row locking (FOR UPDATE)
 */
function checkDateOverlap(int $listingId, string $checkIn, string $checkOut, ?int $excludeBookingId = null, bool $forUpdate = false): bool {
    if ($listingId <= 0 || !isValidDate($checkIn) || !isValidDate($checkOut) || $checkOut <= $checkIn) {
        return true; // Mark as overlapping/unavailable if input dates are invalid
    }

    $db = getDB();
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE listing_id = :listing_id 
              AND booking_status IN ('confirmed', 'pending')
              AND (check_in < :check_out AND check_out > :check_in)";

    $params = [
        ':listing_id' => $listingId,
        ':check_in'   => $checkIn,
        ':check_out'  => $checkOut
    ];

    if ($excludeBookingId !== null && $excludeBookingId > 0) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeBookingId;
    }

    // If active MySQL transaction and lock requested
    if ($forUpdate && Database::getDriver() === 'mysql' && Database::inTransaction()) {
        $sql .= " FOR UPDATE";
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        return $count > 0;
    } catch (\PDOException $e) {
        Logger::error("Date overlap check database error: " . $e->getMessage(), [
            'listing_id' => $listingId,
            'check_in' => $checkIn,
            'check_out' => $checkOut
        ]);
        return true; // Fail-safe: treat as occupied on error to prevent double booking
    }
}

/**
 * Calculate full booking pricing breakdown with strict bound checks
 */
function calculateBookingPrice(array $listing, string $checkIn, string $checkOut): array {
    $nights = calculateNights($checkIn, $checkOut);
    $pricePerNight = max(0.0, (float)($listing['price_per_night'] ?? 0));
    $subtotal = round($pricePerNight * $nights, 2);
    $cleaningFee = max(0.0, (float)($listing['cleaning_fee'] ?? 0));
    $serviceFee = max(0.0, (float)($listing['service_fee'] ?? 0));
    $total = round($subtotal + $cleaningFee + $serviceFee, 2);

    return [
        'nights'          => $nights,
        'price_per_night' => $pricePerNight,
        'subtotal'        => $subtotal,
        'cleaning_fee'    => $cleaningFee,
        'service_fee'     => $serviceFee,
        'total'           => $total
    ];
}

/**
 * Check if a listing is wishlisted by the current user
 */
function isWishlisted(int $listingId, ?int $userId = null): bool {
    if ($listingId <= 0) return false;
    if (!$userId) {
        $userId = $_SESSION['user']['id'] ?? null;
    }
    if (!$userId || (int)$userId <= 0) return false;

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM wishlists WHERE user_id = :uid AND listing_id = :lid LIMIT 1");
        $stmt->execute([':uid' => (int)$userId, ':lid' => $listingId]);
        return (bool)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        Logger::warning("isWishlisted check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Render star ratings HTML safely
 */
function renderRatingStars(float|int|string $rating, int $reviewCount = 0): string {
    $rating = round(max(0, (float)$rating), 1);
    $html = "<span class='d-inline-flex align-items-center gap-1'>";
    $html .= "<i class='fa-solid fa-star text-warning'></i>";
    $html .= "<strong>" . ($rating > 0 ? number_format($rating, 1) : 'New') . "</strong>";
    if ($reviewCount > 0) {
        $html .= "<span class='text-muted'>(" . (int)$reviewCount . ")</span>";
    }
    $html .= "</span>";
    return $html;
}

/**
 * Valid Property Types Whitelist
 */
function getValidPropertyTypes(): array {
    return [
        'Apartment',
        'Villa',
        'House',
        'Cabin',
        'Cottage',
        'Loft',
        'Heritage',
        'Bed & Breakfast',
        'Resort',
        'Chalet',
        'Studio'
    ];
}

function isValidPropertyType(string $type): bool {
    return in_array($type, getValidPropertyTypes(), true);
}

/**
 * Handle listing image upload safely
 * Validates error code, file size, real MIME type, image dimensions, and blocks RCE polyglots
 */
function uploadListingImage(array $file): ?string {
    if (empty($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Enforce 5MB maximum file size limit
    $maxBytes = 5 * 1024 * 1024;
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > $maxBytes) {
        Logger::warning("Image upload rejected: file size invalid or exceeds 5MB limit.", ['size' => $file['size'] ?? 0]);
        return null;
    }

    // Verify actual image structure and dimensions (blocks polyglot scripts / fake images)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        Logger::security("Image upload rejected: getimagesize() failed or corrupted structure.");
        return null;
    }

    // Strictly whitelist MIME types using finfo
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        Logger::security("Image upload rejected: MIME type '{$mime}' not in allowed whitelist.");
        return null;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg'
    };

    // Never use client-supplied filename (prevents directory traversal & double-extension attacks)
    $fileName = 'stay_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (ROOT_PATH . '/uploads/listings/');
    $targetPath = $targetDir . $fileName;

    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0644);
        Logger::info("Listing image uploaded successfully: {$fileName}");
        return (defined('UPLOAD_URL') ? UPLOAD_URL : (BASE_URL . '/uploads/listings/')) . $fileName;
    }

    Logger::error("Failed to move uploaded file to target path: {$targetPath}");
    return null;
}

/**
 * Human readable time difference
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    if ($time === false) {
        return 'recently';
    }
    $diff = time() - $time;
    if ($diff < 0) return 'just now';
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}
