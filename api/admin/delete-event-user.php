<?php
// ============================================================
// API ENDPOINT: Delete Event User
// POST /api/admin/delete-event-user.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');

// Require authentication
$admin = getCurrentAdmin();
if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$user_id = (int)($input['user_id'] ?? 0);
$event_id = (int)($input['event_id'] ?? 0);

if (!$user_id || !$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'User ID and Event ID required']));
}

// Authorization check
if (!$admin['is_super_admin']) {
    $event_access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$event_access || $event_access['role'] !== 'manager') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied - must be event manager']));
    }
}

// Verify user belongs to event
$user = dbGetRow(
    "SELECT id, event_id FROM users WHERE id = ? AND event_id = ?",
    [(int)$user_id, (int)$event_id]
);

if (!$user) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'User not found for this event']));
}

// Delete user
$success = dbDelete("DELETE FROM users WHERE id = ?", [(int)$user_id]);

if (!$success) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to delete user']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'User deleted successfully'
]);
?>
