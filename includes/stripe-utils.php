<?php
// ============================================================
// STRIPE UTILITIES
// Payment processing and checkout session management
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

// Require Stripe library (must be installed via composer)
require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

// Initialize Stripe with secret key
Stripe::setApiKey(STRIPE_SECRET_KEY);

/**
 * Create a Stripe Checkout Session for a winning bid
 * @param int $item_id Item ID
 * @param int $user_id User ID
 * @param float $amount Winning bid amount
 * @param string $item_title Item title
 * @param string $user_email User email (optional)
 * @return array ['success' => bool, 'session_id' => string or 'error' => string]
 */
function createCheckoutSession($item_id, $user_id, $amount, $item_title, $user_email = '') {
    try {
        // Create or retrieve Stripe customer
        $customer = getOrCreateStripeCustomer($user_id, $user_email);
        if (!$customer) {
            return ['success' => false, 'error' => 'Failed to create payment customer'];
        }

        // Create checkout session
        $session = Session::create([
            'payment_method_types' => ['card'],
            'customer' => $customer->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item_title,
                        'description' => 'Silent Auction Item #' . $item_id
                    ],
                    'unit_amount' => (int)($amount * 100) // Amount in cents
                ],
                'quantity' => 1
            ]],
            'mode' => 'payment',
            'success_url' => APP_DOMAIN . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => APP_DOMAIN . '/item.php?id=' . urlencode($item_id),
            'metadata' => [
                'item_id' => $item_id,
                'user_id' => $user_id
            ]
        ]);

        // Store transaction record
        dbInsert(
            "INSERT INTO transactions (user_id, item_id, stripe_checkout_session_id, amount, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [(int)$user_id, (int)$item_id, $session->id, (float)$amount, 'pending']
        );

        return [
            'success' => true,
            'session_id' => $session->id,
            'public_key' => STRIPE_PUBLISHABLE_KEY
        ];
    } catch (Exception $e) {
        error_log("Stripe session creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Payment processing error: ' . $e->getMessage()];
    }
}

/**
 * Get or create a Stripe customer for a user
 * @param int $user_id User ID
 * @param string $email Email address (optional)
 * @return \Stripe\Customer|null
 */
function getOrCreateStripeCustomer($user_id, $email = '') {
    try {
        // Get user record
        $user = dbGetRow(
            "SELECT id, phone_number, full_name, stripe_customer_id FROM users WHERE id = ?",
            [(int)$user_id]
        );

        if (!$user) {
            return null;
        }

        // If customer exists, return it
        if ($user['stripe_customer_id']) {
            return \Stripe\Customer::retrieve($user['stripe_customer_id']);
        }

        // Create new customer
        $customer = \Stripe\Customer::create([
            'phone' => $user['phone_number'],
            'name' => $user['full_name'],
            'email' => $email,
            'metadata' => [
                'user_id' => $user_id
            ]
        ]);

        // Store customer ID
        dbUpdate(
            "UPDATE users SET stripe_customer_id = ? WHERE id = ?",
            [$customer->id, (int)$user_id]
        );

        return $customer;
    } catch (Exception $e) {
        error_log("Stripe customer creation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify and handle Stripe webhook
 * @param string $payload Raw webhook payload
 * @param string $signature Stripe signature header
 * @return array ['success' => bool, 'event' => \Stripe\Event|null, 'error' => string|null]
 */
function handleStripeWebhook($payload, $signature) {
    try {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            STRIPE_WEBHOOK_SECRET
        );
    } catch (SignatureVerificationException $e) {
        error_log("Webhook signature verification failed: " . $e->getMessage());
        return ['success' => false, 'event' => null, 'error' => 'Invalid signature'];
    } catch (Exception $e) {
        error_log("Webhook error: " . $e->getMessage());
        return ['success' => false, 'event' => null, 'error' => 'Webhook error'];
    }

    return ['success' => true, 'event' => $event, 'error' => null];
}

/**
 * Process checkout.session.completed webhook
 * @param \Stripe\Event $event Stripe event
 * @return array ['success' => bool, 'message' => string]
 */
function processCheckoutCompleted($event) {
    $session = $event->data->object;

    // Get payment intent to confirm payment succeeded
    if ($session->payment_status !== 'paid') {
        error_log("Payment not completed for session: " . $session->id);
        return ['success' => false, 'message' => 'Payment not completed'];
    }

    // Update transaction status
    $updated = dbUpdate(
        "UPDATE transactions SET
            status = ?, stripe_payment_intent_id = ?,
            updated_at = NOW()
         WHERE stripe_checkout_session_id = ?",
        ['paid', $session->payment_intent, $session->id]
    );

    if (!$updated) {
        error_log("Failed to update transaction for session: " . $session->id);
        return ['success' => false, 'message' => 'Failed to update transaction'];
    }

    // Log audit event
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            'PAYMENT_COMPLETED',
            $session->metadata->user_id ?? null,
            $session->metadata->item_id ?? null,
            'Payment received for session: ' . $session->id
        ]
    );

    return ['success' => true, 'message' => 'Payment processed successfully'];
}

/**
 * Get transaction details
 * @param int $transaction_id Transaction ID
 * @return array|false
 */
function getTransaction($transaction_id) {
    return dbGetRow(
        "SELECT id, user_id, item_id, stripe_payment_intent_id,
                stripe_checkout_session_id, amount, status, created_at
         FROM transactions WHERE id = ?",
        [(int)$transaction_id]
    );
}

/**
 * Get transactions for user
 * @param int $user_id User ID
 * @return array
 */
function getUserTransactions($user_id) {
    return dbGetAll(
        "SELECT t.id, t.item_id, t.amount, t.status, t.created_at,
                i.title as item_title
         FROM transactions t
         JOIN items i ON i.id = t.item_id
         WHERE t.user_id = ?
         ORDER BY t.created_at DESC",
        [(int)$user_id]
    );
}

