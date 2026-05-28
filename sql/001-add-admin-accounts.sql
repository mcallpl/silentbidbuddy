-- Migration: Add admin_accounts table
-- Created: 2026-05-28
-- Purpose: Support username/password authentication for admin users

CREATE TABLE IF NOT EXISTS admin_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    full_name VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    is_super_admin TINYINT(1) DEFAULT 0,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_is_active (is_active),
    INDEX idx_is_super_admin (is_super_admin),
    INDEX idx_last_login (last_login),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
