<?php
// ============================================================
// API ENDPOINT: Update Item
// POST /api/admin/update-item.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf-generator.php';

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
$item = dbGetRow("SELECT id FROM items WHERE id = ?", [$item_id]);
if (!$item) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'Item not found']));
}

// Build update query dynamically
$updates = [];
$params = [];

if (isset($input['title'])) {
    $updates[] = "title = ?";
    $params[] = $input['title'];
}

if (isset($input['description'])) {
    $updates[] = "description = ?";
    $params[] = $input['description'];
}

if (isset($input['image_url'])) {
    $updates[] = "image_url = ?";
    $params[] = $input['image_url'];
}

if (isset($input['fair_market_value'])) {
    $updates[] = "fair_market_value = ?";
    $params[] = !empty($input['fair_market_value']) ? (float)$input['fair_market_value'] : null;
}

if (isset($input['starting_bid'])) {
    $updates[] = "starting_bid = ?";
    $params[] = (float)$input['starting_bid'];
}

if (isset($input['min_increment'])) {
    $updates[] = "min_increment = ?";
    $params[] = (float)$input['min_increment'];
}

if (isset($input['buy_now_price'])) {
    $updates[] = "buy_now_price = ?";
    $params[] = !empty($input['buy_now_price']) ? (float)$input['buy_now_price'] : null;
}

if (empty($updates)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
}

$params[] = $item_id;

$query = "UPDATE items SET " . implode(", ", $updates) . " WHERE id = ?";
$result = dbQuery($query, $params);

if ($result) {
    // Regenerate document if QR code exists
    try {
        $item = dbGetRow("SELECT * FROM items WHERE id = ?", [$item_id]);
        if ($item && $item['qr_code_url'] && $item['short_url']) {
            $pdf_gen = new ItemPDFGenerator($item);
            $pdf_gen->generate($item['short_url'], $item['qr_code_url']);
        }
    } catch (Exception $e) {
        error_log("Error regenerating document: " . $e->getMessage());
        // Don't fail the update if document generation fails
    }

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
