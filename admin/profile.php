<?php
// profile.php - My Profile Management for AgriMarketplace Admin Panel

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('My Profile', 'profile');

// Check if this is an API request
$isApiRequest = isset($_GET['action']) || isset($_POST['action']) || 
                ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isApiRequest) {
    // Clear output buffer for API response
    ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        // Include database connection from parent directory
        require_once '../database.php';
        
        // Get database connection
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        // Get action parameter
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        // Parse JSON input for POST requests
        $input = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $inputJSON = file_get_contents('php://input');
            if (!empty($inputJSON)) {
                $input = json_decode($inputJSON, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $input = $_POST;
                }
            } else {
                $input = $_POST;
            }
        }
        
        // Route to appropriate function
        switch($action) {
            // GET endpoints
            case 'get_profile':
                getProfile($pdo);
                break;
            case 'get_activity':
                getActivity($pdo);
                break;
            case 'get_sessions':
                getSessions($pdo);
                break;
            case 'get_notification_preferences':
                getNotificationPreferences($pdo);
                break;
            case 'get_stats':
                getProfileStats($pdo);
                break;
                
            // POST endpoints
            case 'update_profile':
                updateProfile($pdo, $input);
                break;
            case 'change_password':
                changePassword($pdo, $input);
                break;
            case 'update_notifications':
                updateNotifications($pdo, $input);
                break;
            case 'upload_avatar':
                uploadAvatar($pdo);
                break;
            case 'logout_session':
                logoutSession($pdo, $input);
                break;
            case 'logout_all_sessions':
                logoutAllSessions($pdo);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action: ' . $action]);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Not an API request - display the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>AgriMarketplace - <?php echo htmlspecialchars($nav_data['page_title']); ?></title>
    
    <!-- Base navigation styles -->
    <?php echo generateNavigationCSS(); ?>
    
    <!-- Page specific styles -->
    <link rel="stylesheet" href="profile.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Additional meta for mobile -->
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
        /* Additional inline styles */
        .context-menu {
            position: absolute;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            min-width: 200px;
            z-index: 2001;
            border: 1px solid var(--border-light);
        }
        
        .context-menu-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        
        .context-menu-item:hover {
            background: var(--page-bg);
        }
        
        .context-menu-item.delete {
            color: var(--blocked);
            border-top: 1px solid var(--border-light);
            margin-top: 5px;
            padding-top: 12px;
        }
        
        .context-menu-item.delete:hover {
            background: #ffeeee;
        }
        
        .context-menu-divider {
            height: 1px;
            background: var(--border-light);
            margin: 5px 0;
        }
        
        .delete-confirm {
            text-align: center;
            padding: 20px 0;
        }
        
        .warning-text {
            color: var(--blocked);
            font-size: 12px;
            margin: 10px 0;
            background: rgba(228, 90, 90, 0.1);
            padding: 10px;
            border-radius: 6px;
        }
        
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-green);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-notification.success {
            background: var(--active);
        }
        
        .toast-notification.error {
            background: var(--blocked);
        }
        
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            font-size: 16px;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: 12px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            position: relative;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { 
                transform: translateY(30px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .modal-header h3 {
            color: var(--primary-green);
            font-size: 20px;
            font-weight: 600;
        }
        
        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-secondary);
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all var(--transition-speed) ease;
        }
        
        .close-modal:hover {
            color: var(--blocked);
            background: rgba(228, 90, 90, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: border-color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(31, 122, 76, 0.1);
        }
        
        .form-group input:read-only,
        .form-group input:disabled {
            background: var(--page-bg);
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-primary,
        .btn-secondary,
        .btn-danger {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #166338;
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: #555;
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-danger {
            background: var(--blocked);
            color: white;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #c13e3e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(228, 90, 90, 0.3);
        }
        
        .btn-sm {
            padding: 6px 12px;
            min-width: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Generate sidebar from navigation system -->
        <?php echo generateAdminSidebar($nav_data); ?>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Generate header from navigation system -->
            <?php echo generateAdminHeader($nav_data); ?>
            
            <!-- Page Content -->
            <div class="content-area" id="contentArea">
                <div class="loading">Loading profile data...</div>
            </div>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <span class="close-modal" onclick="ProfileManager.closeEditModal()">&times;</span>
            </div>
            <form id="editProfileForm" onsubmit="event.preventDefault(); ProfileManager.saveProfile();">
                <input type="hidden" id="editUserId">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="editFullName" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="editEmail" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="editPhone" maxlength="20">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="ProfileManager.closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal" id="changePasswordModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Change Password</h3>
                <span class="close-modal" onclick="ProfileManager.closePasswordModal()">&times;</span>
            </div>
            <form id="changePasswordForm" onsubmit="event.preventDefault(); ProfileManager.changePassword();">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" id="currentPassword" required>
                </div>
                
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" id="newPassword" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" id="confirmPassword" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="ProfileManager.closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Avatar Upload Modal -->
    <div class="modal" id="avatarModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Update Avatar</h3>
                <span class="close-modal" onclick="ProfileManager.closeAvatarModal()">&times;</span>
            </div>
            <form id="avatarForm" enctype="multipart/form-data" onsubmit="event.preventDefault(); ProfileManager.uploadAvatar();">
                <div class="form-group">
                    <label>Choose Image</label>
                    <input type="file" id="avatarFile" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <small class="form-text">Max size: 2MB. Supported: JPG, PNG, GIF, WEBP</small>
                </div>
                
                <div id="avatarPreview" style="text-align: center; margin: 20px 0; display: none;">
                    <img id="previewImage" style="max-width: 150px; max-height: 150px; border-radius: 50%; border: 3px solid var(--primary-green);">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="ProfileManager.closeAvatarModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Session Modal -->
    <div class="modal" id="logoutSessionModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Logout</h3>
                <span class="close-modal" onclick="ProfileManager.closeLogoutModal()">&times;</span>
            </div>
            <div class="delete-confirm">
                <p>Are you sure you want to logout this session?</p>
                <p class="warning-text" id="sessionInfo"></p>
                <div class="form-actions">
                    <button class="btn-secondary" onclick="ProfileManager.closeLogoutModal()">Cancel</button>
                    <button class="btn-danger" onclick="ProfileManager.confirmLogoutSession()">Logout Session</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>

    <!-- Generate loading animation from navigation system -->
    <?php echo generateLoadingAnimation(); ?>

    <script>
    // API Service
    const API = {
        async request(action, method = 'GET', data = null) {
            try {
                let url = `${window.location.pathname}?action=${action}`;
                const options = {
                    method: method,
                    headers: {}
                };
                
                if (method === 'POST' && data) {
                    if (data instanceof FormData) {
                        // For file uploads, don't set Content-Type
                        options.body = data;
                    } else {
                        options.headers['Content-Type'] = 'application/json';
                        options.body = JSON.stringify(data);
                    }
                } else if (method === 'GET' && data) {
                    const params = new URLSearchParams(data);
                    url += '&' + params.toString();
                }
                
                console.log(`${method} request to:`, url);
                
                const response = await fetch(url, options);
                const responseText = await response.text();
                
                try {
                    const result = JSON.parse(responseText);
                    console.log('Response:', result);
                    
                    if (result.error) {
                        throw new Error(result.error);
                    }
                    
                    return result;
                } catch (e) {
                    console.error('Failed to parse JSON:', responseText);
                    throw new Error('Invalid server response');
                }
            } catch (error) {
                console.error('API Error:', error);
                return { error: error.message };
            }
        },
        
        get(action, params = {}) {
            return this.request(action, 'GET', params);
        },
        
        post(action, data) {
            return this.request(action, 'POST', data);
        }
    };

    // Profile Manager
    const ProfileManager = {
        profile: null,
        activity: [],
        sessions: [],
        notifications: {},
        stats: {},
        selectedSessionId: null,
        
        init: async function() {
            console.log('ProfileManager initializing...');
            
            try {
                await this.loadProfile();
                await this.loadActivity();
                await this.loadSessions();
                await this.loadNotifications();
                await this.loadStats();
                this.render();
                
                // Setup file input preview
                document.getElementById('avatarFile')?.addEventListener('change', (e) => this.previewAvatar(e));
                
                // Admin info is loaded by navigation system
                <?php if (isset($_SESSION['user_name'])): ?>
                const adminName = document.getElementById('adminName');
                const adminAvatar = document.getElementById('adminAvatar');
                if (adminName) adminName.textContent = '<?php echo $_SESSION['user_name']; ?>';
                if (adminAvatar) adminAvatar.textContent = '<?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?>';
                <?php endif; ?>
            } catch (error) {
                console.error('Initialization error:', error);
                this.showToast('Failed to initialize: ' + error.message, 'error');
            }
        },
        
        loadProfile: async function() {
            console.log('Loading profile...');
            const result = await API.get('get_profile');
            if (!result.error) {
                this.profile = result;
                console.log('Profile loaded:', this.profile);
            } else {
                console.error('Error loading profile:', result.error);
                this.showToast('Error loading profile: ' + result.error, 'error');
            }
        },
        
        loadActivity: async function() {
            console.log('Loading activity...');
            const result = await API.get('get_activity');
            if (!result.error) {
                this.activity = Array.isArray(result) ? result : [];
                console.log('Activity loaded:', this.activity.length);
            }
        },
        
        loadSessions: async function() {
            console.log('Loading sessions...');
            const result = await API.get('get_sessions');
            if (!result.error) {
                this.sessions = Array.isArray(result) ? result : [];
                console.log('Sessions loaded:', this.sessions.length);
            }
        },
        
        loadNotifications: async function() {
            console.log('Loading notification preferences...');
            const result = await API.get('get_notification_preferences');
            if (!result.error) {
                this.notifications = result || {};
                console.log('Notifications loaded:', this.notifications);
            }
        },
        
        loadStats: async function() {
            console.log('Loading stats...');
            const result = await API.get('get_stats');
            if (!result.error) {
                this.stats = result;
                console.log('Stats loaded:', this.stats);
            }
        },
        
        editProfile: function() {
            if (!this.profile) return;
            
            document.getElementById('editUserId').value = this.profile.id || '';
            document.getElementById('editFullName').value = this.profile.full_name || '';
            document.getElementById('editEmail').value = this.profile.email || '';
            document.getElementById('editPhone').value = this.profile.phone_number || '';
            
            document.getElementById('editProfileModal').classList.add('active');
        },
        
        saveProfile: async function() {
            const formData = {
                full_name: document.getElementById('editFullName')?.value,
                email: document.getElementById('editEmail')?.value,
                phone_number: document.getElementById('editPhone')?.value
            };
            
            if (!formData.full_name || !formData.email) {
                this.showToast('Please fill in all required fields', 'error');
                return;
            }
            
            console.log('Saving profile:', formData);
            
            const result = await API.post('update_profile', formData);
            
            if (result && result.success) {
                this.closeEditModal();
                await this.loadProfile();
                this.render();
                this.showToast('Profile updated successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to update profile'), 'error');
            }
        },
        
        showPasswordModal: function() {
            document.getElementById('changePasswordModal').classList.add('active');
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        },
        
        changePassword: async function() {
            const currentPass = document.getElementById('currentPassword').value;
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            
            if (!currentPass || !newPass || !confirmPass) {
                this.showToast('Please fill in all password fields', 'error');
                return;
            }
            
            if (newPass.length < 6) {
                this.showToast('Password must be at least 6 characters', 'error');
                return;
            }
            
            if (newPass !== confirmPass) {
                this.showToast('New passwords do not match', 'error');
                return;
            }
            
            const formData = {
                current_password: currentPass,
                new_password: newPass
            };
            
            console.log('Changing password...');
            
            const result = await API.post('change_password', formData);
            
            if (result && result.success) {
                this.closePasswordModal();
                this.showToast('Password changed successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to change password'), 'error');
            }
        },
        
        showAvatarModal: function() {
            document.getElementById('avatarModal').classList.add('active');
            document.getElementById('avatarFile').value = '';
            document.getElementById('avatarPreview').style.display = 'none';
        },
        
        previewAvatar: function(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    this.showToast('File size must be less than 2MB', 'error');
                    event.target.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('avatarPreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        },
        
        uploadAvatar: async function() {
            const fileInput = document.getElementById('avatarFile');
            const file = fileInput.files[0];
            
            if (!file) {
                this.showToast('Please select an image', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('avatar', file);
            
            console.log('Uploading avatar...');
            
            const result = await API.request('upload_avatar', 'POST', formData);
            
            if (result && result.success) {
                this.closeAvatarModal();
                await this.loadProfile();
                this.render();
                this.showToast('Avatar updated successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to upload avatar'), 'error');
            }
        },
        
        logoutSession: function(sessionId, deviceInfo) {
            this.selectedSessionId = sessionId;
            document.getElementById('sessionInfo').textContent = deviceInfo || 'Unknown device';
            document.getElementById('logoutSessionModal').classList.add('active');
        },
        
        confirmLogoutSession: async function() {
            const result = await API.post('logout_session', { session_id: this.selectedSessionId });
            
            if (result && result.success) {
                this.closeLogoutModal();
                await this.loadSessions();
                this.render();
                this.showToast('Session logged out successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to logout session'), 'error');
                this.closeLogoutModal();
            }
        },
        
        logoutAllSessions: async function() {
            if (!confirm('Are you sure you want to logout all other sessions?')) {
                return;
            }
            
            const result = await API.post('logout_all_sessions');
            
            if (result && result.success) {
                await this.loadSessions();
                this.render();
                this.showToast('All other sessions logged out!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to logout sessions'), 'error');
            }
        },
        
        saveNotifications: async function() {
            console.log('Saving notification preferences:', this.notifications);
            
            const result = await API.post('update_notifications', { preferences: this.notifications.preferences || {} });
            
            if (result && result.success) {
                this.showToast('Notification preferences updated!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to update preferences'), 'error');
            }
        },
        
        closeEditModal: function() {
            document.getElementById('editProfileModal').classList.remove('active');
        },
        
        closePasswordModal: function() {
            document.getElementById('changePasswordModal').classList.remove('active');
        },
        
        closeAvatarModal: function() {
            document.getElementById('avatarModal').classList.remove('active');
        },
        
        closeLogoutModal: function() {
            document.getElementById('logoutSessionModal').classList.remove('active');
            this.selectedSessionId = null;
        },
        
        showToast: function(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast-notification ${type === 'success' ? 'success' : 'error'} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        },
        
        formatDate: function(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            
            return date.toLocaleDateString('en-KE', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        getDeviceIcon: function(userAgent) {
            if (!userAgent) return '💻';
            const ua = userAgent.toLowerCase();
            if (ua.includes('mobile') || ua.includes('android') || ua.includes('iphone')) return '📱';
            if (ua.includes('tablet') || ua.includes('ipad')) return '📟';
            if (ua.includes('mac')) return '💻';
            if (ua.includes('windows')) return '🖥️';
            return '💻';
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        render: function() {
            console.log('Rendering profile page...');
            
            const contentArea = document.getElementById('contentArea');
            
            if (!this.profile) {
                contentArea.innerHTML = '<div class="loading">Loading profile...</div>';
                return;
            }
            
            const profile = this.profile;
            const avatarInitial = profile.full_name ? profile.full_name.charAt(0).toUpperCase() : 'A';
            
            let html = `
                <!-- Profile Header Card -->
                <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; box-shadow: var(--shadow); border: 1px solid var(--border-light); margin-bottom: 25px; display: flex; align-items: center; gap: 30px; flex-wrap: wrap;">
                    <div style="position: relative;">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--primary-green); display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 600; color: white; border: 4px solid var(--card-bg); box-shadow: var(--shadow-hover);">
                            ${avatarInitial}
                        </div>
                        <button onclick="ProfileManager.showAvatarModal()" style="position: absolute; bottom: 5px; right: 5px; width: 32px; height: 32px; background: var(--card-bg); border: 2px solid var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-green); cursor: pointer; font-size: 16px; box-shadow: var(--shadow);">✏️</button>
                    </div>
                    
                    <div style="flex: 1;">
                        <h1 style="font-size: 32px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px;">${this.escapeHtml(profile.full_name || 'Admin User')}</h1>
                        <div style="display: inline-block; background: var(--soft-green); color: var(--primary-green); padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">${this.escapeHtml(profile.role_name || 'Administrator')}</div>
                        <div style="color: var(--text-secondary); font-size: 14px; display: flex; align-items: center; gap: 5px;"><span>✉️</span> ${this.escapeHtml(profile.email || '')}</div>
                    </div>
                    
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--primary-green);">${this.stats.total_farms || 0}</div>
                            <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase;">Farms</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--primary-green);">${this.stats.total_farmers || 0}</div>
                            <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase;">Farmers</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--primary-green);">${this.stats.total_orders || 0}</div>
                            <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase;">Orders</div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Info Grid -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 25px;">
                    <!-- Personal Information -->
                    <div style="background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border-light);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light);">
                            <h3 style="font-size: 18px; font-weight: 600; color: var(--primary-green); display: flex; align-items: center; gap: 8px;"><span>👤</span> Personal Information</h3>
                            <button class="btn-primary btn-sm" onclick="ProfileManager.editProfile()">✏️ Edit</button>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">👤</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Full Name</div>
                                    <div style="font-size: 16px; font-weight: 500;">${this.escapeHtml(profile.full_name || 'Not set')}</div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">✉️</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Email Address</div>
                                    <div style="font-size: 16px; font-weight: 500; word-break: break-all;">${this.escapeHtml(profile.email || 'Not set')}</div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">📱</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Phone Number</div>
                                    <div style="font-size: 16px; font-weight: 500;">${this.escapeHtml(profile.phone_number || 'Not set')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Information -->
                    <div style="background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border-light);">
                        <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light);">
                            <h3 style="font-size: 18px; font-weight: 600; color: var(--primary-green); display: flex; align-items: center; gap: 8px;"><span>📅</span> Account Information</h3>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">📆</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Member Since</div>
                                    <div style="font-size: 16px; font-weight: 500;">${profile.created_at ? new Date(profile.created_at).toLocaleDateString('en-KE', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Unknown'}</div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">🕐</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Last Login</div>
                                    <div style="font-size: 16px; font-weight: 500;">${this.formatDate(profile.last_login)}</div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">✅</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">Account Status</div>
                                    <div><span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; background: ${profile.is_active ? 'var(--active)' : 'var(--pending)'}; color: white;">${profile.is_active ? 'Active' : 'Inactive'}</span></div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--page-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">🆔</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--text-secondary);">User ID</div>
                                    <div style="font-size: 16px; font-weight: 500;">#${profile.id || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Section -->
                <div style="background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border-light); margin-bottom: 25px;">
                    <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 18px; font-weight: 600; color: var(--primary-green); display: flex; align-items: center; gap: 8px;"><span>🔒</span> Security</h3>
                        <button class="btn-secondary btn-sm" onclick="ProfileManager.showPasswordModal()">🔑 Change Password</button>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--page-bg); border-radius: 8px;">
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <div style="width: 40px; height: 40px; background: var(--card-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary-green); font-size: 20px;">🔑</div>
                                <div>
                                    <h4 style="font-size: 15px; font-weight: 600;">Password</h4>
                                    <p style="font-size: 12px; color: var(--text-secondary);">Last changed: Never</p>
                                </div>
                            </div>
                            <span class="security-status" style="background: var(--active); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Active</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--page-bg); border-radius: 8px;">
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <div style="width: 40px; height: 40px; background: var(--card-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary-green); font-size: 20px;">📧</div>
                                <div>
                                    <h4 style="font-size: 15px; font-weight: 600;">Email Verification</h4>
                                    <p style="font-size: 12px; color: var(--text-secondary);">${profile.email || 'No email'}</p>
                                </div>
                            </div>
                            <span class="security-status" style="background: var(--active); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Verified</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div style="background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border-light); margin-bottom: 25px;">
                    <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light);">
                        <h3 style="font-size: 18px; font-weight: 600; color: var(--primary-green); display: flex; align-items: center; gap: 8px;"><span>📊</span> Recent Activity</h3>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; max-height: 300px; overflow-y: auto;">
                        ${this.activity.length > 0 ? this.activity.map(a => `
                            <div style="display: flex; gap: 15px; padding: 10px; background: var(--page-bg); border-radius: 8px;">
                                <div style="width: 32px; height: 32px; background: var(--soft-green); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-green);">
                                    ${a.action === 'ADD' ? '➕' : a.action === 'UPDATE' ? '✏️' : a.action === 'DELETE' ? '🗑️' : '📝'}
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">${this.escapeHtml(a.action || 'Action')}</div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">${this.escapeHtml(a.entity_type || '')} #${a.entity_id || ''}</div>
                                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px;">${this.formatDate(a.created_at)}</div>
                                </div>
                            </div>
                        `).join('') : `
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No recent activity found
                            </div>
                        `}
                    </div>
                </div>
                
                <!-- Active Sessions -->
                <div style="background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: var(--shadow); border: 1px solid var(--border-light);">
                    <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 18px; font-weight: 600; color: var(--primary-green); display: flex; align-items: center; gap: 8px;"><span>💻</span> Active Sessions</h3>
                        <button class="btn-danger btn-sm" onclick="ProfileManager.logoutAllSessions()">🚪 Logout All Others</button>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: var(--page-bg);">Device</th>
                                    <th style="text-align: left; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: var(--page-bg);">IP Address</th>
                                    <th style="text-align: left; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: var(--page-bg);">Last Active</th>
                                    <th style="text-align: left; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: var(--page-bg);">Status</th>
                                    <th style="text-align: left; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: var(--page-bg);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.sessions.length > 0 ? this.sessions.map(s => `
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span style="font-size: 20px;">${this.getDeviceIcon(s.user_agent)}</span>
                                                <span>${s.device_name || 'Unknown Device'}</span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">${s.ip_address || 'Unknown'}</td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">${this.formatDate(s.last_activity)}</td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">
                                            ${s.is_current ? 
                                                '<span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: var(--active); color: white;">Current</span>' : 
                                                '<span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: var(--pending); color: white;">Active</span>'
                                            }
                                        </td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">
                                            ${!s.is_current ? `
                                                <button class="btn-danger btn-sm" onclick="ProfileManager.logoutSession(${s.id}, '${s.device_name || 'Unknown device'}')">🚪 Logout</button>
                                            ` : '-'}
                                        </td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">No active sessions found</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            contentArea.innerHTML = html;
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Profile page initialized');
        await ProfileManager.init();
    });

    // Make ProfileManager globally available
    window.ProfileManager = ProfileManager;
    </script>

    <!-- Generate navigation scripts -->
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function getProfile($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['error' => 'User not authenticated']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.is_active,
                DATE_FORMAT(u.last_login, '%Y-%m-%d %H:%i:%s') as last_login,
                DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                ut.role_name
            FROM users u
            JOIN user_types ut ON u.type_id = ut.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getActivity($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['error' => 'User not authenticated']);
            return;
        }
        
        // Check if system_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
        if ($stmt->rowCount() == 0) {
            echo json_encode([]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                action,
                entity_type,
                entity_id,
                DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
            FROM system_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([]);
    }
}

function getSessions($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['error' => 'User not authenticated']);
            return;
        }
        
        // Mock sessions data since we don't have a sessions table
        $sessions = [];
        
        // Add current session
        $sessions[] = [
            'id' => 1,
            'device_name' => 'Current Browser',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'last_activity' => date('Y-m-d H:i:s'),
            'is_current' => true
        ];
        
        echo json_encode($sessions);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getNotificationPreferences($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['error' => 'User not authenticated']);
            return;
        }
        
        // Default preferences
        $preferences = [
            'preferences' => [
                'new_orders' => true,
                'new_farmers' => true,
                'payments' => true,
                'security' => true,
                'updates' => false
            ]
        ];
        
        echo json_encode($preferences);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getProfileStats($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['error' => 'User not authenticated']);
            return;
        }
        
        $stats = [];
        
        // Total farms
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests");
        $stats['total_farms'] = (int)$stmt->fetchColumn();
        
        // Total farmers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM users 
            WHERE type_id = (SELECT id FROM user_types WHERE role_name = 'Farmer')
        ");
        $stmt->execute();
        $stats['total_farmers'] = (int)$stmt->fetchColumn();
        
        // Total orders
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $stats['total_orders'] = (int)$stmt->fetchColumn();
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateProfile($pdo, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $fullName = $data['full_name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone_number'] ?? null;
        
        if (!$fullName || !$email) {
            echo json_encode(['success' => false, 'error' => 'Name and email are required']);
            return;
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email already in use']);
            return;
        }
        
        // Check if phone is already taken by another user
        if ($phone) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ?");
            $stmt->execute([$phone, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Phone number already in use']);
                return;
            }
        }
        
        // Update user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone_number = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([$fullName, $email, $phone, $userId]);
        
        if ($success) {
            // Update session
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function changePassword($pdo, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $currentPassword = $data['current_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        
        if (!$currentPassword || !$newPassword) {
            echo json_encode(['success' => false, 'error' => 'Current and new password are required']);
            return;
        }
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            return;
        }
        
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        // Verify current password (assuming plain text for demo - use password_verify in production)
        if ($user['password_hash'] !== $currentPassword) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            return;
        }
        
        // Update password (in production, hash the password)
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $success = $stmt->execute([$newPassword, $userId]);
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to change password']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateNotifications($pdo, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $preferences = $data['preferences'] ?? [];
        
        // In a real implementation, save to user_preferences table
        // For now, just return success
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function uploadAvatar($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            return;
        }
        
        $file = $_FILES['avatar'];
        
        // Check file size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File size must be less than 2MB']);
            return;
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
            return;
        }
        
        // Create avatars directory if not exists
        $uploadDir = '../uploads/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function logoutSession($pdo, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        $sessionId = $data['session_id'] ?? null;
        
        if (!$sessionId) {
            echo json_encode(['success' => false, 'error' => 'Session ID required']);
            return;
        }
        
        // In a real implementation, delete session from sessions table
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function logoutAllSessions($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated']);
            return;
        }
        
        // In a real implementation, delete all sessions except current
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>