<?php
// ============================================================
// API ENDPOINT: Get Current Admin Info
// GET /api/admin/get-current-admin.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');

$admin = getCurrentAdmin();

if (!$admin) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'admin' => [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'is_super_admin' => (bool)$admin['is_super_admin']
    ]
]);
?>
