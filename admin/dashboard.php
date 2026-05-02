<?php
// dashboard.php - Dashboard for AgriMarketplace Admin Panel

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';


$nav_data = initializeAdminNavigation('Dashboard', 'dashboard');

if (isset($_GET['endpoint'])) {
    ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    
    try {
        // Include database connection from parent directory
        require_once '../database.php';
        
        // Get database connection
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $endpoint = $_GET['endpoint'];
        
        switch($endpoint) {
            case 'farmers':
                getFarmers($pdo);
                break;
            case 'events':
                getEvents($pdo);
                break;
            case 'users':
                getUsers($pdo);
                break;
            case 'stats':
                getStats($pdo);
                break;
            case 'admin_info':
                getAdminInfo($pdo);
                break;
            default:
                echo json_encode(['error' => 'Invalid endpoint']);
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
    <link rel="stylesheet" href="dashboard.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Additional meta for mobile -->
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
        /* Additional dashboard-specific styles */
        .dashboard-home {
            max-width: 1600px;
            margin: 0 auto;
        }

        .dashboard-header {
            margin-bottom: 24px;
        }

        .dashboard-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 4px;
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
            margin-bottom: 30px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 122, 76, 0.15);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .farmers-table-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
            overflow-x: auto;
        }

        .farmers-table-container h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .view-all-link {
            font-size: 13px;
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .data-table th {
            text-align: left;
            padding: 12px 8px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            background-color: var(--soft-green);
            border-bottom: 2px solid var(--soft-green);
            white-space: nowrap;
        }

        .data-table td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            color: var(--text-primary);
        }

        .data-table tbody tr:hover {
            background-color: var(--page-bg);
            cursor: pointer;
        }

        .events-sidebar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }

        .events-sidebar h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .event-card {
            padding: 16px;
            background-color: var(--page-bg);
            border-radius: 8px;
            border-left: 4px solid var(--accent-green);
            transition: transform 0.2s ease;
        }

        .event-card:hover {
            transform: translateX(2px);
            box-shadow: var(--shadow);
        }

        .event-type {
            font-weight: 600;
            font-size: 14px;
            color: var(--primary-green);
            margin-bottom: 8px;
        }

        .event-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .event-farmer {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .event-county {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .event-meta {
            font-size: 12px;
            color: var(--accent-green);
            font-weight: 500;
        }

        .farmers-cycle {
            margin-top: 16px;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            border-radius: 8px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }

        .cycle-count {
            font-size: 20px;
            font-weight: 700;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }

        .status-badge.active {
            background-color: var(--active);
            color: white;
        }

        .status-badge.pending {
            background-color: var(--pending);
            color: white;
        }

        .status-badge.blocked {
            background-color: var(--blocked);
            color: white;
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

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .dashboard-header h2 {
                font-size: 16px;
            }
            
            .event-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .farmers-cycle {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .farmers-table-container,
            .events-sidebar {
                padding: 15px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .event-card {
                padding: 12px;
            }
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
                <div class="loading">Loading dashboard...</div>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>

    <!-- Generate loading animation from navigation system -->
    <?php echo generateLoadingAnimation(); ?>

    <script>
    // API Service
    const API = {
        baseUrl: (() => {
            const path = window.location.pathname;
            // For InfinityFree, the path might be just /admin/dashboard.php
            // So baseUrl should be empty or just /admin
            if (path.includes('/admin/')) {
                return '/admin';
            }
            return '';
        })(),
        
        async get(endpoint) {
            try {
                const url = `${window.location.pathname}?endpoint=${endpoint}`;
                console.log('Fetching:', url);
                
                const response = await fetch(url);
                
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
    let farmers = [];
    let events = [];
    let stats = {};

    // DOM Elements
    const contentArea = document.getElementById('contentArea');

    // Load data from database
    async function loadDashboardData() {
        try {
            if (contentArea) {
                contentArea.innerHTML = '<div class="loading">Loading dashboard...</div>';
            }
            
            const [farmersData, eventsData, statsData] = await Promise.all([
                API.get('farmers'),
                API.get('events'),
                API.get('stats')
            ]);
            
            farmers = farmersData || [];
            events = eventsData || [];
            stats = statsData || {};
            
            renderDashboard();
            updateQuickStats();
        } catch (error) {
            console.error('Error loading data:', error);
            if (contentArea) {
                contentArea.innerHTML = '<div class="loading">Error loading data. Please refresh.</div>';
            }
        }
    }

    // Update quick stats in header
    function updateQuickStats() {
        const statFarms = document.getElementById('statFarms');
        const statFarmers = document.getElementById('statFarmers');
        
        if (statFarms) {
            statFarms.textContent = stats.total_farms || farmers.length || '0';
        }
        if (statFarmers) {
            statFarmers.textContent = stats.total_farmers || '0';
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
                        <div class="stat-value">${stats.total_farmers || 0}</div>
                        <div class="stat-label">Total Farmers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.active_plantings || 0}</div>
                        <div class="stat-label">Active Plantings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.due_harvests || 0}</div>
                        <div class="stat-label">Due Harvests</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.total_land || 0}</div>
                        <div class="stat-label">Acres Cultivated</div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="dashboard-grid">
                    <!-- Farmers Table -->
                    <div class="farmers-table-container">
                        <h3>
                            Recent Farmers
                            <a href="farm-management.php" class="view-all-link">View All →</a>
                        </h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Farmer</th>
                                    <th>County/Region</th>
                                    <th>Number of farms</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${farmers.length > 0 ? farmers.slice(0, 5).map(farmer => `
                                    <tr onclick="location.href='farm-management.php'">
                                        <td>${farmer.id || ''}</td>
                                        <td>${farmer.farmer || farmer.full_name || ''}</td>
                                        <td>${farmer.region_name || 'Unknown'}</td>
                                        <td>${farmer.number_of_farms || farmer.planting_count || 0}</td>
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
                        <h3>
                            Upcoming Events
                            <a href="crop-schedule.php" class="view-all-link">View All</a>
                        </h3>
                        <div class="events-list">
                            ${events.length > 0 ? events.slice(0, 4).map(event => `
                                <div class="event-card" onclick="location.href='farm-management.php?id=${event.id}'">
                                    <div class="event-type">
                                        ${event.event_type || 'Harvesting'} on Farm #${event.planting_id || event.id || ''}
                                    </div>
                                    <div class="event-details">
                                        <span class="event-farmer">${event.farmer_name || ''}</span>
                                        <span class="event-county">${event.region_name || 'Unknown'} Region</span>
                                    </div>
                                    <div class="event-meta">
                                        ${event.crop_name || 'Crops'} • ${event.days_left || event.days_remaining || 0} days left
                                    </div>
                                </div>
                            `).join('') : `
                                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    No upcoming events
                                </div>
                            `}
                            <div class="farmers-cycle">
                                <span>Active Farmers Cycle</span>
                                <span class="cycle-count">${stats.active_farmers || farmers.length || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Stats Section -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 30px;">
                    <div class="farmers-table-container">
                        <h3>Top Growing Regions</h3>
                        <table class="data-table" style="min-width: auto;">
                            <tbody>
                                ${stats.top_regions && stats.top_regions.length > 0 ? 
                                    stats.top_regions.map(region => `
                                        <tr>
                                            <td>${region.region_name || 'Unknown'}</td>
                                            <td style="text-align: right;">${region.count || 0} farms</td>
                                        </tr>
                                    `).join('') 
                                    : '<tr><td colspan="2" style="text-align: center;">No region data</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="farmers-table-container">
                        <h3>Popular Crops</h3>
                        <table class="data-table" style="min-width: auto;">
                            <tbody>
                                ${stats.top_crops && stats.top_crops.length > 0 ? 
                                    stats.top_crops.map(crop => `
                                        <tr>
                                            <td>${crop.crop_name || 'Unknown'}</td>
                                            <td style="text-align: right;">${crop.count || 0} farms</td>
                                        </tr>
                                    `).join('') 
                                    : '<tr><td colspan="2" style="text-align: center;">No crop data</td></tr>'
                                }
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentArea.innerHTML = html;
    }

    // Toast notification function
    function showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast-notification ${type === 'success' ? 'success' : type === 'error' ? 'error' : ''} show`;
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Dashboard page initialized');
        await loadDashboardData();
        
        // Listen for global search events
        window.addEventListener('globalSearch', (e) => {
            const searchTerm = e.detail.term;
            console.log('Global search:', searchTerm);
            showToast(`Searching for: ${searchTerm}`, 'info');
        });
    });

    // Make showToast globally available
    window.showToast = showToast;
    </script>

    <!-- Generate navigation scripts (place after page scripts) -->
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API ENDPOINT FUNCTIONS (UPDATED FOR NEW DATABASE SCHEMA) ====================

function getFarmers($pdo) {
    try {
        // Updated query for the new database schema
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name as farmer,
                u.full_name,
                u.email,
                u.phone_number,
                (SELECT COUNT(*) FROM planting_requests WHERE farmer_id = u.id) as number_of_farms,
                (SELECT COUNT(*) FROM planting_requests WHERE farmer_id = u.id AND status IN ('Planted', 'Growing')) as planting_count,
                (SELECT region_name FROM planting_requests WHERE farmer_id = u.id LIMIT 1) as region_name
            FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            WHERE ut.role_name = 'Farmer'
            ORDER BY u.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($farmers);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getEvents($pdo) {
    try {
        // Updated query for the new database schema
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.id as planting_id,
                u.full_name as farmer_name,
                c.crop_name,
                pr.region_name,
                pr.expected_harvest_date,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_left,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_remaining,
                pr.expected_harvest_date as event_date,
                'Harvesting' as event_type
            FROM planting_requests pr
            INNER JOIN users u ON pr.farmer_id = u.id
            INNER JOIN crops c ON pr.crop_id = c.id
            WHERE pr.status IN ('Planted', 'Growing')
                AND pr.expected_harvest_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            ORDER BY pr.expected_harvest_date ASC
            LIMIT 10
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($events);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getUsers($pdo) {
    try {
        // Updated query for the new database schema
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.is_active,
                DATE_FORMAT(u.created_at, '%Y-%m-%d') as created_at,
                ut.role_name
            FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            ORDER BY u.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($users);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getStats($pdo) {
    try {
        $stats = [];
        
        // Total farmers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            WHERE ut.role_name = 'Farmer'
        ");
        $stmt->execute();
        $stats['total_farmers'] = $stmt->fetchColumn();
        
        // Active plantings (Planted or Growing status)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
        ");
        $stmt->execute();
        $stats['active_plantings'] = $stmt->fetchColumn();
        
        // Due harvests (next 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM planting_requests 
            WHERE expected_harvest_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND status != 'Harvested'
        ");
        $stmt->execute();
        $stats['due_harvests'] = $stmt->fetchColumn();
        
        // Total land (acres cultivated)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(land_size_acres), 0) FROM planting_requests
        ");
        $stmt->execute();
        $stats['total_land'] = round($stmt->fetchColumn(), 2);
        
        // Total farms (planting requests)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM planting_requests");
        $stmt->execute();
        $stats['total_farms'] = $stmt->fetchColumn();
        
        // Active farmers (farmers with active plantings)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT farmer_id) FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
        ");
        $stmt->execute();
        $stats['active_farmers'] = $stmt->fetchColumn();
        
        // Top regions
        $stmt = $pdo->prepare("
            SELECT region_name, COUNT(*) as count
            FROM planting_requests
            WHERE region_name IS NOT NULL AND region_name != ''
            GROUP BY region_name
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute();
        $stats['top_regions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top crops
        $stmt = $pdo->prepare("
            SELECT c.crop_name, COUNT(*) as count
            FROM planting_requests pr
            INNER JOIN crops c ON pr.crop_id = c.id
            GROUP BY c.id, c.crop_name
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute();
        $stats['top_crops'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getAdminInfo($pdo) {
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        
        if (!$userId) {
            echo json_encode([]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, phone_number
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($admin ?: []);
    } catch (Exception $e) {
        echo json_encode([]);
    }
}

?>