<?php
// ============================================================
// API ENDPOINT: Get Live Bid Feed
// GET /api/bidding/get-live-feed.php?id=ITEM_ID&limit=20
// ============================================================


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/bidding.php';

header('Content-Type: application/json');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$item_id = (int)($_GET['id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 20);

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Item ID required']));
}

// Validate limit
$limit = min($limit, 100);

// Get recent bids
$bids = getRecentBids($item_id, $limit);

// Format response
$formatted_bids = [];
foreach ($bids as $bid) {
    $formatted_bids[] = [
        'id' => (int)$bid['id'],
        'bid_amount' => (float)$bid['bid_amount'],
        'bidder_name' => $bid['full_name'] ?: 'Anonymous',
        'created_at' => $bid['created_at'],
        'time_ago' => getTimeAgo($bid['created_at'])
    ];
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'bids' => $formatted_bids,
    'count' => count($formatted_bids)
]);

/**
 * Format timestamp as "time ago" string
 */
function getTimeAgo($timestamp) {
    $time_now = time();
    $time_then = strtotime($timestamp);
    $diff = $time_now - $time_then;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

?>
