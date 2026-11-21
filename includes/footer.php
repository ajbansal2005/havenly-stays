<?php
/**
 * StayHub Shared Footer
 */
?>
<footer class="footer mt-auto py-5 bg-white border-top text-muted">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="brand-icon">
                        <i class="fa-solid fa-house-chimney-window"></i>
                    </div>
                    <span class="brand-text fs-5">Stay<strong>Hub</strong></span>
                </div>
                <p class="small text-muted mb-3">
                    A modern, full-featured vacation rental platform inspired by Airbnb. Built with PHP, MySQL, Bootstrap 5, Leaflet Maps, Razorpay, and jQuery.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-code me-1"></i> PHP 8.2</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-database me-1"></i> MySQL / SQLite</span>
                    <span class="badge bg-light text-dark border"><i class="fa-brands fa-bootstrap me-1"></i> Bootstrap 5</span>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-dark fw-bold mb-3">Guest Links</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= BASE_URL ?>/listings.php" class="text-decoration-none text-muted">Explore Stays</a></li>
                    <li><a href="<?= BASE_URL ?>/wishlist.php" class="text-decoration-none text-muted">Saved Wishlist</a></li>
                    <li><a href="<?= BASE_URL ?>/my_bookings.php" class="text-decoration-none text-muted">My Trips</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="text-dark fw-bold mb-3">Hosting & Admin</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= BASE_URL ?>/host/dashboard.php" class="text-decoration-none text-muted">Host Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/host/add_listing.php" class="text-decoration-none text-muted">Add New Stay</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php" class="text-decoration-none text-muted">Admin Approvals</a></li>
                    <li><a href="<?= BASE_URL ?>/database/setup.php" class="text-decoration-none text-muted"><i class="fa-solid fa-rotate me-1"></i> Reset / Re-seed DB</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-dark fw-bold mb-3">Demo Accounts</h6>
                <div class="card bg-light border-0 p-3 small">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Guest:</strong> <code>guest@stayhub.test</code>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Host:</strong> <code>host@stayhub.test</code>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Admin:</strong> <code>admin@stayhub.test</code>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 11px;">
                        Password: <code>guest123</code> / <code>host123</code> / <code>admin123</code>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small">
            <div>
                &copy; <?= date('Y') ?> StayHub, Inc. &bull; Privacy &bull; Terms &bull; Sitemap
            </div>
            <div class="d-flex gap-3 mt-3 mt-md-0">
                <span><i class="fa-solid fa-globe me-1"></i> English (IN)</span>
                <span><i class="fa-solid fa-indian-rupee-sign me-1"></i> INR</span>
            </div>
        </div>
    </div>
</footer>

<!-- Toast notification container for AJAX alerts -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast align-items-center border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/main.js"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>

