<?php
// ============================================================
// API ENDPOINT: Event Branding Management
// GET /api/event/branding.php?id={event_id} - Get branding config
// POST /api/event/branding.php - Save branding config (admin only)
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-auth-middleware.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET: Fetch branding config for an event
// ============================================================
if ($method === 'GET') {
    $event_id = (int)($_GET['id'] ?? 0);

    if (!$event_id) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Missing required parameter: id'
        ]));
    }

    // Verify event exists
    $event = dbGetRow(
        "SELECT id, name FROM events WHERE id = ?",
        [$event_id]
    );

    if (!$event) {
        http_response_code(404);
        die(json_encode([
            'status' => 'error',
            'message' => 'Event not found'
        ]));
    }

    // Try to fetch branding from event_branding table
    $branding = dbGetRow(
        "SELECT * FROM event_branding WHERE event_id = ?",
        [$event_id]
    );

    // If no dedicated branding record, fetch from events table
    if (!$branding) {
        $event_details = dbGetRow(
            "SELECT
                primary_color,
                secondary_color,
                accent_color,
                background_color,
                text_color,
                organization_name,
                organization_logo_url,
                event_location,
                event_description
             FROM events WHERE id = ?",
            [$event_id]
        );

        if ($event_details) {
            $branding = array_merge([
                'id' => null,
                'event_id' => $event_id,
                'created_at' => null,
                'updated_at' => null
            ], $event_details);
        }
    }

    // Apply defaults if not set
    if (!$branding) {
        $branding = [
            'id' => null,
            'event_id' => $event_id,
            'primary_color' => '#2f6f5e',
            'secondary_color' => '#f2b84b',
            'accent_color' => '#000000',
            'background_color' => '#ffffff',
            'text_color' => '#333333',
            'organization_name' => null,
            'organization_logo_url' => null,
            'event_location' => null,
            'event_description' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    } else {
        // Ensure all fields have defaults
        $defaults = [
            'primary_color' => '#2f6f5e',
            'secondary_color' => '#f2b84b',
            'accent_color' => '#000000',
            'background_color' => '#ffffff',
            'text_color' => '#333333'
        ];

        foreach ($defaults as $key => $default) {
            if (empty($branding[$key])) {
                $branding[$key] = $default;
            }
        }
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Branding configuration retrieved',
        'data' => $branding
    ]);
    exit;
}

// ============================================================
// POST: Save branding config (admin only)
// ============================================================
if ($method === 'POST') {
    // Require admin authentication
    $admin = requireAdminAuth();

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON'
        ]));
    }

    $event_id = (int)($input['event_id'] ?? 0);

    if (!$event_id) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Missing required parameter: event_id'
        ]));
    }

    // Verify event exists and admin has access
    $event = dbGetRow(
        "SELECT id, organization_id FROM events WHERE id = ?",
        [$event_id]
    );

    if (!$event) {
        http_response_code(404);
        die(json_encode([
            'status' => 'error',
            'message' => 'Event not found'
        ]));
    }

    // Check admin has access to this event (super admin or event-specific access)
    if (!isset($admin['is_super_admin']) || !$admin['is_super_admin']) {
        $access = checkAdminEventAccess($admin['id'], $event_id);
        if (!$access) {
            http_response_code(403);
            die(json_encode([
                'status' => 'error',
                'message' => 'Unauthorized. You do not have access to this event.'
            ]));
        }
    }

    // Extract and validate branding fields
    $primary_color = $input['primary_color'] ?? null;
    $secondary_color = $input['secondary_color'] ?? null;
    $accent_color = $input['accent_color'] ?? null;
    $background_color = $input['background_color'] ?? null;
    $text_color = $input['text_color'] ?? null;
    $organization_name = $input['organization_name'] ?? null;
    $organization_logo_url = $input['organization_logo_url'] ?? null;
    $event_location = $input['event_location'] ?? null;
    $event_description = $input['event_description'] ?? null;

    // Validate hex colors
    if ($primary_color !== null && !isValidHexColor($primary_color)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid primary_color: must be a valid hex color (e.g., #2f6f5e)'
        ]));
    }

    if ($secondary_color !== null && !isValidHexColor($secondary_color)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid secondary_color: must be a valid hex color'
        ]));
    }

    if ($accent_color !== null && !isValidHexColor($accent_color)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid accent_color: must be a valid hex color'
        ]));
    }

    if ($background_color !== null && !isValidHexColor($background_color)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid background_color: must be a valid hex color'
        ]));
    }

    if ($text_color !== null && !isValidHexColor($text_color)) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid text_color: must be a valid hex color'
        ]));
    }

    // Validate URLs if provided
    if ($organization_logo_url !== null && !empty($organization_logo_url)) {
        if (!filter_var($organization_logo_url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            die(json_encode([
                'status' => 'error',
                'message' => 'Invalid organization_logo_url: must be a valid URL'
            ]));
        }
    }

    // Sanitize text fields
    $organization_name = $organization_name ? sanitizeText($organization_name) : null;
    $event_location = $event_location ? sanitizeText($event_location) : null;
    $event_description = $event_description ? sanitizeText($event_description) : null;

    // Update events table with branding info
    $event_updates = [];
    $event_values = [];

    if ($primary_color !== null) {
        $event_updates[] = "primary_color = ?";
        $event_values[] = $primary_color;
    }
    if ($secondary_color !== null) {
        $event_updates[] = "secondary_color = ?";
        $event_values[] = $secondary_color;
    }
    if ($accent_color !== null) {
        $event_updates[] = "accent_color = ?";
        $event_values[] = $accent_color;
    }
    if ($background_color !== null) {
        $event_updates[] = "background_color = ?";
        $event_values[] = $background_color;
    }
    if ($text_color !== null) {
        $event_updates[] = "text_color = ?";
        $event_values[] = $text_color;
    }
    if ($organization_name !== null) {
        $event_updates[] = "organization_name = ?";
        $event_values[] = $organization_name;
    }
    if ($organization_logo_url !== null) {
        $event_updates[] = "organization_logo_url = ?";
        $event_values[] = $organization_logo_url;
    }
    if ($event_location !== null) {
        $event_updates[] = "event_location = ?";
        $event_values[] = $event_location;
    }
    if ($event_description !== null) {
        $event_updates[] = "event_description = ?";
        $event_values[] = $event_description;
    }

    // Execute update if there are changes
    if (!empty($event_updates)) {
        $event_values[] = $event_id;
        $update_result = dbUpdate(
            "UPDATE events SET " . implode(', ', $event_updates) . " WHERE id = ?",
            $event_values
        );

        if (!$update_result) {
            error_log("Failed to update events table for event_id: " . $event_id);
            http_response_code(500);
            die(json_encode([
                'status' => 'error',
                'message' => 'Failed to save branding configuration'
            ]));
        }
    }

    // Try to update or insert into event_branding table
    $existing_branding = dbGetRow(
        "SELECT id FROM event_branding WHERE event_id = ?",
        [$event_id]
    );

    if ($existing_branding) {
        // Update existing branding record
        $branding_updates = [];
        $branding_values = [];

        if ($primary_color !== null) {
            $branding_updates[] = "primary_color = ?";
            $branding_values[] = $primary_color;
        }
        if ($secondary_color !== null) {
            $branding_updates[] = "secondary_color = ?";
            $branding_values[] = $secondary_color;
        }
        if ($accent_color !== null) {
            $branding_updates[] = "accent_color = ?";
            $branding_values[] = $accent_color;
        }
        if ($background_color !== null) {
            $branding_updates[] = "background_color = ?";
            $branding_values[] = $background_color;
        }
        if ($text_color !== null) {
            $branding_updates[] = "text_color = ?";
            $branding_values[] = $text_color;
        }
        if ($organization_name !== null) {
            $branding_updates[] = "organization_name = ?";
            $branding_values[] = $organization_name;
        }
        if ($organization_logo_url !== null) {
            $branding_updates[] = "organization_logo_url = ?";
            $branding_values[] = $organization_logo_url;
        }
        if ($event_location !== null) {
            $branding_updates[] = "event_location = ?";
            $branding_values[] = $event_location;
        }
        if ($event_description !== null) {
            $branding_updates[] = "event_description = ?";
            $branding_values[] = $event_description;
        }

        if (!empty($branding_updates)) {
            $branding_updates[] = "updated_at = NOW()";
            $branding_values[] = $event_id;

            dbUpdate(
                "UPDATE event_branding SET " . implode(', ', $branding_updates) . " WHERE event_id = ?",
                $branding_values
            );
        }
    } else {
        // Create new branding record if we have content and table exists
        if (dbTableExists('event_branding') && !empty($event_updates)) {
            dbInsert(
                "INSERT INTO event_branding (event_id, primary_color, secondary_color, accent_color, background_color, text_color, organization_name, organization_logo_url, event_location, event_description, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $event_id,
                    $primary_color ?? '#2f6f5e',
                    $secondary_color ?? '#f2b84b',
                    $accent_color ?? '#000000',
                    $background_color ?? '#ffffff',
                    $text_color ?? '#333333',
                    $organization_name,
                    $organization_logo_url,
                    $event_location,
                    $event_description
                ]
            );
        }
    }

    // Log audit event
    dbInsert(
        "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            'EVENT_BRANDING_UPDATED',
            $admin['id'] ?? 0,
            $event_id,
            'Event branding configuration updated'
        ]
    );

    // Fetch and return updated branding
    $branding = dbGetRow(
        "SELECT
            primary_color,
            secondary_color,
            accent_color,
            background_color,
            text_color,
            organization_name,
            organization_logo_url,
            event_location,
            event_description
         FROM events WHERE id = ?",
        [$event_id]
    );

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Branding configuration saved successfully',
        'data' => array_merge([
            'event_id' => $event_id,
            'id' => null,
            'created_at' => null,
            'updated_at' => null
        ], $branding ?? [])
    ]);
    exit;
}

// ============================================================
// METHOD NOT ALLOWED
// ============================================================
http_response_code(405);
die(json_encode([
    'status' => 'error',
    'message' => 'Method not allowed. Supported methods: GET, POST'
]));

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Validate hex color format
 * @param string $color Color value (e.g., #2f6f5e)
 * @return bool True if valid hex color
 */
// NOTE: defined unconditionally (not function_exists-guarded) because it is
// called earlier in this file and PHP only hoists unconditional definitions.
// This endpoint is a standalone entry point that does not include any other file
// defining isValidHexColor(), so there is no redeclaration risk here.
function isValidHexColor($color) {
    if (empty($color)) {
        return true; // Allow null/empty (this endpoint treats blank as "unset")
    }

    // Check if it's a valid hex color (3- or 6-digit)
    return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', (string)$color) === 1;
}

/**
 * Sanitize text input
 * @param string $text Input text
 * @return string Sanitized text
 */
function sanitizeText($text) {
    // Remove any HTML tags and trim whitespace
    $text = strip_tags($text);
    $text = trim($text);

    // Limit length to reasonable values
    if (strlen($text) > 1000) {
        $text = substr($text, 0, 1000);
    }

    return $text;
}

?>
