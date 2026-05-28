<?php
// ============================================================
// DIAGNOSTIC ENDPOINT: Admin Auth Troubleshooting
// GET /api/admin/diagnostic.php?key=<secret>
// Use only for debugging auth issues
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

header('Content-Type: application/json');

// Require a secret key to prevent unauthorized access
$secret_key = 'admin_diagnostic_' . md5(ADMIN_TOKEN ?? 'notset');
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'php_version_id' => PHP_VERSION_ID,
    'config' => [
        'ADMIN_TOKEN_SET' => !empty(ADMIN_TOKEN),
        'ADMIN_TOKEN_LENGTH' => strlen(ADMIN_TOKEN ?? ''),
        'COOKIE_DOMAIN' => COOKIE_DOMAIN,
        'COOKIE_NAME' => ADMIN_COOKIE_NAME,
        'COOKIE_LIFETIME' => ADMIN_COOKIE_LIFETIME,
    ],
    'request' => [
        'HTTPS' => !empty($_SERVER['HTTPS']),
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ],
    'cookies' => [
        'admin_session_token_present' => !empty($_COOKIE[ADMIN_COOKIE_NAME] ?? ''),
        'admin_session_token_value_match' => !empty($_COOKIE[ADMIN_COOKIE_NAME]) && $_COOKIE[ADMIN_COOKIE_NAME] === ADMIN_TOKEN,
        'all_cookies' => array_keys($_COOKIE),
    ],
    'headers' => [
        'HTTP_AUTHORIZATION' => !empty($_SERVER['HTTP_AUTHORIZATION']),
        'REDIRECT_HTTP_AUTHORIZATION' => !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),
    ],
    'auth_status' => [
        'is_logged_in' => isAdminLoggedIn(),
        'has_valid_token' => !empty($_COOKIE[ADMIN_COOKIE_NAME] ?? '') && validateAdminToken($_COOKIE[ADMIN_COOKIE_NAME]),
    ]
];

http_response_code(200);
echo json_encode($diagnostics, JSON_PRETTY_PRINT);

?>
