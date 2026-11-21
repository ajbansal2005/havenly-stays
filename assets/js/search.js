/**
 * StayHub AJAX Live Search & Leaflet OpenStreetMap Handler
 */

let map = null;
let markersLayer = null;
let searchDebounceTimer = null;

$(document).ready(function() {
    initLeafletMap();
    initSearchDatepicker();
    bindSearchEvents();

    // Trigger initial search from URL query parameters
    performAjaxSearch();
});

// Initialize Leaflet OpenStreetMap
function initLeafletMap() {
    const mapElement = document.getElementById('searchMap');
    if (!mapElement) return;

    // Default center on India
    map = L.map('searchMap').setView([20.5937, 78.9629], 5);

    // Free OpenStreetMap Tile Layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);
}

// Initialize Flatpickr Datepicker for Check-in / Check-out
function initSearchDatepicker() {
    const dateInput = document.getElementById('filterDateRange');
    if (!dateInput) return;

    flatpickr('#filterDateRange', {
        mode: 'range',
        minDate: 'today',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'M j',
        onClose: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                $('#filterCheckIn').val(instance.formatDate(selectedDates[0], 'Y-m-d'));
                $('#filterCheckOut').val(instance.formatDate(selectedDates[1], 'Y-m-d'));
            } else if (selectedDates.length === 0) {
                $('#filterCheckIn').val('');
                $('#filterCheckOut').val('');
            }
            performAjaxSearch();
        }
    });
}

// Bind all interactive inputs for Live AJAX filtering
function bindSearchEvents() {
    // City text input (Debounced 350ms)
    $('#filterCity').on('input', function() {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(performAjaxSearch, 350);
    });

    // Guests selector
    $('#filterGuests').on('change', performAjaxSearch);

    // Property Type change
    $('input[name="filterPropertyType"]').on('change', performAjaxSearch);

    // Sort order change
    $('#filterSort').on('change', performAjaxSearch);

    // Price Range Slider
    $('#filterMaxPrice').on('input', function() {
        const val = parseInt($(this).val());
        $('#priceDisplay').text(window.APP_CONFIG.currency + val.toLocaleString('en-IN'));
    }).on('change', performAjaxSearch);

    // Amenities Checkboxes
    $('.filter-amenity-checkbox').on('change', performAjaxSearch);

    // Reset Filters button
    $('#btnResetFilters').on('click', function(e) {
        e.preventDefault();
        $('#filterCity').val('');
        $('#filterCheckIn').val('');
        $('#filterCheckOut').val('');
        const fp = document.getElementById('filterDateRange')._flatpickr;
        if (fp) fp.clear();
        $('#filterGuests').val('');
        $('#filterMaxPrice').val(30000);
        $('#priceDisplay').text(window.APP_CONFIG.currency + '30,000');
        $('input[name="filterPropertyType"][value=""]').prop('checked', true);
        $('.filter-amenity-checkbox').prop('checked', false);
        $('#filterSort').val('newest');
        performAjaxSearch();
    });

    // Mobile Map Toggle
    $('#toggleMapBtn').on('click', function() {
        $('#mapColumn').toggleClass('d-none d-lg-block');
        const isVisible = !$('#mapColumn').hasClass('d-none');
        $(this).html(isVisible ? '<i class="fa-solid fa-list me-1"></i> Show List' : '<i class="fa-solid fa-map me-1"></i> Show Map');
        if (isVisible && map) {
            setTimeout(() => map.invalidateSize(), 200);
        }
    });
}

// Main AJAX search caller
function performAjaxSearch() {
    $('#searchLoadingSpinner').removeClass('d-none');
    $('#listingsContainer').addClass('opacity-50');

    // Gather checked amenities
    const selectedAmenities = [];
    $('.filter-amenity-checkbox:checked').each(function() {
        selectedAmenities.push($(this).val());
    });

    const postData = {
        city: $('#filterCity').val(),
        check_in: $('#filterCheckIn').val(),
        check_out: $('#filterCheckOut').val(),
        guests: $('#filterGuests').val(),
        max_price: $('#filterMaxPrice').val(),
        property_type: $('input[name="filterPropertyType"]:checked').val() || '',
        sort: $('#filterSort').val(),
        amenities: selectedAmenities
    };

    $.ajax({
        url: window.APP_CONFIG.baseUrl + '/api/search_listings.php',
        method: 'GET',
        data: postData,
        dataType: 'json',
        success: function(response) {
            $('#searchLoadingSpinner').addClass('d-none');
            $('#listingsContainer').removeClass('opacity-50');

            if (response.success) {
                renderListings(response.listings);
                updateMapMarkers(response.listings);
                $('#resultsCount').text(response.total_count + ' stays found');
            } else {
                $('#listingsContainer').html('<div class="col-12 py-5 text-center text-muted">Error loading stays.</div>');
            }
        },
        error: function() {
            $('#searchLoadingSpinner').addClass('d-none');
            $('#listingsContainer').removeClass('opacity-50');
            $('#listingsContainer').html('<div class="col-12 py-5 text-center text-danger">Failed to connect to server.</div>');
        }
    });
}

// Render Listing Cards into the Grid
function renderListings(listings) {
    const container = $('#listingsContainer');
    container.empty();

    if (!listings || listings.length === 0) {
        container.html(`
            <div class="col-12 py-5 text-center">
                <div class="p-5 bg-light rounded-4">
                    <i class="fa-solid fa-house-circle-xmark fa-3x text-muted mb-3"></i>
                    <h5 class="fw-bold">No stays match your criteria</h5>
                    <p class="text-muted small">Try broadening your search dates, adjusting the price slider, or resetting filters.</p>
                    <button class="btn btn-outline-dark rounded-pill px-4 mt-2" onclick="$('#btnResetFilters').click();">Reset Filters</button>
                </div>
            </div>
        `);
        return;
    }

    listings.forEach(stay => {
        const ratingHtml = stay.avg_rating > 0 
            ? `<i class="fa-solid fa-star text-warning me-1"></i><strong>${stay.avg_rating}</strong> <span class="text-muted">(${stay.review_count})</span>`
            : `<i class="fa-solid fa-star text-muted me-1"></i><span class="text-muted">New</span>`;

        const isFavClass = stay.is_wishlisted ? 'active' : '';
        const heartIcon = stay.is_wishlisted ? 'fa-solid' : 'fa-regular';

        const cardHtml = `
            <div class="col-xl-6 col-lg-6 col-md-6 col-12 mb-4 stay-grid-item" data-listing-id="${stay.id}">
                <div class="stay-card h-100 position-relative">
                    <button type="button" class="wishlist-heart-btn ${isFavClass}" data-listing-id="${stay.id}" title="Save to wishlist">
                        <i class="${heartIcon} fa-heart"></i>
                    </button>

                    <a href="${stay.detail_url}" class="text-decoration-none text-dark">
                        <div class="stay-image-wrapper mb-2">
                            <span class="stay-badge-type">${stay.property_type}</span>
                            <img src="${stay.primary_image}" alt="${stay.title}" loading="lazy">
                        </div>

                        <div class="d-flex justify-content-between align-items-start mt-2">
                            <h6 class="fw-bold mb-0 text-truncate" style="max-width: 75%;" title="${stay.title}">
                                ${stay.title}
                            </h6>
                            <div class="small">
                                ${ratingHtml}
                            </div>
                        </div>

                        <div class="text-muted small mb-1">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i> ${stay.city}${stay.state ? ', ' + stay.state : ''}
                        </div>

                        <div class="text-muted small mb-2">
                            ${stay.max_guests} guests &bull; ${stay.bedrooms} bed(s) &bull; ${stay.bathrooms} bath(s)
                        </div>

                        <div class="d-flex align-items-baseline gap-1">
                            <span class="fw-bold fs-6 text-dark">${stay.price_formatted}</span>
                            <span class="text-muted small">night</span>
                        </div>
                    </a>
                </div>
            </div>
        `;
        container.append(cardHtml);
    });
}

// Update Leaflet Map Markers with custom price pills
function updateMapMarkers(listings) {
    if (!map || !markersLayer) return;

    markersLayer.clearLayers();
    const bounds = [];

    listings.forEach(stay => {
        if (!stay.latitude || !stay.longitude) return;

        const latLng = [stay.latitude, stay.longitude];
        bounds.push(latLng);

        // Custom HTML marker for Airbnb style price bubbles
        const priceIcon = L.divIcon({
            className: 'price-marker-wrapper',
            html: `<div class="price-marker-pill" data-id="${stay.id}">${stay.price_formatted}</div>`,
            iconSize: [80, 32],
            iconAnchor: [40, 16]
        });

        const marker = L.marker(latLng, { icon: priceIcon });

        // Popup content
        const popupContent = `
            <div style="width: 220px; font-family: sans-serif;">
                <img src="${stay.primary_image}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 8px;">
                <h6 style="margin: 0 0 4px; font-size: 14px; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${stay.title}</h6>
                <div style="color: #666; font-size: 12px; margin-bottom: 6px;">${stay.city} &bull; ${stay.property_type}</div>
                <div style="font-weight: bold; font-size: 14px; color: #222;">${stay.price_formatted} <span style="font-weight: normal; font-size: 12px; color: #777;">night</span></div>
                <a href="${stay.detail_url}" class="btn btn-sm btn-danger w-100 rounded-pill mt-2" style="font-size: 12px;">View Stay</a>
            </div>
        `;

        marker.bindPopup(popupContent, { maxWidth: 240 });
        markersLayer.addLayer(marker);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    }
}

