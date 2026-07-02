<?php
// ============================================================
// ADMIN CRUD ENDPOINT: Admin Accounts Management
// Requires super admin privileges
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// SECURITY: managing admin accounts requires SUPER ADMIN privileges. Previously
// this only checked isAdminLoggedIn(), so any authenticated admin (even a
// single-event, non-super admin) could create super-admin accounts or reset
// any admin's password — a privilege-escalation hole.
$currentAdmin = getAuthenticatedAdminAccount();
if (!$currentAdmin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin session required.']));
}
if (empty($currentAdmin['is_super_admin'])) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Forbidden. Super admin privileges required.']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleListAdmins();
        break;
    case 'get':
        handleGetAdmin();
        break;
    case 'create':
        handleCreateAdmin();
        break;
    case 'update':
        handleUpdateAdmin();
        break;
    case 'toggle-active':
        handleToggleActive();
        break;
    case 'reset-password':
        handleResetPassword();
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function handleListAdmins() {
    $admins = dbGetAll(
        "SELECT id, username, email, full_name, is_super_admin, is_active, last_login, created_at
         FROM admin_accounts ORDER BY created_at DESC"
    );

    echo json_encode([
        'status' => 'ok',
        'data' => $admins ?? []
    ]);
}

function handleGetAdmin() {
    $admin_id = (int)($_GET['admin_id'] ?? 0);
    if (!$admin_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'admin_id required']));
    }

    $admin = dbGetRow(
        "SELECT id, username, email, full_name, is_super_admin, is_active, last_login, created_at FROM admin_accounts WHERE id = ?",
        [$admin_id]
    );

    if (!$admin) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Admin not found']));
    }

    echo json_encode(['status' => 'ok', 'data' => $admin]);
}

function handleCreateAdmin() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
    }

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $email = $input['email'] ?? '';
    $full_name = $input['full_name'] ?? '';
    $is_super_admin = $input['is_super_admin'] ?? 0;

    $admin_id = createAdminAccount($username, $password, $email, $full_name);

    if (!$admin_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Failed to create admin account']));
    }

    if ($is_super_admin) {
        dbUpdate(
            "UPDATE admin_accounts SET is_super_admin = 1 WHERE id = ?",
            [(int)$admin_id]
        );
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Admin account created',
        'admin_id' => $admin_id
    ]);
}

function handleUpdateAdmin() {
    global $currentAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = (int)($_GET['admin_id'] ?? 0);

    if (!$admin_id || !$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'admin_id and body required']));
    }

    // Prevent an admin from stripping their own super-admin role (self-lockout).
    if ($admin_id === (int)$currentAdmin['id']
        && isset($input['is_super_admin']) && !(int)$input['is_super_admin']) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'You cannot remove your own super-admin role.']));
    }

    $updates = [];
    $params = [];

    if (isset($input['email'])) {
        $updates[] = "email = ?";
        $params[] = $input['email'];
    }
    if (isset($input['full_name'])) {
        $updates[] = "full_name = ?";
        $params[] = $input['full_name'];
    }
    if (isset($input['is_super_admin'])) {
        $updates[] = "is_super_admin = ?";
        $params[] = (int)$input['is_super_admin'];
    }

    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
    }

    $params[] = $admin_id;
    $success = dbUpdate(
        "UPDATE admin_accounts SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Admin updated' : 'Failed to update admin'
    ]);
}

function handleToggleActive() {
    global $currentAdmin;
    $admin_id = (int)($_GET['admin_id'] ?? 0);
    if (!$admin_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'admin_id required']));
    }

    // Prevent an admin from deactivating their own account (self-lockout).
    if ($admin_id === (int)$currentAdmin['id']) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'You cannot deactivate your own account.']));
    }

    $success = dbUpdate(
        "UPDATE admin_accounts SET is_active = NOT is_active WHERE id = ?",
        [$admin_id]
    );

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Admin status toggled' : 'Failed to toggle status'
    ]);
}

function handleResetPassword() {
    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = (int)($_GET['admin_id'] ?? 0);
    $new_password = $input['password'] ?? '';

    if (!$admin_id || !$new_password) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'admin_id and password required']));
    }

    if (strlen($new_password) < 8) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']));
    }

    $password_hash = hashAdminPassword($new_password);
    $success = dbUpdate(
        "UPDATE admin_accounts SET password_hash = ? WHERE id = ?",
        [$password_hash, $admin_id]
    );

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Password reset' : 'Failed to reset password'
    ]);
}

?>
