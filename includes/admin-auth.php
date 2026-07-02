<?php
// ============================================================
// ADMIN AUTHENTICATION — LEGACY COMPATIBILITY
// Token-based auth support for backward compatibility
// New system uses admin_accounts table with username/password
// ============================================================

require_once __DIR__ . '/session-manager.php';
require_once __DIR__ . '/db-helpers.php';

// Legacy token validation (deprecated, kept for backward compatibility)
if (!function_exists('validateAdminToken')) {
function validateAdminToken($token) {
    if (empty($token) || !defined('ADMIN_TOKEN')) {
        return false;
    }
    return hash_equals((string)ADMIN_TOKEN, (string)$token);
}
}

// Legacy cookie functions (deprecated, use session-manager.php instead)
if (!function_exists('setAdminCookie')) {
function setAdminCookie($admin_token) {
    setSessionCookie(ADMIN_SESSION_COOKIE_NAME, $admin_token, SESSION_COOKIE_LIFETIME);
}
}

if (!function_exists('clearAdminCookie')) {
function clearAdminCookie() {
    clearSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}
}

if (!function_exists('getAdminToken')) {
function getAdminToken() {
    return getSessionCookie(ADMIN_SESSION_COOKIE_NAME);
}
}

if (!function_exists('isAdminLoggedIn')) {
function isAdminLoggedIn() {
    // SECURITY: validate the session token against the database. Cookie presence
    // alone is NOT sufficient (that was a full admin auth bypass). Accepts either
    // a real admin_accounts session token OR the legacy ADMIN_TOKEN secret.
    $token = getSessionCookie(ADMIN_SESSION_COOKIE_NAME);
    if (empty($token)) {
        return false;
    }

    // Real, active admin account session?
    $admin = dbGetRow(
        "SELECT id FROM admin_accounts WHERE admin_session_token = ? AND is_active = 1",
        [(string)$token]
    );
    if ($admin) {
        return true;
    }

    // Legacy single-tenant ADMIN_TOKEN (constant-time compare).
    return defined('ADMIN_TOKEN') && !empty(ADMIN_TOKEN)
        && hash_equals((string)ADMIN_TOKEN, (string)$token);
}
}

if (!function_exists('requireAdminSession')) {
function requireAdminSession() {
    if (!isAdminLoggedIn()) {
        return false;
    }
    return true;
}
}

?>
