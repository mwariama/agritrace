<?php
// reports.php - Complete Reports & Analytics for AgriMarketplace Admin Panel

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('Reports & Analytics', 'reports');

// Check if this is an API request
$isApiRequest = isset($_GET['action']) || isset($_POST['action']) || 
                ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isApiRequest) {
    // Clear output buffer for API response
    ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        // Include database connection
        require_once '../database.php';
        
        // Get database connection
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        // Get action parameter
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        // Route to appropriate function
        switch($action) {
            case 'get_dashboard_summary':
                getDashboardSummary($pdo);
                break;
            case 'get_sales_report':
                getSalesReport($pdo);
                break;
            case 'get_crop_analytics':
                getCropAnalytics($pdo);
                break;
            case 'get_farmer_performance':
                getFarmerPerformance($pdo);
                break;
            case 'get_buyer_analytics':
                getBuyerAnalytics($pdo);
                break;
            case 'get_regional_insights':
                getRegionalInsights($pdo);
                break;
            case 'get_monthly_trends':
                getMonthlyTrends($pdo);
                break;
            case 'get_order_analytics':
                getOrderAnalytics($pdo);
                break;
            case 'get_revenue_breakdown':
                getRevenueBreakdown($pdo);
                break;
            case 'get_inventory_status':
                getInventoryStatus($pdo);
                break;
            case 'export_report':
                exportReport($pdo);
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
    <link rel="stylesheet" href="reports.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Additional meta for mobile -->
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
        /* Reports Page Styles */
        :root {
            --primary-green: #1F7A4C;
            --primary-green-light: #2E9E6B;
            --soft-green: #e8f5e9;
            --active: #2E7D32;
            --inactive: #9E9E9E;
            --blocked: #E45A5A;
            --warning: #FF9800;
            --info: #2196F3;
            --sold-out: #F44336;
            --pending: #FFC107;
            --delivered: #4CAF50;
            --card-bg: #ffffff;
            --page-bg: #f5f6fa;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-light: #E0E0E0;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-lg: 0 5px 20px rgba(0,0,0,0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
        }

        .content-area {
            padding: 20px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-green-light);
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--page-bg);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-green);
            border: 1px solid var(--primary-green);
        }

        .btn-outline:hover {
            background: var(--soft-green);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Date Range Selector */
        .date-range {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .date-range select,
        .date-range input {
            padding: 8px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .date-range select:focus,
        .date-range input:focus {
            outline: none;
            border-color: var(--primary-green);
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 25px;
            background: var(--card-bg);
            border-radius: 10px;
            padding: 4px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: var(--text-primary);
        }

        .tab-btn.active {
            background: var(--primary-green);
            color: white;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.green { background: #e8f5e9; color: #2E7D32; }
        .stat-icon.blue { background: #e3f2fd; color: #1565C0; }
        .stat-icon.orange { background: #fff3e0; color: #E65100; }
        .stat-icon.purple { background: #f3e5f5; color: #7B1FA2; }
        .stat-icon.red { background: #ffebee; color: #c62828; }
        .stat-icon.teal { background: #e0f2f1; color: #00695C; }

        .stat-trend {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .stat-trend.up { background: #e8f5e9; color: #2E7D32; }
        .stat-trend.down { background: #ffebee; color: #c62828; }
        .stat-trend.neutral { background: #f5f5f5; color: #757575; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .stat-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        /* Chart Containers */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
        }

        .chart-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }

        .chart-container.large {
            height: 400px;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* Tables */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: var(--page-bg);
        }

        table th {
            padding: 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--border-light);
            white-space: nowrap;
        }

        table td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-light);
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success { background: #e8f5e9; color: #2E7D32; }
        .badge-warning { background: #fff3e0; color: #E65100; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1565C0; }
        .badge-neutral { background: #f5f5f5; color: #757575; }

        /* Loading State */
        .loading-overlay {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            font-size: 16px;
            color: var(--text-secondary);
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-light);
            border-top-color: var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success { background: #2E7D32; }
        .toast.error { background: #c62828; }
        .toast.info { background: #1565C0; }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-data .no-data-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range {
                flex-wrap: wrap;
            }
            
            .tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .stat-value {
                font-size: 22px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .chart-container.large {
                height: 300px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
                <div class="loading-overlay">
                    <div class="spinner"></div>
                    <span>Loading reports...</span>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <?php echo generateLoadingAnimation(); ?>

    <script>
    // API Service
    const API = {
        async request(action, method = 'GET', data = null) {
            try {
                let url = window.location.pathname + '?action=' + action;
                const options = {
                    method: method,
                    headers: {}
                };
                
                if (method === 'POST' && data) {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(data);
                } else if (method === 'GET' && data) {
                    const params = new URLSearchParams(data);
                    url += '&' + params.toString();
                }
                
                const response = await fetch(url, options);
                const responseText = await response.text();
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('JSON parse error:', e, 'Response:', responseText);
                    throw new Error('Invalid server response');
                }
                
                if (result.error) {
                    throw new Error(result.error);
                }
                
                return result;
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

    // Reports Manager
    const ReportsManager = {
        currentTab: 'overview',
        dateRange: 'month',
        customStartDate: null,
        customEndDate: null,
        charts: {},
        
        // Chart color palettes
        colors: {
            green: '#1F7A4C',
            greenLight: '#4CAF50',
            greenBg: 'rgba(31, 122, 76, 0.7)',
            greenBgLight: 'rgba(31, 122, 76, 0.2)',
            blue: '#2196F3',
            blueDark: '#1565C0',
            blueBg: 'rgba(33, 150, 243, 0.7)',
            blueBgLight: 'rgba(33, 150, 243, 0.1)',
            orange: '#FF9800',
            orangeDark: '#E65100',
            orangeBg: 'rgba(255, 152, 0, 0.7)',
            orangeBgLight: 'rgba(255, 152, 0, 0.1)',
            purple: '#9C27B0',
            purpleBg: 'rgba(156, 39, 176, 0.7)',
            red: '#F44336',
            redBg: 'rgba(244, 67, 54, 0.7)',
            teal: '#009688',
            tealBg: 'rgba(0, 150, 136, 0.7)',
            yellow: '#FFEB3B',
            yellowBg: 'rgba(255, 235, 59, 0.7)',
            // Pie/Doughnut color array
            pieColors: [
                '#1F7A4C', '#4CAF50', '#8BC34A', '#CDDC39',
                '#FF9800', '#FF5722', '#795548', '#607D8B',
                '#2196F3', '#03A9F4', '#00BCD4', '#009688',
                '#9C27B0', '#E91E63', '#F44336', '#FFEB3B'
            ]
        },
        
        init: async function() {
            console.log('Reports Manager initializing...');
            this.setupEventListeners();
            await this.loadDashboard();
            
            <?php if (isset($_SESSION['user_name'])): ?>
            const adminName = document.getElementById('adminName');
            const adminAvatar = document.getElementById('adminAvatar');
            if (adminName) adminName.textContent = '<?php echo $_SESSION['user_name']; ?>';
            if (adminAvatar) adminAvatar.textContent = '<?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?>';
            <?php endif; ?>
        },
        
        setupEventListeners: function() {
            // Date range change
            document.addEventListener('change', (e) => {
                if (e.target.id === 'dateRangeSelect') {
                    this.dateRange = e.target.value;
                    if (this.dateRange === 'custom') {
                        document.getElementById('customDateRange').style.display = 'flex';
                    } else {
                        document.getElementById('customDateRange').style.display = 'none';
                        this.refreshCurrentTab();
                    }
                }
            });
            
            // Apply custom date button
            document.addEventListener('click', (e) => {
                if (e.target.id === 'applyCustomDate') {
                    this.customStartDate = document.getElementById('startDate').value;
                    this.customEndDate = document.getElementById('endDate').value;
                    this.refreshCurrentTab();
                }
            });
        },
        
        getDateFilter: function() {
            const filter = {};
            
            if (this.dateRange === 'custom') {
                if (this.customStartDate) filter.start_date = this.customStartDate;
                if (this.customEndDate) filter.end_date = this.customEndDate;
            } else {
                filter.period = this.dateRange;
            }
            
            return filter;
        },
        
        switchTab: async function(tab) {
            this.currentTab = tab;
            
            // Destroy existing charts
            this.destroyAllCharts();
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            
            // Show/hide tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.toggle('active', content.id === 'tab-' + tab);
            });
            
            await this.refreshCurrentTab();
        },
        
        destroyAllCharts: function() {
            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].destroy();
                    this.charts[key] = null;
                }
            });
        },
        
        refreshCurrentTab: async function() {
            this.destroyAllCharts();
            
            switch(this.currentTab) {
                case 'overview':
                    await this.loadDashboard();
                    break;
                case 'sales':
                    await this.loadSalesReport();
                    break;
                case 'crops':
                    await this.loadCropAnalytics();
                    break;
                case 'farmers':
                    await this.loadFarmerPerformance();
                    break;
                case 'buyers':
                    await this.loadBuyerAnalytics();
                    break;
                case 'regional':
                    await this.loadRegionalInsights();
                    break;
                case 'orders':
                    await this.loadOrderAnalytics();
                    break;
            }
        },
        
        showLoading: function(tab) {
            const content = document.getElementById('tab-' + tab);
            if (content) {
                content.innerHTML = `
                    <div class="loading-overlay">
                        <div class="spinner"></div>
                        <span>Loading data...</span>
                    </div>
                `;
            }
        },
        
        loadDashboard: async function() {
            this.showLoading('overview');
            
            const result = await API.get('get_dashboard_summary', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderDashboard(result);
            } else {
                this.showToast('Error loading dashboard: ' + (result?.error || 'Unknown error'), 'error');
            }
        },
        
        loadSalesReport: async function() {
            this.showLoading('sales');
            
            const result = await API.get('get_sales_report', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderSalesReport(result);
            } else {
                this.showToast('Error loading sales report', 'error');
            }
        },
        
        loadCropAnalytics: async function() {
            this.showLoading('crops');
            
            const result = await API.get('get_crop_analytics', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderCropAnalytics(result);
            } else {
                this.showToast('Error loading crop analytics', 'error');
            }
        },
        
        loadFarmerPerformance: async function() {
            this.showLoading('farmers');
            
            const result = await API.get('get_farmer_performance', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderFarmerPerformance(result);
            } else {
                this.showToast('Error loading farmer performance', 'error');
            }
        },
        
        loadBuyerAnalytics: async function() {
            this.showLoading('buyers');
            
            const result = await API.get('get_buyer_analytics', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderBuyerAnalytics(result);
            } else {
                this.showToast('Error loading buyer analytics', 'error');
            }
        },
        
        loadRegionalInsights: async function() {
            this.showLoading('regional');
            
            const result = await API.get('get_regional_insights', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderRegionalInsights(result);
            } else {
                this.showToast('Error loading regional insights', 'error');
            }
        },
        
        loadOrderAnalytics: async function() {
            this.showLoading('orders');
            
            const result = await API.get('get_order_analytics', this.getDateFilter());
            
            if (result && !result.error) {
                this.renderOrderAnalytics(result);
            } else {
                this.showToast('Error loading order analytics', 'error');
            }
        },
        
        renderDashboard: function(data) {
            const container = document.getElementById('tab-overview');
            
            // Use fallback data if API returns empty arrays
            const monthlyData = data.monthly_data && data.monthly_data.length > 0 ? data.monthly_data : [
                { month: 'Jan', orders: 0, revenue: 0 },
                { month: 'Feb', orders: 0, revenue: 0 },
                { month: 'Mar', orders: 0, revenue: 0 },
                { month: 'Apr', orders: 0, revenue: 0 },
                { month: 'May', orders: 0, revenue: 0 },
                { month: 'Jun', orders: 0, revenue: 0 }
            ];
            
            const orderStatuses = data.order_statuses && Object.keys(data.order_statuses).length > 0 ? data.order_statuses : {
                'Pending': 0, 'Paid': 0, 'Delivered': 0
            };
            
            const topCrops = data.top_crops && data.top_crops.length > 0 ? data.top_crops : [
                { crop_name: 'No Data', revenue: 0, quantity_sold: 0 }
            ];
            
            let html = `
                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green">💰</div>
                            <span class="stat-trend ${(data.revenue_trend || 0) >= 0 ? 'up' : 'down'}">${(data.revenue_trend || 0) >= 0 ? '▲' : '▼'} ${Math.abs(data.revenue_trend || 0)}%</span>
                        </div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_revenue || 0)}</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue">📦</div>
                            <span class="stat-trend ${(data.orders_trend || 0) >= 0 ? 'up' : 'down'}">${(data.orders_trend || 0) >= 0 ? '▲' : '▼'} ${Math.abs(data.orders_trend || 0)}%</span>
                        </div>
                        <div class="stat-value">${this.formatNumber(data.total_orders || 0)}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon orange">🌾</div>
                        </div>
                        <div class="stat-value">${this.formatNumber(data.total_quantity_sold || 0)} kg</div>
                        <div class="stat-label">Total Quantity Sold</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon purple">👨‍🌾</div>
                        </div>
                        <div class="stat-value">${this.formatNumber(data.active_farmers || 0)}</div>
                        <div class="stat-label">Active Farmers</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon teal">🛒</div>
                        </div>
                        <div class="stat-value">${this.formatNumber(data.active_buyers || 0)}</div>
                        <div class="stat-label">Active Buyers</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon red">📋</div>
                        </div>
                        <div class="stat-value">${this.formatNumber(data.active_listings || 0)}</div>
                        <div class="stat-label">Active Listings</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Revenue vs Orders</div>
                                <div class="chart-subtitle">Monthly comparison</div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueOrdersChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Orders by Status</div>
                                <div class="chart-subtitle">Distribution overview</div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Top Performing Crops</div>
                                <div class="chart-subtitle">By revenue and quantity sold</div>
                            </div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="topCropsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Recent Transactions</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Buyer</th>
                                    <th>Farmer</th>
                                    <th>Crop</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.recent_transactions && data.recent_transactions.length > 0) {
                data.recent_transactions.forEach(function(tx) {
                    const statusBadge = tx.escrow_status === 'Delivered' ? 'badge-success' :
                                       tx.escrow_status === 'Paid' ? 'badge-info' :
                                       tx.escrow_status === 'Pending' ? 'badge-warning' : 'badge-neutral';
                    
                    html += `
                        <tr>
                            <td>#${tx.order_id}</td>
                            <td>${ReportsManager.escapeHtml(tx.buyer_name || 'N/A')}</td>
                            <td>${ReportsManager.escapeHtml(tx.farmer_name || 'N/A')}</td>
                            <td>${ReportsManager.escapeHtml(tx.crop_name || 'N/A')}</td>
                            <td>${tx.quantity_ordered_kg} kg</td>
                            <td>KES ${ReportsManager.formatNumber(tx.total_price)}</td>
                            <td><span class="badge ${statusBadge}">${tx.escrow_status}</span></td>
                            <td>${tx.transaction_date || tx.order_date || 'N/A'}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="8" class="no-data"><div class="no-data-icon">📭</div>No recent transactions</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            // Initialize charts after DOM is updated
            this.initRevenueOrdersChart(monthlyData);
            this.initOrderStatusChart(orderStatuses);
            this.initTopCropsChart(topCrops);
        },
        
        renderSalesReport: function(data) {
            const container = document.getElementById('tab-sales');
            
            const trends = data.revenue_trends && data.revenue_trends.length > 0 ? data.revenue_trends : [
                { period: 'No Data', orders: 0, revenue: 0, quantity: 0, avg_value: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_revenue || 0)}</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">📊</div>
                        <div class="stat-value">KES ${this.formatNumber(data.avg_order_value || 0)}</div>
                        <div class="stat-label">Average Order Value</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">📦</div>
                        <div class="stat-value">${this.formatNumber(data.total_orders || 0)}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">⚖️</div>
                        <div class="stat-value">${this.formatNumber(data.total_quantity_sold || 0)} kg</div>
                        <div class="stat-label">Total Quantity Sold</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Revenue Trends</div>
                                <div class="chart-subtitle">Period breakdown</div>
                            </div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Sales Summary</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th class="text-right">Orders</th>
                                    <th class="text-right">Quantity (kg)</th>
                                    <th class="text-right">Revenue (KES)</th>
                                    <th class="text-right">Avg Order Value</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.sales_summary && data.sales_summary.length > 0) {
                data.sales_summary.forEach(function(item) {
                    html += `
                        <tr>
                            <td>${item.period}</td>
                            <td class="text-right">${ReportsManager.formatNumber(item.orders)}</td>
                            <td class="text-right">${ReportsManager.formatNumber(item.quantity)}</td>
                            <td class="text-right">KES ${ReportsManager.formatNumber(item.revenue)}</td>
                            <td class="text-right">KES ${ReportsManager.formatNumber(item.avg_value)}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="5" class="no-data">No sales data available</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initRevenueTrendChart(trends);
        },
        
        renderCropAnalytics: function(data) {
            const container = document.getElementById('tab-crops');
            
            const distribution = data.crop_distribution && data.crop_distribution.length > 0 ? data.crop_distribution : [
                { crop_name: 'No Data', count: 1 }
            ];
            
            const yieldPerf = data.yield_performance && data.yield_performance.length > 0 ? data.yield_performance : [
                { crop_name: 'No Data', avg_yield: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">🌱</div>
                        <div class="stat-value">${data.total_crops || 0}</div>
                        <div class="stat-label">Total Crops</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_plantings || 0)}</div>
                        <div class="stat-label">Total Plantings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">📏</div>
                        <div class="stat-value">${this.formatNumber(data.total_land || 0)} acres</div>
                        <div class="stat-label">Total Land Cultivated</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">⚡</div>
                        <div class="stat-value">${this.formatNumber(data.avg_yield || 0)} kg/acre</div>
                        <div class="stat-label">Average Yield</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Crop Distribution</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="cropDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Yield Performance</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="yieldPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Crop Performance Details</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th class="text-right">Plantings</th>
                                    <th class="text-right">Farmers</th>
                                    <th class="text-right">Land (acres)</th>
                                    <th class="text-right">Avg Yield/acre</th>
                                    <th class="text-right">Total Yield (kg)</th>
                                    <th class="text-right">Listings</th>
                                    <th class="text-right">Sales</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.crop_details && data.crop_details.length > 0) {
                data.crop_details.forEach(function(crop) {
                    html += `
                        <tr>
                            <td>${ReportsManager.escapeHtml(crop.crop_name)}</td>
                            <td class="text-right">${crop.total_plantings || 0}</td>
                            <td class="text-right">${crop.unique_farmers || 0}</td>
                            <td class="text-right">${ReportsManager.formatNumber(crop.total_land || 0)}</td>
                            <td class="text-right">${ReportsManager.formatNumber(crop.avg_yield || 0)}</td>
                            <td class="text-right">${ReportsManager.formatNumber(crop.total_yield || 0)}</td>
                            <td class="text-right">${crop.total_listings || 0}</td>
                            <td class="text-right">${crop.total_sales || 0}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="8" class="no-data">No crop data available</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initCropDistributionChart(distribution);
            this.initYieldPerformanceChart(yieldPerf);
        },
        
        renderFarmerPerformance: function(data) {
            const container = document.getElementById('tab-farmers');
            
            const farmers = data.farmer_rankings && data.farmer_rankings.length > 0 ? data.farmer_rankings.slice(0, 10) : [
                { farmer_name: 'No Data', total_yield: 0, revenue: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">👨‍🌾</div>
                        <div class="stat-value">${data.total_farmers || 0}</div>
                        <div class="stat-label">Total Farmers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_plantings || 0)}</div>
                        <div class="stat-label">Total Plantings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">📋</div>
                        <div class="stat-value">${data.total_listings || 0}</div>
                        <div class="stat-label">Total Listings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_revenue || 0)}</div>
                        <div class="stat-label">Revenue from Farmers</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Top Farmers by Production</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="topFarmersChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Farmer Rankings</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Farmer</th>
                                    <th class="text-right">Plantings</th>
                                    <th class="text-right">Land (acres)</th>
                                    <th class="text-right">Yield (kg)</th>
                                    <th class="text-right">Listings</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.farmer_rankings && data.farmer_rankings.length > 0) {
                data.farmer_rankings.forEach(function(farmer, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${ReportsManager.escapeHtml(farmer.farmer_name)}</td>
                            <td class="text-right">${farmer.plantings || 0}</td>
                            <td class="text-right">${ReportsManager.formatNumber(farmer.total_land || 0)}</td>
                            <td class="text-right">${ReportsManager.formatNumber(farmer.total_yield || 0)}</td>
                            <td class="text-right">${farmer.listings || 0}</td>
                            <td class="text-right">KES ${ReportsManager.formatNumber(farmer.revenue || 0)}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="7" class="no-data">No farmer data available</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initTopFarmersChart(farmers);
        },
        
        renderBuyerAnalytics: function(data) {
            const container = document.getElementById('tab-buyers');
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">🛒</div>
                        <div class="stat-value">${data.total_buyers || 0}</div>
                        <div class="stat-label">Total Buyers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">📦</div>
                        <div class="stat-value">${this.formatNumber(data.total_orders || 0)}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">⚖️</div>
                        <div class="stat-value">${this.formatNumber(data.total_quantity || 0)} kg</div>
                        <div class="stat-label">Total Purchased</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_spent || 0)}</div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Top Buyers</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Buyer</th>
                                    <th class="text-right">Orders</th>
                                    <th class="text-right">Quantity (kg)</th>
                                    <th class="text-right">Total Spent</th>
                                    <th class="text-right">Avg Order Value</th>
                                    <th>Last Order</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.top_buyers && data.top_buyers.length > 0) {
                data.top_buyers.forEach(function(buyer, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${ReportsManager.escapeHtml(buyer.buyer_name)}</td>
                            <td class="text-right">${buyer.total_orders || 0}</td>
                            <td class="text-right">${ReportsManager.formatNumber(buyer.total_quantity || 0)}</td>
                            <td class="text-right">KES ${ReportsManager.formatNumber(buyer.total_spent || 0)}</td>
                            <td class="text-right">KES ${ReportsManager.formatNumber(buyer.avg_order_value || 0)}</td>
                            <td>${buyer.last_order_date || 'N/A'}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="7" class="no-data">No buyer data available</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        },
        
        renderRegionalInsights: function(data) {
            const container = document.getElementById('tab-regional');
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">📍</div>
                        <div class="stat-value">${data.total_regions || 0}</div>
                        <div class="stat-label">Active Regions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">👨‍🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_farmers || 0)}</div>
                        <div class="stat-label">Farmers in Regions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">📏</div>
                        <div class="stat-value">${this.formatNumber(data.total_land || 0)} acres</div>
                        <div class="stat-label">Total Land</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_yield || 0)} kg</div>
                        <div class="stat-label">Total Expected Yield</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Regional Breakdown</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Region</th>
                                    <th class="text-right">Farmers</th>
                                    <th class="text-right">Plantings</th>
                                    <th class="text-right">Land (acres)</th>
                                    <th class="text-right">Expected Yield (kg)</th>
                                    <th>Crops Grown</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.regional_data && data.regional_data.length > 0) {
                data.regional_data.forEach(function(region) {
                    html += `
                        <tr>
                            <td><strong>${ReportsManager.escapeHtml(region.region_name || 'Unknown')}</strong></td>
                            <td class="text-right">${region.farmers_count || 0}</td>
                            <td class="text-right">${region.plantings_count || 0}</td>
                            <td class="text-right">${ReportsManager.formatNumber(region.total_land || 0)}</td>
                            <td class="text-right">${ReportsManager.formatNumber(region.expected_yield || 0)}</td>
                            <td>${ReportsManager.escapeHtml(region.crops_grown || 'N/A')}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="6" class="no-data">No regional data available</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        },
        
        renderOrderAnalytics: function(data) {
            const container = document.getElementById('tab-orders');
            
            const statuses = data.order_statuses && Object.keys(data.order_statuses).length > 0 ? data.order_statuses : {
                'Pending': 0, 'Paid': 0, 'Delivered': 0
            };
            
            const trends = data.order_trends && data.order_trends.length > 0 ? data.order_trends : [
                { period: 'No Data', count: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">📦</div>
                        <div class="stat-value">${this.formatNumber(data.total_orders || 0)}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">⏳</div>
                        <div class="stat-value">${data.pending_orders || 0}</div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">✅</div>
                        <div class="stat-value">${data.delivered_orders || 0}</div>
                        <div class="stat-label">Delivered Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">📊</div>
                        <div class="stat-value">${data.delivery_rate || 0}%</div>
                        <div class="stat-label">Delivery Rate</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Order Status Distribution</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderStatusPieChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Order Trends</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initOrderStatusPieChart(statuses);
            this.initOrderTrendsChart(trends);
        },
        
        // Chart Initialization Methods
        initRevenueOrdersChart: function(monthlyData) {
            const canvas = document.getElementById('revenueOrdersChart');
            if (!canvas) {
                console.warn('revenueOrdersChart canvas not found');
                return;
            }
            
            if (this.charts.revenueOrders) this.charts.revenueOrders.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.revenueOrders = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(d => d.month),
                    datasets: [
                        {
                            label: 'Revenue (KES)',
                            data: monthlyData.map(d => d.revenue),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: monthlyData.map(d => d.orders),
                            type: 'line',
                            borderColor: this.colors.orange,
                            backgroundColor: this.colors.orangeBgLight,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: this.colors.orange,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: { 
                                display: true, 
                                text: 'Revenue (KES)',
                                color: this.colors.green
                            },
                            ticks: { 
                                callback: v => 'KES ' + this.formatNumber(v),
                                color: this.colors.green
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: { 
                                display: true, 
                                text: 'Orders',
                                color: this.colors.orange
                            },
                            ticks: {
                                color: this.colors.orange
                            },
                            grid: { drawOnChartArea: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        initOrderStatusChart: function(statuses) {
            const canvas = document.getElementById('orderStatusChart');
            if (!canvas) return;
            
            if (this.charts.orderStatus) this.charts.orderStatus.destroy();
            
            const ctx = canvas.getContext('2d');
            const labels = Object.keys(statuses);
            const values = Object.values(statuses);
            const bgColors = [
                this.colors.yellowBg,
                this.colors.blueBg,
                this.colors.greenBg
            ];
            const borderColors = [
                this.colors.yellow,
                this.colors.blue,
                this.colors.green
            ];
            
            this.charts.orderStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: bgColors,
                        borderColor: borderColors,
                        borderWidth: 2,
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        },
        
        initTopCropsChart: function(crops) {
            const canvas = document.getElementById('topCropsChart');
            if (!canvas) return;
            
            if (this.charts.topCrops) this.charts.topCrops.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.topCrops = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: crops.map(c => c.crop_name),
                    datasets: [
                        {
                            label: 'Revenue (KES)',
                            data: crops.map(c => c.revenue || 0),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Quantity Sold (kg)',
                            data: crops.map(c => c.quantity_sold || 0),
                            backgroundColor: this.colors.orangeBg,
                            borderColor: this.colors.orange,
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { 
                                callback: v => this.formatNumber(v)
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        initRevenueTrendChart: function(trends) {
            const canvas = document.getElementById('revenueTrendChart');
            if (!canvas) return;
            
            if (this.charts.revenueTrend) this.charts.revenueTrend.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.revenueTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trends.map(t => t.period),
                    datasets: [{
                        label: 'Revenue (KES)',
                        data: trends.map(t => t.revenue),
                        borderColor: this.colors.green,
                        backgroundColor: this.colors.greenBgLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: this.colors.green,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: { 
                                callback: v => 'KES ' + this.formatNumber(v)
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        initCropDistributionChart: function(distribution) {
            const canvas = document.getElementById('cropDistributionChart');
            if (!canvas) return;
            
            if (this.charts.cropDistribution) this.charts.cropDistribution.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.cropDistribution = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: distribution.map(d => d.crop_name),
                    datasets: [{
                        data: distribution.map(d => d.count),
                        backgroundColor: this.colors.pieColors,
                        borderColor: '#fff',
                        borderWidth: 2,
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
        },
        
        initYieldPerformanceChart: function(performance) {
            const canvas = document.getElementById('yieldPerformanceChart');
            if (!canvas) return;
            
            if (this.charts.yieldPerformance) this.charts.yieldPerformance.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.yieldPerformance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: performance.map(p => p.crop_name),
                    datasets: [{
                        label: 'Avg Yield (kg/acre)',
                        data: performance.map(p => p.avg_yield),
                        backgroundColor: this.colors.greenBg,
                        borderColor: this.colors.green,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        initTopFarmersChart: function(farmers) {
            const canvas = document.getElementById('topFarmersChart');
            if (!canvas) return;
            
            if (this.charts.topFarmers) this.charts.topFarmers.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.topFarmers = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: farmers.map(f => f.farmer_name),
                    datasets: [
                        {
                            label: 'Total Yield (kg)',
                            data: farmers.map(f => f.total_yield || 0),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Revenue (KES)',
                            data: farmers.map(f => f.revenue || 0),
                            backgroundColor: this.colors.orangeBg,
                            borderColor: this.colors.orange,
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { 
                                callback: v => this.formatNumber(v)
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        initOrderStatusPieChart: function(statuses) {
            const canvas = document.getElementById('orderStatusPieChart');
            if (!canvas) return;
            
            if (this.charts.orderStatusPie) this.charts.orderStatusPie.destroy();
            
            const ctx = canvas.getContext('2d');
            const labels = Object.keys(statuses);
            const values = Object.values(statuses);
            
            this.charts.orderStatusPie = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            this.colors.yellowBg,
                            this.colors.blueBg,
                            this.colors.greenBg,
                            this.colors.redBg
                        ],
                        borderColor: [
                            this.colors.yellow,
                            this.colors.blue,
                            this.colors.green,
                            this.colors.red
                        ],
                        borderWidth: 2,
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        },
        
        initOrderTrendsChart: function(trends) {
            const canvas = document.getElementById('orderTrendsChart');
            if (!canvas) return;
            
            if (this.charts.orderTrends) this.charts.orderTrends.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.orderTrends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trends.map(t => t.period),
                    datasets: [{
                        label: 'Orders',
                        data: trends.map(t => t.count),
                        borderColor: this.colors.blue,
                        backgroundColor: this.colors.blueBgLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: this.colors.blue,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        exportReport: async function(type) {
            this.showToast('Export feature coming soon', 'info');
        },
        
        formatNumber: function(num) {
            if (num === null || num === undefined) return '0';
            return parseFloat(num).toLocaleString('en-KE', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        showToast: function(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        },
        
        render: function() {
            const contentArea = document.getElementById('contentArea');
            
            let html = `
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <h2>Reports & Analytics</h2>
                        <p>Comprehensive insights into your marketplace performance</p>
                    </div>
                    <div class="header-actions">
                        <div class="date-range">
                            <select id="dateRangeSelect">
                                <option value="week">This Week</option>
                                <option value="month" selected>This Month</option>
                                <option value="quarter">This Quarter</option>
                                <option value="year">This Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                            <div id="customDateRange" style="display: none; gap: 8px; align-items: center;">
                                <input type="date" id="startDate" style="width: 140px;">
                                <span>to</span>
                                <input type="date" id="endDate" style="width: 140px;">
                                <button class="btn btn-primary btn-sm" id="applyCustomDate">Apply</button>
                            </div>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="ReportsManager.exportReport('pdf')">📄 Export PDF</button>
                        <button class="btn btn-outline btn-sm" onclick="ReportsManager.exportReport('csv')">📊 Export CSV</button>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab-btn active" data-tab="overview" onclick="ReportsManager.switchTab('overview')">📊 Overview</button>
                    <button class="tab-btn" data-tab="sales" onclick="ReportsManager.switchTab('sales')">💰 Sales</button>
                    <button class="tab-btn" data-tab="crops" onclick="ReportsManager.switchTab('crops')">🌾 Crops</button>
                    <button class="tab-btn" data-tab="farmers" onclick="ReportsManager.switchTab('farmers')">👨‍🌾 Farmers</button>
                    <button class="tab-btn" data-tab="buyers" onclick="ReportsManager.switchTab('buyers')">🛒 Buyers</button>
                    <button class="tab-btn" data-tab="regional" onclick="ReportsManager.switchTab('regional')">📍 Regional</button>
                    <button class="tab-btn" data-tab="orders" onclick="ReportsManager.switchTab('orders')">📦 Orders</button>
                </div>
                
                <!-- Tab Contents -->
                <div class="tab-content active" id="tab-overview"></div>
                <div class="tab-content" id="tab-sales"></div>
                <div class="tab-content" id="tab-crops"></div>
                <div class="tab-content" id="tab-farmers"></div>
                <div class="tab-content" id="tab-buyers"></div>
                <div class="tab-content" id="tab-regional"></div>
                <div class="tab-content" id="tab-orders"></div>
            `;
            
            contentArea.innerHTML = html;
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Reports page initialized');
        ReportsManager.render();
        // Small delay to ensure DOM is fully rendered
        await new Promise(resolve => setTimeout(resolve, 100));
        await ReportsManager.init();
    });

    window.ReportsManager = ReportsManager;
    </script>

    <!-- Generate navigation scripts -->
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================
// ... (keep all the same PHP handler functions from previous response)
// getDashboardSummary, getSalesReport, getCropAnalytics, getFarmerPerformance,
// getBuyerAnalytics, getRegionalInsights, getOrderAnalytics, getDateFilter, etc.

function getDashboardSummary($pdo) {
    try {
        $period = $_GET['period'] ?? 'month';
        $dateFilter = getDateFilter($period, $_GET['start_date'] ?? null, $_GET['end_date'] ?? null);
        
        $data = [];
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') $dateFilter");
        $stmt->execute();
        $data['total_revenue'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE 1=1 $dateFilter");
        $stmt->execute();
        $data['total_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') $dateFilter");
        $stmt->execute();
        $data['total_quantity_sold'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT farmer_id) FROM planting_requests WHERE status IN ('Planted', 'Growing')");
        $data['active_farmers'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT buyer_id) FROM orders WHERE 1=1 $dateFilter");
        $stmt->execute();
        $data['active_buyers'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE listing_status = 'Active'");
        $data['active_listings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
        $prevRevenue = round((float)$stmt->fetchColumn(), 2);
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $currRevenue = round((float)$stmt->fetchColumn(), 2);
        $data['revenue_trend'] = $prevRevenue > 0 ? round((($currRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $prevOrders = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $currOrders = (int)$stmt->fetchColumn();
        $data['orders_trend'] = $prevOrders > 0 ? round((($currOrders - $prevOrders) / $prevOrders) * 100, 1) : 0;
        
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue
            FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b') ORDER BY MIN(created_at)
        ");
        $data['monthly_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT escrow_status, COUNT(*) as count FROM orders GROUP BY escrow_status");
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['order_statuses'] = [];
        foreach ($statuses as $s) { $data['order_statuses'][$s['escrow_status']] = (int)$s['count']; }
        
        $stmt = $pdo->query("
            SELECT c.crop_name, COUNT(o.id) as sales_count, COALESCE(SUM(o.quantity_ordered_kg), 0) as quantity_sold, COALESCE(SUM(o.total_price), 0) as revenue
            FROM crops c JOIN planting_requests pr ON c.id = pr.crop_id JOIN marketplace_items mi ON pr.id = mi.planting_request_id JOIN orders o ON mi.id = o.marketplace_item_id
            GROUP BY c.id, c.crop_name ORDER BY revenue DESC LIMIT 5
        ");
        $data['top_crops'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT o.id as order_id, u.full_name as buyer_name, f.full_name as farmer_name, c.crop_name, o.quantity_ordered_kg, o.total_price, o.escrow_status,
                   DATE_FORMAT(o.transaction_date, '%Y-%m-%d %H:%i') as transaction_date, DATE_FORMAT(o.created_at, '%Y-%m-%d') as order_date
            FROM orders o JOIN users u ON o.buyer_id = u.id JOIN marketplace_items mi ON o.marketplace_item_id = mi.id
            JOIN planting_requests pr ON mi.planting_request_id = pr.id JOIN users f ON pr.farmer_id = f.id JOIN crops c ON pr.crop_id = c.id
            ORDER BY o.created_at DESC LIMIT 10
        ");
        $data['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getSalesReport($pdo) {
    try {
        $period = $_GET['period'] ?? 'month';
        $dateFilter = getDateFilter($period, $_GET['start_date'] ?? null, $_GET['end_date'] ?? null);
        
        $data = [];
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') $dateFilter");
        $stmt->execute();
        $data['total_revenue'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE 1=1 $dateFilter");
        $stmt->execute();
        $data['total_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered') $dateFilter");
        $stmt->execute();
        $data['total_quantity_sold'] = round((float)$stmt->fetchColumn(), 2);
        
        $data['avg_order_value'] = $data['total_orders'] > 0 ? round($data['total_revenue'] / $data['total_orders'], 2) : 0;
        
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%d %b') as period, COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue,
                   COALESCE(SUM(quantity_ordered_kg), 0) as quantity, COALESCE(AVG(total_price), 0) as avg_value
            FROM orders WHERE 1=1 $dateFilter GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d') ORDER BY MIN(created_at)
        ");
        $stmt->execute();
        $data['revenue_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['sales_summary'] = $data['revenue_trends'];
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCropAnalytics($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM crops WHERE is_active = 1");
        $data['total_crops'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests");
        $data['total_plantings'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COALESCE(SUM(land_size_acres), 0) FROM planting_requests");
        $data['total_land'] = round((float)$stmt->fetchColumn(), 2);
        $stmt = $pdo->query("SELECT AVG(baseline_yield_per_acre) FROM crops WHERE is_active = 1");
        $data['avg_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT c.crop_name, COUNT(pr.id) as count FROM crops c LEFT JOIN planting_requests pr ON c.id = pr.crop_id GROUP BY c.id, c.crop_name ORDER BY count DESC LIMIT 8");
        $data['crop_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT c.crop_name, AVG(pr.expected_yield_kg / NULLIF(pr.land_size_acres, 0)) as avg_yield FROM crops c JOIN planting_requests pr ON c.id = pr.crop_id GROUP BY c.id, c.crop_name ORDER BY avg_yield DESC LIMIT 10");
        $data['yield_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT c.crop_name, COUNT(DISTINCT pr.id) as total_plantings, COUNT(DISTINCT pr.farmer_id) as unique_farmers,
                   COALESCE(SUM(pr.land_size_acres), 0) as total_land, AVG(pr.expected_yield_kg / NULLIF(pr.land_size_acres, 0)) as avg_yield,
                   COALESCE(SUM(pr.expected_yield_kg), 0) as total_yield, COUNT(DISTINCT mi.id) as total_listings, COUNT(DISTINCT o.id) as total_sales
            FROM crops c LEFT JOIN planting_requests pr ON c.id = pr.crop_id LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id LEFT JOIN orders o ON mi.id = o.marketplace_item_id
            GROUP BY c.id, c.crop_name ORDER BY total_plantings DESC
        ");
        $data['crop_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getFarmerPerformance($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users u JOIN user_types ut ON u.type_id = ut.id WHERE ut.role_name = 'Farmer' AND u.is_active = 1");
        $data['total_farmers'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests");
        $data['total_plantings'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items");
        $data['total_listings'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COALESCE(SUM(o.total_price), 0) FROM orders o JOIN marketplace_items mi ON o.marketplace_item_id = mi.id JOIN planting_requests pr ON mi.planting_request_id = pr.id");
        $data['total_revenue'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("
            SELECT u.full_name as farmer_name, COUNT(DISTINCT pr.id) as plantings, COALESCE(SUM(pr.land_size_acres), 0) as total_land,
                   COALESCE(SUM(pr.expected_yield_kg), 0) as total_yield, COUNT(DISTINCT mi.id) as listings, COALESCE(SUM(o.total_price), 0) as revenue
            FROM users u JOIN user_types ut ON u.type_id = ut.id LEFT JOIN planting_requests pr ON u.id = pr.farmer_id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id LEFT JOIN orders o ON mi.id = o.marketplace_item_id
            WHERE ut.role_name = 'Farmer' GROUP BY u.id, u.full_name ORDER BY revenue DESC LIMIT 20
        ");
        $data['farmer_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getBuyerAnalytics($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT buyer_id) FROM orders");
        $data['total_buyers'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $data['total_orders'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders");
        $data['total_quantity'] = round((float)$stmt->fetchColumn(), 2);
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders");
        $data['total_spent'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("
            SELECT u.full_name as buyer_name, COUNT(o.id) as total_orders, COALESCE(SUM(o.quantity_ordered_kg), 0) as total_quantity,
                   COALESCE(SUM(o.total_price), 0) as total_spent, AVG(o.total_price) as avg_order_value, DATE_FORMAT(MAX(o.created_at), '%Y-%m-%d') as last_order_date
            FROM users u JOIN orders o ON u.id = o.buyer_id GROUP BY u.id, u.full_name ORDER BY total_spent DESC LIMIT 20
        ");
        $data['top_buyers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getRegionalInsights($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT region_name) FROM planting_requests WHERE region_name IS NOT NULL");
        $data['total_regions'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(DISTINCT farmer_id) FROM planting_requests WHERE region_name IS NOT NULL");
        $data['total_farmers'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COALESCE(SUM(land_size_acres), 0) FROM planting_requests");
        $data['total_land'] = round((float)$stmt->fetchColumn(), 2);
        $stmt = $pdo->query("SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests");
        $data['total_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("
            SELECT pr.region_name, COUNT(DISTINCT pr.farmer_id) as farmers_count, COUNT(pr.id) as plantings_count,
                   COALESCE(SUM(pr.land_size_acres), 0) as total_land, COALESCE(SUM(pr.expected_yield_kg), 0) as expected_yield,
                   GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops_grown
            FROM planting_requests pr JOIN crops c ON pr.crop_id = c.id WHERE pr.region_name IS NOT NULL
            GROUP BY pr.region_name ORDER BY plantings_count DESC
        ");
        $data['regional_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getOrderAnalytics($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $data['total_orders'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'Pending'");
        $data['pending_orders'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'Delivered'");
        $data['delivered_orders'] = (int)$stmt->fetchColumn();
        $data['delivery_rate'] = $data['total_orders'] > 0 ? round(($data['delivered_orders'] / $data['total_orders']) * 100, 1) : 0;
        
        $stmt = $pdo->query("SELECT escrow_status, COUNT(*) as count FROM orders GROUP BY escrow_status");
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['order_statuses'] = [];
        foreach ($statuses as $s) { $data['order_statuses'][$s['escrow_status']] = (int)$s['count']; }
        
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%b %d') as period, COUNT(*) as count
            FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d'), DATE_FORMAT(created_at, '%b %d') ORDER BY MIN(created_at)
        ");
        $data['order_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getDateFilter($period, $startDate, $endDate) {
    if ($startDate && $endDate) {
        return "AND created_at BETWEEN '$startDate' AND '$endDate 23:59:59'";
    }
    switch ($period) {
        case 'week': return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        case 'month': return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        case 'quarter': return "AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        case 'year': return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        default: return '';
    }
}

function exportReport($pdo) {
    echo json_encode(['success' => true, 'message' => 'Export initiated', 'download_url' => '#']);
}

?>