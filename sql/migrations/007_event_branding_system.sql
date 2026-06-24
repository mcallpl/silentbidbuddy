-- ============================================================
-- MIGRATION: Event Branding System
-- Created: 2026-06-24
-- Purpose: Extend event table with comprehensive branding and
--          event detail storage. Includes colors, organization
--          metadata, location, and event description.
-- ============================================================

-- Create event_branding table to store per-event branding configuration
-- This table supplements the events table with extensive customization options
CREATE TABLE IF NOT EXISTS event_branding (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key to events table
    event_id INT UNSIGNED NOT NULL UNIQUE,

    -- Color configuration (stored as hex values, e.g., #2f6f5e)
    primary_color VARCHAR(7) DEFAULT '#2f6f5e',
    secondary_color VARCHAR(7) DEFAULT '#f2b84b',
    accent_color VARCHAR(7) DEFAULT '#000000',
    background_color VARCHAR(7) DEFAULT '#ffffff',
    text_color VARCHAR(7) DEFAULT '#333333',

    -- Organization branding information
    organization_name VARCHAR(255),
    organization_logo_url VARCHAR(500),

    -- Event-specific details
    event_location VARCHAR(500),
    event_description TEXT,

    -- Timestamps for audit trail
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,

    -- Indexes for performance
    INDEX idx_event_id (event_id),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add branding columns directly to events table as an alternative/supplement
-- This provides backward compatibility and easier access to primary branding fields
-- Using conditional checks to avoid errors on repeated runs
ALTER TABLE `events` ADD `primary_color` VARCHAR(7) DEFAULT '#2f6f5e' AFTER timezone;
ALTER TABLE `events` ADD `secondary_color` VARCHAR(7) DEFAULT '#f2b84b' AFTER `primary_color`;
ALTER TABLE `events` ADD `accent_color` VARCHAR(7) DEFAULT '#000000' AFTER `secondary_color`;
ALTER TABLE `events` ADD `background_color` VARCHAR(7) DEFAULT '#ffffff' AFTER `accent_color`;
ALTER TABLE `events` ADD `text_color` VARCHAR(7) DEFAULT '#333333' AFTER `background_color`;
ALTER TABLE `events` ADD `organization_name` VARCHAR(255) AFTER `text_color`;
ALTER TABLE `events` ADD `organization_logo_url` VARCHAR(500) AFTER `organization_name`;
ALTER TABLE `events` ADD `event_location` VARCHAR(500) AFTER `organization_logo_url`;
ALTER TABLE `events` ADD `event_description` TEXT AFTER `event_location`;

-- Add indexes on events table for branding columns to improve query performance
ALTER TABLE events ADD INDEX idx_primary_color (primary_color);
ALTER TABLE events ADD INDEX idx_event_location (event_location);

-- Populate default branding from organizations where available
UPDATE events e
INNER JOIN organizations o ON e.organization_id = o.id
SET
    e.primary_color = COALESCE(o.brand_primary, '#2f6f5e'),
    e.secondary_color = COALESCE(o.brand_accent, '#f2b84b'),
    e.organization_logo_url = o.logo_url,
    e.organization_name = o.name
WHERE e.primary_color IS NULL
  OR e.primary_color = '#2f6f5e';
