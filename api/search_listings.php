<?php
/**
 * API: Search and Filter Listings with Live AJAX & Map Data
 * Safe parameterized queries, bounds validation, and XSS sanitization.
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

try {
    $db = getDB();
    $userId = $_SESSION['user']['id'] ?? null;

    // Gather and validate filters
    $rawCity = trim($_GET['city'] ?? '');
    $city = mb_substr($rawCity, 0, 100);

    $checkIn = trim($_GET['check_in'] ?? '');
    $checkOut = trim($_GET['check_out'] ?? '');

    $guests = isset($_GET['guests']) && $_GET['guests'] !== '' ? max(0, min(50, (int)$_GET['guests'])) : 0;
    $minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0.0, min(10000000.0, (float)$_GET['min_price'])) : 0.0;
    $maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? max(0.0, min(10000000.0, (float)$_GET['max_price'])) : 0.0;

    $propertyType = trim($_GET['property_type'] ?? '');
    if (!empty($propertyType) && $propertyType !== 'all' && !isValidPropertyType($propertyType)) {
        $propertyType = '';
    }

    $amenities = [];
    if (isset($_GET['amenities']) && is_array($_GET['amenities'])) {
        foreach ($_GET['amenities'] as $amVal) {
            $amId = (int)$amVal;
            if ($amId > 0) {
                $amenities[] = $amId;
            }
        }
        $amenities = array_unique($amenities);
    }

    $sort = trim($_GET['sort'] ?? 'newest');
    $allowedSorts = ['newest', 'price_asc', 'price_desc', 'rating_desc'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'newest';
    }

    $whereClauses = ["l.status = 'approved'"];
    $params = [];

    // City / Location filter
    if (!empty($city)) {
        // Escape SQL LIKE wildcard characters in user search query
        $escapedCity = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $city);
        $whereClauses[] = "(l.city LIKE :city OR l.title LIKE :city_title OR l.address LIKE :city_addr OR l.state LIKE :city_state)";
        $params[':city'] = "%{$escapedCity}%";
        $params[':city_title'] = "%{$escapedCity}%";
        $params[':city_addr'] = "%{$escapedCity}%";
        $params[':city_state'] = "%{$escapedCity}%";
    }

    // Guests filter
    if ($guests > 0) {
        $whereClauses[] = "l.max_guests >= :guests";
        $params[':guests'] = $guests;
    }

    // Price range
    if ($minPrice > 0) {
        $whereClauses[] = "l.price_per_night >= :min_price";
        $params[':min_price'] = $minPrice;
    }
    if ($maxPrice > 0 && $maxPrice >= $minPrice) {
        $whereClauses[] = "l.price_per_night <= :max_price";
        $params[':max_price'] = $maxPrice;
    }

    // Property type
    if (!empty($propertyType) && $propertyType !== 'all') {
        $whereClauses[] = "l.property_type = :property_type";
        $params[':property_type'] = $propertyType;
    }

    // Date range availability filter (SQL Overlap check)
    if (!empty($checkIn) && !empty($checkOut) && isValidDate($checkIn) && isValidDate($checkOut) && $checkOut > $checkIn) {
        $whereClauses[] = "l.id NOT IN (
            SELECT listing_id FROM bookings 
            WHERE booking_status IN ('confirmed', 'pending')
              AND (check_in < :search_check_out AND check_out > :search_check_in)
        )";
        $params[':search_check_in'] = $checkIn;
        $params[':search_check_out'] = $checkOut;
    }

    // Amenities filter (Must match all requested amenities)
    if (!empty($amenities)) {
        $amenityPlaceholders = [];
        foreach ($amenities as $idx => $amId) {
            $pName = ":am_" . $idx;
            $amenityPlaceholders[] = $pName;
            $params[$pName] = $amId;
        }
        $inClause = implode(',', $amenityPlaceholders);
        $reqCount = count($amenities);
        $whereClauses[] = "l.id IN (
            SELECT listing_id FROM listing_amenities 
            WHERE amenity_id IN ({$inClause}) 
            GROUP BY listing_id 
            HAVING COUNT(DISTINCT amenity_id) >= {$reqCount}
        )";
    }

    // Sort order
    $orderBy = match ($sort) {
        'price_asc'   => 'l.price_per_night ASC, l.id DESC',
        'price_desc'  => 'l.price_per_night DESC, l.id DESC',
        'rating_desc' => 'avg_rating DESC, l.id DESC',
        default       => 'l.featured DESC, l.id DESC'
    };

    $whereSql = implode(' AND ', $whereClauses);

    $sql = "SELECT l.*,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) AS avg_rating,
                   COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) AS review_count,
                   (SELECT image_url FROM listing_images WHERE listing_id = l.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_image
            FROM listings l
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT 150";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Wishlists for current user
    $userWishlist = [];
    if ($userId && (int)$userId > 0) {
        $wStmt = $db->prepare("SELECT listing_id FROM wishlists WHERE user_id = :uid");
        $wStmt->execute([':uid' => (int)$userId]);
        $userWishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $listings = [];
    foreach ($rows as $r) {
        $cleanDesc = strip_tags((string)($r['description'] ?? ''));
        $listings[] = [
            'id'              => (int)$r['id'],
            'title'           => (string)$r['title'],
            'description'     => mb_substr($cleanDesc, 0, 110) . (mb_strlen($cleanDesc) > 110 ? '...' : ''),
            'property_type'   => (string)$r['property_type'],
            'city'            => (string)$r['city'],
            'state'           => (string)($r['state'] ?? ''),
            'latitude'        => (float)$r['latitude'],
            'longitude'       => (float)$r['longitude'],
            'price_per_night' => (float)$r['price_per_night'],
            'price_formatted' => formatPrice($r['price_per_night']),
            'max_guests'      => (int)$r['max_guests'],
            'bedrooms'        => (int)$r['bedrooms'],
            'beds'            => (int)$r['beds'],
            'bathrooms'       => (int)$r['bathrooms'],
            'avg_rating'      => round((float)$r['avg_rating'], 1),
            'review_count'    => (int)$r['review_count'],
            'primary_image'   => $r['primary_image'] ?: 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800',
            'is_wishlisted'   => in_array((int)$r['id'], $userWishlist, false),
            'detail_url'      => BASE_URL . '/listing_detail.php?id=' . (int)$r['id']
        ];
    }

    echo json_encode([
        'success'     => true,
        'total_count' => count($listings),
        'listings'    => $listings
    ]);
} catch (\Throwable $t) {
    Logger::error("API search_listings error: " . $t->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'     => false,
        'message'     => 'Failed to search listings.',
        'total_count' => 0,
        'listings'    => []
    ]);
}
