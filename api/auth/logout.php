<?php
// ============================================================
// API ENDPOINT: Bidder Logout
// POST /api/auth/logout.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$token = getSessionToken();
if ($token) {
    destroySession($token);
}

clearSessionCookie(SESSION_COOKIE_NAME);

echo json_encode(['status' => 'ok', 'message' => 'Signed out']);
?>
