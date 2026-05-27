<?php
// ============================================================
// API ENDPOINT: Close Auction (Admin)
// POST /api/admin/close-auction.php
// Manually triggers auction closing and winner processing
// ============================================================

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auction-engine.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// TODO: Add admin authentication check here
// For now, we'll rely on server-side access control

$result = closeExpiredAuctions();

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'Auction closing process completed',
    'closed_items' => $result['closed_count'],
    'errors' => $result['errors']
]);

?>
