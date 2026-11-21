/**
 * StayHub Global Client-side JavaScript
 */

$(document).ready(function() {

    // Toast Notification Helper
    window.showToast = function(message, type = 'success') {
        const toastEl = $('#liveToast');
        const msgEl = $('#toastMessage');
        
        toastEl.removeClass('toast-success toast-danger toast-info bg-success bg-danger bg-primary text-white');
        
        if (type === 'success') {
            toastEl.addClass('bg-success text-white');
        } else if (type === 'danger' || type === 'error') {
            toastEl.addClass('bg-danger text-white');
        } else {
            toastEl.addClass('bg-primary text-white');
        }

        msgEl.html(message);
        const toast = new bootstrap.Toast(toastEl[0], { delay: 3500 });
        toast.show();
    };

    // AJAX Wishlist Toggle
    $(document).on('click', '.wishlist-heart-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = $(this);
        const listingId = btn.data('listing-id');

        if (!window.APP_CONFIG.isLoggedIn) {
            window.showToast('Please <a href="' + window.APP_CONFIG.baseUrl + '/login.php" class="text-white fw-bold text-decoration-underline">log in</a> to save stays to your wishlist.', 'danger');
            return;
        }

        btn.prop('disabled', true);

        $.ajax({
            url: window.APP_CONFIG.baseUrl + '/api/toggle_wishlist.php',
            method: 'POST',
            dataType: 'json',
            data: {
                listing_id: listingId,
                csrf_token: window.APP_CONFIG.csrfToken
            },
            success: function(res) {
                btn.prop('disabled', false);
                if (res.success) {
                    if (res.wishlisted) {
                        btn.addClass('active');
                        btn.find('i').removeClass('fa-regular').addClass('fa-solid');
                        window.showToast('<i class="fa-solid fa-heart me-1"></i> Saved to your wishlist!', 'success');
                    } else {
                        btn.removeClass('active');
                        btn.find('i').removeClass('fa-solid').addClass('fa-regular');
                        window.showToast('Removed from wishlist', 'info');
                    }

                    // Update badge count
                    if (res.total_count !== undefined) {
                        const badge = $('.wishlist-badge-count');
                        badge.text(res.total_count);
                        if (res.total_count > 0) {
                            badge.removeClass('d-none');
                        } else {
                            badge.addClass('d-none');
                        }
                    }
                } else {
                    window.showToast(res.message || 'Error updating wishlist', 'danger');
                }
            },
            error: function() {
                btn.prop('disabled', false);
                window.showToast('Server communication error', 'danger');
            }
        });
    });

});

