-- =============================================================================
--  APARTMENT RENTAL MARKETPLACE  —  rental_marketplace.sql
-- =============================================================================
--  Plug-and-play schema + seed data for phpMyAdmin (XAMPP / MariaDB 10.x).
--
--  HOW TO IMPORT (phpMyAdmin)
--  --------------------------
--   1. Start Apache + MySQL in the XAMPP Control Panel.
--   2. Open  http://localhost/phpmyadmin
--   3. Click the "Import" tab (do NOT pre-create a database — this script
--      creates `rental_marketplace` itself) and choose this file.
--   4. Press "Import". 8 sample listings are seeded so the tenant feed is
--      never empty on first boot.
--
--  Alternatively, from the terminal:
--     mysql -u root -p < rental_marketplace.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';          -- Africa/Addis_Ababa
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------------
-- 1. Database
-- ---------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `rental_marketplace`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `rental_marketplace`;

-- ---------------------------------------------------------------------------
-- 2. Listings table
--    Every published apartment unit lives here. No users/accounts table is
--    needed because the marketplace is anonymous by design.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `listings`;

CREATE TABLE `listings` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `building_name`  VARCHAR(100)  NOT NULL,
    `location`       VARCHAR(50)   NOT NULL,
    `unit_type`      VARCHAR(50)   NOT NULL,
    `monthly_rent`   DECIMAL(10,2) NOT NULL,
    `description`    TEXT          NULL,
    `landlord_name`  VARCHAR(100)  NOT NULL,
    `landlord_phone` VARCHAR(20)   NOT NULL,
    `status`         ENUM('Active','Rented') NOT NULL DEFAULT 'Active',
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Indexes chosen for the exact query shapes used by explore.php:
    KEY `idx_location`        (`location`),                 -- toolbar dropdown filter
    KEY `idx_unit_type`       (`unit_type`),                -- toolbar dropdown filter
    KEY `idx_status_created`  (`status`, `created_at`),      -- feed: Active, newest first
    KEY `idx_status_location` (`status`, `location`),        -- feed filtered by neighbourhood
    KEY `idx_monthly_rent`    (`monthly_rent`)               -- optional price sorting
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Seed data — 8 realistic listings spread over Bole, Kazanchis, Old
--    Airport, CMC, Sarbet, Lebu and Ayat. All phone numbers use the
--    +2519XXXXXXXX mobile format.
-- ---------------------------------------------------------------------------
INSERT INTO `listings`
    (`building_name`, `location`, `unit_type`, `monthly_rent`, `description`,
     `landlord_name`, `landlord_phone`, `status`, `created_at`)
VALUES
('Bole Skyline Residence', 'Bole', '2-Bedroom', 28000.00,
 'Bright 2-bedroom apartment on the 6th floor of Bole Skyline Residence, five minutes walk from Bole Medhanealem Church. Open-plan living area, fitted kitchen with oven and hob, 24/7 backup generator, secure basement parking, lift access and a shared rooftop terrace with city views. Ideal for a small family or two professionals.',
 'Yohannes Bekele', '+251911234567', 'Active', NOW() - INTERVAL 2 HOUR),

('Kazanchis Business Tower Flats', 'Kazanchis', '1-Bedroom', 17500.00,
 'Neat one-bedroom flat in the Kazanchis Business Tower, directly behind the Kazanchis business district and a short walk to the African Union headquarters. Comes with a furnished kitchen, water tank and pump, in-building security guard and a balcony overlooking the Kazanchis roundabout. Rent excludes utility bills.',
 'Hiwot Tesfaye', '+251922345678', 'Active', NOW() - INTERVAL 9 HOUR),

('Sarbet Green Court', 'Sarbet', 'Studio', 11000.00,
 'Compact and quiet studio in Sarbet Green Court, perfect for a single tenant or a student. Includes a private bathroom, kitchenette, tiled flooring, mosquito screens on every window and a shared garden courtyard. Located two minutes from Sarbet Taxi Terminal with easy access to the ring road.',
 'Dawit Alemu', '+251933456789', 'Active', NOW() - INTERVAL 1 DAY),

('Old Airport Family Home', 'Old Airport', '3-Bedroom', 42000.00,
 'Spacious 3-bedroom family apartment near the Old Airport, close to the British Embassy and the French Lycee. Two bathrooms, a large living and dining room, a servant room, a private water reserve tank and parking space for two cars inside a walled compound. Long-term tenants only.',
 'Meseret Girma', '+251944567890', 'Active', NOW() - INTERVAL 2 DAY),

('CMC Summit Heights', 'CMC', '2-Bedroom', 24000.00,
 'Modern 2-bedroom unit in the CMC Summit Heights compound, a gated community with 24-hour guards, a children''s playground and a paved internal road. Master bedroom with en-suite bathroom, ceramic tiles throughout, built-in wardrobes and a dedicated parking slot. Close to CMC Michael Church and Fresh Corner.',
 'Abel Mekonnen', '+251955678901', 'Active', NOW() - INTERVAL 3 DAY),

('Sarbet Panorama Penthouse', 'Sarbet', 'Penthouse', 55000.00,
 'Top-floor penthouse in Sarbet Panorama with a wraparound balcony and unobstructed views towards the Entoto hills. Three bedrooms, three bathrooms, a private study, a fully fitted open kitchen and a separate laundry room. Two reserved parking spaces and a private water tank with booster pump included.',
 'Selamawit Tadesse', '+251966789012', 'Active', NOW() - INTERVAL 4 DAY),

('Lebu Addis View Apartments', 'Lebu', '1-Bedroom', 14500.00,
 'Well-kept one-bedroom apartment in Lebu Addis View, just off the Jimma Road and close to Lebu St. Michael Church. Freshly painted with new sanitary fittings, a small balcony, a shared laundry area and one open parking space. Water and generator service charges included in the rent.',
 'Tewodros Haile', '+251977890123', 'Active', NOW() - INTERVAL 5 DAY),

('Ayat Heran Village Unit', 'Ayat', '2-Bedroom', 16500.00,
 'Affordable two-bedroom apartment in Ayat Heran Village near the Ayat roundabout and the new Bulbula light-rail corridor. Semi-furnished with a sofa set and dining table, tiled floors, a private water meter and a communal parking lot. Excellent value for a couple or a small family starting out.',
 'Rahel Assefa', '+251988901234', 'Active', NOW() - INTERVAL 6 DAY);

-- ---------------------------------------------------------------------------
-- 4. Sanity check — list every seeded listing and the feed counts.
-- ---------------------------------------------------------------------------
SELECT `id`, `building_name`, `location`, `unit_type`, `monthly_rent`, `status`
FROM `listings`
ORDER BY `created_at` DESC;
