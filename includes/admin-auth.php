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
    $cookie_options = [
        'expires' => time() + ADMIN_COOKIE_LIFETIME,
        'path' => '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    // Use setcookie() with array syntax for PHP 7.3+
    if (PHP_VERSION_ID >= 70300) {
        setcookie(ADMIN_COOKIE_NAME, $admin_token, $cookie_options);
    } else {
        setcookie(
            ADMIN_COOKIE_NAME,
            $admin_token,
            $cookie_options['expires'],
            $cookie_options['path'],
            $cookie_options['domain'],
            $cookie_options['secure'],
            $cookie_options['httponly']
        );
    }
}

function clearAdminCookie() {
    setcookie(ADMIN_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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
