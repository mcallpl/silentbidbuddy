<?php
// ============================================================
// API ENDPOINT: Manually Close Expired Auctions
// POST /api/admin/close-auctions.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auction-closer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$result = closeExpiredAuctions();

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'closed' => $result['closed'],
    'message' => $result['message']
]);
?>
