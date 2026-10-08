<?php
/**
 * =============================================================================
 *  ADDIS RENTALS  —  post_apartment.php  (LANDLORD / LESSOR GATE)
 * =============================================================================
 *  The landlord post creation board. Any visitor can publish an apartment unit
 *  here — there is no account, no login and no approval step.
 *
 *  The form posts to actions/submit_post.php, which performs CSRF
 *  verification, server-side validation and the PDO prepared INSERT.
 *
 *  Fields captured
 *    • Building / apartment name      (required, text)
 *    • Location dropdown              (required, Addis Ababa districts)
 *    • Room configuration dropdown    (required, whitelist from config/db.php)
 *    • Monthly rent in ETB            (required, numeric)
 *    • Short description              (optional, max 700 chars)
 *    • Landlord name                  (required)
 *    • Landlord mobile phone          (required, Ethiopian mobile format)
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$flash = flash_pull();
$errors = (array) ($_SESSION['old']['errors'] ?? []);

/* Error highlighting is one-shot: clear it so a refresh renders a clean form. */
unset($_SESSION['old']['errors']);

/* Live listing counts per district, to guide landlords on where demand sits. */
$counts = array_fill_keys(LOCATIONS, 0);
try {
    foreach (Database::instance()->fetchAll(
        'SELECT location, COUNT(*) AS total
           FROM listings
          WHERE status = :status
          GROUP BY location',
        [':status' => 'Active']
    ) as $bucket) {
        if (isset($counts[$bucket['location']])) {
            $counts[$bucket['location']] = (int) $bucket['total'];
        }
    }
} catch (Throwable $e) {
    error_log('[addis-rentals] district counts failed: ' . $e->getMessage());
}

$currentPage = 'post';

/* Formats a remembered value for a field. */
$val = static function (string $key): string {
    return Security::e(old($key));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Publish your apartment on Addis Rentals — describe the unit, set the monthly rent in birr and reach tenants across Addis Ababa.">
    <title>List your apartment · <?= Security::e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Security::e(url('assets/logo-mark.svg')) ?>">
    <link rel="stylesheet" href="<?= Security::e(url('css/styles.css')) ?>">
</head>
<body class="page">

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= Security::e(url('index.php')) ?>">
            <img class="brand__logo" src="<?= Security::e(url('assets/logo-mark.svg')) ?>" alt="" width="42" height="42">
            <span class="brand__text">
                <?= Security::e(APP_NAME) ?>
                <small>አዲስ አበባ · Addis Ababa</small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a class="site-nav__link" href="<?= Security::e(url('index.php')) ?>">Home</a>
            <a class="site-nav__link" href="<?= Security::e(url('explore.php')) ?>">Apartments for rent</a>
            <a class="site-nav__link site-nav__link--cta" href="<?= Security::e(url('post_apartment.php')) ?>">List your apartment</a>
        </nav>
    </div>
</header>

<main class="page__main">
    <div class="container container--narrow">

        <?php if ($flash !== null): ?>
            <div class="flash flash--<?= Security::e($flash['type']) ?>" role="alert">
                <span><?= Security::e($flash['message']) ?></span>
                <button type="button" class="btn btn--ghost btn--sm" data-dismiss-flash aria-label="Dismiss message">Dismiss</button>
            </div>
        <?php endif; ?>

        <div class="section-head">
            <span class="section-head__eyebrow">
                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#building')) ?>"></use></svg>
                Lessor gate
            </span>
            <h1>Publish your apartment</h1>
            <p class="section-head__lead">
                Describe the unit once and it joins the Addis Rentals feed, where tenants search every day.
            </p>
            <span class="section-head__am">አፓርታማዎን ለኪራይ ያስተዋውቁ</span>
        </div>

        <section class="card" aria-labelledby="post-form-title">
            <div class="form-head">
                <div>
                    <h2 id="post-form-title" class="form-head__title">Unit details</h2>
                    <p class="form-head__subtitle">Fields marked <span class="field__required">*</span> are required. Your listing goes live as soon as you publish it.</p>
                </div>
                <span class="tag tag--status">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#check')) ?>"></use></svg>
                    Addis Ababa · prices in ETB
                </span>
            </div>

            <!-- Where tenants are actually looking right now (live table data) -->
            <div class="hood-hint">
                <p class="hood-hint__title">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#compass')) ?>"></use></svg>
                    Apartments currently listed near you
                </p>
                <ul class="hood-hint__list">
                    <?php foreach (LOCATIONS as $district): ?>
                        <li>
                            <?= Security::e($district) ?>
                            <span class="am"><?= Security::e(neighbourhood_am($district)) ?></span>
                            <strong><?= Security::e((string) $counts[$district]) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="form-note" role="note">
                <svg class="icon icon--md" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#sparkle')) ?>"></use></svg>
                <p><strong>Landlords who describe water supply, generator, parking and floor level get far more calls.</strong> Mention the nearest landmark too — tenants search by landmarks such as Bole Road or CMC Michael Church.</p>
            </div>

            <form id="post-form" method="post" action="<?= Security::e(url('actions/submit_post.php')) ?>" novalidate>

                <!-- Stateless HMAC CSRF token (see config/db.php → Security). -->
                <input type="hidden" name="csrf_token" value="<?= Security::e(Security::csrfToken()) ?>">

                <!-- Honeypot: hidden from humans, irresistible to bots. -->
                <div class="honeypot" aria-hidden="true">
                    <label for="website_url">Website</label>
                    <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-grid">

                    <!-- Building / apartment name -->
                    <div class="field form-grid--full <?= isset($errors['building_name']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="building_name">
                            Building / apartment name
                            <span class="am">(የህንፃው ስም)</span>
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <input class="input" type="text" id="building_name" name="building_name"
                               maxlength="100" minlength="3" required data-required
                               placeholder="e.g. Bole Skyline Residence"
                               value="<?= $val('building_name') ?>">
                        <span class="field__error"><?= Security::e((string) ($errors['building_name'] ?? '')) ?></span>
                    </div>

                    <!-- Location -->
                    <div class="field <?= isset($errors['location']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="location">
                            Neighbourhood
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <select class="select" id="location" name="location" required data-required>
                            <option value="">Select a district of Addis Ababa…</option>
                            <?php foreach (LOCATIONS as $district): ?>
                                <option value="<?= Security::e($district) ?>" <?= old('location') === $district ? 'selected' : '' ?>>
                                    <?= Security::e($district) ?> — <?= Security::e(neighbourhood_am($district)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field__hint"><?= Security::e(neighbourhood_landmark(old('location')) ?: 'Pick the district tenants will search for.') ?></span>
                        <span class="field__error"><?= Security::e((string) ($errors['location'] ?? '')) ?></span>
                    </div>

                    <!-- Room configuration -->
                    <div class="field <?= isset($errors['unit_type']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="unit_type">
                            Room configuration
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <select class="select" id="unit_type" name="unit_type" required data-required>
                            <option value="">Select a unit type…</option>
                            <?php foreach (UNIT_TYPES as $type): ?>
                                <option value="<?= Security::e($type) ?>" <?= old('unit_type') === $type ? 'selected' : '' ?>>
                                    <?= Security::e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field__error"><?= Security::e((string) ($errors['unit_type'] ?? '')) ?></span>
                    </div>

                    <!-- Monthly rent -->
                    <div class="field <?= isset($errors['monthly_rent']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="monthly_rent">
                            Monthly rent
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <div class="input-affix">
                            <input class="input" type="text" id="monthly_rent" name="monthly_rent"
                                   inputmode="decimal" required data-required
                                   placeholder="e.g. 18000"
                                   value="<?= $val('monthly_rent') ?>">
                            <span class="input-affix__suffix">ETB</span>
                        </div>
                        <span class="field__hint">Numbers only — no “ETB” and no commas.</span>
                        <span class="field__error"><?= Security::e((string) ($errors['monthly_rent'] ?? '')) ?></span>
                    </div>

                    <!-- Landlord name -->
                    <div class="field <?= isset($errors['landlord_name']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="landlord_name">
                            Landlord / contact name
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <input class="input" type="text" id="landlord_name" name="landlord_name"
                               maxlength="100" minlength="3" required data-required
                               placeholder="e.g. Yohannes Bekele"
                               value="<?= $val('landlord_name') ?>">
                        <span class="field__error"><?= Security::e((string) ($errors['landlord_name'] ?? '')) ?></span>
                    </div>

                    <!-- Landlord phone -->
                    <div class="field <?= isset($errors['landlord_phone']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="landlord_phone">
                            Mobile phone number
                            <span class="field__required" aria-hidden="true">*</span>
                        </label>
                        <input class="input" type="tel" id="landlord_phone" name="landlord_phone"
                               maxlength="20" required data-required
                               inputmode="tel" autocomplete="tel"
                               placeholder="e.g. 0911234567"
                               value="<?= $val('landlord_phone') ?>">
                        <span class="field__hint">Shown to tenants so they can call you directly.</span>
                        <span class="field__error"><?= Security::e((string) ($errors['landlord_phone'] ?? '')) ?></span>
                    </div>

                    <!-- Description -->
                    <div class="field form-grid--full <?= isset($errors['description']) ? 'has-error' : '' ?>">
                        <label class="field__label" for="description">
                            Short description
                            <span class="am">(አጭር መግለጫ)</span>
                        </label>
                        <textarea class="textarea" id="description" name="description" maxlength="700"
                                  placeholder="Describe the unit: floor level, furnished or not, water tank, generator, parking, security, nearby landmarks…"><?= $val('description') ?></textarea>
                        <div class="field__meta">
                            <span class="field__hint">Optional — but a good description doubles your calls.</span>
                            <span class="counter" id="description-counter">0 / 700 characters</span>
                        </div>
                        <span class="field__error"><?= Security::e((string) ($errors['description'] ?? '')) ?></span>
                    </div>
                </div>

                <!-- Live headline preview rendered by js/marketplace.js -->
                <div class="posted-preview" id="post-preview" aria-live="polite">Building name · District · Unit type · price on request</div>

                <div class="form-actions">
                    <p class="form-actions__note">
                        By publishing you confirm the unit is available and the phone number is correct.
                    </p>
                    <a class="btn btn--ghost" href="<?= Security::e(url('index.php')) ?>">Cancel</a>
                    <button class="btn btn--primary btn--lg" type="submit">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#check')) ?>"></use></svg>
                        <span class="js-submit-label">Publish listing</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="site-footer__tricolour" aria-hidden="true"></div>
        <div class="site-footer__grid">
            <div class="footer-brand">
                <img class="footer-brand__logo" src="<?= Security::e(url('assets/logo-mark.svg')) ?>" alt="" width="36" height="36">
                <span class="footer-brand__text">
                    <span class="footer-brand__name"><?= Security::e(APP_NAME) ?></span>
                    <span class="footer-brand__meta"><?= Security::e(APP_TAGLINE) ?></span>
                </span>
            </div>
            <div class="site-footer__meta">
                <a href="<?= Security::e(url('index.php')) ?>">Home</a>
                <a href="<?= Security::e(url('explore.php')) ?>">Apartments for rent</a>
                <a href="<?= Security::e(url('post_apartment.php')) ?>">List your apartment</a>
            </div>
        </div>
        <p>&copy; <?= Security::e(date('Y')) ?> <?= Security::e(APP_NAME) ?>, Addis Ababa.</p>
    </div>
</footer>

<script src="<?= Security::e(url('js/marketplace.js')) ?>" defer></script>
</body>
</html>
