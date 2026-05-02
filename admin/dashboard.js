// Immediate authentication check - runs before anything else
(function checkAuth() {
    const user = sessionStorage.getItem('user');
    if (!user) {
        window.location.href = '../index.html';
        return;
    }
    
    try {
        const userData = JSON.parse(user);
        if (userData.role !== 'Admin') {
            window.location.href = '../index.html';
            return;
        }
    } catch (e) {
        window.location.href = '../index.html';
    }
})();

// Sidebar Manager for collapsible functionality
const SidebarManager = {
    isCollapsed: false,
    isMobileOpen: false,
    
    init: function() {
        this.createSidebarElements();
        this.loadState();
        this.setupEventListeners();
        this.updateSidebarState();
    },
    
    createSidebarElements: function() {
        // Create toggle button if it doesn't exist
        if (!document.querySelector('.sidebar-toggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'sidebar-toggle';
            toggleBtn.innerHTML = '☰';
            toggleBtn.setAttribute('aria-label', 'Toggle sidebar');
            document.body.appendChild(toggleBtn);
        }
        
        
        if (!document.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        // Add close button to sidebar if it doesn't exist
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && !document.querySelector('.close-sidebar')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-sidebar';
            closeBtn.innerHTML = '✕';
            closeBtn.setAttribute('aria-label', 'Close menu');
            sidebar.insertBefore(closeBtn, sidebar.firstChild);
        }
    },
    
    loadState: function() {
        // Load collapsed state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        this.isCollapsed = savedState === 'true';
    },
    
    saveState: function() {
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);
    },
    
    setupEventListeners: function() {
        const toggleBtn = document.querySelector('.sidebar-toggle');
        const overlay = document.querySelector('.sidebar-overlay');
        const closeBtn = document.querySelector('.close-sidebar');
        const sidebar = document.querySelector('.sidebar');
        
        // Toggle button click
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                
                if (window.innerWidth <= 768) {
                    // Mobile: toggle sidebar open/close
                    this.toggleMobile();
                } else {
                    // Desktop: toggle collapsed state
                    this.toggleCollapsed();
                }
            });
        }
        
        // Close button click (mobile)
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.closeMobile();
            });
        }
        
        // Overlay click (mobile)
        if (overlay) {
            overlay.addEventListener('click', () => {
                this.closeMobile();
            });
        }
        
        // Window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.handleResize();
            }, 250);
        });
        
        // Close mobile menu when clicking on nav links
        const navLinks = document.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    this.closeMobile();
                }
            });
        });
        
        // Escape key to close mobile menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobileOpen) {
                this.closeMobile();
            }
        });
    },
    
    toggleCollapsed: function() {
        this.isCollapsed = !this.isCollapsed;
        this.updateSidebarState();
        this.saveState();
    },
    
    toggleMobile: function() {
        this.isMobileOpen = !this.isMobileOpen;
        this.updateMobileState();
    },
    
    closeMobile: function() {
        this.isMobileOpen = false;
        this.updateMobileState();
    },
    
    updateSidebarState: function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        
        if (sidebar) {
            if (this.isCollapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        }
        
        if (mainContent) {
            if (this.isCollapsed) {
                mainContent.classList.add('expanded');
            } else {
                mainContent.classList.remove('expanded');
            }
        }
        
        // Update toggle button icon
        if (toggleBtn) {
            toggleBtn.innerHTML = this.isCollapsed ? '☰' : '✕';
        }
    },
    
    updateMobileState: function() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        
        if (sidebar) {
            if (this.isMobileOpen) {
                sidebar.classList.add('mobile-open');
            } else {
                sidebar.classList.remove('mobile-open');
            }
        }
        
        if (overlay) {
            if (this.isMobileOpen) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }
        
        // Update toggle button icon
        if (toggleBtn) {
            toggleBtn.innerHTML = this.isMobileOpen ? '✕' : '☰';
        }
        
        // Prevent body scroll when mobile menu is open
        if (this.isMobileOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    },
    
    handleResize: function() {
        if (window.innerWidth > 768) {
            // Switching to desktop
            this.isMobileOpen = false;
            this.updateMobileState();
            document.body.style.overflow = '';
            
            // Reapply desktop collapsed state
            this.updateSidebarState();
        } else {
            // Switching to mobile
            // Remove desktop collapsed classes for proper mobile display
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar) {
                // Keep collapsed state in localStorage but don't apply visual changes on mobile
                // We'll just ensure the mobile menu is closed
                this.isMobileOpen = false;
                this.updateMobileState();
            }
        }
    }
};

// API Service
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
    
    async get(endpoint) {
        try {
            // Check auth before each request
            const user = sessionStorage.getItem('user');
            if (!user) {
                window.location.href = '../index.html';
                return [];
            }
            
            const url = `${this.baseUrl}/dashboard.php?endpoint=${endpoint}`;
            console.log('Fetching:', url);
            
            const response = await fetch(url);
            
            // Handle unauthorized responses
            if (response.status === 401 || response.status === 403) {
                sessionStorage.removeItem('user');
                window.location.href = '../index.html';
                return [];
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API Error:', error);
            return [];
        }
    }
};

// State management
let currentPage = 'dashboard';
let searchTerm = '';
let farmers = [];
let events = [];
let users = [];
let stats = {};
let adminInfo = {};

// DOM Elements
const contentArea = document.getElementById('contentArea');
const pageTitle = document.getElementById('pageTitle');
const navItems = document.querySelectorAll('.nav-item');
const searchInput = document.getElementById('searchInput');
const adminName = document.getElementById('adminName');
const adminAvatar = document.getElementById('adminAvatar');

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize sidebar
    SidebarManager.init();
    
    // Double-check authentication on page load
    const user = sessionStorage.getItem('user');
    if (!user) {
        window.location.href = '../index.html';
        return;
    }
    
    try {
        const userData = JSON.parse(user);
        if (userData.role !== 'Admin') {
            window.location.href = '../index.html';
            return;
        }
        
        // Update admin info from session
        if (userData.name) {
            if (adminName) adminName.textContent = userData.name;
            if (adminAvatar) adminAvatar.textContent = userData.name.charAt(0).toUpperCase();
        }
        
        await loadData();
        setupEventListeners();
        renderContent();
        
    } catch (error) {
        console.error('Authentication error:', error);
        window.location.href = '../index.html';
    }
});

// Load data from database
async function loadData() {
    try {
        if (contentArea) {
            contentArea.innerHTML = '<div class="loading">Loading dashboard...</div>';
        }
        
        const [farmersData, eventsData, usersData, statsData, adminData] = await Promise.all([
            API.get('farmers'),
            API.get('events'),
            API.get('users'),
            API.get('stats'),
            API.get('admin_info')
        ]);
        
        farmers = farmersData || [];
        events = eventsData || [];
        users = usersData || [];
        stats = statsData || {};
        adminInfo = adminData || {};
        
        // Update admin info if available from API
        if (adminInfo.full_name && adminName) {
            adminName.textContent = adminInfo.full_name;
            if (adminAvatar) adminAvatar.textContent = adminInfo.full_name.charAt(0).toUpperCase();
        }
        
        renderContent();
    } catch (error) {
        console.error('Error loading data:', error);
        if (contentArea) {
            contentArea.innerHTML = '<div class="loading">Error loading data. Please refresh.</div>';
        }
    }
}

// Setup event listeners
function setupEventListeners() {
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const href = item.getAttribute('href');
            if (href && !href.startsWith('#')) {
                // For actual links, let them navigate normally
                return;
            }
            
            // For our navigation items with data-page
            const page = item.dataset.page;
            if (page) {
                navigateTo(page);
            }
        });
    });
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchTerm = e.target.value.toLowerCase();
            if (currentPage === 'users') {
                renderUserManagement();
            }
        });
    }
}

// Navigate to page
function navigateTo(page) {
    currentPage = page;
    
    navItems.forEach(item => {
        if (item.dataset.page === page) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
    
    const titles = {
        'dashboard': 'Dashboard',
        'farm-management': 'Farm Management',
        'crop-schedule': 'Crop Schedule',
        'agronomists': 'Agronomists',
        'projections': 'Projections',
        'users': 'Users'
    };
    
    if (pageTitle) {
        pageTitle.textContent = titles[page] || 'Dashboard';
    }
    
    renderContent();
}

// Render content based on current page
function renderContent() {
    switch(currentPage) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'users':
            renderUserManagement();
            break;
        default:
            renderPlaceholder();
    }
}

// Render Dashboard
function renderDashboard() {
    if (!contentArea) return;
    
    const html = `
        <div class="dashboard-home">
            <div class="dashboard-header">
                <h2>Dashboard Home</h2>
                <p>Personal farm information, crop calendar, notifications for upcoming agronomist visits, and actionable insights</p>
            </div>
            
            <!-- Metrics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value">${stats.totalFarmers || 0}</div>
                    <div class="stat-label">Total Farmers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.activePlantings || 0}</div>
                    <div class="stat-label">Active Plantings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.dueHarvests || 0}</div>
                    <div class="stat-label">Due Harvests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.totalLand || 0}</div>
                    <div class="stat-label">Acres Cultivated</div>
                </div>
            </div>
            
            <!-- Main Grid -->
            <div class="dashboard-grid">
                <!-- Farmers Table -->
                <div class="farmers-table-container">
                    <h3>Farmers Table</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Farmer</th>
                                <th>County</th>
                                <th>Number of farms</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${farmers.length > 0 ? farmers.map(farmer => `
                                <tr>
                                    <td>${farmer.id || ''}</td>
                                    <td>${farmer.farmer || ''}</td>
                                    <td>${farmer.county || 'Unknown'}</td>
                                    <td>${farmer.number_of_farms || 0}</td>
                                </tr>
                            `).join('') : `
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px;">
                                        No farmers found
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
                
                <!-- Events Sidebar -->
                <div class="events-sidebar">
                    <h3>Upcoming Events</h3>
                    <div class="events-list">
                        ${events.length > 0 ? events.map(event => `
                            <div class="event-card">
                                <div class="event-type">
                                    Harvesting on Farm #${event.id || ''}
                                </div>
                                <div class="event-details">
                                    <span class="event-farmer">${event.farmer_name || ''}</span>
                                    <span class="event-county">${event.county || 'Unknown'} County</span>
                                </div>
                                <div class="event-meta">
                                    ${event.crop_name || 'Crops'} • ${event.days_left || 0} days left
                                </div>
                            </div>
                        `).join('') : `
                            <div style="text-align: center; padding: 20px;">
                                No upcoming events
                            </div>
                        `}
                        <div class="farmers-cycle">
                            <span>Farmers Cycle</span>
                            <span class="cycle-count">${farmers.length}+</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    contentArea.innerHTML = html;
}

// Render User Management
function renderUserManagement() {
    if (!contentArea) return;
    
    const filteredUsers = users.filter(u => 
        u.full_name && u.full_name.toLowerCase().includes(searchTerm)
    );
    
    const adminUsers = filteredUsers.filter(u => u.role_name === 'Admin');
    const regularUsers = filteredUsers.filter(u => u.role_name !== 'Admin');
    
    const html = `
        <div class="user-management">
            <div class="user-management-header">
                <h2>USER MANAGEMENT</h2>
                <p>Add, edit, or deactivate user accounts, manage roles and permissions, view user activity logs.</p>
            </div>
            
            <!-- Admin Users Section -->
            <div class="admin-users-section">
                <h3>List of admin users</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${adminUsers.length > 0 ? adminUsers.map(user => `
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <span class="user-avatar">${user.full_name ? user.full_name.charAt(0) : 'U'}</span>
                                        <span class="user-name">${user.full_name || ''}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge ${user.is_active ? 'active' : 'pending'}">
                                        ${user.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <button class="icon-btn" onclick="alert('View user: ${user.full_name}')">👁️</button>
                                    <button class="icon-btn" onclick="alert('Edit user: ${user.full_name}')">✏️</button>
                                    <button class="icon-btn" onclick="alert('More options')">⋯</button>
                                </td>
                            </tr>
                        `).join('') : `
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px;">
                                    No admin users found
                                </td>
                            </tr>
                        `}
                    </tbody>
                </table>
            </div>
            
            <!-- Regular Users Section -->
            <div class="regular-users-section">
                <h3>Users</h3>
                <div class="users-list">
                    ${regularUsers.length > 0 ? regularUsers.map(user => `
                        <div class="user-list-item">
                            <div class="user-list-info">
                                <span class="user-name">${user.full_name || ''}</span>
                                <span class="user-code">(${user.email || 'No email'})</span>
                            </div>
                            <div class="user-list-actions">
                                <button class="icon-btn" onclick="alert('View user: ${user.full_name}')">👁️</button>
                                <button class="icon-btn" onclick="alert('Edit user: ${user.full_name}')">✏️</button>
                                <button class="icon-btn" onclick="alert('More options')">⋯</button>
                                ${user.is_active ? '<span class="active-indicator" title="Active">🟢</span>' : '<span class="active-indicator" title="Inactive">🔴</span>'}
                            </div>
                        </div>
                    `).join('') : `
                        <div style="text-align: center; padding: 20px;">
                            No users found
                        </div>
                    `}
                </div>
            </div>
        </div>
    `;
    
    contentArea.innerHTML = html;
}


function renderPlaceholder() {
    if (!contentArea) return;
    
    const pageNames = {
        'farm-management': 'Farm Management',
        'crop-schedule': 'Crop Schedule',
        'agronomists': 'Agronomists',
        'projections': 'Projections'
    };
    
    const html = `
        <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h2 style="margin-bottom: 10px; color: #1F7A4C;">${pageNames[currentPage] || currentPage}</h2>
            <p style="color: #666;">This section is under development</p>
            <div style="margin-top: 30px; color: #76B947; font-size: 48px;">🌱</div>
        </div>
    `;
    
    contentArea.innerHTML = html;
}

// Logout function
function logout() {
    sessionStorage.removeItem('user');
    window.location.href = '../index.html';
}

// Make functions globally available
window.logout = logout;