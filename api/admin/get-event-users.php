<?php
// ============================================================
// API ENDPOINT: Get Users for Event
// GET /api/admin/get-event-users.php?event_id=1
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

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID required']));
}

// Authorization check
if (!$admin['is_super_admin']) {
    $event_access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$event_access) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied']));
    }
}

// Get users for event
$users = dbGetAll(
    "SELECT id, full_name, email, phone_number, user_type, created_by_admin_id, created_at
     FROM users
     WHERE event_id = ?
     ORDER BY created_at DESC",
    [(int)$event_id]
);

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'users' => $users ?: [],
    'event_id' => $event_id
]);
?>
