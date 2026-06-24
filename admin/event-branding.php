<?php
// ============================================================
// EVENT BRANDING CUSTOMIZATION — Admin Panel
// Manage organization branding, colors, and event details
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/page-meta.php';
require_once __DIR__ . '/../includes/db-helpers.php';
require_once __DIR__ . '/../includes/session-manager.php';

// Check admin authentication
if (!isAdminLoggedIn()) {
    header('Location: ' . APP_DOMAIN . '/admin.php');
    exit;
}

$page_title = APP_NAME . ' — Event Branding';

// Fetch organizations
$organizations = dbGetAll("
    SELECT id, name, brand_primary, brand_accent, logo_url
    FROM organizations
    ORDER BY name ASC
");

$selected_org_id = (int)($_GET['org_id'] ?? 0);
if (!$selected_org_id && !empty($organizations)) {
    $selected_org_id = $organizations[0]['id'];
}

$selected_org = null;
$selected_events = [];

if ($selected_org_id) {
    $selected_org = dbGetRow(
        "SELECT id, name, brand_primary, brand_accent, logo_url, contact_email FROM organizations WHERE id = ?",
        [$selected_org_id]
    );

    // Fetch events for this organization
    $selected_events = dbGetAll(
        "SELECT id, name, event_date, auction_start_time, auction_end_time, status
         FROM events
         WHERE organization_id = ?
         ORDER BY event_date DESC",
        [$selected_org_id]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php renderPageMeta([
        'title' => $page_title,
        'description' => 'Event branding and customization panel',
        'stylesheets' => ['css/main.css', 'css/admin.css']
    ]); ?>
    <style>
        /* ============================================================
           EVENT BRANDING PAGE STYLES
           ============================================================ */

        .branding-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .branding-form {
            background: rgba(255, 253, 248, 0.98);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-border-radius);
            padding: 2rem;
            box-shadow: var(--admin-shadow-md);
        }

        .branding-preview {
            background: rgba(255, 253, 248, 0.98);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-border-radius);
            padding: 2rem;
            box-shadow: var(--admin-shadow-md);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--admin-border);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--admin-text-primary);
            margin: 0 0 1.5rem 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 4px solid var(--admin-accent);
            padding-left: 1rem;
        }

        .color-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .color-field {
            display: flex;
            flex-direction: column;
        }

        .color-field label {
            font-weight: 600;
            color: var(--admin-text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .color-input-wrapper {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .color-picker {
            width: 60px;
            height: 48px;
            border: 2px solid var(--admin-border);
            border-radius: calc(var(--admin-border-radius) - 2px);
            cursor: pointer;
            transition: var(--admin-transition);
        }

        .color-picker:hover {
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 3px rgba(217, 154, 43, 0.1);
        }

        .color-text-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--admin-border);
            border-radius: calc(var(--admin-border-radius) - 2px);
            font-family: "Monaco", "Courier New", monospace;
            font-size: 0.85rem;
            transition: var(--admin-transition);
        }

        .color-text-input:focus {
            outline: none;
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 4px rgba(217, 154, 43, 0.1);
        }

        .text-field {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.5rem;
        }

        .text-field label {
            font-weight: 600;
            color: var(--admin-text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input,
        .form-textarea {
            padding: 0.75rem;
            border: 1px solid var(--admin-border);
            border-radius: calc(var(--admin-border-radius) - 2px);
            font-size: 0.95rem;
            font-family: "Avenir Next", -apple-system, sans-serif;
            transition: var(--admin-transition);
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 4px rgba(217, 154, 43, 0.1);
            background: rgba(217, 154, 43, 0.02);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
            font-size: 0.85rem;
        }

        .form-helper {
            font-size: 0.8rem;
            color: var(--admin-text-muted);
            margin-top: 0.3rem;
        }

        /* Organization Selector */
        .org-selector {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .org-button {
            padding: 0.75rem 1.25rem;
            border: 2px solid var(--admin-border);
            background: var(--admin-cream);
            color: var(--admin-text-primary);
            border-radius: calc(var(--admin-border-radius) - 2px);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--admin-transition);
            white-space: nowrap;
        }

        .org-button:hover {
            border-color: var(--admin-accent);
            background: rgba(217, 154, 43, 0.08);
        }

        .org-button.active {
            background: var(--admin-accent);
            color: white;
            border-color: var(--admin-accent);
        }

        /* Form Actions */
        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--admin-border);
        }

        .btn {
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: calc(var(--admin-border-radius) - 2px);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--admin-transition);
            text-align: center;
        }

        .btn-primary {
            background: var(--admin-accent);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #c8861a;
            box-shadow: 0 8px 20px rgba(217, 154, 43, 0.25);
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: transparent;
            color: var(--admin-accent);
            border: 2px solid var(--admin-accent);
        }

        .btn-secondary:hover {
            background: rgba(217, 154, 43, 0.1);
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: calc(var(--admin-border-radius) - 2px);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .message.show {
            display: block;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background: rgba(40, 120, 95, 0.1);
            color: var(--admin-success);
            border: 1px solid var(--admin-success);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-error);
            border: 1px solid var(--admin-error);
        }

        .message.info {
            background: rgba(217, 154, 43, 0.1);
            color: #c8861a;
            border: 1px solid var(--admin-accent);
        }

        /* Preview Styles */
        .preview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--admin-text-primary);
            margin: 0 0 1.5rem 0;
            border-left: 4px solid var(--admin-accent);
            padding-left: 1rem;
        }

        .preview-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--admin-border);
        }

        .preview-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .preview-item-card {
            border: 2px solid;
            border-radius: var(--admin-border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .preview-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .preview-item-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 0.3rem 0;
        }

        .preview-item-number {
            font-size: 0.8rem;
            opacity: 0.7;
            margin: 0;
        }

        .preview-item-price {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .preview-button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: calc(var(--admin-border-radius) - 2px);
            color: white;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            transition: var(--admin-transition);
        }

        .preview-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .preview-org-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: var(--admin-border-radius);
            margin-bottom: 2rem;
        }

        .preview-logo {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .preview-org-info h3 {
            margin: 0 0 0.25rem 0;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .preview-org-info p {
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        /* Loading spinner */
        .btn-spinner {
            display: inline-block;
            margin-left: 0.5rem;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .branding-container {
                grid-template-columns: 1fr;
            }

            .branding-preview {
                position: static;
                max-height: none;
            }

            .color-group {
                grid-template-columns: 1fr;
            }

            .form-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .branding-container {
                padding: 1rem;
            }

            .branding-form,
            .branding-preview {
                padding: 1.5rem;
            }

            .color-field label {
                font-size: 0.85rem;
            }

            .preview-org-header {
                flex-direction: column;
                text-align: center;
            }

            .preview-item-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="admin-page">
    <div class="dashboard-container">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1 class="dashboard-title">🎨 Event Branding</h1>
            </div>
            <div class="header-right">
                <button id="logoutBtn" class="btn btn-secondary btn-small">Logout</button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="admin-content" style="flex: 1; overflow-y: auto;">
            <!-- Organization Selector -->
            <div style="padding: 2rem 2rem 0;">
                <h2 style="margin: 0 0 1rem 0; color: var(--admin-text-primary); font-size: 1rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Select Organization</h2>
                <div class="org-selector">
                    <?php foreach ($organizations as $org): ?>
                        <button
                            class="org-button <?php echo $org['id'] === $selected_org_id ? 'active' : ''; ?>"
                            onclick="selectOrganization(<?php echo $org['id']; ?>)"
                        >
                            <?php echo htmlspecialchars($org['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Branding Form & Preview -->
            <?php if ($selected_org): ?>
                <div class="branding-container">
                    <!-- Form Section -->
                    <div class="branding-form">
                        <form id="brandingForm">
                            <input type="hidden" id="orgId" value="<?php echo $selected_org['id']; ?>">

                            <!-- Messages -->
                            <div id="successMessage" class="message success"></div>
                            <div id="errorMessage" class="message error"></div>

                            <!-- Organization Details -->
                            <div class="form-section">
                                <h3 class="section-title">Organization Details</h3>

                                <div class="text-field">
                                    <label for="orgName">Organization Name</label>
                                    <input
                                        type="text"
                                        id="orgName"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($selected_org['name']); ?>"
                                        placeholder="Enter organization name"
                                        required
                                    />
                                    <span class="form-helper">This name appears throughout the platform</span>
                                </div>

                                <div class="text-field">
                                    <label for="logoUrl">Organization Logo URL</label>
                                    <input
                                        type="url"
                                        id="logoUrl"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($selected_org['logo_url'] ?? ''); ?>"
                                        placeholder="https://example.com/logo.png"
                                    />
                                    <span class="form-helper">Full URL to your logo image (PNG, JPG, SVG recommended)</span>
                                </div>

                                <div class="text-field">
                                    <label for="contactEmail">Contact Email</label>
                                    <input
                                        type="email"
                                        id="contactEmail"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($selected_org['contact_email'] ?? ''); ?>"
                                        placeholder="contact@organization.com"
                                    />
                                    <span class="form-helper">Main contact email for the organization</span>
                                </div>
                            </div>

                            <!-- Color Scheme -->
                            <div class="form-section">
                                <h3 class="section-title">Color Scheme</h3>

                                <div class="color-group">
                                    <div class="color-field">
                                        <label>Primary Color</label>
                                        <div class="color-input-wrapper">
                                            <input
                                                type="color"
                                                id="primaryColor"
                                                class="color-picker"
                                                value="<?php echo htmlspecialchars($selected_org['brand_primary'] ?? '#172235'); ?>"
                                            />
                                            <input
                                                type="text"
                                                id="primaryColorHex"
                                                class="color-text-input"
                                                value="<?php echo htmlspecialchars($selected_org['brand_primary'] ?? '#172235'); ?>"
                                                placeholder="#172235"
                                                maxlength="7"
                                            />
                                        </div>
                                        <span class="form-helper">Main brand color</span>
                                    </div>

                                    <div class="color-field">
                                        <label>Secondary Color</label>
                                        <div class="color-input-wrapper">
                                            <input
                                                type="color"
                                                id="secondaryColor"
                                                class="color-picker"
                                                value="<?php echo htmlspecialchars($_GET['secondary_color'] ?? '#f4f7f2'); ?>"
                                            />
                                            <input
                                                type="text"
                                                id="secondaryColorHex"
                                                class="color-text-input"
                                                value="<?php echo htmlspecialchars($_GET['secondary_color'] ?? '#f4f7f2'); ?>"
                                                placeholder="#f4f7f2"
                                                maxlength="7"
                                            />
                                        </div>
                                        <span class="form-helper">Background and accent color</span>
                                    </div>

                                    <div class="color-field">
                                        <label>Accent Color</label>
                                        <div class="color-input-wrapper">
                                            <input
                                                type="color"
                                                id="accentColor"
                                                class="color-picker"
                                                value="<?php echo htmlspecialchars($selected_org['brand_accent'] ?? '#d99a2b'); ?>"
                                            />
                                            <input
                                                type="text"
                                                id="accentColorHex"
                                                class="color-text-input"
                                                value="<?php echo htmlspecialchars($selected_org['brand_accent'] ?? '#d99a2b'); ?>"
                                                placeholder="#d99a2b"
                                                maxlength="7"
                                            />
                                        </div>
                                        <span class="form-helper">Highlights and CTAs</span>
                                    </div>

                                    <div class="color-field">
                                        <label>Text Color</label>
                                        <div class="color-input-wrapper">
                                            <input
                                                type="color"
                                                id="textColor"
                                                class="color-picker"
                                                value="<?php echo htmlspecialchars($_GET['text_color'] ?? '#172235'); ?>"
                                            />
                                            <input
                                                type="text"
                                                id="textColorHex"
                                                class="color-text-input"
                                                value="<?php echo htmlspecialchars($_GET['text_color'] ?? '#172235'); ?>"
                                                placeholder="#172235"
                                                maxlength="7"
                                            />
                                        </div>
                                        <span class="form-helper">Primary text color</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Event Information -->
                            <?php if (!empty($selected_events)): ?>
                                <div class="form-section">
                                    <h3 class="section-title">Event Details</h3>

                                    <div class="text-field">
                                        <label for="eventId">Select Event (Optional)</label>
                                        <select id="eventId" class="form-input">
                                            <option value="">-- Create New Event --</option>
                                            <?php foreach ($selected_events as $event): ?>
                                                <option value="<?php echo $event['id']; ?>">
                                                    <?php echo htmlspecialchars($event['name']); ?>
                                                    (<?php echo date('M d, Y', strtotime($event['event_date'])); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="form-helper">Select an existing event or leave blank to create a new one</span>
                                    </div>

                                    <div class="text-field">
                                        <label for="eventDate">Event Date</label>
                                        <input
                                            type="date"
                                            id="eventDate"
                                            class="form-input"
                                        />
                                        <span class="form-helper">Date of the auction event</span>
                                    </div>

                                    <div class="text-field">
                                        <label for="eventLocation">Event Location</label>
                                        <input
                                            type="text"
                                            id="eventLocation"
                                            class="form-input"
                                            placeholder="City, State or Venue Name"
                                        />
                                        <span class="form-helper">Where the event takes place</span>
                                    </div>

                                    <div class="text-field">
                                        <label for="eventDescription">Event Description</label>
                                        <textarea
                                            id="eventDescription"
                                            class="form-textarea"
                                            placeholder="Tell bidders about this event..."
                                        ></textarea>
                                        <span class="form-helper">Displayed on the event landing page</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                                    ↻ Reset to Defaults
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span class="btn-text">💾 Save Branding</span>
                                    <span class="btn-spinner" style="display: none;">
                                        <span class="spinner"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Preview Section -->
                    <div class="branding-preview">
                        <h3 class="preview-title">🎨 Live Preview</h3>

                        <!-- Organization Header Preview -->
                        <div class="preview-section">
                            <div id="previewOrgHeader" class="preview-org-header">
                                <div class="preview-logo" id="previewLogo">
                                    <?php echo strtoupper(substr($selected_org['name'], 0, 2)); ?>
                                </div>
                                <div class="preview-org-info">
                                    <h3 id="previewOrgName"><?php echo htmlspecialchars($selected_org['name']); ?></h3>
                                    <p id="previewOrgDate">Event Date TBD</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sample Item Card -->
                        <div class="preview-section">
                            <h4 style="margin: 0 0 1rem 0; color: var(--admin-text-primary); font-size: 0.9rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Sample Item Card</h4>

                            <div id="previewItemCard" class="preview-item-card">
                                <div class="preview-item-header">
                                    <div>
                                        <p class="preview-item-number">Item #001</p>
                                        <h4 class="preview-item-title">Premium Auction Item</h4>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; opacity: 0.7; background: rgba(0, 0, 0, 0.05); padding: 0.3rem 0.6rem; border-radius: 4px;">Active</span>
                                </div>
                                <p style="margin: 1rem 0 0; color: var(--admin-text-secondary); font-size: 0.9rem;">Beautiful collectible item perfect for any collection. High quality and excellent condition.</p>
                                <div class="preview-item-price" id="previewPrice">$250.00</div>
                                <button class="preview-button" id="previewButton">Place Bid Now</button>
                            </div>
                        </div>

                        <!-- Color Palette Preview -->
                        <div class="preview-section">
                            <h4 style="margin: 0 0 1rem 0; color: var(--admin-text-primary); font-size: 0.9rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Color Palette</h4>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div style="border-radius: 6px; overflow: hidden; box-shadow: var(--admin-shadow-sm);">
                                    <div id="previewPrimaryColor" style="height: 60px;"></div>
                                    <div style="padding: 0.75rem; background: var(--admin-cream); border-top: 1px solid var(--admin-border);">
                                        <p style="margin: 0; font-size: 0.8rem; font-weight: 600;">Primary</p>
                                        <p style="margin: 0; font-size: 0.75rem; color: var(--admin-text-muted); font-family: monospace;" id="previewPrimaryHex">#172235</p>
                                    </div>
                                </div>

                                <div style="border-radius: 6px; overflow: hidden; box-shadow: var(--admin-shadow-sm);">
                                    <div id="previewAccentColor" style="height: 60px;"></div>
                                    <div style="padding: 0.75rem; background: var(--admin-cream); border-top: 1px solid var(--admin-border);">
                                        <p style="margin: 0; font-size: 0.8rem; font-weight: 600;">Accent</p>
                                        <p style="margin: 0; font-size: 0.75rem; color: var(--admin-text-muted); font-family: monospace;" id="previewAccentHex">#d99a2b</p>
                                    </div>
                                </div>

                                <div style="border-radius: 6px; overflow: hidden; box-shadow: var(--admin-shadow-sm);">
                                    <div id="previewSecondaryColor" style="height: 60px;"></div>
                                    <div style="padding: 0.75rem; background: var(--admin-cream); border-top: 1px solid var(--admin-border);">
                                        <p style="margin: 0; font-size: 0.8rem; font-weight: 600;">Secondary</p>
                                        <p style="margin: 0; font-size: 0.75rem; color: var(--admin-text-muted); font-family: monospace;" id="previewSecondaryHex">#f4f7f2</p>
                                    </div>
                                </div>

                                <div style="border-radius: 6px; overflow: hidden; box-shadow: var(--admin-shadow-sm);">
                                    <div id="previewTextColor" style="height: 60px;"></div>
                                    <div style="padding: 0.75rem; background: var(--admin-cream); border-top: 1px solid var(--admin-border);">
                                        <p style="margin: 0; font-size: 0.8rem; font-weight: 600;">Text</p>
                                        <p style="margin: 0; font-size: 0.75rem; color: var(--admin-text-muted); font-family: monospace;" id="previewTextHex">#172235</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contrast Warning -->
                        <div id="contrastWarning" class="message warning" style="margin-top: 1rem; padding: 0.75rem; font-size: 0.85rem;">
                            ⚠️ Low contrast detected. Ensure text is readable on backgrounds.
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 3rem 2rem; text-align: center;">
                    <p style="color: var(--admin-text-secondary); font-size: 1.1rem;">Please select an organization to manage branding.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        // ============================================================
        // EVENT BRANDING CUSTOMIZATION SCRIPT
        // ============================================================

        const DEFAULT_COLORS = {
            primary: '#172235',
            secondary: '#f4f7f2',
            accent: '#d99a2b',
            text: '#172235'
        };

        // Color picker synchronization
        document.getElementById('primaryColor')?.addEventListener('input', (e) => {
            document.getElementById('primaryColorHex').value = e.target.value;
            updatePreview();
        });

        document.getElementById('primaryColorHex')?.addEventListener('input', (e) => {
            if (isValidHex(e.target.value)) {
                document.getElementById('primaryColor').value = e.target.value;
                updatePreview();
            }
        });

        document.getElementById('secondaryColor')?.addEventListener('input', (e) => {
            document.getElementById('secondaryColorHex').value = e.target.value;
            updatePreview();
        });

        document.getElementById('secondaryColorHex')?.addEventListener('input', (e) => {
            if (isValidHex(e.target.value)) {
                document.getElementById('secondaryColor').value = e.target.value;
                updatePreview();
            }
        });

        document.getElementById('accentColor')?.addEventListener('input', (e) => {
            document.getElementById('accentColorHex').value = e.target.value;
            updatePreview();
        });

        document.getElementById('accentColorHex')?.addEventListener('input', (e) => {
            if (isValidHex(e.target.value)) {
                document.getElementById('accentColor').value = e.target.value;
                updatePreview();
            }
        });

        document.getElementById('textColor')?.addEventListener('input', (e) => {
            document.getElementById('textColorHex').value = e.target.value;
            updatePreview();
        });

        document.getElementById('textColorHex')?.addEventListener('input', (e) => {
            if (isValidHex(e.target.value)) {
                document.getElementById('textColor').value = e.target.value;
                updatePreview();
            }
        });

        // Logo URL live update
        document.getElementById('logoUrl')?.addEventListener('input', updatePreview);
        document.getElementById('orgName')?.addEventListener('input', updatePreview);
        document.getElementById('eventDate')?.addEventListener('input', updatePreview);

        // Form submission
        document.getElementById('brandingForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveBranding();
        });

        /**
         * Validate if a string is a valid hex color
         */
        function isValidHex(hex) {
            return /^#[0-9A-F]{6}$/i.test(hex);
        }

        /**
         * Calculate contrast ratio between two colors
         */
        function getContrastRatio(color1, color2) {
            const getLuminance = (hex) => {
                const rgb = parseInt(hex.slice(1), 16);
                const r = (rgb >> 16) & 0xff;
                const g = (rgb >> 8) & 0xff;
                const b = (rgb >> 0) & 0xff;

                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? 1 : 0;
            };

            return Math.abs(getLuminance(color1) - getLuminance(color2));
        }

        /**
         * Update preview in real-time
         */
        function updatePreview() {
            const primaryColor = document.getElementById('primaryColor')?.value || DEFAULT_COLORS.primary;
            const secondaryColor = document.getElementById('secondaryColor')?.value || DEFAULT_COLORS.secondary;
            const accentColor = document.getElementById('accentColor')?.value || DEFAULT_COLORS.accent;
            const textColor = document.getElementById('textColor')?.value || DEFAULT_COLORS.text;
            const orgName = document.getElementById('orgName')?.value || 'Organization';
            const logoUrl = document.getElementById('logoUrl')?.value;
            const eventDate = document.getElementById('eventDate')?.value;

            // Update color swatches
            document.getElementById('previewPrimaryColor').style.backgroundColor = primaryColor;
            document.getElementById('previewPrimaryHex').textContent = primaryColor;

            document.getElementById('previewSecondaryColor').style.backgroundColor = secondaryColor;
            document.getElementById('previewSecondaryHex').textContent = secondaryColor;

            document.getElementById('previewAccentColor').style.backgroundColor = accentColor;
            document.getElementById('previewAccentHex').textContent = accentColor;

            document.getElementById('previewTextColor').style.backgroundColor = textColor;
            document.getElementById('previewTextHex').textContent = textColor;

            // Update item card
            const itemCard = document.getElementById('previewItemCard');
            itemCard.style.borderColor = primaryColor;
            itemCard.style.backgroundColor = secondaryColor;
            itemCard.style.color = textColor;

            const itemTitle = itemCard.querySelector('.preview-item-title');
            itemTitle.style.color = textColor;

            const button = document.getElementById('previewButton');
            button.style.backgroundColor = accentColor;
            button.style.color = textColor;

            // Update org header
            const orgHeader = document.getElementById('previewOrgHeader');
            orgHeader.style.backgroundColor = primaryColor;
            orgHeader.style.color = 'white';

            document.getElementById('previewOrgName').textContent = orgName;
            document.getElementById('previewOrgName').style.color = 'white';

            if (eventDate) {
                const dateObj = new Date(eventDate);
                const formatted = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                document.getElementById('previewOrgDate').textContent = formatted;
            }

            // Update logo if provided
            const logo = document.getElementById('previewLogo');
            if (logoUrl && isValidUrl(logoUrl)) {
                logo.style.backgroundImage = `url('${logoUrl}')`;
                logo.style.backgroundSize = 'contain';
                logo.style.backgroundPosition = 'center';
                logo.textContent = '';
            } else {
                logo.style.backgroundImage = 'none';
                logo.textContent = orgName.substring(0, 2).toUpperCase();
            }

            // Check contrast
            const contrast = getContrastRatio(textColor, secondaryColor);
            const contrastWarning = document.getElementById('contrastWarning');
            if (contrast < 0.5) {
                contrastWarning.classList.add('show');
            } else {
                contrastWarning.classList.remove('show');
            }
        }

        /**
         * Validate URL format
         */
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }

        /**
         * Select organization
         */
        function selectOrganization(orgId) {
            window.location.href = `?org_id=${orgId}`;
        }

        /**
         * Reset to default colors
         */
        function resetToDefaults() {
            if (confirm('Are you sure you want to reset to default colors?')) {
                document.getElementById('primaryColor').value = DEFAULT_COLORS.primary;
                document.getElementById('primaryColorHex').value = DEFAULT_COLORS.primary;
                document.getElementById('secondaryColor').value = DEFAULT_COLORS.secondary;
                document.getElementById('secondaryColorHex').value = DEFAULT_COLORS.secondary;
                document.getElementById('accentColor').value = DEFAULT_COLORS.accent;
                document.getElementById('accentColorHex').value = DEFAULT_COLORS.accent;
                document.getElementById('textColor').value = DEFAULT_COLORS.text;
                document.getElementById('textColorHex').value = DEFAULT_COLORS.text;
                updatePreview();
            }
        }

        /**
         * Save branding settings via AJAX
         */
        async function saveBranding() {
            const orgId = document.getElementById('orgId').value;
            const orgName = document.getElementById('orgName').value;
            const logoUrl = document.getElementById('logoUrl').value;
            const contactEmail = document.getElementById('contactEmail').value;
            const primaryColor = document.getElementById('primaryColor').value;
            const accentColor = document.getElementById('accentColor').value;

            // Validate colors
            if (!isValidHex(primaryColor) || !isValidHex(accentColor)) {
                showMessage('error', 'Please enter valid hex color codes (e.g., #172235)');
                return;
            }

            // Validate URL if provided
            if (logoUrl && !isValidUrl(logoUrl)) {
                showMessage('error', 'Please enter a valid URL for the logo');
                return;
            }

            // Disable submit button and show loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').style.display = 'none';
            submitBtn.querySelector('.btn-spinner').style.display = 'inline-block';

            try {
                const response = await fetch('/api/admin/update-branding.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_organization',
                        organization_id: parseInt(orgId),
                        name: orgName,
                        logo_url: logoUrl,
                        contact_email: contactEmail,
                        brand_primary: primaryColor,
                        brand_accent: accentColor
                    })
                });

                const data = await response.json();

                if (data.status === 'ok' || response.ok) {
                    showMessage('success', '✓ Branding settings saved successfully!');
                } else {
                    showMessage('error', data.message || 'Failed to save branding');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('error', 'An error occurred while saving: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.querySelector('.btn-text').style.display = 'inline';
                submitBtn.querySelector('.btn-spinner').style.display = 'none';
            }
        }

        /**
         * Show message
         */
        function showMessage(type, message) {
            const msgEl = document.getElementById(type + 'Message');
            if (msgEl) {
                msgEl.textContent = message;
                msgEl.classList.add('show');
                setTimeout(() => {
                    msgEl.classList.remove('show');
                }, 5000);
            }
        }

        /**
         * Logout
         */
        document.getElementById('logoutBtn')?.addEventListener('click', async () => {
            try {
                await fetch('/api/auth/logout.php', { method: 'POST' });
                window.location.href = '/admin.php';
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = '/admin.php';
            }
        });

        // Initialize preview on load
        document.addEventListener('DOMContentLoaded', updatePreview);
    </script>
</body>
</html>
