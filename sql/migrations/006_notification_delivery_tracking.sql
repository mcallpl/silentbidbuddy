-- ============================================================
-- MIGRATION: Add notification delivery tracking and retry support
-- Created: 2026-06-21
-- Revised: 2026-07-02 — original used MariaDB/Postgres-only syntax
--   (`ADD COLUMN IF NOT EXISTS`, partial `CREATE INDEX ... WHERE`) that is
--   invalid on MySQL 8/9, so it never actually applied. Rewritten for MySQL.
-- ============================================================

-- Add delivery_status column to the notifications table.
-- NOTE: MySQL does not support `ADD COLUMN IF NOT EXISTS`. If this column
-- already exists (e.g. partial prior run), this statement will error 1060 —
-- that is safe to ignore.
ALTER TABLE notifications
    ADD COLUMN delivery_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending';

-- Create notifications_log table for tracking delivery attempts and retries
CREATE TABLE IF NOT EXISTS notifications_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED,
    notification_type VARCHAR(50) NOT NULL,
    delivery_channel ENUM('push', 'sms', 'both', 'in_app') DEFAULT 'push',
    delivery_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempt_number INT DEFAULT 1,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    response_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    next_retry_at DATETIME,

    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,

    INDEX idx_notification_id (notification_id),
    -- Covers both the "pending retries due" scan and per-user delivery lookups.
    -- (MySQL has no partial/filtered indexes, so the original WHERE-clause
    -- indexes were dropped; these composite indexes serve the same queries.)
    INDEX idx_retry_scan (delivery_status, next_retry_at, attempt_number),
    INDEX idx_user_delivery (user_id, delivery_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
