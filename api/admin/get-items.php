<?php
// ============================================================
// API ENDPOINT: Get Paginated Items List
// GET /api/admin/get-items.php?page=1&limit=50
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$admin = requireAdminAuth();

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$page = max(1, $page);
$limit = max(1, min($limit, 100)); // Clamp 1..100

$offset = ($page - 1) * $limit;

// Multi-tenant scoping: restrict to the requested/assigned event(s).
list($scopeSql, $scopeParams) = adminEventScopeClause($admin, 'items.event_id');

// Get total count (scoped)
$total = (int)dbGetValue(
    "SELECT COUNT(*) FROM items WHERE 1=1{$scopeSql}",
    $scopeParams
);

// Get items for current page (scoped)
$items = dbGetAll(
    "SELECT id, item_number, title, image_url, fair_market_value, starting_bid,
            current_high_bid, is_closed, auction_end_time,
            (SELECT COUNT(*) FROM bids WHERE bids.item_id = items.id) as bid_count
     FROM items
     WHERE 1=1{$scopeSql}
     ORDER BY item_number ASC
     LIMIT ? OFFSET ?",
    array_merge($scopeParams, [$limit, $offset])
);

// Calculate time remaining for each item
foreach ($items as &$item) {
    $end_time = strtotime($item['auction_end_time']);
    $now = time();
    $item['time_remaining_seconds'] = max(0, $end_time - $now);
    $item['current_high_bid'] = (float)$item['current_high_bid'];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'items' => $items,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);

?>
