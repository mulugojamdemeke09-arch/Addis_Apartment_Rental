# Addis Rentals — Apartment Rental Marketplace

A complete two-sided apartment rental marketplace for **Addis Ababa**, built with
**HTML5, CSS3, vanilla JavaScript and server-side PHP** on **XAMPP** (Apache + PHP 8 + MySQL/MariaDB).
No frameworks, no build step, no accounts.

- **Lessor gate** — publish an apartment unit in under a minute (`post_apartment.php`).
- **Tenant gate** — browse the live feed with district photography and reveal the owner's phone number (`explore.php`).
- **Ethiopian identity** — Addis Ababa neighbourhood content, Amharic bilingual labels, birr pricing, the Ethiopian tricolour accent, and a custom brand mark.

---

## 1. Project structure

```
rental-marketplace/
├── assets/
│   ├── logo-mark.svg             Brand mark used in the header, footer and favicon
│   ├── logo.svg                  Full horizontal lockup (mark + wordmark)
│   ├── icons.svg                 Inline SVG icon sprite (17 glyphs, currentColor)
│   ├── hero-addis-ababa.jpg      Gateway hero photograph
│   ├── neighbourhood-bole.jpg    District photography for Bole
│   ├── neighbourhood-kazanchis.jpg
│   ├── neighbourhood-sarbet.jpg
│   ├── neighbourhood-cmc.jpg
│   └── neighbourhood-addis.jpg   Generic Addis Ababa street (also the fallback)
├── config/
│   └── db.php                    PDO singleton + HMAC CSRF + validation helpers + district data
├── css/
│   └── styles.css                Unified light-theme stylesheet
├── js/
│   └── marketplace.js            Instant filtering, contact modal, form UX, mobile dialer
├── actions/
│   └── submit_post.php           Validates + INSERTs a listing via PDO prepared statement
├── index.php                     Central splash gateway (hero, live figures, two role pathways)
├── post_apartment.php            Landlord / lessor listing form
├── explore.php                   Tenant feed + filtering toolbar + contact modal
├── success.php                   Post-submission confirmation view
├── rental_marketplace.sql        Database schema + 8 seeded listings (import into phpMyAdmin)
└── README.md                     This document
```

---

## 2. Requirements

| Component | Version |
|---|---|
| XAMPP | 8.0 or newer (PHP 8.0+, MySQL/MariaDB 10.x) |
| PHP extensions | `pdo_mysql` (enabled by default in XAMPP) |
| Browser | Any modern browser (Chrome, Edge, Firefox, Safari) |

---

## 3. Installation — step by step

### Step 1 — Copy the project into `htdocs`

Place the `rental-marketplace` folder directly inside XAMPP's web root:

```bash
# macOS
cp -R rental-marketplace /Applications/XAMPP/xamppfiles/htdocs/

# Windows (PowerShell)
Copy-Item -Recurse rental-marketplace C:\xampp\htdocs\

# Linux
cp -R rental-marketplace /opt/lampp/htdocs/
```

The result must be: `xampp/htdocs/rental-marketplace/index.php`

### Step 2 — Start the servers

Open the **XAMPP Control Panel** and press **Start** next to:

- **Apache** (web server)
- **MySQL** (database server)

Both must show a green "Running" badge before continuing.

### Step 3 — Create the database and tables

1. Open <http://localhost/phpmyadmin> in your browser.
2. Click the **Import** tab. *Do not create a database first* — the script creates `rental_marketplace` itself.
3. Click **Choose File** and select `rental_marketplace.sql` from the project folder.
4. Press **Import** (scroll to the bottom of the page).

You should see *"Import has been successfully finished"*, and a new `rental_marketplace`
database containing the `listings` table with **8 seeded apartments** across Bole, Kazanchis,
Old Airport, CMC, Sarbet, Lebu and Ayat.

**Terminal alternative:**

```bash
# macOS / Linux
/Applications/XAMPP/xamppfiles/bin/mysql -u root < /path/to/rental_marketplace.sql

# Windows
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\rental-marketplace\rental_marketplace.sql
```

### Step 4 — Check the database credentials

`config/db.php` ships with the default XAMPP values, which work out of the box:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'rental_marketplace');
define('DB_USER', 'root');
define('DB_PASS', '');   // empty by default in XAMPP
```

If you have set a MySQL root password, put it in `DB_PASS`.
While you are in that file, replace `CSRF_SECRET` with your own random string.

### Step 5 — Open the marketplace

<http://localhost/rental-marketplace/>

If you rename the project folder, nothing breaks: the base path is derived from the folder
name at runtime (`APP_BASE` in `config/db.php`).

---

## 4. Visual assets and branding

| Asset | Used for | How to replace |
|---|---|---|
| `assets/logo-mark.svg` | Header, footer, browser tab icon | Drop in your own square SVG and keep the filename |
| `assets/logo.svg` | Standalone lockup for print, decks, social | — |
| `assets/icons.svg` | Every UI glyph (`#pin`, `#phone`, `#bed`, …) | Add a `<symbol id="…">` and reference it with `<use href="assets/icons.svg#id">` |
| `assets/hero-addis-ababa.jpg` | Gateway hero band (1600×900) | Replace with a photograph of the same aspect ratio |
| `assets/neighbourhood-*.jpg` | District cards and listing thumbnails (900×675) | One JPEG per district, then update the `image` key in `NEIGHBOURHOODS` |

**District content lives in one place.** `NEIGHBOURHOODS` in `config/db.php` holds each district's
Amharic name, landmark line, blurb, photograph and (through `LOCATIONS`) the validation whitelist.
Edit that array and the dropdowns, filters, gateway cards and listing thumbnails all follow.

**Brand colours** are CSS variables at the top of `css/styles.css`:

| Token | Value | Role |
|---|---|---|
| `--canvas` | `#f4f7f6` | Ultra-light cool page canvas |
| `--surface` | `#ffffff` | White cards and panels |
| `--slate` | `#3b82f6` | Structural slate blue — borders, navigation |
| `--ice` | `#0284c7` | Ice blue — headings and accents |
| `--emerald` | `#10b981` | Call-to-action green — buttons, success tags |

The Ethiopian tricolour (`#078930`, `#fcdd09`, `#da121a`) is used only as a thin accent on the
site header, the footer and the logo.

---

## 5. Testing walkthrough

### A. Tenant flow (read path)

1. The gateway shows the Addis Ababa hero, live figures (apartments listed, districts, average rent, lowest rent)
   and the four photographed districts.
2. Click **Browse apartments** → `explore.php` lists all active units with district photography.
3. Type `generator` into the search box — the grid filters instantly, with no page reload.
4. Set **Neighbourhood = Bole** and **Room configuration = 2-Bedroom** → the counter reads
   "1 of 8 apartments match your filters" and removable filter chips appear.
5. Change **Sort by** to *Rent: low to high* → the cards re-order in place.
6. Press **Reset** → every filter clears and the full feed returns.
7. Click **View Contact Details** on any card → a white modal rises over a blurred backdrop with the
   district photograph on top and the owner instruction:
   > To rent this unit, please call **Yohannes Bekele** directly at **+251911234567**.
8. Press `Esc`, click the backdrop, or press the **✕** button to close. Focus returns to the card you came from.
9. Press **Copy number** → the phone number is copied to your clipboard.
10. Resize the window below 780 px (or open the site on a phone) → the button becomes
    **"Tap to call now"**, the dialer hint appears, and tapping launches the phone app through `tel:`.

### B. Landlord flow (write path)

1. Click **List your apartment** on the gateway or in the navigation.
2. Submit the empty form → inline errors appear under each required field and the page does not reload.
3. Fill in the fields, e.g.:

   | Field | Value |
   |---|---|
   | Building / apartment name | CMC Garden Court |
   | Neighbourhood | CMC |
   | Room configuration | 2-Bedroom |
   | Monthly rent | 26000 |
   | Short description | Bright second-floor unit with a balcony, water tank and one parking slot. |
   | Landlord name | Tigist Alemu |
   | Mobile phone number | 0911223344 |

4. Press **Publish listing** → `success.php` confirms the post and prints the receipt: reference,
   building, neighbourhood (with its Amharic name), landmarks, configuration, rent, landlord
   and contact number.
5. Click **See it in the listings** → the new card is the first in the grid, with the CMC photograph.
6. Optionally confirm the row in MySQL:

```sql
SELECT id, building_name, location, unit_type, monthly_rent, landlord_name
FROM rental_marketplace.listings
ORDER BY id DESC;
```

### C. Behavioural / security checks

| Check | Expected result |
|---|---|
| No-JavaScript browsing | Filters still work — the toolbar is a GET form that re-queries MySQL |
| Empty filter combination | Friendly "No apartments match those filters" panel |
| Missing database | A styled "database unavailable" screen with guidance (no stack trace) |
| Direct visit to `actions/submit_post.php` | Redirected back to the form, nothing inserted |
| Wrong/expired CSRF token | Rejected: "Your session form token expired" |
| `?location=<script>` tampering | Ignored — values are whitelisted against `LOCATIONS` / `UNIT_TYPES` |
| More than 5 posts in 10 minutes | Soft throttle: "You have published several listings in a short time" |
| Rented unit | Set `status` to `Rented` in phpMyAdmin → the row leaves the active feed |

---

## 6. Database reference

Table `listings`:

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `building_name` | VARCHAR(100) | Building or apartment name |
| `location` | VARCHAR(50) | Indexed — one of the seven Addis Ababa districts |
| `unit_type` | VARCHAR(50) | Indexed — Studio / 1-Bedroom / 2-Bedroom / 3-Bedroom / Penthouse |
| `monthly_rent` | DECIMAL(10,2) | Rent in ETB, indexed for price sorting |
| `description` | TEXT NULL | Free-text blurb |
| `landlord_name` | VARCHAR(100) | Contact person |
| `landlord_phone` | VARCHAR(20) | Normalised to `+2519XXXXXXXX` / `+2517XXXXXXXX` |
| `status` | ENUM('Active','Rented') | Default `Active`; only Active rows appear in the feed |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Drives "Newest first" |

Indexes: `idx_location`, `idx_unit_type`, `idx_status_created`, `idx_status_location`, `idx_monthly_rent`.

The seeded districts and their landmark lines are defined in `NEIGHBOURHOODS` (`config/db.php`):
Bole · Kazanchis · Old Airport · CMC · Sarbet · Lebu · Ayat.

---

## 7. Customisation

| What | Where |
|---|---|
| District names, Amharic names, landmarks, blurbs, photos | `NEIGHBOURHOODS` in `config/db.php` |
| Room configurations | `UNIT_TYPES` in `config/db.php` |
| Brand name and tagline | `APP_NAME` / `APP_TAGLINE` in `config/db.php` |
| Brand colours | `:root` tokens in `css/styles.css` |
| Hero headline and gate copy | The hero band and `.gate-card` blocks in `index.php` |
| Description length limit | `DESC_MAX_LENGTH` in `actions/submit_post.php` + `maxlength` on the textarea |
| Anti-spam throttle | `THROTTLE_WINDOW` / `THROTTLE_MAX_POST` in `actions/submit_post.php` |

---

## 8. Security implementation

- **SQL injection** — every query runs through PDO prepared statements with bound parameters;
  `PDO::ATTR_EMULATE_PREPARES` is disabled so MySQL performs real server-side prepares.
  `ORDER BY` is chosen from a fixed internal map, never built from user input.
- **XSS** — all database and user output passes through `Security::e()` (`htmlspecialchars`,
  `ENT_QUOTES`); the modal is hydrated with `textContent`/`createElement`, never `innerHTML`.
- **CSRF** — stateless HMAC-SHA256 tokens (`expiry.signature`) verified with `hash_equals()`,
  2-hour lifetime; no token table is required.
- **Content-Security-Policy** — `script-src 'self'; style-src 'self'` with **zero** inline scripts,
  inline styles and `onclick=` attributes anywhere in the project.
- **Input whitelisting** — district and unit type must match the server-side enums; phone numbers
  are normalised and validated; rent must be a sane positive number.
- **Abuse control** — honeypot field plus a per-session posting throttle.
- **Session hardening** — `HttpOnly`, `SameSite=Lax` cookies; sessions are used for flash
  messages and form repopulation only, never for authentication.

---

## 9. Troubleshooting

| Symptom | Fix |
|---|---|
| "Object not found!" | The folder is not in `htdocs`, or Apache is not running. |
| "database unavailable" screen | MySQL is stopped, or the SQL file has not been imported. |
| "Access denied for user 'root'" | Set your MySQL password in `DB_PASS` (`config/db.php`). |
| "Unknown database 'rental_marketplace'" | Import `rental_marketplace.sql` through phpMyAdmin. |
| Port 80 already in use | XAMPP → Config → `httpd.conf` → change `Listen 80` to `Listen 8080`, then use `http://localhost:8080/rental-marketplace/`. |
| Page loads without styles | Verify `css/styles.css` and `js/marketplace.js` exist and Apache has read access. |
| Icons render as blank boxes | The browser is blocking `assets/icons.svg`; confirm the file exists and is served as `image/svg+xml`. |
| District photos are missing | Confirm the `assets/neighbourhood-*.jpg` files were extracted alongside the PHP files. |
| Session form token expired | Reload `post_apartment.php` to mint a fresh token, then submit again. |
| Phone number not clickable on desktop | `tel:` links need a configured dialer app; use the **Copy number** button instead. |
# Apartment_Rental_management
# Apartment_Rental_management
