<?php
/**
 * StayHub Database Setup Script
 * Can be run via browser: http://localhost:8000/database/setup.php
 * or via CLI: php database/setup.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$isCli = (php_sapi_name() === 'cli');
$lockFile = __DIR__ . '/.installed';

// Security: If already installed and accessed via web browser, require admin authentication
if (!$isCli && file_exists($lockFile)) {
    if (!isLoggedIn() || !hasRole('admin')) {
        http_response_code(403);
        die("<div style='font-family:sans-serif;max-width:550px;margin:50px auto;padding:24px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:12px;'>"
            . "<h4 style='margin-top:0;'>Security Lock: Database Already Initialized</h4>"
            . "<p>The database is already setup. To re-seed or re-run setup via web, you must be logged in as an <strong>Administrator</strong>.</p>"
            . "<p>Alternatively, execute from your terminal: <code>php database/setup.php</code></p>"
            . "<a href='" . BASE_URL . "/login.php' style='display:inline-block;padding:8px 16px;background:#0d6efd;color:white;text-decoration:none;border-radius:6px;'>Log in as Admin</a>"
            . "</div>");
    }
}

function logMsg($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        echo "[$type] " . strip_tags($msg) . PHP_EOL;
    } else {
        $color = $type === 'error' ? 'text-danger' : ($type === 'success' ? 'text-success' : 'text-primary');
        echo "<p class='mb-1 {$color}'><strong>[" . strtoupper($type) . "]</strong> {$msg}</p>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>StayHub Setup</title>"
        . "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>"
        . "</head><body class='bg-light py-5'><div class='container'><div class='card shadow-sm p-4'><h2 class='card-title mb-4'>StayHub Database Initialization</h2>";
}

$mysqlSuccess = false;
$sqliteSuccess = false;

// 1. Try MySQL Setup
try {
    logMsg("Attempting to connect to MySQL server at " . DB_HOST . ":" . DB_PORT . "...");
    $rootPdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    logMsg("Connected to MySQL server! Creating database `" . DB_NAME . "` if not exists...");
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    
    // Connect to database
    $dbPdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    logMsg("Executing MySQL schema.sql...");
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $dbPdo->exec($schemaSql);
    logMsg("Schema created successfully!", "success");

    logMsg("Executing MySQL seed.sql...");
    $seedSql = file_get_contents(__DIR__ . '/seed.sql');
    $dbPdo->exec($seedSql);
    logMsg("Seed data inserted successfully!", "success");

    $mysqlSuccess = true;
} catch (PDOException $e) {
    logMsg("MySQL Setup skipped/failed: " . $e->getMessage(), "error");
    logMsg("Creating/updating portable SQLite database so StayHub runs seamlessly...", "info");
}

// 2. Setup SQLite database as well (gives complete standalone zero-dependency capability)
try {
    $sqliteFile = SQLITE_PATH;
    $sqlitePdo = new PDO("sqlite:" . $sqliteFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $sqlitePdo->exec("PRAGMA foreign_keys = ON;");

    // SQLite Schema
    $sqliteSchema = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        phone TEXT,
        role TEXT DEFAULT 'guest',
        avatar TEXT,
        bio TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS amenities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        icon TEXT NOT NULL,
        category TEXT DEFAULT 'General'
    );

    CREATE TABLE IF NOT EXISTS listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        host_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        property_type TEXT DEFAULT 'Apartment',
        address TEXT NOT NULL,
        city TEXT NOT NULL,
        state TEXT,
        country TEXT DEFAULT 'India',
        latitude REAL DEFAULT 0.0,
        longitude REAL DEFAULT 0.0,
        price_per_night REAL NOT NULL,
        cleaning_fee REAL DEFAULT 0.0,
        service_fee REAL DEFAULT 0.0,
        max_guests INTEGER DEFAULT 1,
        bedrooms INTEGER DEFAULT 1,
        beds INTEGER DEFAULT 1,
        bathrooms INTEGER DEFAULT 1,
        status TEXT DEFAULT 'approved',
        featured INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS listing_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        listing_id INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        is_primary INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS listing_amenities (
        listing_id INTEGER NOT NULL,
        amenity_id INTEGER NOT NULL,
        PRIMARY KEY (listing_id, amenity_id),
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_code TEXT UNIQUE NOT NULL,
        listing_id INTEGER NOT NULL,
        guest_id INTEGER NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        guests_count INTEGER DEFAULT 1,
        total_nights INTEGER NOT NULL,
        price_per_night REAL NOT NULL,
        cleaning_fee REAL DEFAULT 0.0,
        service_fee REAL DEFAULT 0.0,
        total_price REAL NOT NULL,
        payment_status TEXT DEFAULT 'pending',
        payment_method TEXT DEFAULT 'razorpay',
        razorpay_order_id TEXT,
        razorpay_payment_id TEXT,
        booking_status TEXT DEFAULT 'confirmed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER,
        listing_id INTEGER NOT NULL,
        guest_id INTEGER NOT NULL,
        rating INTEGER NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS wishlists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        listing_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (user_id, listing_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
    );
    ";
    $sqlitePdo->exec($sqliteSchema);

    // Populate SQLite Seed Data if empty
    $checkUsers = $sqlitePdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($checkUsers == 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $hostPass = password_hash('host123', PASSWORD_BCRYPT);
        $guestPass = password_hash('guest123', PASSWORD_BCRYPT);

        $sqlitePdo->exec("
        INSERT INTO amenities (id, name, icon, category) VALUES
        (1, 'High-Speed Wi-Fi', 'fa-solid fa-wifi', 'Internet & Office'),
        (2, 'Air Conditioning', 'fa-solid fa-snowflake', 'Climate Control'),
        (3, 'Private Swimming Pool', 'fa-solid fa-person-swimming', 'Outdoor & Luxury'),
        (4, 'Free Parking on Premises', 'fa-solid fa-square-parking', 'Parking & Facilities'),
        (5, 'Fully Equipped Kitchen', 'fa-solid fa-kitchen-set', 'Kitchen & Dining'),
        (6, 'Smart TV with Netflix', 'fa-solid fa-tv', 'Entertainment'),
        (7, 'Dedicated Workspace', 'fa-solid fa-laptop', 'Internet & Office'),
        (8, 'Washing Machine', 'fa-solid fa-soap', 'Laundry & Cleaning'),
        (9, 'Fitness Gym', 'fa-solid fa-dumbbell', 'Outdoor & Luxury'),
        (10, 'Pet Friendly', 'fa-solid fa-paw', 'Special Features'),
        (11, 'Panoramic Sea View', 'fa-solid fa-water', 'Views & Scenery'),
        (12, 'Mountain View', 'fa-solid fa-mountain', 'Views & Scenery');
        ");

        $stmtUser = $sqlitePdo->prepare("
        INSERT INTO users (id, name, email, password, phone, role, avatar, bio, status) VALUES
        (1, 'Admin StayHub', 'admin@stayhub.test', :admin_pass, '+91 98765 43210', 'admin', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80', 'Platform Administrator at StayHub.', 'active'),
        (2, 'Rohit Sharma (Superhost)', 'host@stayhub.test', :host_pass, '+91 98111 22334', 'host', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80', 'Passionate traveler and hospitality enthusiast. Hosting modern luxury villas and cosy city lofts.', 'active'),
        (3, 'Priya Kapoor (Host)', 'host2@stayhub.test', :host_pass, '+91 98222 33445', 'host', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&auto=format&fit=crop&q=80', 'Architect & interior designer creating peaceful mountain escapes and boutique stays.', 'active'),
        (4, 'Ajay Verma (Guest)', 'guest@stayhub.test', :guest_pass, '+91 98333 44556', 'guest', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&auto=format&fit=crop&q=80', 'Tech enthusiast, digital nomad and wanderer loving unique stays.', 'active');
        ");
        $stmtUser->execute([
            ':admin_pass' => $adminPass,
            ':host_pass' => $hostPass,
            ':guest_pass' => $guestPass
        ]);

        $sqliteSeed = "

        INSERT INTO listings (id, host_id, title, description, property_type, address, city, state, country, latitude, longitude, price_per_night, cleaning_fee, service_fee, max_guests, bedrooms, beds, bathrooms, status, featured) VALUES
        (1, 2, 'Luxury Sunset Beach Villa with Infinity Pool', 'Step into paradise at this beachfront sanctuary overlooking the Arabian Sea in North Goa. Enjoy private beach access, a magnificent infinity pool, spacious sun decks, and bespoke concierge services. Ideal for families and luxury getaways.', 'Villa', 'Vagator Beach Road, Anjuna', 'Goa', 'Goa', 'India', 15.5996, 73.7439, 14500.00, 1200.00, 800.00, 8, 4, 4, 4, 'approved', 1),
        (2, 2, 'Skyline Penthouse with Private Terrace & Jacuzzi', 'Perched high in Bandra West with panoramic views of the Mumbai skyline and sea link. Features double-height ceilings, designer Italian furniture, ultra-fast 500Mbps Wi-Fi, and a rooftop cocktail terrace.', 'Apartment', 'Pali Hill, Bandra West', 'Mumbai', 'Maharashtra', 'India', 19.0600, 72.8258, 9200.00, 900.00, 600.00, 4, 2, 2, 2, 'approved', 1),
        (3, 3, 'Pine Forest Glass Cabin & Stargazing Loft', 'A stunning Scandinavian-inspired A-frame cabin nestled amid cedar and pine forests of Old Manali. Floor-to-ceiling glass windows, wood-burning fireplace, heated floors, and unobstructed views of snow-capped Himalayan peaks.', 'Cabin', 'Club House Road, Old Manali', 'Manali', 'Himachal Pradesh', 'India', 32.2530, 77.1750, 5800.00, 500.00, 400.00, 3, 1, 2, 1, 'approved', 1),
        (4, 3, 'Royal Heritage Haveli Suite with Courtyard', 'Experience royal Rajputana elegance inside the historic Pink City. Handcrafted marble jalis, antique brass fittings, private leafy courtyard, and traditional Rajasthani breakfast included every morning.', 'Heritage', 'Johari Bazaar, Pink City', 'Jaipur', 'Rajasthan', 'India', 26.9196, 75.8267, 6400.00, 600.00, 450.00, 4, 2, 2, 2, 'approved', 1),
        (5, 2, 'Minimalist Tech Loft near Indiranagar 100ft Rd', 'Modern studio loft tailored for creative professionals and digital nomads. Ergonomic Herman Miller seating, dual 4K monitors, soundproof acoustic walls, and walking distance to Bengaluru''s top craft breweries.', 'Loft', '12th Main Rd, Indiranagar', 'Bengaluru', 'Karnataka', 'India', 12.9719, 77.6412, 4200.00, 400.00, 300.00, 2, 1, 1, 1, 'approved', 0),
        (6, 3, 'Tranquil Backwater Wooden Cottage with Private Canoe', 'Immerse in the serene backwaters of Alleppey. Features teakwood construction, private canal dock, complimentary wooden canoe, and delicious home-cooked Kerala cuisine served under swaying coconut palms.', 'Cottage', 'Punnamada Lakefront', 'Alleppey', 'Kerala', 'India', 9.5010, 76.3388, 5100.00, 500.00, 350.00, 4, 2, 2, 2, 'approved', 1);

        INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES
        (1, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1200&auto=format&fit=crop&q=80', 1),
        (1, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&auto=format&fit=crop&q=80', 0),
        (1, 'https://images.unsplash.com/photo-1613977257363-707ba9348227?w=1200&auto=format&fit=crop&q=80', 0),
        (1, 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=1200&auto=format&fit=crop&q=80', 0),
        (2, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&auto=format&fit=crop&q=80', 1),
        (2, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=1200&auto=format&fit=crop&q=80', 0),
        (2, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&auto=format&fit=crop&q=80', 0),
        (2, 'https://images.unsplash.com/photo-1502005229762-ee1b2b8100ff?w=1200&auto=format&fit=crop&q=80', 0),
        (3, 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?w=1200&auto=format&fit=crop&q=80', 1),
        (3, 'https://images.unsplash.com/photo-1510798831971-661eb04b3739?w=1200&auto=format&fit=crop&q=80', 0),
        (3, 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=80', 0),
        (3, 'https://images.unsplash.com/photo-1449844908441-8829872d2607?w=1200&auto=format&fit=crop&q=80', 0),
        (4, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200&auto=format&fit=crop&q=80', 1),
        (4, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&auto=format&fit=crop&q=80', 0),
        (4, 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1200&auto=format&fit=crop&q=80', 0),
        (5, 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&auto=format&fit=crop&q=80', 1),
        (5, 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&auto=format&fit=crop&q=80', 0),
        (5, 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&auto=format&fit=crop&q=80', 0),
        (6, 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=1200&auto=format&fit=crop&q=80', 1),
        (6, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&auto=format&fit=crop&q=80', 0),
        (6, 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&auto=format&fit=crop&q=80', 0);

        INSERT INTO listing_amenities (listing_id, amenity_id) VALUES
        (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 10), (1, 11),
        (2, 1), (2, 2), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 11),
        (3, 1), (3, 4), (3, 5), (3, 7), (3, 10), (3, 12),
        (4, 1), (4, 2), (4, 4), (4, 5), (4, 6),
        (5, 1), (5, 2), (5, 4), (5, 5), (5, 6), (5, 7), (5, 8),
        (6, 1), (6, 4), (6, 5), (6, 10), (6, 11);

        INSERT INTO bookings (id, booking_code, listing_id, guest_id, check_in, check_out, guests_count, total_nights, price_per_night, cleaning_fee, service_fee, total_price, payment_status, payment_method, razorpay_order_id, razorpay_payment_id, booking_status) VALUES
        (1, 'BK-STAY-882190', 1, 4, '2026-11-20', '2026-11-25', 4, 5, 14500.00, 1200.00, 800.00, 74500.00, 'paid', 'razorpay', 'order_demo_1001', 'pay_demo_9901', 'confirmed'),
        (2, 'BK-STAY-771234', 1, 4, '2026-12-01', '2026-12-05', 2, 4, 14500.00, 1200.00, 800.00, 60000.00, 'paid', 'razorpay', 'order_demo_1002', 'pay_demo_9902', 'confirmed'),
        (3, 'BK-STAY-663451', 3, 4, '2026-11-15', '2026-11-18', 2, 3, 5800.00, 500.00, 400.00, 18300.00, 'paid', 'razorpay', 'order_demo_1003', 'pay_demo_9903', 'confirmed'),
        (4, 'BK-STAY-554129', 2, 4, '2026-10-10', '2026-10-14', 2, 4, 9200.00, 900.00, 600.00, 38300.00, 'paid', 'razorpay', 'order_demo_1004', 'pay_demo_9904', 'completed');

        INSERT INTO reviews (id, booking_id, listing_id, guest_id, rating, comment, created_at) VALUES
        (1, 4, 2, 4, 5, 'Absolutely breathtaking views of Mumbai! The penthouse was impeccably clean, and the terrace jacuzzi during sunset is an unforgettable experience. Host Rohit was super communicative and helpful.', '2026-10-15 10:30:00'),
        (2, NULL, 1, 4, 5, 'The sunset villa in Goa blew away our expectations. Huge pool, direct beach pathway, and fantastic amenities. We will definitely be coming back!', '2026-09-12 14:20:00'),
        (3, NULL, 3, 4, 5, 'Cozy pine cabin with the best mountain views in Manali. Sitting near the fireplace with fresh coffee was pure bliss.', '2026-08-05 18:00:00');

        INSERT INTO wishlists (user_id, listing_id) VALUES
        (4, 1),
        (4, 3);
        ";
        $sqlitePdo->exec($sqliteSeed);
    }

    logMsg("SQLite database prepared at " . $sqliteFile, "success");
    $sqliteSuccess = true;
} catch (Exception $e) {
    logMsg("SQLite Setup error: " . $e->getMessage(), "error");
}

// Write installation lock file to secure against unauthorized web resets
@file_put_contents($lockFile, date('Y-m-d H:i:s'));

if (!$isCli) {
    echo "<hr><div class='alert alert-info mt-3'>";
    echo "<h5>Demo Accounts Ready:</h5>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@stayhub.test / password: <code>admin123</code></li>";
    echo "<li><strong>Host (Superhost):</strong> host@stayhub.test / password: <code>host123</code></li>";
    echo "<li><strong>Guest:</strong> guest@stayhub.test / password: <code>guest123</code></li>";
    echo "</ul></div>";
    echo "<a href='" . BASE_URL . "/index.php' class='btn btn-primary btn-lg mt-2'>Go to StayHub Homepage &rarr;</a>";
    echo "</div></div></body></html>";
} else {
    echo PHP_EOL . "StayHub Database initialization complete!" . PHP_EOL;
    echo "Demo Credentials:" . PHP_EOL;
    echo "  - Admin: admin@stayhub.test / admin123" . PHP_EOL;
    echo "  - Host:  host@stayhub.test  / host123" . PHP_EOL;
    echo "  - Guest: guest@stayhub.test / guest123" . PHP_EOL;
}
