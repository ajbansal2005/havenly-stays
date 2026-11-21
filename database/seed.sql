-- StayHub Sample Seed Data

-- Insert Amenities
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
(12, 'Mountain View', 'fa-solid fa-mountain', 'Views & Scenery')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Insert Users
-- Passwords:
-- Admin: admin123
-- Host: host123
-- Guest: guest123
INSERT INTO users (id, name, email, password, phone, role, avatar, bio, status) VALUES
(1, 'Admin StayHub', 'admin@stayhub.test', '$2y$10$tuAafwVfOdvmd6S8DH155OC4rRiYiTrxb3D3wLulCwX0rGgAxv6d2', '+91 98765 43210', 'admin', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80', 'Platform Administrator at StayHub.', 'active'),
(2, 'Rohit Sharma (Superhost)', 'host@stayhub.test', '$2y$10$iagrCdzJB8pMyeb5YYX1hO0hBaehmoBECwOTLav/fZKnQG39jQmCe', '+91 98111 22334', 'host', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80', 'Passionate traveler and hospitality enthusiast. Hosting modern luxury villas and cosy city lofts.', 'active'),
(3, 'Priya Kapoor (Host)', 'host2@stayhub.test', '$2y$10$iagrCdzJB8pMyeb5YYX1hO0hBaehmoBECwOTLav/fZKnQG39jQmCe', '+91 98222 33445', 'host', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&auto=format&fit=crop&q=80', 'Architect & interior designer creating peaceful mountain escapes and boutique stays.', 'active'),
(4, 'Ajay Verma (Guest)', 'guest@stayhub.test', '$2y$10$l0hQPVd5sna8tPdZj4xcAOnH2iaUQrO1/xXovDD6TBzBzTtz0h7pC', '+91 98333 44556', 'guest', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&auto=format&fit=crop&q=80', 'Tech enthusiast, digital nomad and wanderer loving unique stays.', 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Insert Sample Listings
INSERT INTO listings (id, host_id, title, description, property_type, address, city, state, country, latitude, longitude, price_per_night, cleaning_fee, service_fee, max_guests, bedrooms, beds, bathrooms, status, featured) VALUES
(1, 2, 'Luxury Sunset Beach Villa with Infinity Pool', 
'Step into paradise at this beachfront sanctuary overlooking the Arabian Sea in North Goa. Enjoy private beach access, a magnificent infinity pool, spacious sun decks, and bespoke concierge services. Ideal for families and luxury getaways.', 
'Villa', 'Vagator Beach Road, Anjuna', 'Goa', 'Goa', 'India', 15.5996000, 73.7439000, 14500.00, 1200.00, 800.00, 8, 4, 4, 4, 'approved', 1),

(2, 2, 'Skyline Penthouse with Private Terrace & Jacuzzi', 
'Perched high in Bandra West with panoramic views of the Mumbai skyline and sea link. Features double-height ceilings, designer Italian furniture, ultra-fast 500Mbps Wi-Fi, and a rooftop cocktail terrace.', 
'Apartment', 'Pali Hill, Bandra West', 'Mumbai', 'Maharashtra', 'India', 19.0600000, 72.8258000, 9200.00, 900.00, 600.00, 4, 2, 2, 2, 'approved', 1),

(3, 3, 'Pine Forest Glass Cabin & Stargazing Loft', 
'A stunning Scandinavian-inspired A-frame cabin nestled amid cedar and pine forests of Old Manali. Floor-to-ceiling glass windows, wood-burning fireplace, heated floors, and unobstructed views of snow-capped Himalayan peaks.', 
'Cabin', 'Club House Road, Old Manali', 'Manali', 'Himachal Pradesh', 'India', 32.2530000, 77.1750000, 5800.00, 500.00, 400.00, 3, 1, 2, 1, 'approved', 1),

(4, 3, 'Royal Heritage Haveli Suite with Courtyard', 
'Experience royal Rajputana elegance inside the historic Pink City. Handcrafted marble jalis, antique brass fittings, private leafy courtyard, and traditional Rajasthani breakfast included every morning.', 
'Heritage', 'Johari Bazaar, Pink City', 'Jaipur', 'Rajasthan', 'India', 26.9196000, 75.8267000, 6400.00, 600.00, 450.00, 4, 2, 2, 2, 'approved', 1),

(5, 2, 'Minimalist Tech Loft near Indiranagar 100ft Rd', 
'Modern studio loft tailored for creative professionals and digital nomads. Ergonomic Herman Miller seating, dual 4K monitors, soundproof acoustic walls, and walking distance to Bengaluru''s top craft breweries.', 
'Loft', '12th Main Rd, Indiranagar', 'Bengaluru', 'Karnataka', 'India', 12.9719000, 77.6412000, 4200.00, 400.00, 300.00, 2, 1, 1, 1, 'approved', 0),

(6, 3, 'Tranquil Backwater Wooden Cottage with Private Canoe', 
'Immerse in the serene backwaters of Alleppey. Features teakwood construction, private canal dock, complimentary wooden canoe, and delicious home-cooked Kerala cuisine served under swaying coconut palms.', 
'Cottage', 'Punnamada Lakefront', 'Alleppey', 'Kerala', 'India', 9.5010000, 76.3388000, 5100.00, 500.00, 350.00, 4, 2, 2, 2, 'approved', 1)
ON DUPLICATE KEY UPDATE title=VALUES(title);

-- Insert Listing Images
INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES
-- Listing 1 (Goa Villa)
(1, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=1200&auto=format&fit=crop&q=80', 1),
(1, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&auto=format&fit=crop&q=80', 0),
(1, 'https://images.unsplash.com/photo-1613977257363-707ba9348227?w=1200&auto=format&fit=crop&q=80', 0),
(1, 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=1200&auto=format&fit=crop&q=80', 0),

-- Listing 2 (Mumbai Penthouse)
(2, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&auto=format&fit=crop&q=80', 1),
(2, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=1200&auto=format&fit=crop&q=80', 0),
(2, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&auto=format&fit=crop&q=80', 0),
(2, 'https://images.unsplash.com/photo-1502005229762-ee1b2b8100ff?w=1200&auto=format&fit=crop&q=80', 0),

-- Listing 3 (Manali Cabin)
(3, 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?w=1200&auto=format&fit=crop&q=80', 1),
(3, 'https://images.unsplash.com/photo-1510798831971-661eb04b3739?w=1200&auto=format&fit=crop&q=80', 0),
(3, 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=80', 0),
(3, 'https://images.unsplash.com/photo-1449844908441-8829872d2607?w=1200&auto=format&fit=crop&q=80', 0),

-- Listing 4 (Jaipur Haveli)
(4, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200&auto=format&fit=crop&q=80', 1),
(4, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&auto=format&fit=crop&q=80', 0),
(4, 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1200&auto=format&fit=crop&q=80', 0),

-- Listing 5 (Bangalore Loft)
(5, 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&auto=format&fit=crop&q=80', 1),
(5, 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&auto=format&fit=crop&q=80', 0),
(5, 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&auto=format&fit=crop&q=80', 0),

-- Listing 6 (Kerala Cottage)
(6, 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=1200&auto=format&fit=crop&q=80', 1),
(6, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&auto=format&fit=crop&q=80', 0),
(6, 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&auto=format&fit=crop&q=80', 0);

-- Insert Listing Amenities
INSERT INTO listing_amenities (listing_id, amenity_id) VALUES
-- Goa Villa
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 10), (1, 11),
-- Mumbai Penthouse
(2, 1), (2, 2), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 11),
-- Manali Cabin
(3, 1), (3, 4), (3, 5), (3, 7), (3, 10), (3, 12),
-- Jaipur Haveli
(4, 1), (4, 2), (4, 4), (4, 5), (4, 6),
-- Bangalore Loft
(5, 1), (5, 2), (5, 4), (5, 5), (5, 6), (5, 7), (5, 8),
-- Kerala Cottage
(6, 1), (6, 4), (6, 5), (6, 10), (6, 11);

-- Insert Sample Bookings (including dates to demonstrate blocked availability on calendar!)
INSERT INTO bookings (id, booking_code, listing_id, guest_id, check_in, check_out, guests_count, total_nights, price_per_night, cleaning_fee, service_fee, total_price, payment_status, payment_method, razorpay_order_id, razorpay_payment_id, booking_status) VALUES
(1, 'BK-STAY-882190', 1, 4, '2026-11-20', '2026-11-25', 4, 5, 14500.00, 1200.00, 800.00, 74500.00, 'paid', 'razorpay', 'order_demo_1001', 'pay_demo_9901', 'confirmed'),
(2, 'BK-STAY-771234', 1, 4, '2026-12-01', '2026-12-05', 2, 4, 14500.00, 1200.00, 800.00, 60000.00, 'paid', 'razorpay', 'order_demo_1002', 'pay_demo_9902', 'confirmed'),
(3, 'BK-STAY-663451', 3, 4, '2026-11-15', '2026-11-18', 2, 3, 5800.00, 500.00, 400.00, 18300.00, 'paid', 'razorpay', 'order_demo_1003', 'pay_demo_9903', 'confirmed'),
(4, 'BK-STAY-554129', 2, 4, '2026-10-10', '2026-10-14', 2, 4, 9200.00, 900.00, 600.00, 38300.00, 'paid', 'razorpay', 'order_demo_1004', 'pay_demo_9904', 'completed')
ON DUPLICATE KEY UPDATE booking_code=VALUES(booking_code);

-- Insert Reviews
INSERT INTO reviews (id, booking_id, listing_id, guest_id, rating, comment, created_at) VALUES
(1, 4, 2, 4, 5, 'Absolutely breathtaking views of Mumbai! The penthouse was impeccably clean, and the terrace jacuzzi during sunset is an unforgettable experience. Host Rohit was super communicative and helpful.', '2026-10-15 10:30:00'),
(2, NULL, 1, 4, 5, 'The sunset villa in Goa blew away our expectations. Huge pool, direct beach pathway, and fantastic amenities. We will definitely be coming back!', '2026-09-12 14:20:00'),
(3, NULL, 3, 4, 5, 'Cozy pine cabin with the best mountain views in Manali. Sitting near the fireplace with fresh coffee was pure bliss.', '2026-08-05 18:00:00')
ON DUPLICATE KEY UPDATE rating=VALUES(rating);

-- Insert Sample Wishlist
INSERT INTO wishlists (user_id, listing_id) VALUES
(4, 1),
(4, 3)
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id);

