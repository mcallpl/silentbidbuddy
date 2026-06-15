<?php
// ============================================================
// ADMIN API: Manage Event Admin Assignments
// CRUD for admin_events bridge table
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$admin = requireSuperAdmin(); // Only super admins can manage admin assignments

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all admin-event assignments
    $assignments = dbGetAll(
        "SELECT ae.id, ae.admin_id, ae.event_id, ae.role,
                aa.username, aa.full_name, e.name as event_name, e.event_date,
                o.name as org_name
         FROM admin_events ae
         JOIN admin_accounts aa ON aa.id = ae.admin_id
         JOIN events e ON e.id = ae.event_id
         JOIN organizations o ON o.id = e.organization_id
         ORDER BY o.name, e.name, aa.username"
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'assignments' => $assignments]);
    exit;
}

if ($method === 'POST') {
    // Create new admin-event assignment
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['admin_id']) || empty($data['event_id']) || empty($data['role'])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
    }

    // Validate role
    if (!in_array($data['role'], ['manager', 'viewer'])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid role']));
    }

    // Check both exist
    $admin_check = dbGetRow("SELECT id FROM admin_accounts WHERE id = ?", [(int)$data['admin_id']]);
    $event_check = dbGetRow("SELECT id FROM events WHERE id = ?", [(int)$data['event_id']]);

    if (!$admin_check || !$event_check) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Admin or event not found']));
    }

    // Check not already assigned
    $existing = dbGetRow(
        "SELECT id FROM admin_events WHERE admin_id = ? AND event_id = ?",
        [(int)$data['admin_id'], (int)$data['event_id']]
    );

    if ($existing) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Admin already assigned to this event']));
    }

    // Create assignment
    $assignment_id = dbInsert(
        "INSERT INTO admin_events (admin_id, event_id, role, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())",
        [(int)$data['admin_id'], (int)$data['event_id'], $data['role']]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_EVENT_ASSIGNED', $admin['id'], "Admin {$data['admin_id']} assigned to event {$data['event_id']} as {$data['role']}"]
    );

    http_response_code(201);
    echo json_encode(['status' => 'ok', 'message' => 'Admin assigned to event', 'assignment_id' => $assignment_id]);
    exit;
}

if ($method === 'PUT') {
    // Update admin-event assignment role
    $data = json_decode(file_get_contents('php://input'), true);
    $assignment_id = (int)($_GET['id'] ?? 0);

    if (!$assignment_id || empty($data['role'])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Missing assignment_id or role']));
    }

    if (!in_array($data['role'], ['manager', 'viewer'])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid role']));
    }

    dbUpdate(
        "UPDATE admin_events SET role = ?, updated_at = NOW() WHERE id = ?",
        [$data['role'], $assignment_id]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_EVENT_ROLE_UPDATED', $admin['id'], "Admin event assignment {$assignment_id} role updated to {$data['role']}"]
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Admin role updated']);
    exit;
}

if ($method === 'DELETE') {
    // Remove admin-event assignment
    $assignment_id = (int)($_GET['id'] ?? 0);

    if (!$assignment_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Missing assignment_id']));
    }

    dbUpdate(
        "DELETE FROM admin_events WHERE id = ?",
        [$assignment_id]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_EVENT_REMOVED', $admin['id'], "Admin event assignment {$assignment_id} removed"]
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Admin removed from event']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
