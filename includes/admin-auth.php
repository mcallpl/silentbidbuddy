<?php
// ============================================================
// ADMIN AUTHENTICATION & SESSION MANAGEMENT
// Handles admin token validation and cookie-based sessions
// ============================================================

define('ADMIN_COOKIE_NAME', 'admin_session_token');
define('ADMIN_COOKIE_LIFETIME', 30 * 24 * 60 * 60); // 30 days

function validateAdminToken($token) {
    if (empty($token) || !defined('ADMIN_TOKEN')) {
        return false;
    }
    return $token === ADMIN_TOKEN;
}

function setAdminCookie($admin_token) {
    $expires = time() + ADMIN_COOKIE_LIFETIME;
    $path = '/';
    $domain = COOKIE_DOMAIN ?: '';
    $secure = !empty($_SERVER['HTTPS']);
    $httponly = true;

    // PHP 7.3+ supports array syntax, older versions use positional args
    if (PHP_VERSION_ID >= 70300) {
        setcookie(ADMIN_COOKIE_NAME, $admin_token, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        // For PHP < 7.3: use positional parameters
        // Note: PHP < 5.2 doesn't have httponly, but we assume >= 5.2
        setcookie(ADMIN_COOKIE_NAME, $admin_token, $expires, $path, $domain, $secure, $httponly);
    }
}

function clearAdminCookie() {
    $expires = time() - 3600;
    $path = '/';
    $domain = COOKIE_DOMAIN ?: '';
    $secure = !empty($_SERVER['HTTPS']);
    $httponly = true;

    if (PHP_VERSION_ID >= 70300) {
        setcookie(ADMIN_COOKIE_NAME, '', [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie(ADMIN_COOKIE_NAME, '', $expires, $path, $domain, $secure, $httponly);
    }
}

function getAdminToken() {
    return $_COOKIE[ADMIN_COOKIE_NAME] ?? null;
}

function isAdminLoggedIn() {
    $token = getAdminToken();
    return !empty($token) && validateAdminToken($token);
}

function requireAdminSession() {
    if (!isAdminLoggedIn()) {
        return false;
    }
    return true;
}

?>
