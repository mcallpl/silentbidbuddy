<?php
// ============================================================
// CRON JOB: Close Expired Auctions
// Run every minute via cron: * * * * * cd /var/www/html/silentbidbuddy && php cron-close-auctions.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';
require_once __DIR__ . '/includes/auction-closer.php';

// Close expired auctions
$result = closeExpiredAuctions();

// Log the result
$log_message = '[CRON] ' . date('Y-m-d H:i:s') . ' - ' . $result['message'];
error_log($log_message);

// Write to file for verification
@file_put_contents(
    __DIR__ . '/logs/auction-closer.log',
    $log_message . PHP_EOL,
    FILE_APPEND
);

echo $result['message'];
?>
