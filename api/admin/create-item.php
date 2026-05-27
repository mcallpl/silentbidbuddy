<?php
// ============================================================
// API ENDPOINT: Create Item (Admin)
// POST /api/admin/create-item.php
// ============================================================


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get input (allow both JSON and form data)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Validate required fields
$required = ['title', 'starting_bid', 'min_increment', 'auction_end_time'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => "$field is required"]));
    }
}

// Get next item number
$last_item = dbGetRow(
    "SELECT item_number FROM items ORDER BY item_number DESC LIMIT 1"
);
$next_item_number = ($last_item ? $last_item['item_number'] : 0) + 1;

// Insert item
$item_id = dbInsert(
    "INSERT INTO items
     (item_number, title, description, image_url, fair_market_value,
      starting_bid, min_increment, buy_now_price, current_high_bid,
      auction_start_time, auction_end_time, is_closed, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    [
        (int)$next_item_number,
        $input['title'],
        $input['description'] ?? '',
        $input['image_url'] ?? '',
        !empty($input['fair_market_value']) ? (float)$input['fair_market_value'] : null,
        (float)$input['starting_bid'],
        (float)$input['min_increment'],
        !empty($input['buy_now_price']) ? (float)$input['buy_now_price'] : null,
        0.00,
        date('Y-m-d H:i:s'), // Now
        $input['auction_end_time']
    ]
);

if (!$item_id) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Failed to create item']));
}

// Log audit event
dbInsert(
    "INSERT INTO audit_log (event_type, item_id, description, created_at)
     VALUES (?, ?, ?, NOW())",
    ['ITEM_CREATED', (int)$item_id, 'Item created: ' . $input['title']]
);

http_response_code(201);
echo json_encode([
    'status' => 'ok',
    'message' => 'Item created successfully',
    'item' => [
        'id' => $item_id,
        'item_number' => $next_item_number,
        'title' => $input['title'],
        'qr_url' => APP_DOMAIN . '/item.php?id=' . urlencode($next_item_number)
    ]
]);

?>
