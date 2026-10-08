<?php
/**
 * =============================================================================
 *  ADDIS RENTALS  —  success.php  (CONFIRMATION VIEW)
 * =============================================================================
 *  Shown after actions/submit_post.php stores a new listing. It confirms that
 *  the post is live on the marketplace and prints a receipt of exactly what
 *  was saved, read back from MySQL with a prepared statement.
 *
 *  Query string
 *    ?id=<listing id>   → renders the full receipt for that listing
 *    ?id=0&filtered=1   → honeypot path: message only, no receipt
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$flash    = flash_pull();
$id       = (int) query_str('id', '0');
$filtered = query_str('filtered') !== '';
$listing  = null;

/* ---------------------------------------------------------------------------
 * Read the freshly created row back so the landlord can verify the details.
 * ------------------------------------------------------------------------ */
if ($id > 0) {
    try {
        $listing = Database::instance()->fetchOne(
            'SELECT id, building_name, location, unit_type, monthly_rent, description,
                    landlord_name, landlord_phone, status, created_at
               FROM listings
              WHERE id = :id
              LIMIT 1',
            [':id' => $id]
        );
    } catch (Throwable $e) {
        error_log('[addis-rentals] receipt lookup failed: ' . $e->getMessage());
    }
}

$currentPage = 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Listing published · <?= Security::e(APP_NAME) ?></title>
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
            <div class="flash flash--<?= Security::e($flash['type']) ?>" role="status">
                <span><?= Security::e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <section class="card success-wrap">
            <div class="success-mark" aria-hidden="true">
                <svg class="icon icon--xl"><use href="<?= Security::e(url('assets/icons.svg#check')) ?>"></use></svg>
            </div>

            <?php if ($listing !== null): ?>
                <h1>Your apartment is now listed</h1>
                <p>
                    Tenants browsing Addis Rentals can find
                    <strong><?= Security::e((string) $listing['building_name']) ?></strong>
                    in <?= Security::e((string) $listing['location']) ?> and call you straight away.
                    Keep your phone reachable — most enquiries arrive within the first day.
                </p>
                <span class="section-head__am">አፓርታማዎ አሁን በገበያ ቦታው ላይ ይገኛል</span>

                <dl class="receipt">
                    <div class="receipt__row">
                        <dt>Listing reference</dt>
                        <dd>#<?= Security::e((string) $listing['id']) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Building / apartment</dt>
                        <dd><?= Security::e((string) $listing['building_name']) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Neighbourhood</dt>
                        <dd>
                            <?= Security::e((string) $listing['location']) ?>
                            <span class="am"><?= Security::e(neighbourhood_am((string) $listing['location'])) ?></span>
                        </dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Landmarks</dt>
                        <dd><?= Security::e(neighbourhood_landmark((string) $listing['location'])) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Room configuration</dt>
                        <dd><?= Security::e((string) $listing['unit_type']) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Monthly rent</dt>
                        <dd>ETB <?= Security::e(money((float) $listing['monthly_rent'])) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Landlord</dt>
                        <dd><?= Security::e((string) $listing['landlord_name']) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Contact number</dt>
                        <dd><?= Security::e((string) $listing['landlord_phone']) ?></dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Status</dt>
                        <dd>
                            <span class="tag tag--status">
                                <svg class="icon icon--sm" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#check')) ?>"></use></svg>
                                <?= Security::e((string) $listing['status']) ?>
                            </span>
                        </dd>
                    </div>
                    <div class="receipt__row">
                        <dt>Published on</dt>
                        <dd><?= Security::e(pretty_date((string) $listing['created_at'])) ?></dd>
                    </div>
                </dl>

                <div class="success-actions">
                    <a class="btn btn--primary btn--lg" href="<?= Security::e(url('explore.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#search')) ?>"></use></svg>
                        <span>See it in the listings</span>
                    </a>
                    <a class="btn btn--outline btn--lg" href="<?= Security::e(url('post_apartment.php')) ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?= Security::e(url('assets/icons.svg#plus')) ?>"></use></svg>
                        <span>List another unit</span>
                    </a>
                </div>

            <?php elseif ($filtered): ?>
                <h1>Thanks — your submission was received</h1>
                <p>Your listing is being prepared for the marketplace. Use the link below to see the current feed in the meantime.</p>
                <div class="success-actions">
                    <a class="btn btn--primary btn--lg" href="<?= Security::e(url('explore.php')) ?>">Browse apartments</a>
                </div>

            <?php else: ?>
                <h1>No listing to display</h1>
                <p>
                    We could not find that reference — it may have been removed. You can publish a new
                    apartment listing at any time.
                </p>
                <div class="success-actions">
                    <a class="btn btn--primary btn--lg" href="<?= Security::e(url('post_apartment.php')) ?>">List an apartment</a>
                    <a class="btn btn--outline btn--lg" href="<?= Security::e(url('explore.php')) ?>">Browse apartments</a>
                </div>
            <?php endif; ?>
        </section>

        <div class="card card--soft">
            <h2>What happens next?</h2>
            <ul class="gate-card__list">
                <li>Your listing joins the Addis Rentals feed straight away, with its district photograph.</li>
                <li>Interested tenants tap “View Contact Details” and call the number you provided.</li>
                <li>Once the unit is taken, reply to the tenant who rented it and keep the listing as your record.</li>
            </ul>
        </div>

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
