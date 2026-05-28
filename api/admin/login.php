<?php
// ============================================================
// API ENDPOINT: Admin Login
// POST /api/admin/login.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Ensure ADMIN_TOKEN is configured
if (empty(ADMIN_TOKEN)) {
    http_response_code(500);
    error_log("FATAL: ADMIN_TOKEN not configured");
    die(json_encode(['status' => 'error', 'message' => 'Server configuration error: admin token not found']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$token = $input['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Token is required']));
}

// Validate token matches ADMIN_TOKEN exactly (strict comparison)
if ($token !== ADMIN_TOKEN) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid admin token']));
}

// Set the admin cookie
setAdminCookie($token);

// Verify cookie was actually set (defensive check)
if (empty($_COOKIE[ADMIN_COOKIE_NAME] ?? '')) {
    error_log("WARNING: Admin cookie not immediately readable after setAdminCookie() call");
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Logged in successfully'
]);

?>
