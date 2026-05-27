<?php
// ============================================================
// API ENDPOINT: Stripe Webhook Handler
// POST /api/checkout/webhook.php
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/stripe-utils.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get raw payload and signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($signature)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing payload or signature']));
}

// Verify and process webhook
$webhook_result = handleStripeWebhook($payload, $signature);

if (!$webhook_result['success']) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => $webhook_result['error']]));
}

$event = $webhook_result['event'];

// Route based on event type
switch ($event->type) {
    case 'checkout.session.completed':
        $result = processCheckoutCompleted($event);
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message'],
            'event_type' => $event->type
        ]);
        break;

    case 'charge.failed':
        $charge = $event->data->object;
        dbUpdate(
            "UPDATE transactions SET status = ? WHERE stripe_payment_intent_id = ?",
            ['failed', $charge->payment_intent ?? '']
        );
        dbInsert(
            "INSERT INTO audit_log (event_type, description, created_at)
             VALUES (?, ?, NOW())",
            ['PAYMENT_FAILED', 'Payment failed for charge: ' . ($charge->id ?? 'unknown')]
        );
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Charge failed recorded', 'event_type' => $event->type]);
        break;

    case 'charge.refunded':
        // Handle refunds if needed
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Refund event received', 'event_type' => $event->type]);
        break;

    default:
        // Acknowledge other events
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Event received', 'event_type' => $event->type]);
}

?>
