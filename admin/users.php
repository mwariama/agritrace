<?php
// users.php - User Management for AgriMarketplace Admin Panel

// Include navigation system (handles authentication automatically)
require_once 'admin_navigation.php';

// Initialize navigation with authentication check
$nav_data = initializeAdminNavigation('User Management', 'users');

// Check if this is an API request
if (isset($_GET['action']) || isset($_POST['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../database.php';
    handleApiRequest();
    exit;
}

// Generate the page with navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriMarketplace - <?php echo htmlspecialchars($nav_data['page_title']); ?></title>
    
    <!-- Base navigation styles -->
    <?php echo generateNavigationCSS(); ?>
    
    <!-- Page specific styles -->
    <link rel="stylesheet" href="users.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Additional meta for mobile -->
    <meta name="theme-color" content="#1F7A4C">
    
    <style>
        /* Additional inline styles from users.html */
        .context-menu {
            position: absolute;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 1000;
            min-width: 180px;
            border: 1px solid var(--border-light);
        }
        .context-menu-item {
            padding: 10px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s;
        }
        .context-menu-item:hover {
            background-color: var(--page-bg);
        }
        .context-menu-item.delete:hover {
            color: var(--blocked);
            background-color: #ffeeee;
        }
        .user-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .user-detail-section h4 {
            color: var(--primary-green);
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 5px;
        }
        .user-detail-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
            padding: 20px;
            background: var(--soft-green);
            border-radius: 8px;
        }
        .stat-box {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-green);
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .detail-table th {
            text-align: left;
            padding: 8px;
            background: var(--soft-green);
            font-size: 12px;
        }
        .detail-table td {
            padding: 8px;
            border-bottom: 1px solid var(--border-light);
            font-size: 12px;
        }
        .activities-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            gap: 10px;
            font-size: 12px;
        }
        .activity-action {
            font-weight: 600;
            color: var(--primary-green);
        }
        .activity-entity {
            color: var(--text-secondary);
        }
        .activity-time {
            margin-left: auto;
            color: var(--text-secondary);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-green);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .btn-primary {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-secondary {
            background: var(--text-secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-danger {
            background: var(--blocked);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .profile-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .profile-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--soft-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--primary-green);
            border: 3px solid var(--primary-green);
            overflow: hidden;
        }
        .profile-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .password-strength {
            margin-top: 5px;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s;
        }
        .strength-weak { background: var(--blocked); width: 25%; }
        .strength-fair { background: var(--pending); width: 50%; }
        .strength-good { background: var(--accent-green); width: 75%; }
        .strength-strong { background: var(--active); width: 100%; }
        .user-avatar-img {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            object-fit: cover;
        }
        .user-detail-avatar-img {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Generate sidebar from navigation system -->
        <?php echo generateAdminSidebar($nav_data); ?>
        
        <main class="main-content">
            <!-- Generate header from navigation system -->
            <?php echo generateAdminHeader($nav_data); ?>
            
            <!-- Page Content -->
            <div class="content-area" id="contentArea">
                <div class="loading">Loading users...</div>
            </div>
        </main>
    </div>

    <!-- Modals (exactly as in users.html) -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <span class="close-modal" onclick="UserManager.closeModal()">&times;</span>
            </div>
            <div id="userDetailContent"></div>
        </div>
    </div>

    <div class="modal" id="editUserModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Edit User</h3>
                <span class="close-modal" onclick="UserManager.closeEditModal()">&times;</span>
            </div>
            <div id="alertMessage" class="alert"></div>
            <form id="editUserForm" onsubmit="event.preventDefault(); UserManager.saveUser();">
                <input type="hidden" id="editUserId">
                <div class="profile-upload">
                    <div class="profile-preview" id="profilePreview">
                        <span id="profileInitial">👤</span>
                    </div>
                    <input type="file" id="profileImage" accept="image/*" style="display: none;">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('profileImage').click()">Upload Photo</button>
                </div>
                <div class="form-group">
                    <label for="editFullName">Full Name *</label>
                    <input type="text" id="editFullName" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label for="editEmail">Email *</label>
                    <input type="email" id="editEmail" required placeholder="Enter email address">
                </div>
                <div class="form-group">
                    <label for="editPhone">Phone Number *</label>
                    <input type="tel" id="editPhone" required placeholder="Enter phone number">
                </div>
                <div class="form-group">
                    <label for="editUserType">User Type *</label>
                    <select id="editUserType" required>
                        <option value="">Select user type</option>
                        <option value="Farmer">Farmer</option>
                        <option value="Buyer">Buyer</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="passwordField">
                    <label for="editPassword">Password *</label>
                    <input type="password" id="editPassword" placeholder="Enter password">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="passwordStrength"></div>
                    </div>
                </div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" id="editIsActive" checked>
                        Active Account
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <button type="button" class="btn-secondary" onclick="UserManager.closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close-modal" onclick="UserManager.closeDeleteModal()">&times;</span>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <p style="margin-bottom: 20px;">Are you sure you want to delete this user?</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button class="btn-danger" onclick="UserManager.confirmDelete()">Delete</button>
                    <button class="btn-secondary" onclick="UserManager.closeDeleteModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="resetPasswordModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <span class="close-modal" onclick="UserManager.closeResetModal()">&times;</span>
            </div>
            <div id="resetAlert" class="alert"></div>
            <form id="resetPasswordForm" onsubmit="event.preventDefault(); UserManager.confirmResetPassword();">
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" required placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" required placeholder="Confirm new password">
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="resetPasswordStrength"></div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Reset Password</button>
                    <button type="button" class="btn-secondary" onclick="UserManager.closeResetModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: none;"></div>

    <!-- Generate loading animation from navigation system -->
    <?php echo generateLoadingAnimation(); ?>

    <!-- JavaScript (exactly as in users.js, with corrected API baseUrl) -->
    <script>
    // API 
    const API = {
        baseUrl: (() => {
            const path = window.location.pathname;
            if (path.includes('/COMP390-PROJECT/admin/')) {
                return '/COMP390-PROJECT/admin';
            } else if (path.includes('/admin/')) {
                return '/admin';
            }
            return '';
        })(),
        
        async get(action, params = {}) {
            try {
                const queryString = new URLSearchParams({ action, ...params }).toString();
                const url = `${this.baseUrl}/users.php?${queryString}`;
                console.log('GET request:', url);
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                console.log('GET response:', data);
                return data;
            } catch (error) {
                console.error('API Error:', error);
                return { error: error.message };
            }
        },
        
        async post(action, data) {
            try {
                const url = `${this.baseUrl}/users.php?action=${action}`;
                console.log('POST request:', url, data);
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                console.log('POST response:', result);
                return result;
            } catch (error) {
                console.error('API Error:', error);
                return { success: false, error: error.message };
            }
        }
    };

    // User Manager (exactly as in users.js)
    const UserManager = {
        users: [],
        filteredUsers: [],
        currentFilter: 'all',
        searchTerm: '',
        currentPage: 1,
        itemsPerPage: 10,
        selectedUserId: null,
        stats: {
            active: 0,
            inactive: 0,
            Farmers: 0,
            Buyers: 0,
            Admins: 0
        },
        userTypes: [],
        
        init: async function() {
            console.log('Initializing UserManager...');
            await this.loadUserTypes();
            await this.loadStats();
            await this.loadUsers();
            this.setupEventListeners();
            this.render();
        },
        
        loadUsers: async function() {
            console.log('Loading users...');
            const result = await API.get('get_users');
            if (!result.error) {
                this.users = result;
                this.filteredUsers = [...this.users];
                console.log('Users loaded:', this.users.length);
            } else {
                console.error('Error loading users:', result.error);
                this.showToast('Error loading users: ' + result.error, 'error');
            }
        },
        
        loadStats: async function() {
            console.log('Loading stats...');
            const result = await API.get('get_stats');
            if (!result.error) {
                this.stats = { ...this.stats, ...result };
                console.log('Stats loaded:', this.stats);
            }
        },
        
        loadUserTypes: async function() {
            console.log('Loading user types...');
            const result = await API.get('get_user_types');
            if (!result.error) {
                this.userTypes = result;
                console.log('User types loaded:', this.userTypes);
            }
        },
        
        loadAdminInfo: async function() {
            try {
                const response = await fetch(`${API.baseUrl}/dashboard.php?endpoint=admin_info`);
                if (response.ok) {
                    const adminData = await response.json();
                    if (adminData.full_name) {
                        document.getElementById('adminName').textContent = adminData.full_name;
                        document.getElementById('adminAvatar').textContent = adminData.full_name.charAt(0);
                    }
                }
            } catch (error) {
                console.error('Error loading admin info:', error);
            }
        },
        
        setupEventListeners: function() {
            console.log('Setting up event listeners...');
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.searchTerm = e.target.value.toLowerCase();
                    this.currentPage = 1;
                    this.filterUsers();
                });
            }
            
            // Close context menu when clicking outside
            document.addEventListener('click', () => {
                document.querySelectorAll('.context-menu').forEach(m => m.remove());
            });
        },
        
        filterUsers: function() {
            console.log('Filtering users with filter:', this.currentFilter, 'search:', this.searchTerm);
            this.filteredUsers = this.users.filter(user => {
                const matchesSearch = 
                    (user.full_name && user.full_name.toLowerCase().includes(this.searchTerm)) ||
                    (user.email && user.email.toLowerCase().includes(this.searchTerm)) ||
                    (user.phone_number && user.phone_number.includes(this.searchTerm));
                
                if (this.currentFilter === 'all') return matchesSearch;
                if (this.currentFilter === 'active') return matchesSearch && user.is_active == 1;
                if (this.currentFilter === 'inactive') return matchesSearch && user.is_active == 0;
                return matchesSearch && user.user_type && user.user_type.toLowerCase() === this.currentFilter;
            });
            console.log('Filtered users count:', this.filteredUsers.length);
            this.render();
        },
        
        setFilter: function(filter) {
            console.log('Setting filter to:', filter);
            this.currentFilter = filter;
            this.currentPage = 1;
            this.filterUsers();
        },
        
        getPaginatedUsers: function() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredUsers.slice(start, end);
        },
        
        getTotalPages: function() {
            return Math.ceil(this.filteredUsers.length / this.itemsPerPage);
        },
        
        changePage: function(page) {
            console.log('Changing to page:', page);
            if (page < 1 || page > this.getTotalPages()) return;
            this.currentPage = page;
            this.render();
        },
        
        // View User Details
        viewUser: async function(userId) {
            console.log('Viewing user:', userId);
            this.selectedUserId = userId;
            const userData = await API.get('get_user', { user_id: userId });
            if (!userData.error && userData) {
                this.showUserModal(userData);
            } else {
                this.showToast('Error loading user details', 'error');
            }
        },
        
        showUserModal: function(user) {
            console.log('Showing user modal for:', user);
            const modal = document.getElementById('userModal');
            const content = document.getElementById('userDetailContent');
            
            const userType = user.user_type || 'N/A';
            const userTypeLower = userType.toLowerCase();
            
            let html = `
                <div class="user-detail-header">
                    <div class="user-detail-avatar">${user.full_name ? user.full_name.charAt(0) : 'U'}</div>
                    <div class="user-detail-title">
                        <h2>${user.full_name || 'N/A'}</h2>
                        <span class="role-badge ${userTypeLower}">${userType}</span>
                    </div>
                </div>
                
                <div class="user-detail-grid">
                    <div class="user-detail-section">
                        <h4>Contact Information</h4>
                        <div class="user-detail-item">
                            <div class="user-detail-label">Email</div>
                            <div class="user-detail-value">${user.email || 'N/A'}</div>
                        </div>
                        <div class="user-detail-item">
                            <div class="user-detail-label">Phone</div>
                            <div class="user-detail-value">${user.phone_number || 'N/A'}</div>
                        </div>
                        <div class="user-detail-item">
                            <div class="user-detail-label">Status</div>
                            <div class="user-detail-value">
                                <span class="status-badge ${user.is_active ? 'active' : 'inactive'}">
                                    ${user.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-detail-section">
                        <h4>Account Information</h4>
                        <div class="user-detail-item">
                            <div class="user-detail-label">Last Login</div>
                            <div class="user-detail-value">${user.last_login || 'Never'}</div>
                        </div>
                        <div class="user-detail-item">
                            <div class="user-detail-label">Member Since</div>
                            <div class="user-detail-value">${user.created_at || 'N/A'}</div>
                        </div>
                        <div class="user-detail-item">
                            <div class="user-detail-label">User ID</div>
                            <div class="user-detail-value">${user.id}</div>
                        </div>
                    </div>
                </div>
                
                <div class="user-detail-stats">
                    <div class="stat-box">
                        <div class="stat-value">${user.total_farms || 0}</div>
                        <div class="stat-label">Total Farms</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${user.total_orders || 0}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${user.total_land || 0}</div>
                        <div class="stat-label">Acres</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${user.total_yield || 0}</div>
                        <div class="stat-label">Yield (kg)</div>
                    </div>
                </div>
            `;
            
            if (user.user_type === 'Farmer' && user.plantings && user.plantings.length > 0) {
                html += `
                    <div class="user-detail-section">
                        <h4>Recent Plantings</h4>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Land (acres)</th>
                                    <th>Yield (kg)</th>
                                    <th>Status</th>
                                    <th>Harvest Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${user.plantings.map(p => `
                                    <tr>
                                        <td>${p.crop_name || 'N/A'}</td>
                                        <td>${p.land_size_acres || 0}</td>
                                        <td>${p.expected_yield_kg || 0}</td>
                                        <td><span class="status-badge">${p.status || 'N/A'}</span></td>
                                        <td>${p.expected_harvest_date || 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            if (user.user_type === 'Buyer' && user.orders && user.orders.length > 0) {
                html += `
                    <div class="user-detail-section">
                        <h4>Recent Orders</h4>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Quantity (kg)</th>
                                    <th>Total Price</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${user.orders.map(o => `
                                    <tr>
                                        <td>${o.crop_name || 'N/A'}</td>
                                        <td>${o.quantity_ordered_kg || 0}</td>
                                        <td>KES ${o.total_price || 0}</td>
                                        <td><span class="status-badge">${o.escrow_status || 'N/A'}</span></td>
                                        <td>${o.created_at || 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            if (user.recent_activities && user.recent_activities.length > 0) {
                html += `
                    <div class="user-detail-section">
                        <h4>Recent Activities</h4>
                        <div class="activities-list">
                            ${user.recent_activities.map(a => `
                                <div class="activity-item">
                                    <span class="activity-action">${a.action || 'N/A'}</span>
                                    <span class="activity-entity">${a.entity_type || ''}</span>
                                    <span class="activity-time">${a.created_at || ''}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            html += `
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button class="btn-primary" onclick="UserManager.editUser(${user.id})">Edit User</button>
                    <button class="btn-secondary" onclick="UserManager.closeModal()">Close</button>
                </div>
            `;
            
            content.innerHTML = html;
            modal.classList.add('active');
        },
        
        // Edit User
        editUser: function(userId) {
            console.log('Edit user called with ID:', userId);
            this.selectedUserId = userId;
            
            if (userId) {
                this.showEditModal(null);
                this.loadUserForEdit(userId);
            } else {
                this.showEditModal(null);
            }
        },
        
        loadUserForEdit: async function(userId) {
            console.log('Loading user for edit:', userId);
            const userData = await API.get('get_user', { user_id: userId });
            if (!userData.error && userData) {
                this.populateEditModal(userData);
            } else {
                this.showToast('Error loading user data', 'error');
            }
        },
        
        showEditModal: function(user) {
            console.log('Showing edit modal with user:', user);
            const modal = document.getElementById('editUserModal');
            const title = document.getElementById('modalTitle');
            const alertDiv = document.getElementById('alertMessage');
            
            if (!modal) {
                console.error('Edit modal not found in DOM');
                return;
            }
            
            if (alertDiv) {
                alertDiv.style.display = 'none';
                alertDiv.className = 'alert';
            }
            
            if (user) {
                this.populateEditModal(user);
            } else {
                const userIdField = document.getElementById('editUserId');
                const fullNameField = document.getElementById('editFullName');
                const emailField = document.getElementById('editEmail');
                const phoneField = document.getElementById('editPhone');
                const userTypeField = document.getElementById('editUserType');
                const isActiveField = document.getElementById('editIsActive');
                
                if (userIdField) userIdField.value = '';
                if (fullNameField) fullNameField.value = '';
                if (emailField) emailField.value = '';
                if (phoneField) phoneField.value = '';
                if (userTypeField) userTypeField.value = 'Farmer';
                if (isActiveField) isActiveField.checked = true;
                
                title.textContent = 'Add New User';
            }
            
            modal.classList.add('active');
            console.log('Edit modal displayed');
            
            document.querySelectorAll('.context-menu').forEach(m => m.remove());
        },
        
        populateEditModal: function(user) {
            console.log('Populating edit modal with:', user);
            document.getElementById('editUserId').value = user.id || '';
            document.getElementById('editFullName').value = user.full_name || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editPhone').value = user.phone_number || '';
            document.getElementById('editUserType').value = user.user_type || 'Farmer';
            document.getElementById('editIsActive').checked = user.is_active == 1;
            document.getElementById('modalTitle').textContent = 'Edit User';
        },
        
        saveUser: async function() {
            console.log('Saving user...');
            
            const userId = document.getElementById('editUserId').value;
            const fullName = document.getElementById('editFullName').value.trim();
            const email = document.getElementById('editEmail').value.trim();
            const phone = document.getElementById('editPhone').value.trim();
            const userType = document.getElementById('editUserType').value;
            const isActive = document.getElementById('editIsActive').checked;
            
            const formData = {
                full_name: fullName,
                email: email,
                phone_number: phone,
                user_type: userType,
                is_active: isActive
            };
            
            console.log('Form data:', formData);
            
            if (!formData.full_name) {
                this.showAlert('Please enter full name', 'error');
                return;
            }
            if (!formData.email) {
                this.showAlert('Please enter email', 'error');
                return;
            }
            if (!this.validateEmail(formData.email)) {
                this.showAlert('Please enter a valid email address', 'error');
                return;
            }
            if (!formData.phone_number) {
                this.showAlert('Please enter phone number', 'error');
                return;
            }
            if (!formData.user_type) {
                this.showAlert('Please select user type', 'error');
                return;
            }
            
            let result;
            if (userId) {
                formData.user_id = parseInt(userId);
                console.log('Updating user:', formData);
                result = await API.post('update_user', formData);
            } else {
                console.log('Creating user:', formData);
                result = await API.post('create_user', formData);
            }
            
            console.log('Save result:', result);
            
            if (result && result.success) {
                this.closeEditModal();
                await this.loadUsers();
                await this.loadStats();
                this.render();
                this.showToast(userId ? 'User updated successfully!' : 'User created successfully!', 'success');
            } else {
                const errorMsg = result ? (result.error || 'Failed to save user') : 'Failed to save user';
                this.showAlert('Error: ' + errorMsg, 'error');
            }
        },
        
        showAlert: function(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            if (alertDiv) {
                alertDiv.textContent = message;
                alertDiv.className = 'alert ' + type;
                alertDiv.style.display = 'block';
                
                setTimeout(() => {
                    alertDiv.style.display = 'none';
                }, 5000);
            } else {
                alert(message);
            }
        },
        
        showToast: function(message, type) {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.textContent = message;
                toast.style.cssText = `
                    background: ${type === 'success' ? '#4CAF50' : '#f44336'};
                    color: white;
                    padding: 12px 24px;
                    border-radius: 4px;
                    font-size: 14px;
                    display: block;
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 9999;
                `;
                
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 3000);
            } else {
                alert(message);
            }
        },
        
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // Delete User
        deleteUser: function(userId) {
            console.log('Delete user called with ID:', userId);
            this.selectedUserId = userId;
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('active');
            }
            
            document.querySelectorAll('.context-menu').forEach(m => m.remove());
        },
        
        confirmDelete: async function() {
            console.log('Confirming delete for user:', this.selectedUserId);
            if (!this.selectedUserId) return;
            
            const result = await API.post('delete_user', { user_id: this.selectedUserId });
            console.log('Delete result:', result);
            
            if (result && result.success) {
                this.closeDeleteModal();
                await this.loadUsers();
                await this.loadStats();
                this.render();
                this.showToast('User deleted successfully!', 'success');
            } else {
                const errorMsg = result ? (result.error || 'Failed to delete user') : 'Failed to delete user';
                this.showToast('Error: ' + errorMsg, 'error');
                this.closeDeleteModal();
            }
        },
        
        // Toggle User Status
        toggleUserStatus: async function(userId, currentStatus) {
            console.log('Toggling status for user:', userId, 'current:', currentStatus);
            if (!confirm(`Are you sure you want to ${currentStatus ? 'deactivate' : 'activate'} this user?`)) {
                return;
            }
            
            const result = await API.post('update_status', {
                user_id: userId,
                is_active: currentStatus ? 0 : 1
            });
            
            console.log('Toggle status result:', result);
            
            if (result && result.success) {
                await this.loadUsers();
                this.render();
                this.showToast(`User ${currentStatus ? 'deactivated' : 'activated'} successfully!`, 'success');
            } else {
                const errorMsg = result ? (result.error || 'Failed to update status') : 'Failed to update status';
                this.showToast('Error: ' + errorMsg, 'error');
            }
        },
        
        // More Options (shows action menu)
        moreOptions: function(userId, event) {
            console.log('More options for user:', userId);
            event.stopPropagation();
            this.selectedUserId = userId;
            
            document.querySelectorAll('.context-menu').forEach(m => m.remove());
            
            const menu = document.createElement('div');
            menu.className = 'context-menu';
            menu.innerHTML = `
                <div class="context-menu-item" onclick="UserManager.viewUser(${userId}); event.stopPropagation();">
                    <span>👁️</span> View Details
                </div>
                <div class="context-menu-item" onclick="UserManager.editUser(${userId}); event.stopPropagation();">
                    <span>✏️</span> Edit User
                </div>
                <div class="context-menu-item" onclick="UserManager.resetPassword(${userId}); event.stopPropagation();">
                    <span>🔑</span> Reset Password
                </div>
                <div class="context-menu-item" onclick="UserManager.sendEmail(${userId}); event.stopPropagation();">
                    <span>📧</span> Send Email
                </div>
                <div class="context-menu-item delete" onclick="UserManager.deleteUser(${userId}); event.stopPropagation();">
                    <span>🗑️</span> Delete User
                </div>
            `;
            
            menu.style.position = 'absolute';
            menu.style.top = (event.pageY - 10) + 'px';
            menu.style.left = (event.pageX - 10) + 'px';
            
            document.body.appendChild(menu);
            event.stopPropagation();
        },
        
        // Additional features
        resetPassword: async function(userId) {
            console.log('Reset password for user:', userId);
            if (confirm('Reset password to default (welcome123)?')) {
                this.showToast('Password reset functionality - implement in users.php', 'error');
            }
        },
        
        sendEmail: function(userId) {
            console.log('Send email to user:', userId);
            const user = this.users.find(u => u.id == userId);
            if (user && user.email) {
                window.location.href = `mailto:${user.email}`;
            } else {
                this.showToast('User email not found', 'error');
            }
        },
        
        // Modal controls
        closeModal: function() {
            console.log('Closing user modal');
            document.getElementById('userModal').classList.remove('active');
        },
        
        closeEditModal: function() {
            console.log('Closing edit modal');
            document.getElementById('editUserModal').classList.remove('active');
            const alertDiv = document.getElementById('alertMessage');
            if (alertDiv) {
                alertDiv.style.display = 'none';
            }
        },
        
        closeDeleteModal: function() {
            console.log('Closing delete modal');
            document.getElementById('deleteModal').classList.remove('active');
            this.selectedUserId = null;
        },
        
        closeResetModal: function() {
            console.log('Closing reset modal');
            document.getElementById('resetPasswordModal').classList.remove('active');
            this.selectedUserId = null;
        },
        
        // Render users table
        render: function() {
            console.log('Rendering users table...');
            const paginatedUsers = this.getPaginatedUsers();
            const totalPages = this.getTotalPages();
            const contentArea = document.getElementById('contentArea');
            
            if (!contentArea) {
                console.error('Content area not found');
                return;
            }
            
            let html = `
                <!-- Stats Cards -->
                <div class="user-stats-cards">
                    <div class="user-stat-card">
                        <div class="user-stat-icon">👥</div>
                        <div class="user-stat-content">
                            <h4>Total Users</h4>
                            <div class="stat-number">${this.users.length}</div>
                        </div>
                    </div>
                    <div class="user-stat-card">
                        <div class="user-stat-icon">✅</div>
                        <div class="user-stat-content">
                            <h4>Active</h4>
                            <div class="stat-number">${this.stats.active || 0}</div>
                        </div>
                    </div>
                    <div class="user-stat-card">
                        <div class="user-stat-icon">👨‍🌾</div>
                        <div class="user-stat-content">
                            <h4>Farmers</h4>
                            <div class="stat-number">${this.stats.Farmers || this.stats.Farmer || 0}</div>
                        </div>
                    </div>
                    <div class="user-stat-card">
                        <div class="user-stat-icon">🛒</div>
                        <div class="user-stat-content">
                            <h4>Buyers</h4>
                            <div class="stat-number">${this.stats.Buyers || this.stats.Buyer || 0}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <div class="user-filters">
                        <button class="filter-btn ${this.currentFilter === 'all' ? 'active' : ''}" onclick="UserManager.setFilter('all')">All Users</button>
                        <button class="filter-btn ${this.currentFilter === 'admin' ? 'active' : ''}" onclick="UserManager.setFilter('admin')">Admins</button>
                        <button class="filter-btn ${this.currentFilter === 'farmer' ? 'active' : ''}" onclick="UserManager.setFilter('farmer')">Farmers</button>
                        <button class="filter-btn ${this.currentFilter === 'buyer' ? 'active' : ''}" onclick="UserManager.setFilter('buyer')">Buyers</button>
                        <button class="filter-btn ${this.currentFilter === 'active' ? 'active' : ''}" onclick="UserManager.setFilter('active')">Active</button>
                        <button class="filter-btn ${this.currentFilter === 'inactive' ? 'active' : ''}" onclick="UserManager.setFilter('inactive')">Inactive</button>
                    </div>
                    <button class="btn-primary" onclick="UserManager.editUser(null)" style="display: flex; align-items: center; gap: 8px;">
                        <span>➕</span> Add New User
                    </button>
                </div>
                
                <!-- Users Table -->
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Joined</th>
                                <th>Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (paginatedUsers.length > 0) {
                paginatedUsers.forEach(user => {
                    const userType = user.user_type || 'N/A';
                    const userTypeLower = userType.toLowerCase();
                    const isActive = user.is_active == 1;
                    
                    html += `
                        <tr onclick="UserManager.viewUser(${user.id})" style="cursor: pointer;">
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm">${user.full_name ? user.full_name.charAt(0) : 'U'}</div>
                                    <div class="user-info-sm">
                                        <h4>${user.full_name || 'N/A'}</h4>
                                        <p>ID: ${user.id}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>${user.email || 'N/A'}</div>
                                <small>${user.phone_number || 'N/A'}</small>
                            </td>
                            <td>
                                <span class="role-badge ${userTypeLower}">${userType}</span>
                            </td>
                            <td>
                                <span class="status-badge ${isActive ? 'active' : 'inactive'}">
                                    ${isActive ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>${user.last_login || 'Never'}</td>
                            <td>${user.created_at || 'N/A'}</td>
                            <td>
                                ${userType === 'Farmer' ? `${user.total_farms || 0} farms` : ''}
                                ${userType === 'Buyer' ? `${user.total_orders || 0} orders` : ''}
                            </td>
                            <td onclick="event.stopPropagation()">
                                <div class="action-buttons">
                                    <label class="toggle-switch" title="Toggle Status">
                                        <input type="checkbox" 
                                            ${isActive ? 'checked' : ''} 
                                            onchange="UserManager.toggleUserStatus(${user.id}, ${isActive})">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button class="action-btn view" title="View Details" onclick="UserManager.viewUser(${user.id}); event.stopPropagation();">👁️</button>
                                    <button class="action-btn edit" title="Edit User" onclick="UserManager.editUser(${user.id}); event.stopPropagation();">✏️</button>
                                    <button class="action-btn more" title="More Options" onclick="UserManager.moreOptions(${user.id}, event)">⋯</button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No users found
                        </td>
                    </tr>
                `;
            }
            
            html += `
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    ${totalPages > 1 ? `
                        <div class="pagination">
                            <button class="pagination-btn" onclick="UserManager.changePage(${this.currentPage - 1})" 
                                ${this.currentPage === 1 ? 'disabled' : ''}>←</button>
                            ${Array.from({ length: totalPages }, (_, i) => i + 1).map(page => `
                                <button class="pagination-btn ${page === this.currentPage ? 'active' : ''}" 
                                    onclick="UserManager.changePage(${page})">${page}</button>
                            `).join('')}
                            <button class="pagination-btn" onclick="UserManager.changePage(${this.currentPage + 1})"
                                ${this.currentPage === totalPages ? 'disabled' : ''}>→</button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            contentArea.innerHTML = html;
            console.log('Render complete');
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('DOM loaded, initializing UserManager...');
        await UserManager.init();
        await UserManager.loadAdminInfo();
        
        window.UserManager = UserManager;
        console.log('UserManager initialized and available globally');
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.UserManager = UserManager;
        });
    } else {
        window.UserManager = UserManager;
    }
    </script>

    <!-- Generate navigation scripts -->
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

/**
 * Handle API requests from the users page
 */
function handleApiRequest() {
    global $pdo;
    
    try {
        $pdo = getDBConnection();
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        // Parse JSON input for POST requests
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch($action) {
            case 'get_users':
                getUsers($pdo);
                break;
            case 'get_user':
                getUserDetails($pdo);
                break;
            case 'create_user':
                createUser($pdo, $input);
                break;
            case 'update_user':
                updateUser($pdo, $input);
                break;
            case 'update_status':
                updateUserStatus($pdo, $input);
                break;
            case 'delete_user':
                deleteUser($pdo, $input);
                break;
            case 'get_stats':
                getUserStats($pdo);
                break;
            case 'get_user_types':
                getUserTypes($pdo);
                break;
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get all users with basic information
 */
function getUsers($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.is_active,
                DATE_FORMAT(u.last_login, '%Y-%m-%d %H:%i') as last_login,
                DATE_FORMAT(u.created_at, '%Y-%m-%d') as created_at,
                ut.role_name as user_type,
                (SELECT COUNT(*) FROM planting_requests WHERE farmer_id = u.id) as total_farms,
                (SELECT COUNT(*) FROM orders WHERE buyer_id = u.id) as total_orders
            FROM users u
            JOIN user_types ut ON u.type_id = ut.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get detailed information for a specific user
 */
function getUserDetails($pdo) {
    try {
        $userId = $_GET['user_id'] ?? 0;
        
        if (!$userId) {
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.is_active,
                u.type_id,
                DATE_FORMAT(u.last_login, '%Y-%m-%d %H:%i') as last_login,
                DATE_FORMAT(u.created_at, '%Y-%m-%d') as created_at,
                ut.role_name as user_type,
                (SELECT COUNT(*) FROM planting_requests WHERE farmer_id = u.id) as total_farms,
                (SELECT COUNT(*) FROM orders WHERE buyer_id = u.id) as total_orders,
                (SELECT COALESCE(SUM(land_size_acres), 0) FROM planting_requests WHERE farmer_id = u.id) as total_land,
                (SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests WHERE farmer_id = u.id) as total_yield
            FROM users u
            JOIN user_types ut ON u.type_id = ut.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Get recent activities
            $stmt = $pdo->prepare("
                SELECT action, entity_type, created_at
                FROM system_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $result['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get planting requests if farmer
            if ($result['user_type'] === 'Farmer') {
                $stmt = $pdo->prepare("
                    SELECT 
                        pr.id,
                        c.crop_name,
                        pr.land_size_acres,
                        pr.expected_yield_kg,
                        pr.status,
                        DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                        DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date
                    FROM planting_requests pr
                    LEFT JOIN crops c ON pr.crop_id = c.id
                    WHERE pr.farmer_id = ?
                    ORDER BY pr.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$userId]);
                $result['plantings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get orders if buyer
            if ($result['user_type'] === 'Buyer') {
                $stmt = $pdo->prepare("
                    SELECT 
                        o.id,
                        o.quantity_ordered_kg,
                        o.total_price,
                        o.escrow_status,
                        DATE_FORMAT(o.created_at, '%Y-%m-%d') as created_at,
                        c.crop_name
                    FROM orders o
                    LEFT JOIN marketplace_items mi ON o.marketplace_item_id = mi.id
                    LEFT JOIN planting_requests pr ON mi.planting_request_id = pr.id
                    LEFT JOIN crops c ON pr.crop_id = c.id
                    WHERE o.buyer_id = ?
                    ORDER BY o.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$userId]);
                $result['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Create a new user
 */
function createUser($pdo, $data) {
    try {
        // Validate required fields
        if (empty($data['full_name']) || empty($data['email']) || empty($data['phone_number']) || empty($data['user_type'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            return;
        }
        
        // Check if phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$data['phone_number']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Phone number already exists']);
            return;
        }
        
        // Get user type ID
        $stmt = $pdo->prepare("SELECT id FROM user_types WHERE role_name = ?");
        $stmt->execute([$data['user_type']]);
        $typeId = $stmt->fetchColumn();
        
        if (!$typeId) {
            echo json_encode(['success' => false, 'error' => 'Invalid user type']);
            return;
        }
        
        // Generate password hash (default password: welcome123)
        $defaultPassword = password_hash('welcome123', PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (type_id, full_name, email, phone_number, password_hash, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        
        $stmt->execute([
            $typeId,
            $data['full_name'],
            $data['email'],
            $data['phone_number'],
            $defaultPassword,
            $isActive
        ]);
        
        $newUserId = $pdo->lastInsertId();
        
        // Log the action
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (user_id, action, entity_type, entity_id, new_data) 
                VALUES (?, 'CREATE', 'users', ?, ?)
            ");
            $stmt->execute([
                1, // Assuming admin ID 1
                $newUserId,
                json_encode($data)
            ]);
        } catch (Exception $e) {
            error_log('Failed to log user creation: ' . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'user_id' => $newUserId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update an existing user
 */
function updateUser($pdo, $data) {
    try {
        $userId = $data['user_id'] ?? 0;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            return;
        }
        
        // Check if email exists for another user
        if (!empty($data['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                return;
            }
        }
        
        // Check if phone exists for another user
        if (!empty($data['phone_number'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ?");
            $stmt->execute([$data['phone_number'], $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Phone number already exists']);
                return;
            }
        }
        
        // Get current user data for logging
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        // Build update query dynamically
        $updates = [];
        $params = [];
        
        $allowedFields = ['full_name', 'email', 'phone_number', 'is_active'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Update user type if provided
        if (!empty($data['user_type'])) {
            $stmt = $pdo->prepare("SELECT id FROM user_types WHERE role_name = ?");
            $stmt->execute([$data['user_type']]);
            $typeId = $stmt->fetchColumn();
            if ($typeId) {
                $updates[] = "type_id = ?";
                $params[] = $typeId;
            }
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            return;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            // Log the action
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, entity_type, entity_id, old_data, new_data) 
                    VALUES (?, 'UPDATE', 'users', ?, ?, ?)
                ");
                $stmt->execute([
                    1, // Assuming admin ID 1
                    $userId,
                    json_encode($oldData),
                    json_encode($data)
                ]);
            } catch (Exception $e) {
                error_log('Failed to log user update: ' . $e->getMessage());
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update user']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update user active status
 */
function updateUserStatus($pdo, $data) {
    try {
        $userId = $data['user_id'] ?? 0;
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 0;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            return;
        }
        
        // Get current status for logging
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldStatus = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $result = $stmt->execute([$isActive, $userId]);
        
        if ($result) {
            // Log the action
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, entity_type, entity_id, old_data, new_data) 
                    VALUES (?, 'UPDATE_STATUS', 'users', ?, ?, ?)
                ");
                $stmt->execute([
                    1, // Assuming admin ID 1
                    $userId,
                    json_encode(['is_active' => $oldStatus]),
                    json_encode(['is_active' => $isActive])
                ]);
            } catch (Exception $e) {
                error_log('Failed to log status update: ' . $e->getMessage());
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update status']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete or deactivate a user
 */
function deleteUser($pdo, $data) {
    try {
        $userId = $data['user_id'] ?? 0;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            return;
        }
        
        // Get user data for logging
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        // Check if user has related records
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM planting_requests WHERE farmer_id = ?");
        $stmt->execute([$userId]);
        $plantings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ?");
        $stmt->execute([$userId]);
        $orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($plantings > 0 || $orders > 0) {
            // Soft delete - just deactivate
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            $deleteType = 'soft';
        } else {
            // Hard delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $deleteType = 'hard';
        }
        
        // Log the action
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (user_id, action, entity_type, entity_id, old_data, new_data) 
                VALUES (?, 'DELETE', 'users', ?, ?, ?)
            ");
            $stmt->execute([
                1, // Assuming admin ID 1
                $userId,
                json_encode($userData),
                json_encode(['delete_type' => $deleteType])
            ]);
        } catch (Exception $e) {
            error_log('Failed to log user deletion: ' . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'delete_type' => $deleteType]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get user statistics
 */
function getUserStats($pdo) {
    try {
        $stats = [];
        
        // Total users by type
        $stmt = $pdo->prepare("
            SELECT 
                ut.role_name,
                COUNT(u.id) as count
            FROM user_types ut
            LEFT JOIN users u ON ut.id = u.type_id
            GROUP BY ut.id, ut.role_name
        ");
        $stmt->execute();
        $userTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($userTypes as $type) {
            $stats[$type['role_name'] . 's'] = (int)$type['count'];
        }
        
        // Active vs Inactive
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            FROM users
        ");
        $stmt->execute();
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active'] = (int)($status['active'] ?? 0);
        $stats['inactive'] = (int)($status['inactive'] ?? 0);
        
        // New users this month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute();
        $stats['new_this_month'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Total logins
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_login IS NOT NULL");
        $stmt->execute();
        $stats['total_logins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get all user types
 */
function getUserTypes($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, role_name FROM user_types");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>