/**
 * StayHub Listing Detail & Booking Widget Logic
 * - Calendar availability blocking via Flatpickr
 * - Live price computation & double booking check
 * - Razorpay Test Mode checkout
 */

let datepickerInstance = null;

$(document).ready(function() {
    initPropertyMap();
    initAvailabilityCalendar();
    bindBookingWidgetEvents();
    bindReviewForm();
});

// 1. Initialize Leaflet Map for this property
function initPropertyMap() {
    const mapEl = document.getElementById('propertyDetailMap');
    if (!mapEl) return;

    const lat = parseFloat($(mapEl).data('lat')) || 20.5937;
    const lng = parseFloat($(mapEl).data('lng')) || 78.9629;
    const title = $(mapEl).data('title') || 'Listing Location';

    const pMap = L.map('propertyDetailMap').setView([lat, lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(pMap);

    const marker = L.marker([lat, lng]).addTo(pMap);
    marker.bindPopup(`<strong>${title}</strong><br>Exact location provided after booking.`).openPopup();
}

// 2. Initialize Availability Calendar & Block Booked Dates
function initAvailabilityCalendar() {
    const listingId = $('#bookingListingId').val();
    if (!listingId) return;

    // Fetch already booked ranges from API
    $.ajax({
        url: window.APP_CONFIG.baseUrl + '/api/get_booked_dates.php',
        method: 'GET',
        data: { listing_id: listingId },
        dataType: 'json',
        success: function(res) {
            const disabledRanges = [];
            if (res.success && res.ranges) {
                res.ranges.forEach(r => {
                    disabledRanges.push({
                        from: r.from,
                        to: r.to
                    });
                });
            }

            // Setup Flatpickr on the date inputs
            datepickerInstance = flatpickr('#bookingDatesInput', {
                mode: 'range',
                minDate: 'today',
                dateFormat: 'Y-m-d',
                disable: disabledRanges,
                altInput: true,
                altFormat: 'M j, Y',
                onClose: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const checkIn = instance.formatDate(selectedDates[0], 'Y-m-d');
                        const checkOut = instance.formatDate(selectedDates[1], 'Y-m-d');
                        $('#bookingCheckIn').val(checkIn);
                        $('#bookingCheckOut').val(checkOut);
                        checkAvailabilityAndCalculate(listingId, checkIn, checkOut);
                    } else {
                        resetPriceBreakdown();
                    }
                }
            });
        }
    });
}

// 3. Live Price Calculation & Double Booking Check
function checkAvailabilityAndCalculate(listingId, checkIn, checkOut) {
    const guestsCount = $('#bookingGuests').val() || 1;
    const btnSubmit = $('#btnReserve');

    $('#priceCalculationBox').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-danger"></div> Checking dates...</div>');
    btnSubmit.prop('disabled', true);

    $.ajax({
        url: window.APP_CONFIG.baseUrl + '/api/check_availability.php',
        method: 'POST',
        data: {
            listing_id: listingId,
            check_in: checkIn,
            check_out: checkOut,
            guests_count: guestsCount
        },
        dataType: 'json',
        success: function(res) {
            if (res.available) {
                const c = res.calculation;
                const html = `
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-decoration-underline">${c.price_per_night_formatted} &times; ${c.nights} nights</span>
                        <span>${c.subtotal_formatted}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-decoration-underline">Cleaning fee</span>
                        <span>${c.cleaning_fee_formatted}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-decoration-underline">StayHub service fee</span>
                        <span>${c.service_fee_formatted}</span>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between fw-bold fs-6">
                        <span>Total before taxes</span>
                        <span class="text-danger">${c.total_formatted}</span>
                    </div>
                `;
                $('#priceCalculationBox').html(html);
                $('#bookingTotalPrice').val(c.total);
                btnSubmit.prop('disabled', false).text('Reserve');
            } else {
                $('#priceCalculationBox').html(`
                    <div class="alert alert-danger py-2 small mb-0">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> ${res.message}
                    </div>
                `);
                btnSubmit.prop('disabled', true).text('Dates Unavailable');
            }
        },
        error: function() {
            $('#priceCalculationBox').html('<div class="alert alert-danger py-2 small">Error verifying availability.</div>');
            btnSubmit.prop('disabled', true);
        }
    });
}

function resetPriceBreakdown() {
    $('#priceCalculationBox').html('<div class="text-muted small text-center py-2">Select check-in and check-out dates to view total price.</div>');
    $('#btnReserve').prop('disabled', true).text('Check Availability');
}

// 4. Handle Guests change & Form submission
function bindBookingWidgetEvents() {
    $('#bookingGuests').on('change', function() {
        const checkIn = $('#bookingCheckIn').val();
        const checkOut = $('#bookingCheckOut').val();
        const listingId = $('#bookingListingId').val();
        if (checkIn && checkOut) {
            checkAvailabilityAndCalculate(listingId, checkIn, checkOut);
        }
    });

    // Form submission -> initiate Razorpay or process booking
    $('#bookingForm').on('submit', function(e) {
        if (!window.APP_CONFIG.isLoggedIn) {
            e.preventDefault();
            window.location.href = window.APP_CONFIG.baseUrl + '/login.php?redirect=' + encodeURIComponent(window.location.href);
            return false;
        }

        const checkIn = $('#bookingCheckIn').val();
        const checkOut = $('#bookingCheckOut').val();
        if (!checkIn || !checkOut) {
            e.preventDefault();
            alert('Please select both check-in and check-out dates.');
            return false;
        }
    });
}

// 5. Review Form Submission
function bindReviewForm() {
    $('#reviewForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');

        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

        $.ajax({
            url: window.APP_CONFIG.baseUrl + '/api/submit_review.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                submitBtn.prop('disabled', false).text('Submit Review');
                if (res.success) {
                    window.showToast(res.message, 'success');
                    $('#writeReviewModal').modal('hide');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    window.showToast(res.message, 'danger');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text('Submit Review');
                window.showToast('Network error while posting review', 'danger');
            }
        });
    });
}

