<?php
// ============================================================
// SILENT BID BUDDY — Central Configuration
// Loads vault secrets and establishes database connection
// ============================================================

// Load vault secrets (shared across all projects)
$vault_path = dirname(__DIR__) . '/vault/secrets.php';
if (!file_exists($vault_path)) {
    die("ERROR: Vault secrets file not found at $vault_path\n");
}
require_once $vault_path;

// Override with local config if it exists (for server-specific settings)
$local_config_path = __DIR__ . '/config.local.php';
if (file_exists($local_config_path)) {
    require_once $local_config_path;
}

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', $vault_db_user ?? 'root');
define('DB_PASS', $vault_db_pass ?? '');
define('DB_NAME', 'silentbidbuddy');

// ============================================================
// STRIPE CONFIGURATION
// ============================================================
define('STRIPE_SECRET_KEY', $vault_stripe_secret_key ?? '');
define('STRIPE_PUBLISHABLE_KEY', $vault_stripe_publishable_key ?? '');
define('STRIPE_WEBHOOK_SECRET', $vault_stripe_webhook_secrets['silentbidbuddy'] ?? '');

// ============================================================
// TWILIO CONFIGURATION
// ============================================================
define('TWILIO_ACCOUNT_SID', $vault_twilio_sid ?? '');
define('TWILIO_AUTH_TOKEN', $vault_twilio_token ?? '');
define('TWILIO_PHONE_NUMBER', $vault_twilio_phone ?? '');

// ============================================================
// APPLICATION CONFIGURATION
// ============================================================
define('SESSION_LIFETIME', 30 * 24 * 60 * 60); // 30 days in seconds
define('VERIFICATION_CODE_LIFETIME', 15 * 60); // 15 minutes
define('MAX_VERIFICATION_ATTEMPTS', 5);
define('RATE_LIMIT_CODES_PER_MINUTE', 5);
define('ANTI_SNIPING_MINUTES', 2);

define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('QR_CODES_DIR', __DIR__ . '/qr_codes/');

define('APP_DOMAIN', getenv('APP_DOMAIN') ?: 'http://localhost');
define('APP_NAME', 'Silent Bid Buddy');

// ============================================================
// DATABASE CONNECTION SINGLETON
// ============================================================
function getDB() {
    static $db = null;

    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($db->connect_error) {
            error_log("Database connection failed: " . $db->connect_error);
            die(json_encode([
                'status' => 'error',
                'message' => 'Database connection failed'
            ]));
        }

        // Set charset to UTF-8
        $db->set_charset('utf8mb4');

        // Set timezone to America/Los_Angeles
        $offset = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->format('P');
        $db->query("SET time_zone = '$offset'");
    }

    return $db;
}

// ============================================================
// ERROR HANDLING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Ensure logs directory exists
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================================
// SESSION CONFIGURATION
// ============================================================
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
