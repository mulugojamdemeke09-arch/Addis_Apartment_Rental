<?php
/**
 * =============================================================================
 *  ADDIS RENTALS  —  explore.php  (TENANT GATE + FEED ENGINE)
 * =============================================================================
 *  The tenant marketplace feed. It reads every ACTIVE listing from the local
 *  MySQL table and renders it as a photographic card grid, together with a
 *  filtering toolbar that works in two complementary ways:
 *
 *    • Server side (works with JavaScript disabled): the toolbar is a GET form,
 *      so submitting it re-runs the query with the selected district, room
 *      configuration, keyword and sort order.
 *    • Client side (instant): js/marketplace.js filters and re-orders the cards
 *      already on the page, with no reload and no extra request.
 *
 *  Every card carries the landlord metadata in data-* attributes. Clicking
 *  "View Contact Details" opens the white modal overlay and prints:
 *      "To rent this unit, please call [Landlord Name] directly at [Phone]".
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

/* ---------------------------------------------------------------------------
 * 1. READ + VALIDATE THE FILTER PARAMETERS (whitelisted values only)
 * ------------------------------------------------------------------------ */
$filterLocation = Security::clean(query_str('location'), 50);
$filterType     = Security::clean(query_str('unit_type'), 50);
$filterTerm     = Security::clean(query_str('q'), 60);
$filterSort     = Security::clean(query_str('sort'), 20);

if (!Security::inList($filterLocation, LOCATIONS)) { $filterLocation = ''; }
if (!Security::inList($filterType, UNIT_TYPES))    { $filterType = ''; }
if (!in_array($filterSort, ['newest', 'price_asc', 'price_desc'], true)) { $filterSort = 'newest'; }

/* Sort expression is chosen from a fixed map — never concatenated from input. */
$orderBy = [
    'newest'     => 'created_at DESC, id DESC',
    'price_asc'  => 'monthly_rent ASC, created_at DESC',
    'price_desc' => 'monthly_rent DESC, created_at DESC',
][$filterSort];

/* ---------------------------------------------------------------------------
 * 2. BUILD THE QUERY WITH PDO BOUND PARAMETERS
 * ------------------------------------------------------------------------ */
$sql    = 'SELECT id, building_name, location, unit_type, monthly_rent, description,
                  landlord_name, landlord_phone, status, created_at
             FROM listings
            WHERE status = :status';
$params = [':status' => 'Active'];

if ($filterLocation !== '') {
    $sql .= ' AND location = :location';
    $params[':location'] = $filterLocation;
}
if ($filterType !== '') {
    $sql .= ' AND unit_type = :unit_type';
    $params[':unit_type'] = $filterType;
}
if ($filterTerm !== '') {
    $sql .= ' AND (building_name LIKE :term OR description LIKE :term OR landlord_name LIKE :term OR location LIKE :term)';
    $params[':term'] = '%' . $filterTerm . '%';
}
$sql .= ' ORDER BY ' . $orderBy;

$listings = [];
$dbError  = null;

try {
    $listings = Database::instance()->fetchAll($sql, $params);
} catch (Throwable $e) {
    error_log('[addis-rentals] feed query failed: ' . $e->getMessage());
    $dbError = 'The listings could not be loaded right now. Please refresh the page.';
}

$flash       = flash_pull();
$totalShown  = count($listings);
$currentPage = 'explore';
$hasFilters  = ($filterLocation !== '' || $filterType !== '' || $filterTerm !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Apartments for rent in Addis Ababa — filter by neighbourhood such as Bole, CMC, Kazanchis and Sarbet, compare monthly rents in ETB and call the owner directly.">
    <title>Apartments for rent in Addis Ababa · <?= Security::e(APP_NAME) ?></title>
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
            <a class="site-nav__link is-active" href="<?= Security::e(url('explore.php')) ?>">Apartments for rent</a>
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

        <?php if ($dbError !== null): ?>
            <div class="flash flash--error" role="alert"><span><?= Security::e($dbError) ?></span></div>
        <?php endif; ?>

        <div class="feed-head">
            <div class="section-head">
                <span class="section-head__eyebrow">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#compass')) ?>"></use></svg>
                    Tenant gate
                </span>
                <h1 class="feed-head__title">Apartments for rent in Addis Ababa</h1>
                <p class="feed-head__subtitle">
                    Every unit currently available from owners across the capital.
                </p>
                <span class="section-head__am">የሚከራዩ አፓርታማዎች ዝርዝር</span>
            </div>
            <a class="btn btn--outline" href="<?= Security::e(url('post_apartment.php')) ?>">
                <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#plus')) ?>"></use></svg>
                <span>I have a unit to list</span>
            </a>
        </div>

        <!-- ============ FILTERING TOOLBAR (server side + instant client side) ============ -->
        <form class="toolbar" id="filter-form" method="get" action="<?= Security::e(url('explore.php')) ?>" role="search">
            <div class="toolbar__field">
                <label class="toolbar__label" for="filter-search">Search</label>
                <input class="input" type="search" id="filter-search" name="q"
                       placeholder="Building, landmark or keyword…"
                       value="<?= Security::e($filterTerm) ?>">
            </div>

            <div class="toolbar__field">
                <label class="toolbar__label" for="filter-location">Neighbourhood</label>
                <select class="select" id="filter-location" name="location">
                    <option value="">All districts</option>
                    <?php foreach (LOCATIONS as $district): ?>
                        <option value="<?= Security::e($district) ?>" <?= $filterLocation === $district ? 'selected' : '' ?>>
                            <?= Security::e($district) ?> — <?= Security::e(neighbourhood_am($district)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="toolbar__field">
                <label class="toolbar__label" for="filter-type">Room configuration</label>
                <select class="select" id="filter-type" name="unit_type">
                    <option value="">All configurations</option>
                    <?php foreach (UNIT_TYPES as $type): ?>
                        <option value="<?= Security::e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>>
                            <?= Security::e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="toolbar__field">
                <label class="toolbar__label" for="filter-sort">Sort by</label>
                <select class="select" id="filter-sort" name="sort">
                    <option value="newest"     <?= $filterSort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="price_asc"  <?= $filterSort === 'price_asc' ? 'selected' : '' ?>>Rent: low to high</option>
                    <option value="price_desc" <?= $filterSort === 'price_desc' ? 'selected' : '' ?>>Rent: high to low</option>
                </select>
            </div>

            <div class="toolbar__actions">
                <button class="btn btn--outline btn--sm" type="submit">Apply</button>
                <button class="btn btn--ghost btn--sm" type="button" id="filter-reset">Reset</button>
            </div>
        </form>

        <!-- ============ RESULTS SUMMARY + ACTIVE FILTER CHIPS ============ -->
        <div class="results-bar">
            <span class="results-bar__count" id="result-count">
                <b><?= Security::e((string) $totalShown) ?></b>
                <?= $totalShown === 1 ? 'apartment' : 'apartments' ?> available
                <?= $hasFilters ? 'for your filters' : 'in Addis Ababa' ?>
            </span>
            <span class="results-bar__chips" id="active-filters"></span>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost btn--sm" href="<?= Security::e(url('explore.php')) ?>">Clear all filters</a>
            <?php endif; ?>
        </div>

        <!-- ============ THE FEED ============ -->
        <section class="feed-grid" id="feed-grid" aria-label="Apartment listings">

            <?php foreach ($listings as $listing): ?>
                <?php
                    $district  = (string) $listing['location'];
                    $rent      = (float) $listing['monthly_rent'];
                    $createdTs = (int) strtotime((string) $listing['created_at']);
                    $isRented  = ((string) $listing['status']) !== 'Active';
                    $image     = neighbourhood_image($district);
                ?>
                <article class="listing-card <?= $isRented ? 'is-rented' : '' ?>"
                         data-building="<?= Security::e((string) $listing['building_name']) ?>"
                         data-location="<?= Security::e($district) ?>"
                         data-unit-type="<?= Security::e((string) $listing['unit_type']) ?>"
                         data-rent="<?= Security::e(number_format($rent, 2, '.', '')) ?>"
                         data-description="<?= Security::e((string) $listing['description']) ?>"
                         data-landlord="<?= Security::e((string) $listing['landlord_name']) ?>"
                         data-phone="<?= Security::e((string) $listing['landlord_phone']) ?>"
                         data-created="<?= Security::e((string) $createdTs) ?>"
                         data-image="<?= Security::e(url($image)) ?>"
                         data-district="<?= Security::e($district) ?>">

                    <div class="listing-card__media">
                        <img src="<?= Security::e(url($image)) ?>"
                             alt="Apartments in <?= Security::e($district) ?>, Addis Ababa"
                             width="900" height="675" loading="lazy">
                        <div class="tag-row">
                            <span class="tag tag--location">
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#pin')) ?>"></use></svg>
                                <?= Security::e($district) ?>
                            </span>
                            <span class="tag tag--type">
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#bed')) ?>"></use></svg>
                                <?= Security::e((string) $listing['unit_type']) ?>
                            </span>
                            <?php if ($isRented): ?>
                                <span class="tag tag--rented">Rented</span>
                            <?php else: ?>
                                <span class="tag tag--status">Available</span>
                            <?php endif; ?>
                        </div>
                        <span class="listing-card__id">Ref #<?= Security::e((string) $listing['id']) ?></span>
                    </div>

                    <div class="listing-card__body">
                        <div class="listing-card__top">
                            <div>
                                <h2 class="listing-card__title">
                                    <?= Security::e((string) $listing['building_name']) ?>
                                </h2>
                                <p class="listing-card__hood">
                                    <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#pin')) ?>"></use></svg>
                                    <span class="am"><?= Security::e(neighbourhood_am($district)) ?></span>
                                    <?= Security::e(neighbourhood_landmark($district)) ?>
                                </p>
                            </div>
                            <div class="listing-card__price">
                                <span class="listing-card__amount">ETB <?= Security::e(money($rent)) ?></span>
                                <span class="listing-card__period">per month</span>
                            </div>
                        </div>

                        <?php if (trim((string) $listing['description']) !== ''): ?>
                            <p class="listing-card__desc"><?= Security::e((string) $listing['description']) ?></p>
                        <?php endif; ?>

                        <div class="listing-card__meta">
                            <span>
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#home')) ?>"></use></svg>
                                <?= Security::e((string) $listing['unit_type']) ?>
                            </span>
                            <span>
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#calendar')) ?>"></use></svg>
                                <?= Security::e(pretty_date((string) $listing['created_at'])) ?>
                            </span>
                            <span><?= Security::e(relative_age((string) $listing['created_at'])) ?></span>
                        </div>

                        <div class="listing-card__actions">
                            <button type="button" class="btn btn--primary js-contact">
                                <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#phone')) ?>"></use></svg>
                                <span>View Contact Details</span>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- Shown by js/marketplace.js when the client-side filters match nothing -->
        <div class="empty-state" id="feed-empty" <?= $totalShown > 0 ? 'hidden' : '' ?>>
            <div class="empty-state__icon" aria-hidden="true">
                <svg class="icon icon--xl"><use href="<?= Security::e(url('assets/icons.svg#search')) ?>"></use></svg>
            </div>
            <h3>No apartments match those filters</h3>
            <p>
                Try another district or room configuration — Bole and CMC usually have the widest choice.
                <span class="am">ሌላ ሰፈር ወይም የክፍል አይነት ይሞክሩ።</span>
            </p>
            <a class="btn btn--outline" href="<?= Security::e(url('explore.php')) ?>">Show every apartment</a>
        </div>

    </div>
</main>

<!-- =========================================================================
     CONTACT DETAILS MODAL
     A clean white card that pops over a blurred backdrop. It is populated
     entirely by js/marketplace.js from the data-* attributes of the clicked
     listing card, so no additional request or page is required.
     ========================================================================= -->
<div class="modal-root" id="contact-modal" role="dialog" aria-modal="true"
     aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal" role="document">

        <div class="modal__media">
            <img id="modal-image" src="<?= Security::e(url('assets/neighbourhood-addis.jpg')) ?>"
                 alt="" width="900" height="394">
            <span class="modal__location">
                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#pin')) ?>"></use></svg>
                <span id="modal-media-location">Addis Ababa</span>
            </span>
        </div>

        <button type="button" class="modal__close" id="modal-close" aria-label="Close contact details">
            <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#close')) ?>"></use></svg>
        </button>

        <span class="modal__eyebrow">
            <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#check')) ?>"></use></svg>
            Owner-direct contact
        </span>

        <h2 class="modal__title" id="modal-title">Apartment unit</h2>
        <p class="modal__subtitle">Speak with the property owner to arrange a viewing.</p>

        <div class="modal__price">
            <b id="modal-rent">ETB 0.00</b>
            <span>per month</span>
        </div>

        <p class="modal__instruction" id="modal-instruction">
            To rent this unit, please call the landlord directly.
        </p>

        <div class="modal__specs">
            <div class="spec">
                <span class="spec__label">Neighbourhood</span>
                <span class="spec__value" id="modal-location">—</span>
            </div>
            <div class="spec">
                <span class="spec__label">Configuration</span>
                <span class="spec__value" id="modal-type">—</span>
            </div>
            <div class="spec">
                <span class="spec__label">Building</span>
                <span class="spec__value" id="modal-building">—</span>
            </div>
            <div class="spec">
                <span class="spec__label">Listed</span>
                <span class="spec__value" id="modal-added">—</span>
            </div>
        </div>

        <div class="modal__actions">
            <a class="btn btn--primary btn--lg btn--block" id="modal-call" href="tel:">
                <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#phone')) ?>"></use></svg>
                <span class="js-call-label">Call the landlord</span>
            </a>
            <p class="modal__phone">
                <span id="modal-phone-label">—</span>
                <button type="button" class="btn btn--outline btn--sm" id="modal-copy">Copy number</button>
            </p>
            <p class="modal__footnote" id="modal-call-hint" hidden>
                Detected a mobile device — tapping the button opens your phone dialer.
            </p>
            <p class="modal__footnote" id="modal-copy-status" aria-live="polite"></p>
        </div>
    </div>
</div>

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
