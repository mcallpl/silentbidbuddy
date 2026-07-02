<?php
// ============================================================
// ADMIN CRUD ENDPOINT: Items Management
// Requires super admin privileges
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/admin-accounts.php';
require_once __DIR__ . '/../../includes/session-manager.php';

header('Content-Type: application/json');

// Check if admin is logged in (DB-validated) and capture identity for scoping.
$currentAdmin = getAuthenticatedAdminAccount();
if (!$currentAdmin) {
    error_log('[ADMIN CRUD] ❌ Admin not logged in - Auth check failed');
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin session required.']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
error_log('[ADMIN CRUD] ✓ Admin authenticated - Proceeding with action: ' . ($action ?: 'none'));

/**
 * Allowed event IDs for the current admin: null = all (super admin), else a list.
 */
function currentAdminAllowedEvents($admin) {
    if (!empty($admin['is_super_admin'])) {
        return null;
    }
    $rows = dbGetAll("SELECT event_id FROM admin_events WHERE admin_id = ?", [(int)($admin['id'] ?? 0)]);
    return array_map(static fn($r) => (int)$r['event_id'], $rows ?: []);
}

/**
 * Can the current admin manage this item (tenant isolation by event)?
 */
function adminCanAccessItem($admin, $item_id) {
    if (!empty($admin['is_super_admin'])) {
        return true;
    }
    $event_id = dbGetValue("SELECT event_id FROM items WHERE id = ?", [(int)$item_id]);
    if ($event_id === null || $event_id === false) {
        return false;
    }
    $allowed = currentAdminAllowedEvents($admin);
    return in_array((int)$event_id, $allowed ?? [], true);
}

switch ($action) {
    case 'list':
        handleListItems();
        break;
    case 'get':
        handleGetItem();
        break;
    case 'create':
        handleCreateItem();
        break;
    case 'update':
        handleUpdateItem();
        break;
    case 'delete':
        handleDeleteItem();
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function handleListItems() {
    global $currentAdmin;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Multi-tenant scoping.
    $requested = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    $allowed = currentAdminAllowedEvents($currentAdmin); // null = all
    $where = "1=1";
    $params = [];
    if ($allowed === null) {
        if ($requested > 0) { $where .= " AND event_id = ?"; $params[] = $requested; }
    } elseif ($requested > 0) {
        if (!in_array($requested, $allowed, true)) { $where .= " AND 1=0"; }
        else { $where .= " AND event_id = ?"; $params[] = $requested; }
    } elseif (empty($allowed)) {
        $where .= " AND 1=0";
    } else {
        $ph = implode(',', array_fill(0, count($allowed), '?'));
        $where .= " AND event_id IN ($ph)";
        $params = array_merge($params, $allowed);
    }

    $items = dbGetAll(
        "SELECT id, item_number, title, starting_bid, current_high_bid, current_high_bidder_id,
                auction_start_time, auction_end_time, is_closed, created_at
         FROM items WHERE {$where} ORDER BY item_number ASC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );

    $total = (int)dbGetValue("SELECT COUNT(*) FROM items WHERE {$where}", $params);

    echo json_encode([
        'status' => 'ok',
        'data' => $items ?? [],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetItem() {
    global $currentAdmin;
    $item_id = (int)($_GET['item_id'] ?? 0);
    if (!$item_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id required']));
    }

    if (!adminCanAccessItem($currentAdmin, $item_id)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden: item belongs to another event.']));
    }

    $item = dbGetRow(
        "SELECT i.*, u.full_name as winner_name FROM items i
         LEFT JOIN users u ON i.current_high_bidder_id = u.id
         WHERE i.id = ?",
        [$item_id]
    );

    if (!$item) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Item not found']));
    }

    echo json_encode(['status' => 'ok', 'data' => $item]);
}

function handleCreateItem() {
    global $currentAdmin;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
    }

    $required = ['title', 'starting_bid', 'auction_end_time'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => "$field required"]));
        }
    }

    // Auto-assign the next lot number if the form didn't supply one (the item
    // form has no item_number field).
    $item_number = (int)($input['item_number'] ?? 0);
    if ($item_number <= 0) {
        $item_number = ((int)dbGetValue("SELECT COALESCE(MAX(item_number), 100) FROM items")) + 1;
    }

    // An item MUST belong to an event, or it is invisible to bidders (the public
    // catalog filters by event_id). Require and authorize the event.
    $event_id = (int)($input['event_id'] ?? 0);
    if (!$event_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'event_id is required to create an item.']));
    }
    $allowed = currentAdminAllowedEvents($currentAdmin);
    if ($allowed !== null && !in_array($event_id, $allowed, true)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden: you cannot add items to that event.']));
    }
    if (!dbGetValue("SELECT id FROM events WHERE id = ?", [$event_id])) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Event not found.']));
    }

    $item_id = dbInsert(
        "INSERT INTO items (event_id, item_number, title, description, starting_bid, min_increment,
                           auction_start_time, auction_end_time, is_closed)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
        [
            $event_id,
            $item_number,
            $input['title'],
            $input['description'] ?? '',
            (float)$input['starting_bid'],
            (float)($input['min_increment'] ?? 5.00),
            $input['auction_start_time'] ?? date('Y-m-d H:i:s'),
            $input['auction_end_time']
        ]
    );

    echo json_encode([
        'status' => 'ok',
        'message' => 'Item created',
        'item_id' => $item_id
    ]);
}

function handleUpdateItem() {
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = (int)($_GET['item_id'] ?? 0);

    error_log('[ADMIN CRUD UPDATE] Item ID: ' . $item_id . ', Input: ' . json_encode($input));

    if (!$item_id || !$input) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id and body required']));
    }

    // Verify item exists
    $item_check = dbGetRow("SELECT id FROM items WHERE id = ?", [$item_id]);
    if (!$item_check) {
        http_response_code(404);
        die(json_encode(['status' => 'error', 'message' => 'Item not found']));
    }

    // Tenant isolation: only manage items in your own event(s).
    if (!adminCanAccessItem($GLOBALS['currentAdmin'], $item_id)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden: item belongs to another event.']));
    }

    $updates = [];
    $params = [];

    $updatable = ['title', 'description', 'image_url', 'fair_market_value', 'starting_bid', 'min_increment', 'buy_now_price', 'auction_start_time', 'auction_end_time', 'is_closed'];
    foreach ($updatable as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
            error_log('[ADMIN CRUD UPDATE] Setting ' . $field . ' = ' . (is_array($input[$field]) ? json_encode($input[$field]) : $input[$field]));
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'No fields to update']));
    }

    $params[] = $item_id;

    $sql = "UPDATE items SET " . implode(', ', $updates) . " WHERE id = ?";
    error_log('[ADMIN CRUD UPDATE] SQL: ' . $sql . ' with params: ' . json_encode($params));

    $success = dbUpdate($sql, $params);

    if (!$success) {
        error_log('[ADMIN CRUD UPDATE] ❌ Update failed for item ' . $item_id);
        http_response_code(400);
    } else {
        error_log('[ADMIN CRUD UPDATE] ✓ Update successful for item ' . $item_id);
    }

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Item updated' : 'Failed to update item'
    ]);
}

function handleDeleteItem() {
    global $currentAdmin;
    $item_id = (int)($_GET['item_id'] ?? 0);
    if (!$item_id) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'item_id required']));
    }

    if (!adminCanAccessItem($currentAdmin, $item_id)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden: item belongs to another event.']));
    }

    // Deleting an item CASCADES to its bids and transactions (FK ON DELETE
    // CASCADE). Refuse to destroy financial records: block deletion if a paid
    // transaction exists for this item.
    $paid = (int)dbGetValue(
        "SELECT COUNT(*) FROM transactions WHERE item_id = ? AND status = 'paid'",
        [$item_id]
    );
    if ($paid > 0) {
        http_response_code(409);
        die(json_encode(['status' => 'error', 'message' => 'Cannot delete: this item has completed (paid) transactions.']));
    }

    $success = dbDelete("DELETE FROM items WHERE id = ?", [$item_id]);

    // Audit trail for a destructive action.
    dbInsert(
        "INSERT INTO audit_log (event_type, description, created_at) VALUES (?, ?, NOW())",
        ['ITEM_DELETED', 'Admin ' . (int)($currentAdmin['id'] ?? 0) . ' deleted item ' . $item_id]
    );

    echo json_encode([
        'status' => $success ? 'ok' : 'error',
        'message' => $success ? 'Item deleted' : 'Failed to delete item'
    ]);
}

?>
