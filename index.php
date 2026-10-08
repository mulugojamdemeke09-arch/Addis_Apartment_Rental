<?php
/**
 * =============================================================================
 *  ADDIS RENTALS  —  index.php  (CENTRAL SPLASH GATEWAY)
 * =============================================================================
 *  The welcoming canvas. It splits the audience into the two sides of the
 *  marketplace:
 *
 *    • "List Your Apartment"  → lessor gate  → post_apartment.php
 *    • "Find a Rental Home"   → tenant gate  → explore.php
 *
 *  The page is dressed with real Addis Ababa photography (assets/*.jpg), the
 *  brand mark (assets/logo-mark.svg) and the Ethiopian tricolour accent, and
 *  it reports live figures straight from the listings table.
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

/* ---------------------------------------------------------------------------
 * Live marketplace statistics for the hero strip.
 * ------------------------------------------------------------------------ */
$stats = ['active' => 0, 'districts' => 0, 'average' => 0.0, 'lowest' => 0.0];

/* Per-district listing counts used by the neighbourhood showcase. */
$counts = array_fill_keys(LOCATIONS, 0);

try {
    $db = Database::instance();

    $row = $db->fetchOne(
        'SELECT COUNT(*) AS active_listings,
                COUNT(DISTINCT location) AS covered_districts,
                COALESCE(AVG(monthly_rent), 0) AS average_rent,
                COALESCE(MIN(monthly_rent), 0) AS lowest_rent
           FROM listings
          WHERE status = :status',
        [':status' => 'Active']
    );

    if ($row) {
        $stats['active']    = (int) $row['active_listings'];
        $stats['districts'] = (int) $row['covered_districts'];
        $stats['average']   = (float) $row['average_rent'];
        $stats['lowest']    = (float) $row['lowest_rent'];
    }

    foreach ($db->fetchAll(
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
    // Database::instance() renders a friendly screen when the connection is
    // down; this guard keeps the gateway renderable in every other case.
    error_log('[addis-rentals] stats failed: ' . $e->getMessage());
}

/* The five showcase cards: districts with photography first, then a city-wide
 * card that routes straight into the full feed. */
$showcaseOrder  = ['Bole', 'Kazanchis', 'Sarbet', 'CMC'];
$orderedDistricts = array_merge($showcaseOrder, array_values(array_diff(LOCATIONS, $showcaseOrder)));

$flash       = flash_pull();
$currentPage = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Addis Rentals — browse apartments for rent across Bole, Kazanchis, CMC, Sarbet and Ayat, or publish your own unit and reach tenants directly.">
    <title><?= Security::e(APP_NAME) ?> — apartments for rent in Addis Ababa</title>
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
            <a class="site-nav__link <?= $currentPage === 'home' ? 'is-active' : '' ?>"
               href="<?= Security::e(url('index.php')) ?>">Home</a>
            <a class="site-nav__link" href="<?= Security::e(url('explore.php')) ?>">Apartments for rent</a>
            <a class="site-nav__link site-nav__link--cta" href="<?= Security::e(url('post_apartment.php')) ?>">List your apartment</a>
        </nav>
    </div>
</header>

<main class="page__main">
    <div class="container">

        <?php if ($flash !== null): ?>
            <div class="flash flash--<?= Security::e($flash['type']) ?>" role="status">
                <span><?= Security::e($flash['message']) ?></span>
                <button type="button" class="btn btn--ghost btn--sm" data-dismiss-flash aria-label="Dismiss message">Dismiss</button>
            </div>
        <?php endif; ?>

        <!-- ================= HERO BAND (Addis Ababa) ================= -->
        <section class="hero-band" aria-labelledby="hero-title">
            <div class="hero-band__media" aria-hidden="true">
                <img src="<?= Security::e(url('assets/hero-addis-ababa.jpg')) ?>"
                     alt="" width="1600" height="900" fetchpriority="high">
            </div>
            <div class="hero-band__scrim" aria-hidden="true"></div>

            <div class="hero-band__inner">
                <span class="section-head__eyebrow">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#flag')) ?>"></use></svg>
                    Addis Ababa rental marketplace
                </span>

                <h1 id="hero-title">Find your next home in Addis Ababa — or rent out the one you own</h1>

                <p class="hero-band__lead">
                    From Bole high-rises to family compounds in CMC and hillside flats in Sarbet:
                    browse real prices in birr, and speak to the property owner yourself.
                </p>

                <div class="hero-band__actions">
                    <a class="btn btn--primary btn--lg" href="<?= Security::e(url('explore.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#search')) ?>"></use></svg>
                        <span>Browse apartments</span>
                    </a>
                    <a class="btn btn--outline btn--lg" href="<?= Security::e(url('post_apartment.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#plus')) ?>"></use></svg>
                        <span>List your apartment</span>
                    </a>
                </div>

                <p class="hero-band__note">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#phone')) ?>"></use></svg>
                    Tenant enquiries reach the owner by phone, the way renting actually works here.
                </p>
            </div>
        </section>

        <!-- ================= LIVE FIGURES ================= -->
        <section class="stat-row" aria-label="Marketplace at a glance">
            <div class="stat-card">
                <span class="stat-card__label">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#home')) ?>"></use></svg>
                    Apartments listed
                </span>
                <b class="stat-card__value"><?= Security::e((string) $stats['active']) ?></b>
            </div>
            <div class="stat-card">
                <span class="stat-card__label">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#compass')) ?>"></use></svg>
                    Neighbourhoods
                </span>
                <b class="stat-card__value"><?= Security::e((string) $stats['districts']) ?></b>
            </div>
            <div class="stat-card">
                <span class="stat-card__label">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#wallet')) ?>"></use></svg>
                    Average monthly rent
                </span>
                <b class="stat-card__value"><?= Security::e(money($stats['average'])) ?> ETB</b>
            </div>
            <div class="stat-card">
                <span class="stat-card__label">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#tag')) ?>"></use></svg>
                    Lowest listed rent
                </span>
                <b class="stat-card__value"><?= Security::e(money($stats['lowest'])) ?> ETB</b>
            </div>
        </section>

        <!-- ================= THE TWO GATES ================= -->
        <section class="gate-grid" aria-label="Choose your pathway">

            <!-- LESSOR GATE -->
            <article class="gate-card gate-card--lessor">
                <div class="gate-card__icon" aria-hidden="true">
                    <svg class="icon icon--lg"><use href="<?= Security::e(url('assets/icons.svg#building')) ?>"></use></svg>
                </div>
                <div>
                    <h2 class="gate-card__title">List Your Apartment</h2>
                    <p class="gate-card__subtitle">Lessor gate · <span class="am">አፓርታማዎን ለኪራይ ያስተዋውቁ</span></p>
                </div>
                <div class="gate-card__body">
                    <p>Describe the unit once, and tenants across the capital can find it the same day.</p>
                    <ul class="gate-card__list">
                        <li>Building, district, monthly rent in birr and your contact number</li>
                        <li>Your listing appears in the feed the moment you publish it</li>
                        <li>Enquiries come straight to your phone — no middlemen</li>
                    </ul>
                </div>
                <div class="gate-card__cta">
                    <a class="btn btn--primary" href="<?= Security::e(url('post_apartment.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#plus')) ?>"></use></svg>
                        <span>Create a listing</span>
                    </a>
                    <span class="gate-card__arrow" aria-hidden="true">
                        Continue
                        <svg class="icon icon--sm"><use href="<?= Security::e(url('assets/icons.svg#arrow-right')) ?>"></use></svg>
                    </span>
                </div>
            </article>

            <!-- TENANT GATE -->
            <article class="gate-card gate-card--tenant">
                <div class="gate-card__icon" aria-hidden="true">
                    <svg class="icon icon--lg"><use href="<?= Security::e(url('assets/icons.svg#key')) ?>"></use></svg>
                </div>
                <div>
                    <h2 class="gate-card__title">Find a Rental Home</h2>
                    <p class="gate-card__subtitle">Tenant gate · <span class="am">የሚከራይ ቤት ይፈልጉ</span></p>
                </div>
                <div class="gate-card__body">
                    <p>Filter every active unit by district and room configuration, then arrange a visit.</p>
                    <ul class="gate-card__list">
                        <li>Instant filters for district, studio, bedroom count and penthouse</li>
                        <li>Monthly rents shown in ETB, exactly as the owner set them</li>
                        <li>Owner phone number revealed in one click</li>
                    </ul>
                </div>
                <div class="gate-card__cta">
                    <a class="btn btn--primary" href="<?= Security::e(url('explore.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#search')) ?>"></use></svg>
                        <span>Browse apartments</span>
                    </a>
                    <span class="gate-card__arrow" aria-hidden="true">
                        Continue
                        <svg class="icon icon--sm"><use href="<?= Security::e(url('assets/icons.svg#arrow-right')) ?>"></use></svg>
                    </span>
                </div>
            </article>
        </section>

        <!-- ================= NEIGHBOURHOODS ================= -->
        <section aria-labelledby="hood-title" class="section-block">
            <div class="section-head">
                <span class="section-head__eyebrow">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#compass')) ?>"></use></svg>
                    Where to live
                </span>
                <h2 id="hood-title">Neighbourhoods of Addis Ababa</h2>
                <p class="section-head__lead">
                    Every district has its own rhythm — pick the one that fits your commute.
                </p>
                <span class="section-head__am">የአዲስ አበባ ሰፈሮች</span>
            </div>

            <div class="hood-grid">
                <?php foreach ($showcaseOrder as $district): ?>
                    <article class="hood-card">
                        <div class="hood-card__media">
                            <img src="<?= Security::e(url(neighbourhood_image($district))) ?>"
                                 alt="Apartments in <?= Security::e($district) ?>, Addis Ababa"
                                 width="900" height="675" loading="lazy">
                            <span class="hood-card__badge">
                                <?= Security::e(neighbourhood_am($district)) ?>
                            </span>
                        </div>
                        <div class="hood-card__body">
                            <div class="hood-card__title">
                                <h3><?= Security::e($district) ?></h3>
                                <span class="hood-card__count">
                                    <?= Security::e((string) $counts[$district]) ?> listed
                                </span>
                            </div>
                            <p class="hood-card__landmark">
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#pin')) ?>"></use></svg>
                                <?= Security::e(neighbourhood_landmark($district)) ?>
                            </p>
                            <p class="hood-card__blurb"><?= Security::e(neighbourhood_blurb($district)) ?></p>
                            <a class="hood-card__cta" href="<?= Security::e(url('explore.php')) ?>?location=<?= Security::e(urlencode($district)) ?>">
                                See apartments in <?= Security::e($district) ?>
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#arrow-right')) ?>"></use></svg>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="hood-chips">
                <span class="hood-chips__label">All districts</span>
                <?php foreach ($orderedDistricts as $district): ?>
                    <a class="hood-chip" href="<?= Security::e(url('explore.php')) ?>?location=<?= Security::e(urlencode($district)) ?>">
                        <?= Security::e($district) ?>
                        <span class="am"><?= Security::e(neighbourhood_am($district)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ================= HOW IT WORKS ================= -->
        <section class="steps section-block" aria-label="How Addis Rentals works">
            <article class="step">
                <span class="step__icon" aria-hidden="true">
                    <svg class="icon"><use href="<?= Security::e(url('assets/icons.svg#compass')) ?>"></use></svg>
                </span>
                <h3>Choose your district</h3>
                <p>Start from the neighbourhood you want — Bole for business, CMC for family compounds, Sarbet for the ring road.</p>
            </article>
            <article class="step">
                <span class="step__icon" aria-hidden="true">
                    <svg class="icon"><use href="<?= Security::e(url('assets/icons.svg#tag')) ?>"></use></svg>
                </span>
                <h3>Compare real prices</h3>
                <p>Every card shows the monthly rent in birr and the room configuration, so you can judge value at a glance.</p>
            </article>
            <article class="step">
                <span class="step__icon" aria-hidden="true">
                    <svg class="icon"><use href="<?= Security::e(url('assets/icons.svg#phone')) ?>"></use></svg>
                </span>
                <h3>Call the owner</h3>
                <p>Tap “View Contact Details” to reach the landlord directly and arrange a visit at a time that suits you.</p>
            </article>
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
