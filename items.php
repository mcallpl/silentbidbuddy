<?php
// ============================================================
// SILENT BID BUDDY — Items Listing
// Browse all active auction items
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Check authentication
$user = getCurrentUser();

// Get all active items
$items = dbGetAll(
    "SELECT id, item_number, title, description, image_url, fair_market_value,
            starting_bid, current_high_bid, auction_end_time, is_closed,
            TIMESTAMPDIFF(SECOND, NOW(), auction_end_time) as time_remaining
     FROM items
     WHERE is_closed = 0 AND auction_end_time > NOW()
     ORDER BY auction_end_time ASC"
);

$page_title = 'All Items - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/mobile.css">
</head>
<body class="items-list-page">
    <header class="app-header">
        <h1><?php echo APP_NAME; ?></h1>
        <button class="btn-menu">≡</button>
    </header>

    <div class="container items-container">
        <!-- Items Grid -->
        <section class="items-section">
            <h2>Active Auction Items</h2>

            <?php if (empty($items)): ?>
                <div class="no-items-message">
                    <p>🔔 No active items at this time.</p>
                    <p>Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="items-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <!-- Item Image -->
                            <div class="item-card-image">
                                <?php if ($item['image_url']): ?>
                                    <?php
                                        $imageUrl = $item['image_url'];
