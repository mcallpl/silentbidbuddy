<?php
// ============================================================
// ADMIN API: Manage Organization Admin Assignments
// CRUD for admin_organizations bridge table
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
    // List all admin-organization assignments
    $assignments = dbGetAll(
        "SELECT ao.id, ao.admin_id, ao.organization_id, ao.role,
                aa.username, aa.full_name, o.name as org_name, o.slug as org_slug
         FROM admin_organizations ao
         JOIN admin_accounts aa ON aa.id = ao.admin_id
         JOIN organizations o ON o.id = ao.organization_id
         ORDER BY o.name, aa.username"
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'assignments' => $assignments]);
    exit;
}

if ($method === 'POST') {
    // Create new admin-organization assignment
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['admin_id']) || empty($data['organization_id']) || empty($data['role'])) {
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
    $org_check = dbGetRow("SELECT id FROM organizations WHERE id = ?", [(int)$data['organization_id']]);

    if (!$admin_check || !$org_check) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Admin or organization not found']));
    }

    // Check not already assigned
    $existing = dbGetRow(
        "SELECT id FROM admin_organizations WHERE admin_id = ? AND organization_id = ?",
        [(int)$data['admin_id'], (int)$data['organization_id']]
    );

    if ($existing) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Admin already assigned to this organization']));
    }

    // Create assignment
    $assignment_id = dbInsert(
        "INSERT INTO admin_organizations (admin_id, organization_id, role, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())",
        [(int)$data['admin_id'], (int)$data['organization_id'], $data['role']]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_ORG_ASSIGNED', $admin['id'], "Admin {$data['admin_id']} assigned to org {$data['organization_id']} as {$data['role']}"]
    );

    http_response_code(201);
    echo json_encode(['status' => 'ok', 'message' => 'Admin assigned to organization', 'assignment_id' => $assignment_id]);
    exit;
}

if ($method === 'PUT') {
    // Update admin-organization assignment role
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
        "UPDATE admin_organizations SET role = ?, updated_at = NOW() WHERE id = ?",
        [$data['role'], $assignment_id]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_ORG_ROLE_UPDATED', $admin['id'], "Admin organization assignment {$assignment_id} role updated to {$data['role']}"]
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Admin role updated']);
    exit;
}

if ($method === 'DELETE') {
    // Remove admin-organization assignment
    $assignment_id = (int)($_GET['id'] ?? 0);

    if (!$assignment_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Missing assignment_id']));
    }

    dbUpdate(
        "DELETE FROM admin_organizations WHERE id = ?",
        [$assignment_id]
    );

    // Audit log
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, description, created_at)
         VALUES (?, ?, ?, NOW())",
        ['ADMIN_ORG_REMOVED', $admin['id'], "Admin organization assignment {$assignment_id} removed"]
    );

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Admin removed from organization']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
