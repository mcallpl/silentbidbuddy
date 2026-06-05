<?php
// ============================================================
// AUCTION CLOSER — Automatically close expired auctions
// Marks auctions as closed and reconciles bidder stats
// ============================================================

/**
 * Close all expired auctions
 * @return array Results with count of closed items
 */
function closeExpiredAuctions() {
    // Find all open auctions where end time has passed
    $expired_items = dbGetAll(
        "SELECT id, current_high_bid, current_high_bidder_id
         FROM items
         WHERE is_closed = 0 AND auction_end_time <= NOW()"
    );

    if (empty($expired_items)) {
        return ['closed' => 0, 'message' => 'No expired auctions to close'];
    }

    $closed_count = 0;

    foreach ($expired_items as $item) {
        // Mark item as closed
        $result = dbUpdate(
            "UPDATE items SET is_closed = 1 WHERE id = ?",
            [(int)$item['id']]
        );

        if ($result) {
            $closed_count++;

            // Log the closure
            error_log('[AUCTION] Closed item ' . $item['id'] .
                      ' - Winner: ' . ($item['current_high_bidder_id'] ?? 'None') .
                      ' - Final bid: $' . $item['current_high_bid']);
        }
    }

    return [
        'closed' => $closed_count,
        'message' => "Closed $closed_count expired auction(s)"
    ];
}

/**
 * Get bidder reconciliation stats
 * Returns all bidders with their won/spent amounts
 */
function getBidderStats() {
    return dbGetAll(
        "SELECT
            u.id,
            u.full_name,
            u.phone_number,
            COUNT(DISTINCT b.id) as bid_count,
            SUM(CASE WHEN i.is_closed = 1 AND i.current_high_bidder_id = u.id THEN 1 ELSE 0 END) as items_won,
            SUM(CASE WHEN i.is_closed = 1 AND i.current_high_bidder_id = u.id THEN i.current_high_bid ELSE 0 END) as total_spent
         FROM users u
         LEFT JOIN bids b ON u.id = b.user_id
         LEFT JOIN items i ON b.item_id = i.id
         GROUP BY u.id, u.full_name, u.phone_number
         ORDER BY total_spent DESC"
    );
}
?>
