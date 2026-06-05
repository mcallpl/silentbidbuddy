<?php
// ============================================================
// API ENDPOINT: Get Paginated Bids List
// GET /api/admin/get-bids.php?page=1&limit=50
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$page = max(1, $page);
$limit = min($limit, 100);

$offset = ($page - 1) * $limit;

// Get total count
$total = dbGetValue("SELECT COUNT(*) FROM bids");

// Get bids with item and user details
$bids = dbGetAll(
    "SELECT
        b.id,
        b.item_id,
        b.user_id,
        b.bid_amount,
        b.created_at,
        i.title as item_title,
        i.item_number,
        i.current_high_bid,
        i.is_closed,
        u.full_name,
        CONCAT(SUBSTR(u.phone_number, 1, 6), '...', SUBSTR(u.phone_number, -4)) as phone_display
     FROM bids b
     JOIN items i ON i.id = b.item_id
     JOIN users u ON u.id = b.user_id
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?",
    [$limit, $offset]
);

// Format response
foreach ($bids as &$bid) {
    $bid['bid_amount'] = (float)$bid['bid_amount'];
    $bid['current_high_bid'] = (float)$bid['current_high_bid'];
    $bid['is_closed'] = (bool)$bid['is_closed'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'bids' => $bids,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);

?>
