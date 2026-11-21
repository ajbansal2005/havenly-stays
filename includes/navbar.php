<?php
/**
 * StayHub Shared Navigation Bar
 */
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? null;

// Wishlist count
$wishlistCount = 0;
if ($currentUser) {
    try {
        $db = getDB();
        $stmtW = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = :uid");
        $stmtW->execute([':uid' => $currentUser['id']]);
        $wishlistCount = (int)$stmtW->fetchColumn();
    } catch (Exception $e) {
        $wishlistCount = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top main-navbar">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/">
            <div class="brand-icon">
                <i class="fa-solid fa-house-chimney-window"></i>
            </div>
            <span class="brand-text">Stay<strong>Hub</strong></span>
        </a>

        <!-- Center Search Pill (Shows on desktop when not on home search) -->
        <div class="d-none d-md-flex mx-auto search-nav-pill">
            <a href="<?= BASE_URL ?>/listings.php" class="btn btn-outline-secondary rounded-pill px-3 py-2 d-flex align-items-center gap-3 text-decoration-none shadow-sm text-dark">
                <span class="fw-semibold px-2 border-end">Anywhere</span>
                <span class="fw-semibold px-2 border-end">Any week</span>
                <span class="text-muted px-2">Add guests</span>
                <div class="search-nav-btn">
                    <i class="fa-solid fa-magnifying-glass text-white fa-xs"></i>
                </div>
            </a>
        </div>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#stayNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="stayNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="<?= BASE_URL ?>/listings.php">
                        <i class="fa-solid fa-compass me-1 text-muted"></i> Explore Stays
                    </a>
                </li>

                <?php if ($userRole === 'admin'): ?>
                    <!-- Admin Links -->
                    <li class="nav-item">
                        <a class="nav-link fw-semibold text-danger" href="<?= BASE_URL ?>/admin/dashboard.php">
                            <i class="fa-solid fa-shield-halved me-1"></i> Admin Panel
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($userRole === 'host'): ?>
                    <!-- Host Links -->
                    <li class="nav-item">
                        <a class="nav-link fw-semibold text-primary" href="<?= BASE_URL ?>/host/dashboard.php">
                            <i class="fa-solid fa-chart-line me-1"></i> Host Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-danger rounded-pill px-3" href="<?= BASE_URL ?>/host/add_listing.php">
                            <i class="fa-solid fa-plus me-1"></i> Add Stay
                        </a>
                    </li>
                <?php elseif ($userRole === 'guest'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-muted" href="<?= BASE_URL ?>/register.php?role=host">
                            Become a Host
                        </a>
                    </li>
                <?php elseif (!$currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link text-muted" href="<?= BASE_URL ?>/register.php?role=host">
                            StayHub your home
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($currentUser): ?>
                    <!-- Wishlist icon -->
                    <li class="nav-item">
                        <a class="nav-link position-relative px-2" href="<?= BASE_URL ?>/wishlist.php" title="Wishlist">
                            <i class="fa-regular fa-heart fa-lg text-danger"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger wishlist-badge-count <?= $wishlistCount > 0 ? '' : 'd-none' ?>">
                                <?= $wishlistCount ?>
                            </span>
                        </a>
                    </li>

                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown ms-lg-2">
                        <button class="btn btn-outline-light border rounded-pill d-flex align-items-center gap-2 py-1 px-2 shadow-sm text-dark dropdown-toggle" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-bars text-muted ms-1"></i>
                            <?php if (!empty($currentUser['avatar'])): ?>
                                <img src="<?= e($currentUser['avatar']) ?>" class="rounded-circle object-fit-cover" width="30" height="30" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-circle">
                                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 py-2 mt-2" aria-labelledby="userMenuDropdown" style="min-width: 230px;">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-truncate"><?= e($currentUser['name']) ?></div>
                                <div class="small text-muted text-truncate"><?= e($currentUser['email']) ?></div>
                                <span class="badge bg-light text-dark border text-uppercase mt-1" style="font-size: 10px;">
                                    Role: <?= e($currentUser['role']) ?>
                                </span>
                            </li>

                            <?php if ($userRole === 'guest'): ?>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/my_bookings.php"><i class="fa-solid fa-suitcase me-2 text-muted"></i> My Bookings</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/wishlist.php"><i class="fa-solid fa-heart me-2 text-danger"></i> My Wishlist</a></li>
                            <?php elseif ($userRole === 'host'): ?>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/host/dashboard.php"><i class="fa-solid fa-gauge me-2 text-muted"></i> Host Overview</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/host/listings.php"><i class="fa-solid fa-hotel me-2 text-muted"></i> My Listings</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/host/bookings.php"><i class="fa-solid fa-calendar-check me-2 text-muted"></i> Guest Reservations</a></li>
                            <?php elseif ($userRole === 'admin'): ?>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-pie me-2 text-muted"></i> Admin Dashboard</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/listings.php"><i class="fa-solid fa-check-to-slot me-2 text-muted"></i> Listing Approvals</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/bookings.php"><i class="fa-solid fa-receipt me-2 text-muted"></i> All Bookings</a></li>
                                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/users.php"><i class="fa-solid fa-users me-2 text-muted"></i> Manage Users</a></li>
                            <?php endif; ?>

                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/profile.php"><i class="fa-solid fa-user-gear me-2 text-muted"></i> Account Settings</a></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-dark rounded-pill px-3 btn-sm me-2" href="<?= BASE_URL ?>/login.php">Log in</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-danger rounded-pill px-3 btn-sm" href="<?= BASE_URL ?>/register.php">Sign up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

