<?php
// ============================================================
// AUTHENTICATION MODULE
// Session validation, user helpers, and auth utilities
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/session-manager.php';

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
 * CRITICAL: Users must stay logged in for 30 days without re-verification
 * @param string $token Session token
 * @return array|false User data or false if invalid
 */
function validateSessionToken($token) {
    if (empty($token)) {
        return false;
    }

    $user_email_select = dbColumnExists('users', 'email') ? ', u.email' : '';

    // Fetch session record - check if token exists and is not expired
    // Sessions expire after 30 days (SESSION_LIFETIME = 30 * 24 * 60 * 60)
    $session = dbGetRow(
        "SELECT s.user_id, s.expires_at, u.id, u.phone_number, u.full_name, u.stripe_customer_id {$user_email_select}
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.session_token = ? AND s.expires_at > NOW()",
        [(string)$token]
    );

    if (!$session) {
        return false;
    }

    // Session is valid - return user data
    return [
        'id' => $session['user_id'],
        'phone_number' => $session['phone_number'],
        'full_name' => $session['full_name'],
        'email' => $session['email'] ?? '',
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
        if (defined('DEBUG_LOG') && DEBUG_LOG) {
            error_log('[SESSION] Found session_token in cookie: ' . substr($_COOKIE['session_token'], 0, 10) . '...');
        }
        return $_COOKIE['session_token'];
    }

    if (defined('DEBUG_LOG') && DEBUG_LOG) {
        error_log('[SESSION] No session_token found. Cookie domain: ' . COOKIE_DOMAIN . ', Available cookies: ' . json_encode(array_keys($_COOKIE)));
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
 * Require admin authentication (supports both legacy token and new account system)
 * @return array|void Admin data if using account system, or dies if invalid
 */
function requireAdminAuth() {
    // Primary path: DB-validated admin account session (cookie or Bearer token).
    $admin_data = getAdminFromSession();
    if ($admin_data) {
        return $admin_data;
    }

    // Legacy ADMIN_TOKEN fallback for backward compatibility. Only usable if a
    // token is actually configured; compared in constant time.
    if (!empty(ADMIN_TOKEN)) {
        $token = null;

        // Bearer Authorization header first
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!empty($auth_header) && preg_match('/Bearer\s+(\S+)/', $auth_header, $m)) {
            $token = $m[1];
        }

        // Legacy login.php stores the raw ADMIN_TOKEN in the same cookie. That
        // value already failed the DB lookup in getAdminFromSession above, so the
        // only way past the constant-time check below is an exact secret match.
        if (empty($token)) {
            $token = $_COOKIE[ADMIN_SESSION_COOKIE_NAME] ?? '';
        }

        if (!empty($token) && hash_equals((string)ADMIN_TOKEN, (string)$token)) {
            // Synthetic super-admin identity for the single-tenant legacy token.
            return [
                'id' => 0,
                'username' => 'legacy-admin',
                'email' => null,
                'full_name' => 'Legacy Admin',
                'is_super_admin' => 1,
                'is_active' => 1,
                'organization_id' => null,
                'legacy' => true,
            ];
        }
    }

    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin authentication required.']));
}

/**
 * Build an event-scoping SQL clause for admin list endpoints.
 *
 * Enforces multi-tenant isolation: super admins see all events (or a single
 * requested event via ?event_id=), while a non-super admin is restricted to the
 * events assigned to them in admin_events. Returns a clause fragment (already
 * prefixed with " AND ...", or "" for unrestricted) plus its bound params.
 *
 * @param array  $admin   The admin row from requireAdminAuth()
 * @param string $column  Fully-qualified event_id column, e.g. "items.event_id"
 * @return array [string $sqlFragment, array $params]
 */
function adminEventScopeClause($admin, $column) {
    $requested = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

    // Super admin (or the legacy single-tenant token): unrestricted, but honor a
    // specific ?event_id= filter when the dashboard sends one.
    if (!empty($admin['is_super_admin'])) {
        return $requested > 0 ? [" AND {$column} = ?", [$requested]] : ['', []];
    }

    // Non-super admin: limited to their assigned events.
    $rows = dbGetAll("SELECT event_id FROM admin_events WHERE admin_id = ?", [(int)($admin['id'] ?? 0)]);
    $allowed = array_map(static fn($r) => (int)$r['event_id'], $rows ?: []);

    if ($requested > 0) {
        // Requested a specific event they don't have access to → return nothing.
        if (!in_array($requested, $allowed, true)) {
            return [' AND 1=0', []];
        }
        return [" AND {$column} = ?", [$requested]];
    }

    // No specific event requested → restrict to all their assigned events.
    if (empty($allowed)) {
        return [' AND 1=0', []];
    }
    $placeholders = implode(',', array_fill(0, count($allowed), '?'));
    return [" AND {$column} IN ({$placeholders})", $allowed];
}

/**
 * Resolve the list of event IDs an admin request is scoped to.
 * @return array|null  null = all events (unrestricted super admin, no filter),
 *                     [] = no access (return nothing),
 *                     [ids...] = restrict to these event IDs.
 */
function adminAllowedEventIds($admin) {
    $requested = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

    if (!empty($admin['is_super_admin'])) {
        return $requested > 0 ? [$requested] : null;
    }

    $rows = dbGetAll("SELECT event_id FROM admin_events WHERE admin_id = ?", [(int)($admin['id'] ?? 0)]);
    $allowed = array_map(static fn($r) => (int)$r['event_id'], $rows ?: []);

    if ($requested > 0) {
        return in_array($requested, $allowed, true) ? [$requested] : [];
    }
    return $allowed;
}

/**
 * Get current admin from session (new account system).
 * SECURITY: the token is validated against admin_accounts in the database —
 * a non-empty cookie alone is NOT sufficient (that was a full auth bypass).
 * Accepts the token from the session cookie or a Bearer Authorization header.
 * @return array|false Full admin row or false if not authenticated
 */
function getAdminFromSession() {
    $admin_session_token = $_COOKIE[ADMIN_SESSION_COOKIE_NAME] ?? null;

    // Also accept a Bearer token for API clients
    if (empty($admin_session_token)) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!empty($auth_header) && preg_match('/Bearer\s+(\S+)/', $auth_header, $m)) {
            $admin_session_token = $m[1];
        }
    }

    if (empty($admin_session_token)) {
        return false;
    }

    // The token must belong to a real, active admin account.
    return dbGetRow(
        "SELECT id, username, email, full_name, is_super_admin, is_active, organization_id
         FROM admin_accounts
         WHERE admin_session_token = ? AND is_active = 1",
        [(string)$admin_session_token]
    );
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
 * @param string $email Optional email address
 * @return int User ID
 */
function getOrCreateUser($phone, $full_name = '', $email = '') {
    $has_email_column = dbColumnExists('users', 'email');

    // Check if user exists
    $user = dbGetRow(
        "SELECT id FROM users WHERE phone_number = ?",
        [$phone]
    );

    if ($user) {
        // Update profile info if provided (allows users to fix details on re-login)
        if ($has_email_column && !empty($email)) {
            dbUpdate(
                "UPDATE users SET full_name = ?, email = ? WHERE id = ?",
                [$full_name, $email, (int)$user['id']]
            );
        } elseif (!empty($full_name)) {
            dbUpdate(
                "UPDATE users SET full_name = ? WHERE id = ?",
                [$full_name, (int)$user['id']]
            );
        }
        return $user['id'];
    }

    // Create new user. A UNIQUE key on phone_number (uq_phone) enforces one
    // global identity per phone. Use INSERT ... ON DUPLICATE KEY UPDATE so two
    // concurrent first-time verifications for the same phone can't create a
    // duplicate row (the loser of the race no-ops and we re-select the id below).
    if ($has_email_column) {
        dbQuery(
            "INSERT INTO users (phone_number, full_name, email) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = COALESCE(NULLIF(VALUES(full_name), ''), full_name),
                email = COALESCE(NULLIF(VALUES(email), ''), email)",
            [$phone, $full_name, $email]
        );
    } else {
        dbQuery(
            "INSERT INTO users (phone_number, full_name) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = COALESCE(NULLIF(VALUES(full_name), ''), full_name)",
            [$phone, $full_name]
        );
    }

    $row = dbGetRow("SELECT id FROM users WHERE phone_number = ?", [$phone]);
    return $row ? (int)$row['id'] : false;
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
    // Look up the most recent unused code for this phone number ONLY.
    // CRITICAL: do NOT filter by the guessed code here. The old query matched on
    // `code = ?`, so a wrong guess matched zero rows and the `attempts` counter
    // never incremented — making the 5-attempt brute-force lockout dead code.
    $record = dbGetRow(
        "SELECT id, code, attempts, UNIX_TIMESTAMP(expires_at) as expires_ts
         FROM verification_codes
         WHERE phone_number = ? AND is_used = 0
         ORDER BY created_at DESC LIMIT 1",
        [$phone]
    );

    if (!$record) {
        return false;
    }

    // Expired? (compare via Unix timestamps to avoid timezone confusion)
    if ((int)$record['expires_ts'] < time()) {
        return false;
    }

    // Too many prior attempts: lock out and burn the code so it can't be reused.
    if ((int)$record['attempts'] >= MAX_VERIFICATION_ATTEMPTS) {
        dbUpdate("UPDATE verification_codes SET is_used = 1 WHERE id = ?", [(int)$record['id']]);
        return false;
    }

    // Constant-time comparison of the guess against the real code.
    if (!hash_equals((string)$record['code'], (string)$code)) {
        // Wrong guess counts toward the lockout.
        dbUpdate("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?", [(int)$record['id']]);
        return false;
    }

    // Correct code: consume it atomically. The `AND is_used = 0` guard plus the
    // affected-rows check closes a TOCTOU race where two simultaneous requests
    // with the correct code could both create a session from one code.
    $db = getDB();
    $stmt = $db->prepare("UPDATE verification_codes SET is_used = 1 WHERE id = ? AND is_used = 0");
    $stmt->bind_param('i', $record['id']);
    $stmt->execute();
    $consumed = $stmt->affected_rows === 1;
    $stmt->close();

    return $consumed;
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

?>
