# StayHub — Airbnb-Style Vacation Rental Web Platform

A full-featured vacation rental platform inspired by **Airbnb**, built with **PHP 8**, **MySQL (PDO)**, **Bootstrap 5**, and **jQuery**. Designed for technical interviews and production portfolios, featuring clean architecture, double-booking prevention, an interactive Leaflet map, live AJAX filters, and Razorpay test payments.

---

## 🌟 Highlight Features

| Feature | Implementation Details |
|---|---|
| **Role-Based Auth** | Guest, Host, and Admin roles with secure bcrypt hashing (`password_hash`), session management, and CSRF token verification. |
| **Availability Calendar** | Airbnb-style date picker using Flatpickr + jQuery querying `api/get_booked_dates.php`. Already-booked dates are automatically disabled and grayed out. |
| **Double-Booking Prevention** | Strict SQL date-overlap validation: `WHERE (check_in < :check_out AND check_out > :check_in)` executed both during live calculation and directly before transaction commit. |
| **Live AJAX Search & Filters** | Search by city, dates, guest capacity, price range slider, property types (Villa, Cabin, Apartment, etc.), and multi-select amenities without full page reloads. |
| **Interactive Leaflet Maps** | 100% free OpenStreetMap integration via Leaflet.js with custom price bubble markers, popup cards, and an interactive draggable pin picker for hosts. |
| **Reviews & Ratings** | Guests who have completed a stay can submit verified 1–5 star reviews with comments, dynamically recalculating the listing's average rating. |
| **Wishlist / Favorites** | One-click AJAX heart toggling with instant visual feedback and a dynamic navbar badge counter. |
| **Razorpay Checkout** | Integrated test-mode checkout with `Checkout.js` modal and instant test payment simulation. |
| **Email Confirmation Receipts** | Detailed HTML email receipts with listing photo, itinerary, pricing breakdown, and host contact details. Logged to `storage/logs/mail_receipts.html` and ready for SMTP/PHPMailer. |
| **Dual Database Support** | Native support for standard MySQL (`127.0.0.1:3306`, DB: `airbnb_db`) with automatic portable SQLite fallback for zero-dependency local demos. |

---

## 🚀 Quick Start (Running Locally)

### Option 1: One-Click Windows Batch Script
Double-click `run_server.bat` in the project root:
```cmd
run_server.bat
```
This automatically runs database initialization and starts the PHP built-in server at **http://localhost:8000**.

### Option 2: Command Line (CLI)
From the project folder, run:
```bash
# 1. Initialize and seed database
C:\xampp\php\php.exe database/setup.php

# 2. Start the web server
C:\xampp\php\php.exe -S localhost:8000
```
Then open [http://localhost:8000](http://localhost:8000) in your web browser.

---

## 🔑 Demo Accounts (Pre-Seeded)

For testing and interviews, 1-click login buttons are available on the login page:

| Role | Email | Password | Permissions |
|---|---|---|---|
| **Guest** | `guest@stayhub.test` | `guest123` | Search stays, view photos, book with dates, wishlist stays, submit reviews |
| **Host** | `host@stayhub.test` | `host123` | Host dashboard, add stay with Leaflet pin picker, edit/delete stays, manage guest reservations |
| **Admin** | `admin@stayhub.test` | `admin123` | Approve/reject pending listings, manage users (promote/suspend), view all platform bookings |

---

## 📂 Project Architecture

```
airbnb_project/
├── .env                           # Environment credentials & API keys (ignored by git)
├── .env.example                   # Template for environment configuration
├── .gitignore                     # Git ignore rules for sensitive credentials & sessions
├── index.php                      # Homepage with search hero, destination cards & featured stays
├── listings.php                   # Explore stays with live AJAX filters & Leaflet map split view
├── listing_detail.php             # Property gallery, amenities, Leaflet map, reviews, booking widget
├── book.php                       # Double-booking SQL verification & Razorpay test checkout
├── booking_confirmation.php       # Reservation success screen with receipt breakdown
├── wishlist.php                   # Guest's saved favorite stays
├── my_bookings.php                # Guest booking history, cancellation & stay reviews
├── login.php                      # User login (includes 1-click demo buttons)
├── register.php                   # Account creation with Guest / Host role selector
├── logout.php                     # Safe session logout
├── profile.php                    # User profile & avatar management
│
├── host/
│   ├── dashboard.php              # Host earnings, listings overview, recent reservations
│   ├── listings.php               # Manage host's stays with status badges
│   ├── add_listing.php            # Add stay form with Leaflet map pin picker & image uploads
│   ├── edit_listing.php           # Edit details, update photos, set cover image
│   ├── delete_listing.php         # Safe deletion handler (blocks deletion if active bookings exist)
│   └── bookings.php               # Guest reservations on host's properties
│
├── admin/
│   ├── dashboard.php              # Global platform metrics, revenue, pending approvals
│   ├── listings.php               # Approve / reject pending listings, toggle featured
│   ├── users.php                  # Manage user roles & activate/suspend accounts
│   └── bookings.php               # Comprehensive overview of all platform bookings
│
├── api/
│   ├── search_listings.php        # AJAX search & filter endpoint returning JSON + coordinates
│   ├── get_booked_dates.php       # Returns booked date ranges for Flatpickr calendar blocking
│   ├── check_availability.php     # SQL overlap check & price breakdown computation
│   ├── toggle_wishlist.php        # AJAX favorite/unfavorite toggle
│   └── submit_review.php          # Review submission with completed-stay validation
│
├── config/
│   ├── config.php                 # Global app settings, Razorpay keys, mail config
│   └── database.php               # PDO singleton with MySQL default & SQLite auto-fallback
│
├── database/
│   ├── schema.sql                 # Pure MySQL schema (InnoDB, Foreign Keys, Indices)
│   ├── seed.sql                   # Realistic sample data (amenities, users, listings, bookings, reviews)
│   └── setup.php                  # Web and CLI installer that creates tables and seeds
│
├── includes/
│   ├── auth.php                   # Session security, role guards, CSRF tokens
│   ├── helpers.php                # Price formatting, nights calc, image uploads, star ratings
│   ├── mailer.php                 # HTML booking confirmation email generator & local receipt archive
│   ├── header.php                 # HTML head, Bootstrap 5, FontAwesome, Flatpickr & Leaflet CSS
│   ├── navbar.php                 # Dynamic navigation bar tailored to user role
│   └── footer.php                 # Shared footer, scripts, toast notification container
│
├── assets/
│   ├── css/
│   │   └── style.css              # Custom Airbnb-style design system & responsive layout
│   └── js/
│       ├── main.js                # Toast notifications & AJAX wishlist toggle
│       ├── search.js              # Live AJAX search, slider debounce & Leaflet map markers
│       └── booking.js             # Flatpickr datepicker blocking & Razorpay checkout
│
├── uploads/listings/              # Uploaded property images
├── storage/
│   ├── logs/mail_receipts.html    # Archived HTML booking receipts for inspection
│   └── sessions/                  # Local PHP session files
│
└── run_server.bat                 # One-click Windows starter script
```

---

## 🛡️ Technical Deep-Dive for Interviewers

### 1. Double-Booking Prevention (SQL Overlap Check)
Two date intervals $[A, B]$ and $[C, D]$ overlap if and only if:
$$\text{check\_in} < D \quad \text{AND} \quad \text{check\_out} > C$$

StayHub enforces this in SQL:
```sql
SELECT COUNT(*) FROM bookings 
WHERE listing_id = :listing_id 
  AND booking_status IN ('confirmed', 'pending')
  AND (check_in < :check_out AND check_out > :check_in);
```
This is verified:
1. **Client-side**: Date ranges are disabled on Flatpickr.
2. **AJAX pre-check**: `api/check_availability.php` calculates pricing only if available.
3. **Server-side commit**: In `book.php`, right before inserting the booking record.

### 2. Live AJAX Search & Leaflet Sync
- Typing in the city input is debounced by 350ms to minimize network traffic.
- Moving the price slider dynamically updates the DOM label and triggers search.
- When results return from `api/search_listings.php`, `assets/js/search.js`:
  1. Re-renders the card grid.
  2. Clears existing map markers and instantiates custom HTML price pills (`₹14,500`).
  3. Automatically zooms and pans the Leaflet map bounds (`map.fitBounds(bounds)`).

### 3. Payment Flow & Fallback Mode
- Uses Razorpay `Checkout.js` modal configured with `rzp_test_...` credentials.
- If live test keys are not configured or internet access is limited, the modal seamlessly prompts for test payment simulation, generating a test payment ID `pay_sim_...` and proceeding to booking confirmation.

### 4. Booking Email Confirmation
- `Mailer::sendBookingConfirmation()` formats an email receipt including check-in instructions, pricing breakdown, and host contact details.
- To eliminate SMTP dependency while testing, every confirmation receipt is saved to:
  `storage/logs/mail_receipts.html`
  You can open this file in any web browser to inspect the rendered email receipt.

---

## 📄 License
Open source for educational and portfolio demonstration purposes.

