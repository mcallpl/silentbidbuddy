<?php
// ============================================================
// SILENT BID BUDDY — Admin Dashboard
// Comprehensive admin interface for auction management
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin-auth.php';

$is_logged_in = isAdminLoggedIn();
$page_title = APP_NAME . ' — Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-page">
    <!-- Login Screen (shown if not logged in) -->
    <div id="loginContainer" class="login-container" style="<?php echo $is_logged_in ? 'display:none;' : ''; ?>">
        <div class="login-box">
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p class="subtitle">Admin Dashboard</p>

            <form id="loginForm" class="admin-form">
                <div class="form-group">
                    <label for="adminTokenInput" class="form-label">Admin Token</label>
                    <input
                        type="password"
                        id="adminTokenInput"
                        class="form-input"
                        placeholder="Enter your admin token"
                        required
                        autocomplete="off"
                    />
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-spinner" style="display: none;">Signing in...</span>
                </button>

                <div id="loginError" class="error-message" style="display: none;"></div>
            </form>
        </div>
    </div>

    <!-- Dashboard (shown if logged in) -->
    <div id="dashboardContainer" class="dashboard-container" style="<?php echo !$is_logged_in ? 'display:none;' : ''; ?>">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1 class="dashboard-title"><?php echo htmlspecialchars(APP_NAME); ?> — Admin</h1>
            </div>
            <div class="header-right">
                <button id="logoutBtn" class="btn btn-secondary btn-small">Logout</button>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="admin-nav">
            <button class="nav-tab active" data-section="dashboard">Dashboard</button>
            <button class="nav-tab" data-section="items">Items</button>
            <button class="nav-tab" data-section="transactions">Transactions</button>
            <button class="nav-tab" data-section="users">Users</button>
        </nav>

        <!-- Main Content -->
        <main class="admin-content">
            <!-- Dashboard Section -->
            <section id="dashboardSection" class="admin-section active">
                <h2>Live Auction Metrics</h2>
                <div class="last-updated">Last Updated: <span id="metricsTimestamp">-</span></div>

                <!-- Metrics Cards -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value" id="metricActiveItems">0</div>
                        <div class="metric-label">Active Items</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricActiveBidders">0</div>
                        <div class="metric-label">Active Bidders (1h)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricTotalBids">0</div>
                        <div class="metric-label">Total Bids (1h)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="metricTotalRaised">$0</div>
                        <div class="metric-label">Estimated Raised</div>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-label">Pending Payments</div>
                        <div class="status-value" id="statusPending">0</div>
                    </div>
                    <div class="status-card">
                        <div class="status-label">Completion Rate</div>
                        <div class="status-value" id="statusCompletion">0%</div>
                    </div>
                </div>

                <!-- High Traffic Items -->
                <h3 style="margin-top: 2rem;">High-Traffic Items</h3>
                <div id="highTrafficContainer" class="data-table">
                    <p class="loading">Loading items...</p>
                </div>

                <!-- Recent Activity -->
                <h3 style="margin-top: 2rem;">Recent Activity</h3>
                <div id="recentActivityContainer" class="data-table">
                    <p class="loading">Loading activity...</p>
                </div>
            </section>

            <!-- Items Section -->
            <section id="itemsSection" class="admin-section">
                <h2>Auction Items</h2>

                <!-- Create Item Button -->
                <button id="createItemBtn" class="btn btn-primary" style="margin-bottom: 1.5rem;">+ Create Item</button>

                <!-- Items List -->
                <div id="itemsContainer" class="data-table">
                    <p class="loading">Loading items...</p>
                </div>

                <!-- Pagination -->
                <div id="itemsPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Transactions Section -->
            <section id="transactionsSection" class="admin-section">
                <h2>Transactions & Payments</h2>

                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="transactionStatusFilter">Filter by Status:</label>
                    <select id="transactionStatusFilter" class="form-input" style="width: auto;">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <!-- Transactions List -->
                <div id="transactionsContainer" class="data-table">
                    <p class="loading">Loading transactions...</p>
                </div>

                <!-- Pagination -->
                <div id="transactionsPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>

            <!-- Users Section -->
            <section id="usersSection" class="admin-section">
                <h2>Bidders & Users</h2>

                <!-- Search -->
                <div class="filter-group">
                    <input
                        type="text"
                        id="userSearchInput"
                        class="form-input"
                        placeholder="Search by name or phone..."
                        style="width: 100%; margin-bottom: 1rem;"
                    />
                </div>

                <!-- Users List -->
                <div id="usersContainer" class="data-table">
                    <p class="loading">Loading users...</p>
                </div>

                <!-- Pagination -->
                <div id="usersPagination" class="pagination" style="display: none; margin-top: 1rem;"></div>
            </section>
        </main>
    </div>

    <!-- Create/Edit Item Modal -->
    <div id="itemModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="itemModalTitle">Create New Item</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <form id="itemForm" class="admin-form">
                <div class="form-group">
                    <label class="form-label">Item Title *</label>
                    <input type="text" name="title" class="form-input" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Item Image</label>
                    <div class="image-upload-zone" id="imageUploadZone">
                        <input type="file" id="imageFileInput" name="image_file" accept="image/*" style="display: none;" />
                        <input type="hidden" name="image_url" id="imageUrlInput" />
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <p class="upload-text">Drag image or URL here or <button type="button" class="upload-btn" id="browseImageBtn">browse from Mac Photo</button></p>
                            <p class="upload-hint">File (JPG, PNG, GIF, WebP) or image URL</p>
                        </div>
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img id="previewImg" alt="Preview" />
                            <button type="button" class="remove-image-btn" id="removeImageBtn">✕ Remove</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Fair Market Value</label>
                    <input type="number" name="fair_market_value" class="form-input" step="0.01" />
                </div>
                <div class="form-group">
                    <label class="form-label">Starting Bid *</label>
                    <input type="number" name="starting_bid" class="form-input" step="0.01" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Minimum Increment *</label>
                    <input type="number" name="min_increment" class="form-input" step="0.01" value="5" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Buy Now Price</label>
                    <input type="number" name="buy_now_price" class="form-input" step="0.01" />
                </div>
                <div class="form-group">
                    <label class="form-label">Auction Duration (hours:minutes:seconds) *</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="duration_hours" class="form-input" min="0" value="2" style="width: 80px;" />
                        <span>:</span>
                        <input type="number" name="duration_minutes" class="form-input" min="0" max="59" value="0" style="width: 80px;" />
                        <span>:</span>
                        <input type="number" name="duration_seconds" class="form-input" min="0" max="59" value="0" style="width: 80px;" />
                    </div>
                </div>
                <div id="itemFormError" class="error-message" style="display: none;"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Item</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="userModalTitle">Bidder Details</h2>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div id="userModalBody" class="modal-body">
                <p class="loading">Loading user details...</p>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            AdminDashboard.init(<?php echo json_encode($is_logged_in); ?>);
        });
    </script>
</body>
</html>
