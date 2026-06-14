<?php
// ============================================================
// SILENT BID BUDDY — Payment Success Page
// Post-checkout confirmation
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/public-nav.php';

// Get session ID from query param
$session_id = $_GET['session_id'] ?? '';
$user = getCurrentUser();

// Fetch transaction by session ID
$transaction = dbGetRow(
    "SELECT t.id, t.item_id, t.amount, t.status, i.title
     FROM transactions t
     JOIN items i ON i.id = t.item_id
     WHERE t.stripe_checkout_session_id = ?",
    [$session_id]
);

if (!$transaction) {
    renderPublicMessagePage([
        'status' => 404,
        'title' => 'Payment',
        'heading' => 'We could not find that payment record',
        'message' => 'If you just completed checkout, your payment may still be processing. You can return to My Bids to check your item status.',
        'actions' => [
            ['href' => 'my-bids.php', 'label' => 'View My Bids', 'class' => 'btn-primary'],
            ['href' => 'items.php', 'label' => 'Browse Items', 'class' => 'btn-secondary']
        ],
        'user' => $user
    ]);
}

$page_title = 'Payment Successful - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Thank you for supporting this fundraising auction through Silent Bid Buddy.'
    ]); ?>
</head>
<body class="success-page">
    <?php renderPublicHeader(['back_href' => 'items.php', 'back_label' => '← Items', 'user' => $user]); ?>

    <div class="container success-container">
        <section class="success-message">
            <div class="success-icon">🎉</div>
            <h1>Thank You!</h1>
            <p class="success-text">Your payment has been received.</p>

            <div class="success-details">
                <h3><?php echo htmlspecialchars($transaction['title']); ?></h3>
                <p class="amount">$<?php echo number_format($transaction['amount'], 2); ?></p>
                <p class="status">
                    Status: <span class="badge badge-success">
                        <?php echo ucfirst($transaction['status']); ?>
                    </span>
                </p>
            </div>

            <div class="next-steps">
                <h3>What's Next?</h3>
                <ol>
                    <li>Watch for SMS updates about your item</li>
                    <li>Arrange pickup or delivery with the nonprofit</li>
                    <li>Enjoy knowing your donation supports a great cause!</li>
                </ol>
            </div>

            <div class="action-buttons">
                <a href="items.php" class="btn btn-primary btn-large">Back to Auction</a>
                <a href="my-bids.php" class="btn btn-secondary">View My Bids</a>
            </div>
        </section>
    </div>
</body>
