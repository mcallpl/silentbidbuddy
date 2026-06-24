<?php
// ============================================================
// BRANDING SYSTEM EXAMPLES
// Demonstrates how to use the comprehensive CSS branding system
//
// This file is for reference only — do not deploy to production
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/branding.php';
require_once __DIR__ . '/includes/db-helpers.php';

// Example 1: Fetch branding data for an event
// ============================================================
echo "<!-- EXAMPLE 1: Fetch Branding Data -->\n";

$event_id = 1;
$branding = getBrandingData($event_id);

echo "Event {$event_id} Branding:\n";
echo "  Primary Color: " . $branding['primary_color'] . "\n";
echo "  Accent Color: " . $branding['accent_color'] . "\n";
echo "  Text Color: " . $branding['text_color'] . "\n";

// Example 2: Generate CSS for inline styles
// ============================================================
echo "\n<!-- EXAMPLE 2: Generate CSS Variables -->\n";

$css = getBrandingCSS($event_id, false);  // false = pretty-print
echo "<pre>$css</pre>\n";

// Example 3: Output complete style tag
// ============================================================
echo "\n<!-- EXAMPLE 3: Complete Style Tag -->\n";

echo getBrandingStyleTag($event_id);

// Example 4: Validate colors
// ============================================================
echo "\n<!-- EXAMPLE 4: Color Validation -->\n";

$colors = ['#2E7D32', '#FF0000', '#12345', 'not-a-color'];
foreach ($colors as $color) {
    $valid = isValidHexColor($color) ? 'Valid' : 'Invalid';
    echo "{$color}: {$valid}\n";
}

// Example 5: Check contrast ratios
// ============================================================
echo "\n<!-- EXAMPLE 5: Contrast Ratio Checking -->\n";

$white = '#FFFFFF';
$black = '#000000';
$gray = '#808080';

$ratio_white_black = getContrastRatio($white, $black);
$ratio_white_gray = getContrastRatio($white, $gray);
$ratio_gray_black = getContrastRatio($gray, $black);

echo "Contrast Ratios (higher is better):\n";
echo "  White on Black: " . round($ratio_white_black, 2) . ":1\n";
echo "  White on Gray: " . round($ratio_white_gray, 2) . ":1\n";
echo "  Gray on Black: " . round($ratio_gray_black, 2) . ":1\n";

// Example 6: Check WCAG compliance
// ============================================================
echo "\n<!-- EXAMPLE 6: WCAG Contrast Compliance -->\n";

$text_color = '#212121';
$bg_colors = ['#FFFFFF', '#F5F5F5', '#D0D0D0'];

foreach ($bg_colors as $bg) {
    $compliant = hasGoodContrast($text_color, $bg) ? 'PASS' : 'FAIL';
    echo "{$text_color} on {$bg}: {$compliant}\n";
}

// Example 7: Get contrasting text color
// ============================================================
echo "\n<!-- EXAMPLE 7: Automatic Text Color Selection -->\n";

$backgrounds = ['#FFFFFF', '#000000', '#2E7D32', '#F57C00'];

foreach ($backgrounds as $bg) {
    $text = getContrastingTextColor($bg);
    echo "Background: {$bg} → Text Color: {$text}\n";
}

// Example 8: Generate JSON for JavaScript
// ============================================================
echo "\n<!-- EXAMPLE 8: JSON for JavaScript -->\n";

$json = getBrandingJSON($event_id);
echo "<pre>$json</pre>\n";

// Example 9: Use in CSS
// ============================================================
echo "\n<!-- EXAMPLE 9: CSS Implementation -->\n";
?>

<style>
    /* Using CSS variables in stylesheets */
    .branding-example-container {
        background: var(--branding-light-bg);
        border: 1px solid var(--branding-border);
        padding: 20px;
        border-radius: var(--branding-radius-md);
        margin: 20px 0;
    }

    .branding-example-header {
        background: var(--branding-primary);
        color: var(--branding-text-on-primary);
        padding: 15px;
        border-radius: var(--branding-radius-md);
        margin-bottom: 15px;
    }

    .branding-example-button {
        background: var(--branding-accent);
        color: var(--branding-text-on-accent);
        padding: 10px 20px;
        border: none;
        border-radius: var(--branding-radius-md);
        cursor: pointer;
        transition: var(--branding-transition);
    }

    .branding-example-button:hover {
        background: var(--branding-accent-dark);
        transform: translateY(-2px);
        box-shadow: var(--branding-shadow-md);
    }

    .branding-example-card {
        background: var(--branding-background);
        border: 1px solid var(--branding-border);
        padding: 15px;
        border-radius: var(--branding-radius-md);
        box-shadow: var(--branding-shadow-sm);
    }

    .branding-example-success {
        color: var(--branding-success);
        padding: 10px;
        background: rgba(40, 120, 95, 0.1);
        border-left: 4px solid var(--branding-success);
    }

    .branding-example-error {
        color: var(--branding-error);
        padding: 10px;
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--branding-error);
    }
</style>

<div class="branding-example-container">
    <div class="branding-example-header">
        <h2>Branding System Example</h2>
    </div>

    <p>This demonstrates how the CSS branding system works.</p>

    <button class="branding-example-button">Action Button</button>

    <div class="branding-example-card" style="margin-top: 20px;">
        <h3 style="color: var(--branding-primary);">Sample Card</h3>
        <p style="color: var(--branding-text-secondary);">This card uses branding colors for styling.</p>
    </div>

    <div class="branding-example-success" style="margin-top: 15px;">
        ✓ Success state — uses --branding-success color
    </div>

    <div class="branding-example-error" style="margin-top: 10px;">
        ✗ Error state — uses --branding-error color
    </div>
</div>

<?php
// Example 10: How to use in PHP templates
// ============================================================
echo "\n<!-- EXAMPLE 10: PHP Template Usage -->\n";
?>

<div style="background: var(--branding-primary); color: var(--branding-text-on-primary); padding: 20px; margin: 20px 0;">
    <h2>Dynamic Header</h2>
    <p>This header automatically uses the event's primary branding color.</p>
</div>

<?php
// Example 11: Database query example
// ============================================================
echo "\n<!-- EXAMPLE 11: Database Query -->\n";

// Update event branding
// dbUpdate(
//     "UPDATE events SET primary_color = ?, accent_color = ? WHERE id = ?",
//     ['#FF0000', '#00FF00', 1]
// );

// Fetch and verify
$event = dbGetRow("SELECT primary_color, accent_color FROM events WHERE id = ?", [1]);
if ($event) {
    echo "Event 1 colors:\n";
    echo "  Primary: " . htmlspecialchars($event['primary_color']) . "\n";
    echo "  Accent: " . htmlspecialchars($event['accent_color']) . "\n";
}

// Example 12: JavaScript integration
// ============================================================
echo "\n<!-- EXAMPLE 12: JavaScript Integration -->\n";
?>

<script>
// Fetch branding from API and update CSS variables
async function updateBrandingColors(eventId) {
    try {
        const response = await fetch(`/api/event/branding.php?id=${eventId}`);
        const data = await response.json();

        if (data.status === 'ok') {
            const root = document.documentElement;
            const branding = data.data;

            // Update CSS variables
            root.style.setProperty('--branding-primary', branding.primary_color);
            root.style.setProperty('--branding-accent', branding.accent_color);
            root.style.setProperty('--branding-secondary', branding.secondary_color);
            root.style.setProperty('--branding-text', branding.text_color);
            root.style.setProperty('--branding-background', branding.background_color);

            console.log('Branding updated:', branding);
        }
    } catch (error) {
        console.error('Failed to update branding:', error);
    }
}

// Usage: updateBrandingColors(1);
</script>

<?php
// Example 13: Common CSS patterns
// ============================================================
echo "\n<!-- EXAMPLE 13: Common CSS Patterns -->\n";
?>

<style>
    /* Button variations */
    .btn-primary {
        background: var(--branding-primary);
        color: var(--branding-text-on-primary);
    }

    .btn-secondary {
        background: var(--branding-secondary);
        color: var(--branding-text-on-primary);
    }

    .btn-accent {
        background: var(--branding-accent);
        color: var(--branding-text-on-accent);
    }

    .btn-ghost {
        border: 2px solid var(--branding-primary);
        color: var(--branding-primary);
        background: transparent;
    }

    /* Badge variations */
    .badge-primary {
        background: var(--branding-primary);
        color: white;
    }

    .badge-success {
        background: var(--branding-success);
        color: white;
    }

    .badge-error {
        background: var(--branding-error);
        color: white;
    }

    /* State-based styling */
    .is-active {
        color: var(--branding-primary);
        font-weight: bold;
    }

    .is-disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Responsive branding */
    @media (max-width: 768px) {
        :root {
            --branding-radius-md: 6px;
        }
    }
</style>

<?php
// Example 14: Color mixing and gradients
// ============================================================
echo "\n<!-- EXAMPLE 14: Gradients Using Branding Colors -->\n";
?>

<style>
    .gradient-primary-to-accent {
        background: linear-gradient(
            to right,
            var(--branding-primary),
            var(--branding-accent)
        );
        color: white;
        padding: 20px;
        border-radius: var(--branding-radius-md);
        margin: 20px 0;
    }

    .gradient-light-primary {
        background: linear-gradient(
            135deg,
            var(--branding-light-bg),
            var(--branding-background)
        );
        padding: 20px;
        border-radius: var(--branding-radius-md);
    }
</style>

<div class="gradient-primary-to-accent">
    <h3>Gradient Using Branding Colors</h3>
    <p>This gradient automatically uses the event's primary and accent colors.</p>
</div>

<div class="gradient-light-primary">
    <p>Light gradient using branding colors.</p>
</div>

<?php
// Example 15: Testing contrasts
// ============================================================
echo "\n<!-- EXAMPLE 15: Contrast Testing -->\n";

$text_colors = ['#FFFFFF', '#212121', '#666666'];
$bg_colors = ['#2E7D32', '#1976D2', '#F57C00'];

echo "<table border='1' cellpadding='10' style='margin: 20px 0;'>\n";
echo "<tr><th>Text Color</th><th>Background Color</th><th>Contrast Ratio</th><th>WCAG AA?</th></tr>\n";

foreach ($text_colors as $text) {
    foreach ($bg_colors as $bg) {
        $ratio = getContrastRatio($text, $bg);
        $wcag_aa = $ratio >= 4.5 ? 'PASS' : 'FAIL';
        echo "<tr>";
        echo "<td style='background: $text; color: white;'>$text</td>";
        echo "<td style='background: $bg; color: $text;'>$bg</td>";
        echo "<td>" . round($ratio, 2) . ":1</td>";
        echo "<td>$wcag_aa</td>";
        echo "</tr>\n";
    }
}

echo "</table>\n";

// Example 16: Admin usage
// ============================================================
echo "\n<!-- EXAMPLE 16: Admin Panel Integration -->\n";

// This is how the admin panel would update branding:
/*
// In /admin/event-branding.php or admin API endpoint
if ($_POST['action'] === 'update_branding') {
    $event_id = (int)$_POST['event_id'];
    $primary = $_POST['primary_color'];
    $accent = $_POST['accent_color'];

    // Validate
    if (!isValidHexColor($primary) || !isValidHexColor($accent)) {
        die('Invalid color format');
    }

    // Update database
    dbUpdate(
        "UPDATE events SET primary_color = ?, accent_color = ? WHERE id = ?",
        [$primary, $accent, $event_id]
    );

    // Return updated CSS
    $css = getBrandingCSS($event_id);
    echo json_encode(['status' => 'ok', 'css' => $css]);
}
*/

?>

<div style="background: var(--branding-light-bg); padding: 20px; margin-top: 30px; border-radius: var(--branding-radius-md);">
    <h3 style="color: var(--branding-primary);">All Examples Complete!</h3>
    <p style="color: var(--branding-text-secondary);">
        This file demonstrates all the key features of the CSS branding system.
        For production use, reference the BRANDING_SYSTEM.md documentation.
    </p>
</div>

</pre>

<?php
// ============================================================
// Key Takeaways
// ============================================================
// 1. All colors are defined as CSS variables
// 2. Variables can be updated dynamically per event
// 3. PHP provides validation and contrast checking
// 4. No need to hardcode colors in CSS anymore
// 5. JavaScript can update colors in real-time
// 6. Accessibility features built-in
// ============================================================
?>
