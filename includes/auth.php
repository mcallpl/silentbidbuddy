<?php
// ============================================================
// AUTHENTICATION MODULE
// Session validation, user helpers, and auth utilities
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Normalize phone number to E.164 format (+1XXXXXXXXXX)
 * @param string $phone Raw phone number
 * @return string|false E.164 formatted phone or false if invalid
 */
function normalizePhone($phone) {
    // Remove all non-digit characters
    $cleaned = preg_replace('/\D/', '', $phone);

    // Handle 10-digit US numbers (add country code)
    if (strlen($cleaned) === 10) {
        $cleaned = '1' . $cleaned;
    }

    // Validate length (must be 11 for US)
    if (strlen($cleaned) !== 11) {
        return false;
    }

    return '+' . $cleaned;
}

/**
 * Validate session token and return user data if valid
 * @param string $token Session token
 * @return array|false User data or false if invalid
 */
function validateSessionToken($token) {
    if (empty($token)) {
        return false;
    }

    // Fetch session record
    $session = dbGetRow(
        "SELECT s.user_id, s.expires_at, u.id, u.phone_number, u.full_name, u.stripe_customer_id
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.session_token = ? AND s.expires_at > NOW()",
        [(string)$token]
    );

    if (!$session) {
        return false;
    }

    return [
        'id' => $session['id'],
        'phone_number' => $session['phone_number'],
        'full_name' => $session['full_name'],
        'stripe_customer_id' => $session['stripe_customer_id']
    ];
}

/**
 * Get session token from Authorization header, request body, GET param, or cookie
 * @return string|null
 */
function getSessionToken() {
    // Check Authorization header (for API calls)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/', $auth_header, $matches)) {
            return $matches[1];
        }
    }

    // Check request body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['session_token'])) {
        return $input['session_token'];
    }

    // Check GET parameter (for page loads from client redirects)
    if (!empty($_GET['session_token'])) {
        return $_GET['session_token'];
    }

    // Check cookie
    if (!empty($_COOKIE['session_token'])) {
        return $_COOKIE['session_token'];
    }

    return null;
}

/**
 * Require valid session and return user data
 * Dies with JSON error if invalid
 * @return array User data
 */
function requireAuth() {
    $token = getSessionToken();
    $user = validateSessionToken($token);

    if (!$user) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Unauthorized. Invalid or expired session.'
        ]));
    }

    return $user;
}

/**
 * Create new session for user
 * @param int $user_id User ID
 * @return string Session token
 */
function createSession($user_id) {
    $token = bin2hex(random_bytes(32)); // 64-char token
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    dbInsert(
        "INSERT INTO sessions (user_id, session_token, expires_at, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)",
        [
            (int)$user_id,
            $token,
            $expires_at,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]
    );

    return $token;
}

/**
 * Invalidate a session
 * @param string $token Session token
 * @return bool
 */
function destroySession($token) {
    return dbDelete(
        "DELETE FROM sessions WHERE session_token = ?",
        [(string)$token]
    );
}

/**
 * Get or create user by phone number
 * @param string $phone Normalized phone number
 * @param string $full_name Optional name
 * @return int User ID
 */
function getOrCreateUser($phone, $full_name = '') {
    // Check if user exists
    $user = dbGetRow(
        "SELECT id FROM users WHERE phone_number = ?",
        [$phone]
    );

    if ($user) {
        return $user['id'];
    }

    // Create new user
    return dbInsert(
        "INSERT INTO users (phone_number, full_name) VALUES (?, ?)",
        [$phone, $full_name]
    );
}

/**
 * Generate verification code
 * @return string 6-digit code
 */
function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Create verification code for phone number
 * @param string $phone Normalized phone number
 * @return string Generated code
 */
function createVerificationCode($phone) {
    // Invalidate previous codes
    dbUpdate(
        "UPDATE verification_codes SET is_used = 1 WHERE phone_number = ? AND is_used = 0",
        [$phone]
    );

    $code = generateVerificationCode();
    $expires_at = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_LIFETIME);

    dbInsert(
        "INSERT INTO verification_codes (phone_number, code, expires_at, attempts)
         VALUES (?, ?, ?, ?)",
        [$phone, $code, $expires_at, 0]
    );

    return $code;
}

/**
 * Verify a verification code
 * @param string $phone Normalized phone number
 * @param string $code Code to verify
 * @return bool
 */
function verifyCode($phone, $code) {
    // Get the code record
    $record = dbGetRow(
        "SELECT id, attempts, is_used, expires_at FROM verification_codes
         WHERE phone_number = ? AND code = ? AND is_used = 0
         ORDER BY created_at DESC LIMIT 1",
        [$phone, (string)$code]
    );

    if (!$record) {
        return false;
    }

    // Check if expired
    if (strtotime($record['expires_at']) < time()) {
        return false;
    }

    // Check attempt count
    if ($record['attempts'] >= MAX_VERIFICATION_ATTEMPTS) {
        return false;
    }

    // Increment attempts
    dbUpdate(
        "UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?",
        [(int)$record['id']]
    );

    // Mark as used on successful verification
    dbUpdate(
        "UPDATE verification_codes SET is_used = 1 WHERE id = ?",
        [(int)$record['id']]
    );

    return true;
}

/**
 * Check if phone number has exceeded rate limit for code requests
 * @param string $phone Normalized phone number
 * @return bool True if over limit
 */
function isPhoneRateLimited($phone) {
    $count = dbCount(
        'verification_codes',
        'phone_number = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
        [$phone]
    );

    return $count >= RATE_LIMIT_CODES_PER_MINUTE;
}

/**
 * Get current authenticated user
 * @return array|false User data or false if not authenticated
 */
function getCurrentUser() {
    $token = getSessionToken();
    if (!$token) {
        return false;
    }

    return validateSessionToken($token);
}

/**
 * Check if current request is authenticated
 * @return bool
 */
function isAuthenticated() {
    return getCurrentUser() !== false;
}

