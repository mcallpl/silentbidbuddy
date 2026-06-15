<?php
// ============================================================
// API ENDPOINT: Create User for Event
// POST /api/admin/create-event-user.php
// ============================================================
// Event admins can create admin/viewer users for their events
// Super admins can create users for any event

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

// Validate input
$event_id = (int)($input['event_id'] ?? 0);
$full_name = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone_number = trim($input['phone_number'] ?? '');
$user_type = $input['user_type'] ?? 'bidder';

if (!$event_id || !$full_name || !$phone_number) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Event ID, full name, and phone number are required']));
}

if (!in_array($user_type, ['admin', 'viewer', 'bidder'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid user type']));
}

// Authorization check: must be super admin or event manager for this event
if (!$admin['is_super_admin']) {
    $event_access = checkAdminEventAccess($admin['id'], $event_id);
    if (!$event_access || $event_access['role'] !== 'manager') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Access denied - must be event manager']));
    }
}

// Verify event exists
$event = dbGetRow("SELECT id FROM events WHERE id = ?", [(int)$event_id]);
if (!$event) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Event not found']));
}

// Check if phone number already exists
$existing = dbGetRow(
    "SELECT id FROM users WHERE phone_number = ? AND event_id = ?",
    [$phone_number, (int)$event_id]
);
if ($existing) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'User with this phone number already exists for this event']));
}

// Create user
$user_id = dbInsert(
    "INSERT INTO users (phone_number, full_name, email, event_id, user_type, created_by_admin_id)
     VALUES (?, ?, ?, ?, ?, ?)",
    [$phone_number, $full_name, $email ?: null, (int)$event_id, $user_type, (int)$admin['id']]
);

if (!$user_id) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to create user']));
}

http_response_code(201);
echo json_encode([
    'status' => 'ok',
    'message' => "User created successfully",
    'user_id' => $user_id,
    'user' => [
        'id' => $user_id,
        'full_name' => $full_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'user_type' => $user_type,
        'event_id' => $event_id
    ]
]);
?>
