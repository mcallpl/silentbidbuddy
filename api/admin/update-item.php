<?php
// ============================================================
// API ENDPOINT: Update Item
// POST /api/admin/update-item.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

requireAdminAuth();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
}

$item_id = (int)($input['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'item_id is required']));
}

// Verify item exists
$item = dbGetRow("SELECT id FROM items WHERE id = ?", ['i', $item_id]);
if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Build update query dynamically
$updates = [];
$params = [];
$types = '';

if (isset($input['title'])) {
    $updates[] = "title = ?";
    $types .= 's';
    $params[] = $input['title'];
}

if (isset($input['description'])) {
    $updates[] = "description = ?";
    $types .= 's';
    $params[] = $input['description'];
}

if (isset($input['image_url'])) {
    $updates[] = "image_url = ?";
    $types .= 's';
    $params[] = $input['image_url'];
}

if (isset($input['fair_market_value'])) {
    $updates[] = "fair_market_value = ?";
    $types .= 'd';
    $params[] = (float)$input['fair_market_value'];
}

if (isset($input['starting_bid'])) {
    $updates[] = "starting_bid = ?";
    $types .= 'd';
    $params[] = (float)$input['starting_bid'];
}

if (isset($input['min_increment'])) {
    $updates[] = "min_increment = ?";
    $types .= 'd';
    $params[] = (float)$input['min_increment'];
}

if (isset($input['buy_now_price'])) {
    $updates[] = "buy_now_price = ?";
    $types .= 'd';
    $params[] = (float)$input['buy_now_price'];
}

if (empty($updates)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
}

$types .= 'i';
$params[] = $item_id;

$query = "UPDATE items SET " . implode(", ", $updates) . " WHERE id = ?";
$result = dbQuery($query, $types, ...$params);

if ($result) {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Item updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update item'
    ]);
}

?>
