// ============================================================
// SILENT BID BUDDY — Admin Dashboard JavaScript
// Handles authentication, metrics polling, CRUD operations
// ============================================================

const AdminDashboard = {
    config: {
        metricsRefreshRate: 2000, // 2 seconds
        apiBaseUrl: '/api/admin'
    },

    state: {
        isLoggedIn: false,
        currentSection: 'dashboard',
        currentPage: {},
        metricsInterval: null
    },

    init(isLoggedIn) {
        this.state.isLoggedIn = isLoggedIn;

        if (isLoggedIn) {
            this.setupDashboard();
        } else {
            this.setupLogin();
        }
    },

    setupLogin() {
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const token = document.getElementById('adminTokenInput').value;
            const btn = loginForm.querySelector('.btn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');

            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline';
            btn.disabled = true;

            try {
                const response = await fetch(this.config.apiBaseUrl + '/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token })
                });
                // Note: login endpoint doesn't require auth header since we haven't logged in yet

                const data = await response.json();

                if (response.ok && data.status === 'ok') {
                    window.location.reload();
                } else {
                    loginError.textContent = data.message || 'Invalid token';
                    loginError.style.display = 'block';
                }
            } catch (error) {
                loginError.textContent = 'Error: ' + error.message;
                loginError.style.display = 'block';
            } finally {
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
                btn.disabled = false;
            }
        });
    },

    setupDashboard() {
        // Get admin token from cookie
        this.getAdminTokenFromCookie();

        this.setupNav();
        this.setupModals();
        this.setupButtons();
        this.setupFilters();

        // Load initial dashboard
        this.showSection('dashboard');

        // Start metrics polling
        this.startMetricsPolling();
    },

    getAdminTokenFromCookie() {
        // Extract token from admin_session_token cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'admin_session_token') {
                this.adminToken = decodeURIComponent(value);
                return;
            }
        }
    },

    getAuthHeaders() {
        return {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + (this.adminToken || '')
        };
    },

    setupNav() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.showSection(tab.dataset.section);
            });
        });
    },

    showSection(section) {
        // Update nav tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.section === section);
        });

        // Update sections
        document.querySelectorAll('.admin-section').forEach(sec => {
            sec.classList.toggle('active', sec.id === section + 'Section');
        });

        this.state.currentSection = section;

        // Load section data
        if (section === 'dashboard') {
            this.loadMetrics();
        } else if (section === 'items') {
            this.loadItems(1);
        } else if (section === 'transactions') {
            this.loadTransactions(1);
        } else if (section === 'users') {
            this.loadUsers(1);
        }
    },

    // ============================================================
    // METRICS & DASHBOARD
    // ============================================================

    startMetricsPolling() {
        this.loadMetrics();
        this.state.metricsInterval = setInterval(() => {
            if (this.state.currentSection === 'dashboard') {
                this.loadMetrics();
            }
        }, this.config.metricsRefreshRate);
    },

    async loadMetrics() {
        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-metrics.php', {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                const metrics = data.metrics;
                const summary = data.summary;

                // Update metric cards
                document.getElementById('metricActiveItems').textContent = metrics.active_items || 0;
                document.getElementById('metricActiveBidders').textContent = metrics.active_bidders || 0;
                document.getElementById('metricTotalBids').textContent = metrics.total_bids || 0;
                document.getElementById('metricTotalRaised').textContent = '$' + this.formatCurrency(summary.total_raised || 0);

                // Update status cards
                document.getElementById('statusPending').textContent = summary.pending_payments || 0;
                const completionRate = summary.completion_rate || 0;
                document.getElementById('statusCompletion').textContent = completionRate + '%';

                // Update timestamp
                const now = new Date();
                document.getElementById('metricsTimestamp').textContent = now.toLocaleTimeString();

                // Render high-traffic items
                this.renderHighTrafficItems(metrics.high_traffic_items || []);

                // Render recent activity
                this.renderRecentActivity(metrics.recent_bids || []);
            }
        } catch (error) {
            console.error('Error loading metrics:', error);
        }
    },

    renderHighTrafficItems(items) {
        const container = document.getElementById('highTrafficContainer');

        if (items.length === 0) {
            container.innerHTML = '<p class="empty-state">No bidding activity yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item</th><th>Bids</th><th>Current Bid</th></tr></thead><tbody>';

        items.slice(0, 5).forEach(item => {
            html += `<tr>
                <td>${this.escapeHtml(item.title)}</td>
                <td>${item.bid_count}</td>
                <td>$${this.formatCurrency(item.current_high_bid)}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    renderRecentActivity(bids) {
        const container = document.getElementById('recentActivityContainer');

        if (bids.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent activity</p>';
            return;
        }

        let html = '<div class="activity-list">';

        bids.slice(0, 10).forEach(bid => {
            const time = new Date(bid.created_at).toLocaleTimeString();
            html += `<div class="activity-item">
                <span class="time">${time}</span>
                <span class="activity">${this.escapeHtml(bid.full_name)} bid $${this.formatCurrency(bid.bid_amount)} on "${this.escapeHtml(bid.title)}"</span>
            </div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    // ============================================================
    // ITEMS MANAGEMENT
    // ============================================================

    async loadItems(page = 1) {
        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-items.php?page=' + page + '&limit=25', {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderItemsTable(data.items);
                this.renderPagination('itemsPagination', page, data.pagination.pages, (p) => this.loadItems(p));
                this.state.currentPage.items = page;
            }
        } catch (error) {
            console.error('Error loading items:', error);
        }
    },

    renderItemsTable(items) {
        const container = document.getElementById('itemsContainer');

        if (items.length === 0) {
            container.innerHTML = '<p class="empty-state">No items yet. Create one to get started.</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item #</th><th>Title</th><th>Status</th><th>Current Bid</th><th>Bids</th><th>Time</th><th>Actions</th></tr></thead><tbody>';

        items.forEach(item => {
            const status = item.is_closed ? 'Closed' : 'Active';
            const timeRemaining = item.time_remaining_seconds > 0
                ? this.formatTime(item.time_remaining_seconds)
                : 'Ended';

            html += `<tr>
                <td>#${item.item_number}</td>
                <td>${this.escapeHtml(item.title)}</td>
                <td><span class="badge badge-${item.is_closed ? 'danger' : 'success'}">${status}</span></td>
                <td>$${this.formatCurrency(item.current_high_bid)}</td>
                <td>${item.bid_count}</td>
                <td>${timeRemaining}</td>
                <td>
                    <button class="btn btn-small btn-secondary edit-item" data-id="${item.id}">Edit</button>
                    <button class="btn btn-small btn-secondary delete-item" data-id="${item.id}">Delete</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Attach event listeners
        container.querySelectorAll('.edit-item').forEach(btn => {
            btn.addEventListener('click', () => this.editItem(btn.dataset.id));
        });

        container.querySelectorAll('.delete-item').forEach(btn => {
            btn.addEventListener('click', () => this.deleteItem(btn.dataset.id));
        });
    },

    editItem(itemId) {
        // Open modal and populate with item data
        const modal = document.getElementById('itemModal');
        document.getElementById('itemModalTitle').textContent = 'Edit Item';
        document.getElementById('itemForm').dataset.itemId = itemId;
        document.getElementById('itemFormError').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        modal.style.display = 'block';
        this.setupImageUpload();
        // TODO: Load item data and populate form
    },

    async deleteItem(itemId) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(this.config.apiBaseUrl + '/delete-item.php', {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({ item_id: parseInt(itemId) })
            });

            const data = await response.json();

            if (response.ok && data.status === 'ok') {
                this.showToast('Item deleted successfully', 'success');
                this.loadItems(this.state.currentPage.items || 1);
            } else {
                this.showToast(data.message || 'Error deleting item', 'error');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        }
    },

    // ============================================================
    // TRANSACTIONS
    // ============================================================

    async loadTransactions(page = 1, status = '') {
        try {
            let url = this.config.apiBaseUrl + '/get-transactions.php?page=' + page + '&limit=25';
            if (status) {
                url += '&status=' + encodeURIComponent(status);
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderTransactionsTable(data.transactions);
                this.renderPagination('transactionsPagination', page, data.pagination.pages, (p) => this.loadTransactions(p, status));
                this.state.currentPage.transactions = page;
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
        }
    },

    renderTransactionsTable(transactions) {
        const container = document.getElementById('transactionsContainer');

        if (transactions.length === 0) {
            container.innerHTML = '<p class="empty-state">No transactions yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Item</th><th>Winner</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>';

        transactions.forEach(t => {
            const statusBadge = `<span class="badge badge-${t.status === 'paid' ? 'success' : t.status === 'pending' ? 'warning' : 'danger'}">${t.status}</span>`;
            const date = new Date(t.created_at).toLocaleDateString();

            html += `<tr>
                <td>${this.escapeHtml(t.item_title)}</td>
                <td>${this.escapeHtml(t.winner_name)}</td>
                <td>$${this.formatCurrency(t.amount)}</td>
                <td>${statusBadge}</td>
                <td>${date}</td>
                <td>
                    <button class="btn btn-small btn-secondary" onclick="AdminDashboard.showToast('Resend SMS not yet implemented', 'info')">Resend SMS</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    // ============================================================
    // USERS
    // ============================================================

    async loadUsers(page = 1, search = '') {
        try {
            let url = this.config.apiBaseUrl + '/get-users.php?page=' + page + '&limit=25';
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }

            const response = await fetch(url, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                this.renderUsersTable(data.users);
                this.renderPagination('usersPagination', page, data.pagination.pages, (p) => this.loadUsers(p, search));
                this.state.currentPage.users = page;
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    },

    renderUsersTable(users) {
        const container = document.getElementById('usersContainer');

        if (users.length === 0) {
            container.innerHTML = '<p class="empty-state">No users yet</p>';
            return;
        }

        let html = '<table class="admin-table"><thead><tr><th>Name</th><th>Phone</th><th>Bids</th><th>Won</th><th>Total Spent</th><th>Last Bid</th><th>Actions</th></tr></thead><tbody>';

        users.forEach(user => {
            const lastBid = user.last_bid_at ? new Date(user.last_bid_at).toLocaleTimeString() : '-';

            html += `<tr>
                <td>${this.escapeHtml(user.full_name)}</td>
                <td>${user.phone_display}</td>
                <td>${user.bid_count}</td>
                <td>${user.items_won}</td>
                <td>$${this.formatCurrency(user.total_spent)}</td>
                <td>${lastBid}</td>
                <td>
                    <button class="btn btn-small btn-secondary view-user" data-id="${user.id}">View</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Attach event listeners
        container.querySelectorAll('.view-user').forEach(btn => {
            btn.addEventListener('click', () => this.viewUserDetails(btn.dataset.id));
        });
    },

    async viewUserDetails(userId) {
        const modal = document.getElementById('userModal');
        const body = document.getElementById('userModalBody');

        modal.style.display = 'block';
        body.innerHTML = '<p class="loading">Loading user details...</p>';

        try {
            const response = await fetch(this.config.apiBaseUrl + '/get-user-details.php?user_id=' + userId, {
                headers: this.getAuthHeaders()
            });
            const data = await response.json();

            if (data.status === 'ok') {
                const user = data.user;
                document.getElementById('userModalTitle').textContent = user.full_name + ' — Details';

                let html = '<div class="user-details">';
                html += '<h3>User Information</h3>';
                html += '<p><strong>Name:</strong> ' + this.escapeHtml(user.full_name) + '</p>';
                html += '<p><strong>Phone:</strong> ' + user.phone_display + '</p>';
                html += '<p><strong>Member Since:</strong> ' + new Date(user.created_at).toLocaleDateString() + '</p>';

                if (data.wins.length > 0) {
                    html += '<h3>Won Items</h3>';
                    html += '<table class="admin-table"><thead><tr><th>Item</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                    data.wins.forEach(win => {
                        const statusBadge = `<span class="badge badge-${win.transaction_status === 'paid' ? 'success' : 'warning'}">${win.transaction_status || 'Pending'}</span>`;
                        html += '<tr><td>' + this.escapeHtml(win.title) + '</td><td>$' + this.formatCurrency(win.winning_amount) + '</td><td>' + statusBadge + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                if (data.bid_history.length > 0) {
                    html += '<h3>Recent Bids</h3>';
                    html += '<table class="admin-table"><thead><tr><th>Item</th><th>Bid Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                    data.bid_history.slice(0, 10).forEach(bid => {
                        let badgeClass = 'badge-secondary';
                        if (bid.status === 'WON') badgeClass = 'badge-success';
                        else if (bid.status === 'CURRENT HIGH BID') badgeClass = 'badge-warning';
                        const status = '<span class="badge ' + badgeClass + '">' + bid.status + '</span>';
                        const date = new Date(bid.created_at).toLocaleString();
                        html += '<tr><td>' + this.escapeHtml(bid.item_title) + '</td><td>$' + this.formatCurrency(bid.bid_amount) + '</td><td>' + status + '</td><td>' + date + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                html += '</div>';
                body.innerHTML = html;
            }
        } catch (error) {
            body.innerHTML = '<p class="error-message">Error loading user details: ' + error.message + '</p>';
        }
    },

    // ============================================================
    // MODALS & FORMS
    // ============================================================

    setupModals() {
        // Modal close buttons
        document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const modal = btn.closest('.modal');
                if (modal) modal.style.display = 'none';
            });
        });

        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Item form submission
        document.getElementById('itemForm').addEventListener('submit', (e) => this.handleItemFormSubmit(e));
    },

    async handleItemFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);
        const itemId = form.dataset.itemId;

        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            image_url: formData.get('image_url'),
            fair_market_value: formData.get('fair_market_value') ? parseFloat(formData.get('fair_market_value')) : null,
            starting_bid: parseFloat(formData.get('starting_bid')),
            min_increment: parseFloat(formData.get('min_increment')),
            buy_now_price: formData.get('buy_now_price') ? parseFloat(formData.get('buy_now_price')) : null
        };

        if (!itemId) {
            // Creating new item
            const hours = parseInt(formData.get('duration_hours')) || 0;
            const minutes = parseInt(formData.get('duration_minutes')) || 0;
            const seconds = parseInt(formData.get('duration_seconds')) || 0;

            const endTime = new Date();
            endTime.setHours(endTime.getHours() + hours);
            endTime.setMinutes(endTime.getMinutes() + minutes);
            endTime.setSeconds(endTime.getSeconds() + seconds);

            data.auction_end_time = endTime.toISOString();
        }

        try {
            const url = itemId
                ? this.config.apiBaseUrl + '/update-item.php'
                : this.config.apiBaseUrl + '/create-item.php';

            const response = await fetch(url, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify(itemId ? { item_id: itemId, ...data } : data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'ok') {
                this.showToast(itemId ? 'Item updated' : 'Item created', 'success');
                document.getElementById('itemModal').style.display = 'none';
                form.reset();
                this.loadItems(this.state.currentPage.items || 1);
            } else {
                const errorDiv = document.getElementById('itemFormError');
                errorDiv.textContent = result.message || 'Error saving item';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            const errorDiv = document.getElementById('itemFormError');
            errorDiv.textContent = 'Error: ' + error.message;
            errorDiv.style.display = 'block';
        }
    },

    setupButtons() {
        // Create item button
        document.getElementById('createItemBtn')?.addEventListener('click', () => {
            document.getElementById('itemModalTitle').textContent = 'Create New Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemForm').dataset.itemId = '';
            document.getElementById('itemFormError').style.display = 'none';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('uploadPlaceholder').style.display = 'block';
            document.getElementById('itemModal').style.display = 'block';
            this.setupImageUpload();
        });

        // Logout button
        document.getElementById('logoutBtn').addEventListener('click', () => {
            this.logout();
        });
    },

    setupImageUpload() {
        const zone = document.getElementById('imageUploadZone');
        const fileInput = document.getElementById('imageFileInput');
        const browseBtn = document.getElementById('browseImageBtn');
        const removeBtn = document.getElementById('removeImageBtn');

        if (!zone) return;

        browseBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) this.handleImageFile(file);
        });

        removeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.clearImage();
        });

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');

            // Try to handle file drop first
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    this.handleImageFile(file);
                    return;
                }
            }

            // Try to handle text URL drop
            const text = e.dataTransfer.getData('text/plain') || e.dataTransfer.getData('text/uri-list');
            if (text) {
                const trimmedText = text.trim();
                // Check if it looks like a URL
                if (trimmedText.startsWith('http://') || trimmedText.startsWith('https://')) {
                    this.handleImageURL(trimmedText);
                } else {
                    this.showToast('Please drop an image file or image URL', 'error');
                }
            } else if (files.length === 0) {
                this.showToast('Please drop an image file or image URL', 'error');
            }
        });
    },

    handleImageFile(file) {
        if (file.size > 10 * 1024 * 1024) {
            this.showToast('Image must be smaller than 10MB', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl = e.target.result;
            document.getElementById('imageUrlInput').value = dataUrl;
            document.getElementById('previewImg').src = dataUrl;
            document.getElementById('imagePreview').style.display = 'flex';
            document.getElementById('uploadPlaceholder').style.display = 'none';
        };
        reader.onerror = () => {
            this.showToast('Error reading image file', 'error');
        };
        reader.readAsDataURL(file);
    },

    handleImageURL(url) {
        // Show loading state
        const previewImg = document.getElementById('previewImg');
        previewImg.alt = 'Loading...';
        document.getElementById('imageUrlInput').value = url;
        document.getElementById('imagePreview').style.display = 'flex';
        document.getElementById('uploadPlaceholder').style.display = 'none';

        // Test if the URL is a valid image
        const img = new Image();
        img.onload = () => {
            // URL is valid, update preview
            previewImg.src = url;
            previewImg.alt = 'Preview';
            this.showToast('Image URL loaded', 'success');
        };
        img.onerror = () => {
            this.showToast('Invalid image URL or image not accessible', 'error');
            this.clearImage();
        };
        img.src = url;
    },

    clearImage() {
        document.getElementById('imageFileInput').value = '';
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('previewImg').src = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
    },

    setupFilters() {
        // Transaction status filter
        document.getElementById('transactionStatusFilter').addEventListener('change', (e) => {
            this.loadTransactions(1, e.target.value);
        });

        // User search (with debounce)
        let searchTimeout;
        document.getElementById('userSearchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.loadUsers(1, e.target.value);
            }, 300);
        });
    },

    // ============================================================
    // UTILITIES
    // ============================================================

    async logout() {
        try {
            await fetch(this.config.apiBaseUrl + '/logout.php', {
                method: 'POST',
                headers: this.getAuthHeaders()
            });
            window.location.reload();
        } catch (error) {
            console.error('Logout error:', error);
            window.location.href = '/admin.php';
        }
    },

    renderPagination(containerId, currentPage, totalPages, onPageClick) {
        const container = document.getElementById(containerId);

        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        let html = '<div class="pagination-links">';

        if (currentPage > 1) {
            html += `<button class="pagination-btn" onclick="AdminDashboard.handlePaginationClick(${currentPage - 1}, arguments[1])">← Previous</button>`;
        }

        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            const active = i === currentPage ? ' active' : '';
            html += `<button class="pagination-btn${active}" onclick="AdminDashboard.handlePaginationClick(${i}, arguments[1])">${i}</button>`;
        }

        if (currentPage < totalPages) {
            html += `<button class="pagination-btn" onclick="AdminDashboard.handlePaginationClick(${currentPage + 1}, arguments[1])">Next →</button>`;
        }

        html += '</div>';
        container.innerHTML = html;

        // Store callback for pagination clicks
        window.AdminDashboardPaginationCallback = onPageClick;
    },

    handlePaginationClick(page, event) {
        event?.preventDefault();
        if (window.AdminDashboardPaginationCallback) {
            window.AdminDashboardPaginationCallback(page);
        }
    },

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    formatCurrency(amount) {
        return parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        } else {
            return secs + 's';
        }
    },

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
};
