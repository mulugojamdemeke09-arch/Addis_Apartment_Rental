<?php
/**
 * =============================================================================
 *  APARTMENT RENTAL MARKETPLACE  —  actions/submit_post.php
 * =============================================================================
 *  Handles the landlord posting form (post_apartment.php).
 *
 *  FLOW
 *  ----
 *   1. Reject anything that is not a POST request.
 *   2. Verify the stateless HMAC CSRF token (config/db.php → Security).
 *   3. Silently drop submissions that filled the honeypot field (bots).
 *   4. Apply a light session throttle (max 5 listings / 10 minutes).
 *   5. Sanitise + validate every field server-side. Client-side validation in
 *      marketplace.js is only a convenience — this file is the authority.
 *   6. INSERT through a PDO prepared statement (no string concatenation, so
 *      SQL injection is structurally impossible).
 *   7. Redirect (POST → Redirect → GET) to success.php, or back to the form
 *      with the errors and the previously typed values preserved.
 *
 *  This script never echoes HTML: it always ends in a redirect.
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/* --- Anti-spam throttle settings ----------------------------------------- */
const THROTTLE_WINDOW   = 600;   // seconds (10 minutes)
const THROTTLE_MAX_POST = 5;     // listings allowed per window
const DESC_MAX_LENGTH   = 700;

/* ---------------------------------------------------------------------------
 * 1. Only POST is accepted.
 * ------------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('info', 'Fill in the form below to publish your apartment.');
    header('Location: ' . url('post_apartment.php'));
    exit;
}

/** Sends the user back to the form with a message. */
function back_to_form(string $type, string $message): void
{
    flash_set($type, $message);
    header('Location: ' . url('post_apartment.php') . '#post-form');
    exit;
}

/* ---------------------------------------------------------------------------
 * 2. CSRF verification — a forged or stale token is rejected outright.
 * ------------------------------------------------------------------------ */
$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : null;

if (!Security::csrfVerify($csrfToken)) {
    back_to_form('error', 'Your session form token expired. Please review the form and submit again.');
}

/* ---------------------------------------------------------------------------
 * 3. Honeypot — real users never see, let alone fill, this field.
 *    We pretend the post succeeded so bots do not learn the rule.
 * ------------------------------------------------------------------------ */
if (!empty($_POST['website_url'])) {
    header('Location: ' . url('success.php') . '?id=0&filtered=1');
    exit;
}

/* ---------------------------------------------------------------------------
 * 4. Throttle — keeps an anonymous marketplace from being flooded.
 * ------------------------------------------------------------------------ */
$now  = time();
$hits = array_values(array_filter(
    (array) ($_SESSION['post_times'] ?? []),
    static fn(int $t): bool => ($now - $t) < THROTTLE_WINDOW
));

if (count($hits) >= THROTTLE_MAX_POST) {
    back_to_form('error', 'You have published several listings in a short time. Please wait a few minutes and try again.');
}

/* ---------------------------------------------------------------------------
 * 5. Collect, sanitise and validate.
 * ------------------------------------------------------------------------ */
$input = [
    'building_name'  => Security::clean(post_str('building_name'), 100),
    'location'       => Security::clean(post_str('location'), 50),
    'unit_type'      => Security::clean(post_str('unit_type'), 50),
    'monthly_rent'   => trim(post_str('monthly_rent')),
    'description'    => Security::clean(post_str('description'), DESC_MAX_LENGTH),
    'landlord_name'  => Security::clean(post_str('landlord_name'), 100),
    'landlord_phone' => Security::clean(post_str('landlord_phone'), 20),
];

$errors = [];

/* Building / apartment name */
if (mb_strlen($input['building_name']) < 3) {
    $errors['building_name'] = 'Please enter a building or apartment name (at least 3 characters).';
}

/* Location — must match the whitelist used by the dropdown */
if (!Security::inList($input['location'], LOCATIONS)) {
    $errors['location'] = 'Choose a location from the list.';
}

/* Unit type — must match the whitelist used by the dropdown */
if (!Security::inList($input['unit_type'], UNIT_TYPES)) {
    $errors['unit_type'] = 'Choose a room configuration from the list.';
}

/* Monthly rent — numeric, positive, and inside a sane range */
if ($input['monthly_rent'] === '' || !is_numeric($input['monthly_rent'])) {
    $errors['monthly_rent'] = 'Enter the monthly rent as a number (ETB).';
} else {
    $rent = (float) $input['monthly_rent'];
    if ($rent <= 0) {
        $errors['monthly_rent'] = 'The monthly rent must be greater than 0.';
    } elseif ($rent > 1000000) {
        $errors['monthly_rent'] = 'That rent looks unrealistic — please check the amount.';
    }
}

/* Optional description, with a helpful minimum when supplied */
if ($input['description'] !== '' && mb_strlen($input['description']) < 20) {
    $errors['description'] = 'Please describe the unit in at least 20 characters (or leave it empty).';
}

/* Landlord name */
if (mb_strlen($input['landlord_name']) < 3) {
    $errors['landlord_name'] = 'Please enter the landlord or contact name.';
}

/* Landlord phone — normalised to +2519XXXXXXXX / +2517XXXXXXXX */
$normalisedPhone = Security::normalisePhone($input['landlord_phone']);
if ($normalisedPhone === null) {
    $errors['landlord_phone'] = 'Enter a valid Ethiopian mobile number, e.g. 0911234567 or +251911234567.';
} else {
    $input['landlord_phone'] = $normalisedPhone;
}

/* ---------------------------------------------------------------------------
 * 6. Any validation failure → back to the form with the values preserved.
 * ------------------------------------------------------------------------ */
if ($errors !== []) {
    old_remember([
        'building_name'  => $input['building_name'],
        'location'       => $input['location'],
        'unit_type'      => $input['unit_type'],
        'monthly_rent'   => $input['monthly_rent'],
        'description'    => $input['description'],
        'landlord_name'  => $input['landlord_name'],
        'landlord_phone' => post_str('landlord_phone'),
        'errors'         => $errors,
    ]);
    flash_set('error', 'Please correct the highlighted fields and submit again.');
    header('Location: ' . url('post_apartment.php') . '#post-form');
    exit;
}

/* ---------------------------------------------------------------------------
 * 7. Insert through a prepared statement.
 * ------------------------------------------------------------------------ */
try {
    $db   = Database::instance();
    $rows = $db->execute(
        'INSERT INTO listings
            (building_name, location, unit_type, monthly_rent, description,
             landlord_name, landlord_phone, status)
         VALUES
            (:building_name, :location, :unit_type, :monthly_rent, :description,
             :landlord_name, :landlord_phone, :status)',
        [
            ':building_name'  => $input['building_name'],
            ':location'       => $input['location'],
            ':unit_type'      => $input['unit_type'],
            ':monthly_rent'   => number_format((float) $input['monthly_rent'], 2, '.', ''),
            ':description'    => $input['description'],
            ':landlord_name'  => $input['landlord_name'],
            ':landlord_phone' => $input['landlord_phone'],
            ':status'         => 'Active',
        ]
    );

    if ($rows !== 1) {
        throw new RuntimeException('The listing could not be saved.');
    }

    $listingId = $db->lastInsertId();

    /* Record the successful post for the throttle window. */
    $hits[] = $now;
    $_SESSION['post_times'] = $hits;

    old_clear();
    flash_set('success', 'Your apartment is now live on the marketplace.');

    header('Location: ' . url('success.php') . '?id=' . $listingId);
    exit;
} catch (Throwable $e) {
    error_log('[rental-marketplace] insert failed: ' . $e->getMessage());
    old_remember(array_merge($input, ['errors' => []]));
    back_to_form('error', 'Something went wrong while saving your listing. Please try again.');
}
