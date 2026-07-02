<?php
// ============================================================
// BRANDING HELPER FUNCTIONS
// Manages CSS variables and event-specific branding
//
// Usage:
//   $css = getBrandingCSS($event_id);
//   echo $css; // Output in <style> tag in <head>
//
//   $branding = getBrandingData($event_id);
//   // Use individual colors: $branding['primary_color']
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';

/**
 * Fetch branding data for a specific event from the database
 * Returns complete branding configuration with defaults
 *
 * @param int $event_id Event ID to fetch branding for
 * @return array Associative array with all branding colors and settings
 */
function getBrandingDataForEvent($event_id) {
    $event_id = (int)$event_id;

    if (!$event_id) {
        return getDefaultBranding();
    }

    // Try to fetch from event_branding table first
    $branding = dbGetRow(
        "SELECT * FROM event_branding WHERE event_id = ? LIMIT 1",
        [$event_id]
    );

    // Fall back to events table
    if (!$branding) {
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
             FROM events WHERE id = ? LIMIT 1",
            [$event_id]
        );
    }

    // Merge with defaults for missing fields
    if (!$branding) {
        return getDefaultBranding();
    }

    return array_merge(getDefaultBranding(), $branding);
}

/**
 * Get default branding configuration
 * Used when no event-specific branding is set
 *
 * @return array Default branding colors and settings
 */
function getDefaultBranding() {
    return [
        'primary_color' => '#2E7D32',      // Green (Ryan's Reach default)
        'secondary_color' => '#1976D2',     // Blue
        'accent_color' => '#F57C00',        // Orange
        'background_color' => '#FFFFFF',    // White
        'text_color' => '#212121',          // Dark Gray
        'text_secondary_color' => '#666666',// Medium Gray
        'text_muted_color' => '#999999',    // Light Gray
        'border_color' => '#DDDDDD',        // Light Border
        'light_bg_color' => '#F5F5F5',      // Light Background
        'success_color' => '#28785F',       // Green
        'error_color' => '#EF4444',         // Red
        'warning_color' => '#F57C00',       // Orange
        'info_color' => '#1976D2',          // Blue
        'organization_name' => 'Organization',
        'organization_logo_url' => null,
        'event_location' => null,
        'event_description' => null
    ];
}

/**
 * Validate a hex color code
 * Accepts 3-digit and 6-digit hex colors
 *
 * @param string $color Color value (e.g., #2E7D32 or #2E7)
 * @return bool True if valid hex color
 */
if (!function_exists('isValidHexColor')) {
function isValidHexColor($color) {
    return preg_match('/^#[A-Fa-f0-9]{3}(?:[A-Fa-f0-9]{3})?$/', (string)$color) === 1;
}
}

/**
 * Generate CSS custom property overrides for an event
 * Returns inline CSS ready to be embedded in <style> tag
 *
 * @param int $event_id Event ID to generate branding for
 * @param bool $minify Whether to minify the output (default: true)
 * @return string CSS code with :root { --variable: value; }
 *
 * @example
 *   // In page <head>:
 *   <style><?php echo getBrandingCSS($event_id); ?></style>
 *
 *   // Or with minification disabled for debugging:
 *   <style><?php echo getBrandingCSS($event_id, false); ?></style>
 */
function getBrandingCSS($event_id, $minify = true) {
    $branding = getBrandingDataForEvent($event_id);

    // Validate all colors, use defaults if invalid
    $colors = [
        'primary' => validateColorOrDefault($branding['primary_color'], '#2E7D32'),
        'secondary' => validateColorOrDefault($branding['secondary_color'], '#1976D2'),
        'accent' => validateColorOrDefault($branding['accent_color'], '#F57C00'),
        'background' => validateColorOrDefault($branding['background_color'], '#FFFFFF'),
        'text' => validateColorOrDefault($branding['text_color'], '#212121'),
        'text_secondary' => validateColorOrDefault($branding['text_secondary_color'] ?? '#666666', '#666666'),
        'text_muted' => validateColorOrDefault($branding['text_muted_color'] ?? '#999999', '#999999'),
        'border' => validateColorOrDefault($branding['border_color'], '#DDDDDD'),
        'light_bg' => validateColorOrDefault($branding['light_bg_color'] ?? '#F5F5F5', '#F5F5F5'),
        'success' => validateColorOrDefault($branding['success_color'] ?? '#28785F', '#28785F'),
        'error' => validateColorOrDefault($branding['error_color'] ?? '#EF4444', '#EF4444'),
        'warning' => validateColorOrDefault($branding['warning_color'] ?? '#F57C00', '#F57C00'),
        'info' => validateColorOrDefault($branding['info_color'] ?? '#1976D2', '#1976D2')
    ];

    // Calculate derived colors for hover/active states
    $primary_dark = lightenDarkenColor($colors['primary'], -20);
    $primary_light = lightenDarkenColor($colors['primary'], 20);
    $secondary_dark = lightenDarkenColor($colors['secondary'], -20);
    $secondary_light = lightenDarkenColor($colors['secondary'], 20);
    $accent_dark = lightenDarkenColor($colors['accent'], -20);
    $accent_light = lightenDarkenColor($colors['accent'], 20);

    // Build CSS content
    $css_vars = [
        '--branding-primary' => $colors['primary'],
        '--branding-primary-dark' => $primary_dark,
        '--branding-primary-light' => $primary_light,
        '--branding-secondary' => $colors['secondary'],
        '--branding-secondary-dark' => $secondary_dark,
        '--branding-secondary-light' => $secondary_light,
        '--branding-accent' => $colors['accent'],
        '--branding-accent-dark' => $accent_dark,
        '--branding-accent-light' => $accent_light,
        '--branding-background' => $colors['background'],
        '--branding-light-bg' => $colors['light_bg'],
        '--branding-text' => $colors['text'],
        '--branding-text-secondary' => $colors['text_secondary'],
        '--branding-text-muted' => $colors['text_muted'],
        '--branding-border' => $colors['border'],
        '--branding-success' => $colors['success'],
        '--branding-error' => $colors['error'],
        '--branding-warning' => $colors['warning'],
        '--branding-info' => $colors['info'],
    ];

    // Build CSS string
    $css = ":root { ";
    foreach ($css_vars as $var_name => $var_value) {
        $css .= $var_name . ": " . $var_value . "; ";
    }
    $css .= "}";

    // Minify if requested
    if ($minify) {
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/;\s+}/', '}', $css);
    } else {
        // Pretty print for debugging
        $css = ":root {\n";
        foreach ($css_vars as $var_name => $var_value) {
            $css .= "    " . $var_name . ": " . $var_value . ";\n";
        }
        $css .= "}\n";
    }

    return $css;
}

/**
 * Generate HTML <style> tag with branding CSS
 * Ready to embed directly in page <head>
 *
 * @param int $event_id Event ID
 * @param bool $minify Whether to minify output (default: true)
 * @return string Complete <style> tag with branding CSS
 *
 * @example
 *   // In page <head>:
 *   <?php echo getBrandingStyleTag($event_id); ?>
 */
function getBrandingStyleTag($event_id, $minify = true) {
    $css = getBrandingCSS($event_id, $minify);
    return "<style data-branding=\"event-{$event_id}\">\n{$css}\n</style>\n";
}

/**
 * Generate JSON with branding data for JavaScript
 * Useful for frontend dynamic updates
 *
 * @param int $event_id Event ID
 * @return string JSON string with branding configuration
 *
 * @example
 *   // In page <head>:
 *   <script>
 *       const brandingConfig = <?php echo getBrandingJSON($event_id); ?>;
 *   </script>
 */
function getBrandingJSON($event_id) {
    $branding = getBrandingDataForEvent($event_id);
    return json_encode($branding, JSON_UNESCAPED_SLASHES);
}

/**
 * Validate color or return default if invalid
 * Internal helper function
 *
 * @param string $color Color to validate
 * @param string $default Default color if invalid
 * @return string Valid color hex code
 */
function validateColorOrDefault($color, $default) {
    if (empty($color) || !isValidHexColor($color)) {
        return $default;
    }
    return $color;
}

/**
 * Lighten or darken a hex color
 * Used to generate hover/active states from primary colors
 *
 * @param string $color Hex color (e.g., #2E7D32)
 * @param int $percent Percent to lighten (positive) or darken (negative)
 * @return string Adjusted hex color
 */
function lightenDarkenColor($color, $percent) {
    $hash = strpos($color, '#') === 0 ? 1 : 0;
    $rgb = hexToRgb($color);

    if (!$rgb) {
        return $color; // Return original if conversion fails
    }

    $r = max(0, min(255, $rgb['r'] + ($rgb['r'] * $percent / 100)));
    $g = max(0, min(255, $rgb['g'] + ($rgb['g'] * $percent / 100)));
    $b = max(0, min(255, $rgb['b'] + ($rgb['b'] * $percent / 100)));

    return '#' . str_pad(dechex((int)$r), 2, '0', STR_PAD_LEFT) .
           str_pad(dechex((int)$g), 2, '0', STR_PAD_LEFT) .
           str_pad(dechex((int)$b), 2, '0', STR_PAD_LEFT);
}

/**
 * Convert hex color to RGB array
 * Internal helper function
 *
 * @param string $hex Hex color (e.g., #2E7D32)
 * @return array|false Array with 'r', 'g', 'b' keys, or false on error
 */
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);

    // Handle 3-digit hex
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    if (strlen($hex) !== 6) {
        return false;
    }

    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * Get contrast ratio between two colors (WCAG formula)
 * Used to determine if text is readable on background
 *
 * @param string $color1 First hex color
 * @param string $color2 Second hex color
 * @return float Contrast ratio (1-21)
 */
function getContrastRatio($color1, $color2) {
    $rgb1 = hexToRgb($color1);
    $rgb2 = hexToRgb($color2);

    if (!$rgb1 || !$rgb2) {
        return 1;
    }

    $luminance1 = getRelativeLuminance($rgb1);
    $luminance2 = getRelativeLuminance($rgb2);

    $lighter = max($luminance1, $luminance2);
    $darker = min($luminance1, $luminance2);

    return ($lighter + 0.05) / ($darker + 0.05);
}

/**
 * Get relative luminance of a color (WCAG formula)
 * Internal helper for contrast calculation
 *
 * @param array $rgb RGB array with 'r', 'g', 'b' keys
 * @return float Relative luminance (0-1)
 */
function getRelativeLuminance($rgb) {
    $r = $rgb['r'] / 255;
    $g = $rgb['g'] / 255;
    $b = $rgb['b'] / 255;

    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Check if background and text have sufficient contrast
 * Returns true if contrast ratio is >= 4.5 (WCAG AA standard)
 *
 * @param string $text_color Text color hex code
 * @param string $bg_color Background color hex code
 * @param float $min_ratio Minimum contrast ratio (default: 4.5)
 * @return bool True if contrast is sufficient
 */
function hasGoodContrast($text_color, $bg_color, $min_ratio = 4.5) {
    $ratio = getContrastRatio($text_color, $bg_color);
    return $ratio >= $min_ratio;
}

/**
 * Generate a contrasting text color (black or white) based on background
 * Useful for dynamic text color in UI elements
 *
 * @param string $bg_color Background hex color
 * @return string Either '#000000' (black) or '#FFFFFF' (white)
 */
function getContrastingTextColor($bg_color) {
    $rgb = hexToRgb($bg_color);
    if (!$rgb) {
        return '#000000';
    }

    // Calculate luminance
    $luminance = getRelativeLuminance($rgb);

    // Return white for dark backgrounds, black for light backgrounds
    return $luminance > 0.5 ? '#000000' : '#FFFFFF';
}

?>
