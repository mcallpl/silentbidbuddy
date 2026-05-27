<?php
// ============================================================
// API ENDPOINT: Get Live Metrics (Admin)
// GET /api/admin/get-metrics.php
// ============================================================


require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auction-engine.php';

header('Content-Type: application/json');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// TODO: Add admin authentication check here

// Get metrics
$metrics = getLiveMetrics();
$summary = getAuctionSummary();

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'metrics' => $metrics,
    'summary' => $summary
]);

?>
