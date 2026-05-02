<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

define('SESSION_TIMEOUT', 1800);

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time >= SESSION_TIMEOUT) {
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
            
            header("Location: ../index.php?timeout=1");
            exit();
        }
    }
    
    $_SESSION['last_activity'] = time();
}

function checkAdminAuth() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
    
    checkSessionTimeout();
    
    return true;
}

function initializeAdminNavigation($page_title = 'Dashboard', $active_page = 'dashboard') {
    checkAdminAuth();
    
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? 'Admin';
    $user_email = $_SESSION['user_email'] ?? '';
    $user_role = $_SESSION['user_role'] ?? 'Admin';
    
    return [
        'page_title' => $page_title,
        'active_page' => $active_page,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_role' => $user_role,
        'base_path' => '/admin/'
    ];
}

function generateNavigationCSS() {
    return '<link rel="stylesheet" href="admin_navigation.css">';
}

function generateAdminSidebar($nav_data) {
    $active_page = $nav_data['active_page'] ?? 'dashboard';
    $user_name = $nav_data['user_name'] ?? 'Admin';
    $user_email = $nav_data['user_email'] ?? '';
    
    $active_dashboard = ($active_page === 'dashboard') ? 'active' : '';
    $active_farm_management = ($active_page === 'farm-management') ? 'active' : '';
    $active_crop_schedule = ($active_page === 'crop-schedule') ? 'active' : '';
    $active_growth_tracking = ($active_page === 'growth-tracking') ? 'active' : '';
    $active_projections = ($active_page === 'projections') ? 'active' : '';
    $active_users = ($active_page === 'users') ? 'active' : '';
    $active_profile = ($active_page === 'profile') ? 'active' : '';
    $active_listings = ($active_page === 'listings') ? 'active' : '';
    $active_orders = ($active_page === 'orders') ? 'active' : '';
    $active_reports = ($active_page === 'reports') ? 'active' : '';
    
    ob_start();
    ?>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <span class="logo-icon">🌾</span>
                <span class="logo-text">AgriTrace</span>
            </div>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times">✕</i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                <p><?php echo htmlspecialchars($user_email); ?></p>
                <span class="user-role-badge">ADMIN</span>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <ul>
                <li class="<?php echo $active_dashboard; ?>">
                    <a href="dashboard.php" data-tooltip="Dashboard">
                        <i class="nav-icon">📊</i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo $active_farm_management; ?>">
                    <a href="farm-management.php" data-tooltip="Farm Management">
                        <i class="nav-icon">🌾</i>
                        <span class="nav-text">Farm Management</span>
                    </a>
                </li>
                <li class="<?php echo $active_crop_schedule; ?>">
                    <a href="crop-schedule.php" data-tooltip="Crop Schedule">
                        <i class="nav-icon">🌱</i>
                        <span class="nav-text">Crop Schedule</span>
                    </a>
                </li>
                <li class="<?php echo $active_growth_tracking; ?>">
                    <a href="growth-tracking.php" data-tooltip="Growth Tracking">
                        <i class="nav-icon">📈</i>
                        <span class="nav-text">Growth Tracking</span>
                    </a>
                </li>
                <li class="<?php echo $active_listings; ?>">
                    <a href="listings.php" data-tooltip="Listings">
                        <i class="nav-icon">📋</i>
                        <span class="nav-text">Listings</span>
                    </a>
                </li>
                <li class="<?php echo $active_orders; ?>">
                    <a href="orders.php" data-tooltip="Orders">
                        <i class="nav-icon">📦</i>
                        <span class="nav-text">Orders</span>
                    </a>
                </li>
                <li class="<?php echo $active_users; ?>">
                    <a href="users.php" data-tooltip="Users">
                        <i class="nav-icon">👤</i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="<?php echo $active_projections; ?>">
                    <a href="projections.php" data-tooltip="Projections">
                        <i class="nav-icon">📊</i>
                        <span class="nav-text">Projections</span>
                    </a>
                </li>
                <li class="<?php echo $active_reports; ?>">
                    <a href="reports.php" data-tooltip="Reports">
                        <i class="nav-icon">📑</i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="<?php echo $active_profile; ?>">
                    <a href="profile.php" data-tooltip="My Profile">
                        <i class="nav-icon">👤</i>
                        <span class="nav-text">My Profile</span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php" data-tooltip="Logout" class="logout-link">
                        <i class="nav-icon">🚪</i>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="version-info">
                <span>v1.0.0</span>
            </div>
        </div>
    </aside>
    <?php
    return ob_get_clean();
}

function generateAdminHeader($nav_data) {
    $page_title = $nav_data['page_title'] ?? 'Dashboard';
    $user_name = $nav_data['user_name'] ?? 'Admin';
    
    ob_start();
    ?>
    <header class="admin-header">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="toggle-icon">☰</i>
            </button>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>
        
        <div class="header-right">
            
            <div class="search-box">
                <i class="search-icon">🔍</i>
                <input type="text" placeholder="Search..." id="globalSearch">
            </div>
            
            <div class="notifications">
                <button class="notification-btn" id="notificationBtn">
                    <i>🔔</i>
                    <span class="notification-badge" id="notificationCount">0</span>
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <span class="mark-read">Mark all as read</span>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-item">
                            <p>No new notifications</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="user-menu">
                <button class="user-menu-btn" id="userMenuBtn">
                    <span class="user-avatar-small"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <i class="dropdown-icon">▼</i>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <a href="profile.php"><i>👤</i> My Profile</a>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="logout-link"><i>🚪</i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    <?php
    return ob_get_clean();
}

function generateLoadingAnimation() {
    ob_start();
    ?>
    <div class="page-loader" id="pageLoader">
        <div class="loader-content">
            <div class="agri-loader">
                <div class="loader-leaf">🌱</div>
                <div class="loader-leaf">🌿</div>
                <div class="loader-leaf">🌾</div>
            </div>
            <div class="loader-text">Loading AgriTrace...</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function generateNavigationScripts() {
    ob_start();
    ?>
    <script>
    const SessionManager = {
        timeoutDuration: <?php echo SESSION_TIMEOUT; ?> * 1000,
        lastActivity: Date.now(),
        checkInterval: null,
        
        init: function() {
            console.log('SessionManager initializing...');
            
            <?php if (isset($_SESSION['last_activity'])): ?>
            this.lastActivity = <?php echo $_SESSION['last_activity']; ?> * 1000;
            <?php endif; ?>
            
            this.trackActivity();
            
            this.startSessionCheck();
        },
        
        startSessionCheck: function() {
            this.checkInterval = setInterval(() => {
                this.checkSession();
            }, 30000);
        },
        
        checkSession: function() {
            const now = Date.now();
            const elapsed = now - this.lastActivity;
            
            if (elapsed >= this.timeoutDuration) {
                console.log('Session expired due to inactivity');
                this.logoutNow();
            }
        },
        
        trackActivity: function() {
            const events = ['mousedown', 'keydown', 'scroll', 'mousemove', 'touchstart'];
            
            events.forEach(eventType => {
                document.addEventListener(eventType, () => {
                    this.lastActivity = Date.now();
                }, { passive: true });
            });
        },
        
        logoutNow: function() {
            window.location.href = '../logout.php?timeout=1';
        }
    };

    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = '../logout.php';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        SessionManager.init();
        

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('adminSidebar');
        const sidebarClose = document.getElementById('sidebarClose');
        const mainContent = document.querySelector('.main-content');
        
        const sidebarState = localStorage.getItem('adminSidebarCollapsed');
        if (sidebarState === 'true' && window.innerWidth > 1024) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        }

        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                if (window.innerWidth > 1024) {
                    sidebar.classList.toggle('collapsed');
                    if (mainContent) {
                        mainContent.classList.toggle('expanded');
                    }
                    localStorage.setItem('adminSidebarCollapsed', sidebar.classList.contains('collapsed'));
                } else {
                    sidebar.classList.toggle('mobile-open');
                }
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
            });
        }

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 1024 && 
                sidebar.classList.contains('mobile-open') && 
                !sidebar.contains(event.target) && 
                (!menuToggle || !menuToggle.contains(event.target))) {
                sidebar.classList.remove('mobile-open');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('mobile-open');
            } else {
                sidebar.classList.remove('collapsed');
                if (mainContent) {
                    mainContent.classList.remove('expanded');
                }
            }
        });


        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-menu')) {
                    userDropdown.classList.remove('show');
                }
            });
        }


        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.notifications')) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }

        const globalSearch = document.getElementById('globalSearch');
        if (globalSearch) {
            let searchTimeout;
            globalSearch.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const searchTerm = e.target.value.trim();
                
                searchTimeout = setTimeout(() => {
                    if (searchTerm.length > 2) {
                        console.log('Searching for:', searchTerm);
                        window.dispatchEvent(new CustomEvent('globalSearch', { 
                            detail: { term: searchTerm } 
                        }));
                    }
                }, 500);
            });
        }

        const pageLoader = document.getElementById('pageLoader');
        
        if (pageLoader) {
            let isLoading = true;
            const loadStartTime = Date.now();
            const MIN_LOAD_TIME = 500;
            const MAX_LOAD_TIME = 5000;
            
            function hideLoader() {
                if (!isLoading) return;
                
                const loadTime = Date.now() - loadStartTime;
                const remainingTime = Math.max(0, MIN_LOAD_TIME - loadTime);
                
                setTimeout(() => {
                    pageLoader.style.opacity = '0';
                    pageLoader.style.transition = 'opacity 0.3s ease';
                    
                    setTimeout(() => {
                        pageLoader.style.display = 'none';
                        isLoading = false;
                    }, 300);
                }, remainingTime);
            }
            
            if (document.readyState === 'complete') {
                hideLoader();
            } else {
                window.addEventListener('load', hideLoader);
                
                setTimeout(() => {
                    if (isLoading) {
                        hideLoader();
                    }
                }, MAX_LOAD_TIME);
            }
        }


        function initTooltips() {
            if (window.innerWidth <= 1024) {
                document.querySelectorAll('.sidebar-menu a[data-tooltip]').forEach(link => {
                    link.style.position = 'relative';
                });
            }
        }
        
        initTooltips();
        window.addEventListener('resize', initTooltips);

        function loadQuickStats() {
            fetch('dashboard.php?endpoint=stats')
                .then(response => response.json())
                .then(data => {
                    const statFarms = document.getElementById('statFarms');
                    const statFarmers = document.getElementById('statFarmers');
                    const statRevenue = document.getElementById('statRevenue');
                    
                    if (statFarms) statFarms.textContent = data.total_farms || '0';
                    if (statFarmers) statFarmers.textContent = data.total_farmers || '0';
                    if (statRevenue) statRevenue.textContent = 'KES ' + (data.revenue || '0');
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        if (document.getElementById('statFarms')) {
            loadQuickStats();
        }

        const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
        const navLinks = document.querySelectorAll('.sidebar-menu a');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.closest('li').classList.add('active');
            }
        });

        const logoutLinks = document.querySelectorAll('.logout-link');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('timeout') === '1') {
            console.log('Session expired due to inactivity');
        }
    });

 
    function showToast(message, type = 'info', duration = 3000) {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-message">${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }

    function formatCurrency(amount) {
        return 'KES ' + parseFloat(amount).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-KE', options);
    }

    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    window.logout = logout;
    window.showToast = showToast;
    window.formatCurrency = formatCurrency;
    window.formatDate = formatDate;
    window.confirmAction = confirmAction;
    window.SessionManager = SessionManager;
    </script>
    <?php
    return ob_get_clean();
}
?>