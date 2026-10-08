<?php
/**
 * =============================================================================
 *  APARTMENT RENTAL MARKETPLACE  —  config/db.php
 * =============================================================================
 *  Single application bootstrap file. It is required by EVERY page and action
 *  script in the project (index.php, explore.php, post_apartment.php,
 *  success.php, actions/submit_post.php).
 *
 *  Responsibilities
 *  ----------------
 *  1. Environment constants (database credentials, app paths, business enums).
 *  2. A SECURE PDO connectivity pipeline, implemented as a strict OOP
 *     singleton (one connection per request, no globals, no repeated connects).
 *  3. Stateless, HMAC-signed CSRF tokens (no server-side token table needed).
 *  4. Escaping / sanitising helpers and a {success, message, data} JSON
 *     envelope used by any future AJAX endpoint.
 *  5. A strict Content-Security-Policy header (the project ships ZERO inline
 *     <script> and ZERO inline style="..." attributes so the policy holds).
 *
 *  Requires: PHP 8.0+, ext-pdo_mysql
 * =============================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * 1. ENVIRONMENT
 * ------------------------------------------------------------------------ */

/** Brand name shown in the header, page titles and the footer. */
define('APP_NAME', 'Addis Rentals');

/** Short brand promise, used on the splash gateway. */
define('APP_TAGLINE', 'Apartment rentals across Addis Ababa');

/** Absolute filesystem path of the project root (…/htdocs/rental-marketplace). */
define('APP_ROOT', dirname(__DIR__));

/**
 * Web base path of the project, derived from the folder name so the app works
 * whether it is mounted at /rental-marketplace/ or anywhere else.
 * Example: /rental-marketplace/
 */
define('APP_BASE', '/' . basename(APP_ROOT) . '/');

/** Set to false on a production host to hide raw database errors. */
define('APP_DEBUG', true);

/* --- MySQL / MariaDB credentials (default local development values) ------ */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'rental_marketplace');
define('DB_USER', 'root');
define('DB_PASS', '');          // empty root password on a default local install

/* --- CSRF ------------------------------------------------------------------
 * Stateless HMAC token signing key. CHANGE THIS to your own random string
 * before going live; changing it simply invalidates old form tokens.
 * ------------------------------------------------------------------------ */
define('CSRF_SECRET', 'rmk_2f8c41d7b95a4e0c8d13f6a7e5b2c490_change_me_per_deployment');
define('CSRF_TTL',    7200);    // token lifetime in seconds (2 hours)

/* --- Addis Ababa neighbourhoods -----------------------------------------
 * Single source of truth for the location dropdown, the feed filter and the
 * neighbourhood showcase. Each entry carries:
 *   am       — the Amharic name of the district
 *   landmark — the reference points tenants actually navigate by
 *   blurb    — one-line description shown on the gateway
 *   image    — photography used on the gateway card and on listing cards
 * ---------------------------------------------------------------------- */
const NEIGHBOURHOODS = [
    'Bole' => [
        'am'       => 'ቦሌ',
        'landmark' => 'Bole Road · Edna Mall · Airport corridor',
        'blurb'    => 'The commercial and shopping heart of the capital, minutes from the airport.',
        'image'    => 'assets/neighbourhood-bole.jpg',
    ],
    'Kazanchis' => [
        'am'       => 'ካዛንቺስ',
        'landmark' => 'African Union quarter · Kazanchis business district',
        'blurb'    => 'Corporate towers and serviced flats beside the diplomatic quarter.',
        'image'    => 'assets/neighbourhood-kazanchis.jpg',
    ],
    'Old Airport' => [
        'am'       => 'ኦልድ አየርፖርት',
        'landmark' => 'Embassy row · International schools',
        'blurb'    => 'Leafy diplomatic streets around the old airfield.',
        'image'    => 'assets/neighbourhood-addis.jpg',
    ],
    'CMC' => [
        'am'       => 'ሲኤምሲ',
        'landmark' => 'CMC Michael Church · Fresh Corner',
        'blurb'    => 'Gated family compounds and quiet internal roads.',
        'image'    => 'assets/neighbourhood-cmc.jpg',
    ],
    'Sarbet' => [
        'am'       => 'ሳርቤት',
        'landmark' => 'Sarbet taxi terminal · Ring Road',
        'blurb'    => 'Hillside blocks above the ring road, close to everything.',
        'image'    => 'assets/neighbourhood-sarbet.jpg',
    ],
    'Lebu' => [
        'am'       => 'ለቡ',
        'landmark' => 'Jimma Road · Lebu St. Michael',
        'blurb'    => 'Fast-growing western suburb along the Jimma Road.',
        'image'    => 'assets/neighbourhood-addis.jpg',
    ],
    'Ayat' => [
        'am'       => 'አያት',
        'landmark' => 'Ayat roundabout · Light-rail corridor',
        'blurb'    => 'Affordable new-builds on the city edge, good value per square metre.',
        'image'    => 'assets/neighbourhood-addis.jpg',
    ],
];

/** Neighbourhood names used for validation and the dropdowns. */
define('LOCATIONS', array_keys(NEIGHBOURHOODS));

const UNIT_TYPES = [
    'Studio',
    '1-Bedroom',
    '2-Bedroom',
    '3-Bedroom',
    'Penthouse',
];

/* --- Error reporting (development friendly) ------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Africa/Addis_Ababa');

/* ---------------------------------------------------------------------------
 * 2. SESSION (used only for flash messages + form repopulation — the app
 *    deliberately has NO user accounts and NO authentication layer)
 * ------------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
    ]);
    session_start();
}

/* ---------------------------------------------------------------------------
 * 3. SECURITY HEADERS
 *    No inline scripts / styles are used anywhere, so a strict CSP is safe.
 * ------------------------------------------------------------------------ */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self'; style-src 'self'; img-src 'self' data:; " .
        "font-src 'self'; connect-src 'self'; form-action 'self'; " .
        "base-uri 'self'; frame-ancestors 'none'"
    );
}

/* =========================================================================
 *  DATABASE  —  strict PDO singleton
 * ========================================================================= */
final class Database
{
    /** @var Database|null The single shared instance. */
    private static ?Database $instance = null;

    /** @var PDO The one live connection. */
    private PDO $pdo;

    /** Private constructor: the only place a PDO object is ever created. */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real server-side prepares
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            $this->fail($e);
        }
    }

    /** Clone disabled — a singleton must not be duplicated. */
    private function __clone()
    {
    }

    /** Unserialisation disabled — prevents instance injection. */
    public function __wakeup(): void
    {
        throw new RuntimeException('Database singleton cannot be unserialised.');
    }

    /** Returns the shared instance, creating it on first use. */
    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Direct access to the underlying PDO handle. */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Runs a SELECT and returns every row.
     *
     * @param  string               $sql    SQL with named placeholders (:name).
     * @param  array<string, mixed> $params Bound parameters.
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Runs a SELECT and returns the first row, or null when there is none. */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Runs an INSERT/UPDATE/DELETE and returns the number of affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Primary key generated by the last INSERT. */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /** Friendly, non-leaking failure screen when the connection dies. */
    private function fail(PDOException $e): void
    {
        error_log('[rental-marketplace] DB connection failed: ' . $e->getMessage());

        http_response_code(500);
        $detail = APP_DEBUG ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : '';
        $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . "<title>Database error</title>"
           . "<style>body{font-family:system-ui,Segoe UI,sans-serif;background:#f4f7f6;color:#0f172a;"
           . "display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}"
           . ".box{background:#fff;border:1px solid #e2e8f0;border-left:5px solid #3b82f6;border-radius:16px;"
           . "padding:2rem 2.25rem;max-width:620px;box-shadow:0 18px 40px rgba(15,23,42,.08)}"
           . "h1{margin:0 0 .5rem;font-size:1.35rem}p{margin:.4rem 0;line-height:1.6;color:#475569}"
           . "code{background:#f1f5f9;padding:.15rem .4rem;border-radius:6px;font-size:.85rem;color:#0369a1}"
           . "</style></head><body><div class=\"box\"><h1>{$appName}: database unavailable</h1>"
           . '<p>The listing database is currently unreachable. Confirm that the database server is running '
           . 'and that <code>rental_marketplace.sql</code> has been imported, then reload this page.</p>'
           . "<p>Check the credentials in <code>config/db.php</code> "
           . '(host ' . DB_HOST . ', database ' . DB_NAME . ', user ' . DB_USER . ').</p>'
           . ($detail !== '' ? "<p><em>Details:</em> {$detail}</p>" : '')
           . '</div></body></html>';
        exit;
    }
}

/* =========================================================================
 *  SECURITY HELPERS  —  escaping, stateless CSRF, validation
 * ========================================================================= */
final class Security
{
    /**
     * Builds a stateless CSRF token: "<expiry>.<hmac>".
     * Nothing is stored on the server, so tokens survive session loss.
     */
    public static function csrfToken(): string
    {
        $expiry = time() + CSRF_TTL;
        return $expiry . '.' . hash_hmac('sha256', (string) $expiry, CSRF_SECRET);
    }

    /** Constant-time verification of a token produced by csrfToken(). */
    public static function csrfVerify(?string $token): bool
    {
        if ($token === null || !str_contains($token, '.')) {
            return false;
        }
        [$expiry, $mac] = explode('.', $token, 2);

        if (!ctype_digit($expiry) || (int) $expiry < time()) {
            return false;   // malformed or expired
        }
        $expected = hash_hmac('sha256', $expiry, CSRF_SECRET);
        return hash_equals($expected, $mac);
    }

    /** Escapes a value for safe HTML output (XSS defence). */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Normalises free text: trims, strips control characters, collapses long
     * whitespace runs and hard-caps the length.
     */
    public static function clean(?string $value, int $maxLength = 500): string
    {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? '';
        $value = trim($value);
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    /** Accepts Ethiopian and international mobile formats, converts to +251… */
    public static function normalisePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $raw) ?? '';
        $bare   = ltrim($digits, '+');

        if (preg_match('/^2519\d{8}$/', $bare)) {          // 2519XXXXXXXX
            return '+' . $bare;
        }
        if (preg_match('/^09\d{8}$/', $bare)) {            // 09XXXXXXXX
            return '+251' . substr($bare, 1);
        }
        if (preg_match('/^9\d{8}$/', $bare)) {             // 9XXXXXXXX
            return '+251' . $bare;
        }
        if (preg_match('/^07\d{8}$/', $bare)) {            // 07XXXXXXXX (Safaricom ET)
            return '+251' . substr($bare, 1);
        }
        return null;
    }

    /** Validates an enum value against one of the whitelists in this file. */
    public static function inList(string $value, array $list): bool
    {
        return in_array($value, $list, true);
    }
}

/* =========================================================================
 *  HTTP / FLASH HELPERS
 * ========================================================================= */

/** Stores a one-shot message for the next request. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Reads and immediately clears the pending flash message. */
function flash_pull(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Keeps submitted values so a failed form can be refilled. */
function old_remember(array $data): void
{
    $_SESSION['old'] = $data;
}

/** Reads one previously submitted value. */
function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['old'][$key] ?? $default);
}

/** Drops the remembered form values (called after a successful insert). */
function old_clear(): void
{
    unset($_SESSION['old']);
}

/**
 * Standard API envelope: {success, message, data}.
 * Only used by JSON endpoints; page scripts render HTML instead.
 */
function json_response(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Reads a GET parameter as a STRING, even if the client sent an array
 * (?location[]=Bole). Untrusted input must never reach a string-typed
 * parameter, otherwise PHP throws a TypeError.
 */
function query_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? null;
    return is_string($value) ? $value : $default;
}

/** Reads a POST field as a STRING — see query_str() for the rationale. */
function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? null;
    return is_string($value) ? $value : $default;
}

/* ---------------------------------------------------------------------------
 * NEIGHBOURHOOD LOOKUP HELPERS
 * ------------------------------------------------------------------------ */

/** Amharic name of a district, e.g. "ቦሌ". */
function neighbourhood_am(string $location): string
{
    return NEIGHBOURHOODS[$location]['am'] ?? '';
}

/** Landmark line for a district, e.g. "Bole Road · Edna Mall". */
function neighbourhood_landmark(string $location): string
{
    return NEIGHBOURHOODS[$location]['landmark'] ?? '';
}

/** One-line description of a district. */
function neighbourhood_blurb(string $location): string
{
    return NEIGHBOURHOODS[$location]['blurb'] ?? '';
}

/**
 * Photograph used for a district (falls back to the generic Addis Ababa
 * street scene for districts without their own shot).
 */
function neighbourhood_image(string $location): string
{
    return NEIGHBOURHOODS[$location]['image'] ?? 'assets/neighbourhood-addis.jpg';
}

/** Builds an absolute-safe application URL: url('explore.php') → /…/explore.php */
function url(string $path = ''): string
{
    return APP_BASE . ltrim($path, '/');
}

/** Formats a rent amount as "12,000.00". */
function money(float $amount): string
{
    return number_format($amount, 2, '.', ',');
}

/** Formats a MySQL timestamp as "12 Mar 2026". */
function pretty_date(?string $timestamp): string
{
    if (!$timestamp) {
        return '—';
    }
    $ts = strtotime($timestamp);
    return $ts ? date('d M Y', $ts) : '—';
}

/** Renders a <time> element holding a MySQL datetime. */
function relative_age(?string $timestamp): string
{
    if (!$timestamp) {
        return '';
    }
    $seconds = time() - (int) strtotime($timestamp);
    if ($seconds < 3600) {
        return max(1, (int) floor($seconds / 60)) . ' min ago';
    }
    if ($seconds < 86400) {
        return (int) floor($seconds / 3600) . ' h ago';
    }
    return (int) floor($seconds / 86400) . ' d ago';
}
