<?php
/**
 * StayHub 404 - Page Not Found
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

http_response_code(404);
$pageTitle = '404 - Page Not Found';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="p-5 bg-light rounded-4 shadow-sm border">
                <div class="text-danger display-1 fw-bold mb-3">404</div>
                <h3 class="fw-bold mb-2">Oops! We can't seem to find that page</h3>
                <p class="text-muted mb-4">
                    The stay or page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="<?= BASE_URL ?>/" class="btn btn-danger rounded-pill px-4">
                        <i class="fa-solid fa-house me-2"></i> Return Home
                    </a>
                    <a href="<?= BASE_URL ?>/listings.php" class="btn btn-outline-dark rounded-pill px-4">
                        <i class="fa-solid fa-compass me-2"></i> Explore Stays
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
