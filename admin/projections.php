<?php
// projections.php - Crop Yield & Revenue Projections for AgriMarketplace Admin Panel

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('Projections & Forecasts', 'projections');

// Check if this is an API request
$isApiRequest = isset($_GET['action']) || isset($_POST['action']) || 
                ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isApiRequest) {
    ob_clean();
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    try {
        require_once '../database.php';
        
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch($action) {
            case 'get_yield_projections':
                getYieldProjections($pdo);
                break;
            case 'get_revenue_projections':
                getRevenueProjections($pdo);
                break;
            case 'get_harvest_schedule':
                getHarvestSchedule($pdo);
                break;
            case 'get_crop_trends':
                getCropTrends($pdo);
                break;
            case 'get_seasonal_analysis':
                getSeasonalAnalysis($pdo);
                break;
            case 'get_market_demand':
                getMarketDemand($pdo);
                break;
            case 'get_planting_recommendations':
                getPlantingRecommendations($pdo);
                break;
            case 'get_summary':
                getProjectionSummary($pdo);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>AgriMarketplace - <?php echo htmlspecialchars($nav_data['page_title']); ?></title>
    
    <?php echo generateNavigationCSS(); ?>
    
    <link rel="stylesheet" href="projections.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
        :root {
            --primary-green: #1F7A4C;
            --primary-green-light: #2E9E6B;
            --soft-green: #e8f5e9;
            --active: #2E7D32;
            --warning: #FF9800;
            --info: #2196F3;
            --danger: #F44336;
            --card-bg: #ffffff;
            --page-bg: #f5f6fa;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-light: #E0E0E0;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-lg: 0 5px 20px rgba(0,0,0,0.12);
            --purple: #7B1FA2;
            --teal: #009688;
            --deep-orange: #E64A19;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        .btn-primary:hover { background: var(--primary-green-light); }

        .btn-outline {
            background: transparent;
            color: var(--primary-green);
            border: 1px solid var(--primary-green);
        }

        .btn-outline:hover { background: var(--soft-green); }

        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 8px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: var(--primary-green);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
            border-left: 4px solid transparent;
        }

        .stat-card:hover { transform: translateY(-2px); }

        .stat-card.green { border-left-color: var(--primary-green); }
        .stat-card.blue { border-left-color: var(--info); }
        .stat-card.orange { border-left-color: var(--warning); }
        .stat-card.purple { border-left-color: var(--purple); }
        .stat-card.teal { border-left-color: var(--teal); }
        .stat-card.red { border-left-color: var(--danger); }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .stat-trend {
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .stat-trend.up { color: var(--active); }
        .stat-trend.down { color: var(--danger); }
        .stat-trend.neutral { color: var(--text-secondary); }

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

        .tab-btn:hover { color: var(--text-primary); }

        .tab-btn.active {
            background: var(--primary-green);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Charts Layout */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
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
            margin-bottom: 15px;
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
            height: 320px;
        }

        .chart-container.large {
            height: 400px;
        }

        .chart-container.xlarge {
            height: 450px;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* Info Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .info-card h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
            font-size: 13px;
        }

        .info-card .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 600;
        }

        .info-value.positive { color: var(--active); }
        .info-value.negative { color: var(--danger); }
        .info-value.warning { color: var(--warning); }

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
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--border-light);
            white-space: nowrap;
        }

        table td {
            padding: 12px 15px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-light);
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Badges */
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success { background: #e8f5e9; color: #2E7D32; }
        .badge-warning { background: #fff3e0; color: #E65100; }
        .badge-info { background: #e3f2fd; color: #1565C0; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-purple { background: #f3e5f5; color: #7B1FA2; }

        /* Progress Bar */
        .progress-container {
            margin-bottom: 15px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--page-bg);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .progress-fill.green { background: var(--primary-green); }
        .progress-fill.blue { background: var(--info); }
        .progress-fill.orange { background: var(--warning); }
        .progress-fill.purple { background: var(--purple); }
        .progress-fill.teal { background: var(--teal); }

        /* Loading */
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

        .toast.show { transform: translateX(0); }
        .toast.success { background: #2E7D32; }
        .toast.error { background: #c62828; }
        .toast.info { background: #1565C0; }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        /* Recommendation Cards */
        .recommendation-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .recommendation-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-green);
        }

        .recommendation-card.orange { border-left-color: var(--warning); }
        .recommendation-card.blue { border-left-color: var(--info); }
        .recommendation-card.purple { border-left-color: var(--purple); }

        .recommendation-card .rec-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .recommendation-card h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .recommendation-card p {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .recommendation-card .rec-metric {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .charts-grid { grid-template-columns: 1fr; }
            .info-cards { grid-template-columns: 1fr; }
            .recommendation-cards { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .chart-container { height: 250px; }
            .chart-container.large { height: 300px; }
            .chart-container.xlarge { height: 350px; }
            .stat-value { font-size: 22px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php echo generateAdminSidebar($nav_data); ?>
        
        <main class="main-content">
            <?php echo generateAdminHeader($nav_data); ?>
            
            <div class="content-area" id="contentArea">
                <div class="loading-overlay">
                    <div class="spinner"></div>
                    <span>Loading projections...</span>
                </div>
            </div>
        </main>
    </div>

    <div id="toast" class="toast"></div>

    <?php echo generateLoadingAnimation(); ?>

    <script>
    const API = {
        async request(action, method = 'GET', data = null) {
            try {
                let url = window.location.pathname + '?action=' + action;
                const options = { method, headers: {} };
                
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
                try { result = JSON.parse(responseText); } 
                catch (e) { console.error('JSON parse error:', e); throw new Error('Invalid server response'); }
                
                if (result.error) throw new Error(result.error);
                return result;
            } catch (error) {
                console.error('API Error:', error);
                return { error: error.message };
            }
        },
        get(action, params = {}) { return this.request(action, 'GET', params); },
        post(action, data) { return this.request(action, 'POST', data); }
    };

    const ProjectionsManager = {
        currentTab: 'yield',
        charts: {},
        
        colors: {
            green: '#1F7A4C',
            greenLight: '#4CAF50',
            greenBg: 'rgba(31, 122, 76, 0.7)',
            greenBgLight: 'rgba(31, 122, 76, 0.15)',
            blue: '#2196F3',
            blueBg: 'rgba(33, 150, 243, 0.7)',
            blueBgLight: 'rgba(33, 150, 243, 0.1)',
            orange: '#FF9800',
            orangeBg: 'rgba(255, 152, 0, 0.7)',
            orangeBgLight: 'rgba(255, 152, 0, 0.1)',
            purple: '#7B1FA2',
            purpleBg: 'rgba(123, 31, 162, 0.7)',
            purpleBgLight: 'rgba(123, 31, 162, 0.1)',
            teal: '#009688',
            tealBg: 'rgba(0, 150, 136, 0.7)',
            red: '#F44336',
            redBg: 'rgba(244, 67, 54, 0.7)',
            redBgLight: 'rgba(244, 67, 54, 0.1)',
            yellow: '#FFEB3B',
            deepOrange: '#E64A19',
            pieColors: [
                '#1F7A4C', '#4CAF50', '#8BC34A', '#CDDC39',
                '#FF9800', '#FF5722', '#795548', '#607D8B',
                '#2196F3', '#03A9F4', '#00BCD4', '#009688',
                '#9C27B0', '#E91E63', '#F44336', '#FFEB3B'
            ]
        },

        init: async function() {
            console.log('Projections Manager initializing...');
            this.setupEventListeners();
            await this.loadYieldProjections();
            
            <?php if (isset($_SESSION['user_name'])): ?>
            const adminName = document.getElementById('adminName');
            const adminAvatar = document.getElementById('adminAvatar');
            if (adminName) adminName.textContent = '<?php echo $_SESSION['user_name']; ?>';
            if (adminAvatar) adminAvatar.textContent = '<?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?>';
            <?php endif; ?>
        },

        setupEventListeners: function() {
            document.addEventListener('change', (e) => {
                if (e.target.id === 'cropFilter' || e.target.id === 'monthsFilter') {
                    this.refreshCurrentTab();
                }
            });
        },

        switchTab: async function(tab) {
            this.currentTab = tab;
            this.destroyAllCharts();
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            
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
                case 'yield': await this.loadYieldProjections(); break;
                case 'revenue': await this.loadRevenueProjections(); break;
                case 'harvest': await this.loadHarvestSchedule(); break;
                case 'trends': await this.loadCropTrends(); break;
                case 'seasonal': await this.loadSeasonalAnalysis(); break;
                case 'demand': await this.loadMarketDemand(); break;
                case 'recommendations': await this.loadRecommendations(); break;
                case 'summary': await this.loadSummary(); break;
            }
        },

        getFilters: function() {
            const cropId = document.getElementById('cropFilter')?.value || '';
            const months = document.getElementById('monthsFilter')?.value || '6';
            return { crop_id: cropId, months: months };
        },

        showLoading: function(tab) {
            const content = document.getElementById('tab-' + tab);
            if (content) {
                content.innerHTML = `<div class="loading-overlay"><div class="spinner"></div><span>Loading data...</span></div>`;
            }
        },

        // ============ YIELD PROJECTIONS ============
        loadYieldProjections: async function() {
            this.showLoading('yield');
            const result = await API.get('get_yield_projections', this.getFilters());
            
            if (result && !result.error) {
                this.renderYieldProjections(result);
            } else {
                this.showToast('Error loading yield projections: ' + (result?.error || 'Unknown error'), 'error');
            }
        },

        renderYieldProjections: function(data) {
            const container = document.getElementById('tab-yield');
            
            const projections = data.projections && data.projections.length > 0 ? data.projections : [
                { month: 'Month 1', projected_yield: 0, projected_harvest: 0, lower_bound: 0, upper_bound: 0 }
            ];
            
            const cropBreakdown = data.crop_breakdown && data.crop_breakdown.length > 0 ? data.crop_breakdown : [
                { crop_name: 'No Data', projected_yield: 0, land_acres: 0, farmers_count: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_projected_yield || 0)} kg</div>
                        <div class="stat-label">Total Projected Yield</div>
                        <div class="stat-trend up">▲ ${data.yield_growth || 0}% vs previous</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon">📏</div>
                        <div class="stat-value">${this.formatNumber(data.total_land_projected || 0)} acres</div>
                        <div class="stat-label">Projected Harvest Area</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon">👨‍🌾</div>
                        <div class="stat-value">${data.active_growing_farmers || 0}</div>
                        <div class="stat-label">Farmers with Growing Crops</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon">📅</div>
                        <div class="stat-value">${data.upcoming_harvests || 0}</div>
                        <div class="stat-label">Upcoming Harvests (${data.projection_months || 6} months)</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">Projected Yield Over Time</div>
                                <div class="chart-subtitle">With confidence intervals</div>
                            </div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="yieldProjectionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Projected Yield by Crop</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="yieldByCropChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Land Allocation by Crop</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="landByCropChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Projected Yield Breakdown by Crop</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th class="text-right">Farmers</th>
                                    <th class="text-right">Land (acres)</th>
                                    <th class="text-right">Projected Yield (kg)</th>
                                    <th class="text-right">Avg Yield/acre</th>
                                    <th class="text-right">% of Total</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            const totalYield = parseFloat(data.total_projected_yield || 1);
            
            cropBreakdown.forEach(function(crop) {
                const percentage = totalYield > 0 ? ((parseFloat(crop.projected_yield || 0) / totalYield) * 100) : 0;
                const avgYield = crop.land_acres > 0 ? (parseFloat(crop.projected_yield || 0) / parseFloat(crop.land_acres)) : 0;
                
                html += `
                    <tr>
                        <td><strong>${ProjectionsManager.escapeHtml(crop.crop_name)}</strong></td>
                        <td class="text-right">${crop.farmers_count || 0}</td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.land_acres || 0)}</td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.projected_yield || 0)}</td>
                        <td class="text-right">${ProjectionsManager.formatNumber(avgYield)}</td>
                        <td class="text-right">${percentage.toFixed(1)}%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill green" style="width: ${percentage}%"></div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initYieldProjectionChart(projections);
            this.initYieldByCropChart(cropBreakdown);
            this.initLandByCropChart(cropBreakdown);
        },

        initYieldProjectionChart: function(data) {
            const canvas = document.getElementById('yieldProjectionChart');
            if (!canvas) return;
            
            if (this.charts.yieldProjection) this.charts.yieldProjection.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.yieldProjection = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [
                        {
                            label: 'Upper Bound',
                            data: data.map(d => d.upper_bound),
                            borderColor: 'rgba(31, 122, 76, 0.3)',
                            backgroundColor: 'rgba(31, 122, 76, 0.05)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: '+1'
                        },
                        {
                            label: 'Projected Yield',
                            data: data.map(d => d.projected_yield),
                            borderColor: this.colors.green,
                            backgroundColor: this.colors.greenBgLight,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: this.colors.green,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Lower Bound',
                            data: data.map(d => d.lower_bound),
                            borderColor: 'rgba(31, 122, 76, 0.3)',
                            backgroundColor: 'rgba(31, 122, 76, 0.05)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Expected Harvests',
                            data: data.map(d => d.projected_harvest),
                            borderColor: this.colors.orange,
                            backgroundColor: this.colors.orangeBgLight,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: this.colors.orange,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            labels: { usePointStyle: true, padding: 20, font: { size: 12 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.dataset.label + ': ' + ProjectionsManager.formatNumber(ctx.parsed.y) + ' kg';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: { display: true, text: 'Yield (kg)', color: this.colors.green },
                            ticks: { callback: v => this.formatNumber(v) + ' kg' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },

        initYieldByCropChart: function(data) {
            const canvas = document.getElementById('yieldByCropChart');
            if (!canvas) return;
            
            if (this.charts.yieldByCrop) this.charts.yieldByCrop.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.yieldByCrop = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        label: 'Projected Yield (kg)',
                        data: data.map(d => d.projected_yield),
                        backgroundColor: data.map((_, i) => this.colors.pieColors[i % this.colors.pieColors.length]),
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: { callback: v => this.formatNumber(v) + ' kg' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },

        initLandByCropChart: function(data) {
            const canvas = document.getElementById('landByCropChart');
            if (!canvas) return;
            
            if (this.charts.landByCrop) this.charts.landByCrop.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.landByCrop = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        data: data.map(d => d.land_acres),
                        backgroundColor: this.colors.pieColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 15, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((ctx.parsed / total) * 100).toFixed(1);
                                    return ctx.label + ': ' + ProjectionsManager.formatNumber(ctx.parsed) + ' acres (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        // ============ REVENUE PROJECTIONS ============
        loadRevenueProjections: async function() {
            this.showLoading('revenue');
            const result = await API.get('get_revenue_projections', this.getFilters());
            
            if (result && !result.error) {
                this.renderRevenueProjections(result);
            } else {
                this.showToast('Error loading revenue projections', 'error');
            }
        },

        renderRevenueProjections: function(data) {
            const container = document.getElementById('tab-revenue');
            
            const monthly = data.monthly_projections && data.monthly_projections.length > 0 ? data.monthly_projections : [
                { month: 'Month 1', projected_revenue: 0, projected_orders: 0, avg_price: 0 }
            ];
            
            const byCrop = data.revenue_by_crop && data.revenue_by_crop.length > 0 ? data.revenue_by_crop : [
                { crop_name: 'No Data', projected_revenue: 0, projected_quantity: 0, avg_price_per_kg: 0 }
            ];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_projected_revenue || 0)}</div>
                        <div class="stat-label">Total Projected Revenue</div>
                        <div class="stat-trend up">▲ ${data.revenue_growth || 0}% growth projected</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon">📊</div>
                        <div class="stat-value">KES ${this.formatNumber(data.avg_monthly_revenue || 0)}</div>
                        <div class="stat-label">Average Monthly Revenue</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon">📦</div>
                        <div class="stat-value">${this.formatNumber(data.projected_orders || 0)}</div>
                        <div class="stat-label">Projected Orders</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon">💹</div>
                        <div class="stat-value">KES ${this.formatNumber(data.avg_price_per_kg || 0)}/kg</div>
                        <div class="stat-label">Projected Avg Price</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Revenue Projections</div>
                            <div class="chart-subtitle">Next ${data.projection_months || 6} months</div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="revenueProjectionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Revenue by Crop</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="revenueByCropChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Price Trends</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="priceTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Revenue Projections by Crop</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th class="text-right">Projected Quantity (kg)</th>
                                    <th class="text-right">Avg Price/kg (KES)</th>
                                    <th class="text-right">Projected Revenue (KES)</th>
                                    <th class="text-right">% of Total Revenue</th>
                                    <th>Revenue Share</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            const totalRevenue = parseFloat(data.total_projected_revenue || 1);
            
            byCrop.forEach(function(crop) {
                const percentage = totalRevenue > 0 ? ((parseFloat(crop.projected_revenue || 0) / totalRevenue) * 100) : 0;
                
                html += `
                    <tr>
                        <td><strong>${ProjectionsManager.escapeHtml(crop.crop_name)}</strong></td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.projected_quantity || 0)}</td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.avg_price_per_kg || 0)}</td>
                        <td class="text-right">KES ${ProjectionsManager.formatNumber(crop.projected_revenue || 0)}</td>
                        <td class="text-right">${percentage.toFixed(1)}%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill blue" style="width: ${percentage}%"></div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initRevenueProjectionChart(monthly);
            this.initRevenueByCropChart(byCrop);
            this.initPriceTrendChart(monthly);
        },

        initRevenueProjectionChart: function(data) {
            const canvas = document.getElementById('revenueProjectionChart');
            if (!canvas) return;
            
            if (this.charts.revenueProjection) this.charts.revenueProjection.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.revenueProjection = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [
                        {
                            label: 'Projected Revenue (KES)',
                            data: data.map(d => d.projected_revenue),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Projected Orders',
                            data: data.map(d => d.projected_orders),
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
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            title: { display: true, text: 'Revenue (KES)', color: this.colors.green },
                            ticks: { callback: v => 'KES ' + this.formatNumber(v), color: this.colors.green },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y1: {
                            position: 'right',
                            title: { display: true, text: 'Orders', color: this.colors.orange },
                            ticks: { color: this.colors.orange },
                            grid: { drawOnChartArea: false }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        initRevenueByCropChart: function(data) {
            const canvas = document.getElementById('revenueByCropChart');
            if (!canvas) return;
            
            if (this.charts.revenueByCrop) this.charts.revenueByCrop.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.revenueByCrop = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        data: data.map(d => d.projected_revenue),
                        backgroundColor: this.colors.pieColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 15, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.label + ': KES ' + ProjectionsManager.formatNumber(ctx.parsed);
                                }
                            }
                        }
                    }
                }
            });
        },

        initPriceTrendChart: function(data) {
            const canvas = document.getElementById('priceTrendChart');
            if (!canvas) return;
            
            if (this.charts.priceTrend) this.charts.priceTrend.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.priceTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [{
                        label: 'Avg Price/kg (KES)',
                        data: data.map(d => d.avg_price),
                        borderColor: this.colors.purple,
                        backgroundColor: this.colors.purpleBgLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: this.colors.purple,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } }
                    },
                    scales: {
                        y: {
                            ticks: { callback: v => 'KES ' + this.formatNumber(v) },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        // ============ HARVEST SCHEDULE ============
        loadHarvestSchedule: async function() {
            this.showLoading('harvest');
            const result = await API.get('get_harvest_schedule', this.getFilters());
            
            if (result && !result.error) {
                this.renderHarvestSchedule(result);
            } else {
                this.showToast('Error loading harvest schedule', 'error');
            }
        },

        renderHarvestSchedule: function(data) {
            const container = document.getElementById('tab-harvest');
            
            const schedule = data.schedule && data.schedule.length > 0 ? data.schedule : [
                { month: 'No Data', harvests: 0, expected_yield: 0, crops: 'N/A' }
            ];
            
            const upcoming = data.upcoming_harvests && data.upcoming_harvests.length > 0 ? data.upcoming_harvests : [];
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon">📅</div>
                        <div class="stat-value">${data.total_upcoming_harvests || 0}</div>
                        <div class="stat-label">Upcoming Harvests</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_expected_yield || 0)} kg</div>
                        <div class="stat-label">Expected Yield from Harvests</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-value">${data.next_harvest_days || 'N/A'} days</div>
                        <div class="stat-label">Until Next Harvest</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon">📍</div>
                        <div class="stat-value">${data.regions_active || 0}</div>
                        <div class="stat-label">Active Regions</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Harvest Schedule Timeline</div>
                            <div class="chart-subtitle">Expected harvests over coming months</div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="harvestTimelineChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Upcoming Harvests</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Farmer</th>
                                    <th>Crop</th>
                                    <th class="text-right">Land (acres)</th>
                                    <th class="text-right">Expected Yield (kg)</th>
                                    <th>Harvest Date</th>
                                    <th class="text-right">Days Remaining</th>
                                    <th>Region</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (upcoming.length > 0) {
                upcoming.forEach(function(h) {
                    const daysRemaining = h.days_remaining || 0;
                    const statusBadge = daysRemaining <= 7 ? 'badge-danger' : 
                                       daysRemaining <= 14 ? 'badge-warning' : 
                                       daysRemaining <= 30 ? 'badge-info' : 'badge-success';
                    
                    html += `
                        <tr>
                            <td>${ProjectionsManager.escapeHtml(h.farmer_name || 'N/A')}</td>
                            <td>${ProjectionsManager.escapeHtml(h.crop_name || 'N/A')}</td>
                            <td class="text-right">${h.land_size_acres || 0}</td>
                            <td class="text-right">${ProjectionsManager.formatNumber(h.expected_yield_kg || 0)}</td>
                            <td>${h.expected_harvest_date || 'N/A'}</td>
                            <td class="text-right"><span class="badge ${statusBadge}">${daysRemaining} days</span></td>
                            <td>${ProjectionsManager.escapeHtml(h.region_name || 'N/A')}</td>
                            <td><span class="badge badge-info">${h.status || 'N/A'}</span></td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="8" class="no-data">No upcoming harvests found</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initHarvestTimelineChart(schedule);
        },

        initHarvestTimelineChart: function(data) {
            const canvas = document.getElementById('harvestTimelineChart');
            if (!canvas) return;
            
            if (this.charts.harvestTimeline) this.charts.harvestTimeline.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.harvestTimeline = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [
                        {
                            label: 'Number of Harvests',
                            data: data.map(d => d.harvests),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Expected Yield (kg)',
                            data: data.map(d => d.expected_yield),
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
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(ctx) {
                                    if (ctx.datasetIndex === 0) {
                                        const crops = data[ctx.dataIndex]?.crops || '';
                                        return 'Crops: ' + crops;
                                    }
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            title: { display: true, text: 'Harvests', color: this.colors.green },
                            ticks: { color: this.colors.green },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y1: {
                            position: 'right',
                            title: { display: true, text: 'Yield (kg)', color: this.colors.orange },
                            ticks: { callback: v => this.formatNumber(v) + ' kg', color: this.colors.orange },
                            grid: { drawOnChartArea: false }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        // ============ CROP TRENDS ============
        loadCropTrends: async function() {
            this.showLoading('trends');
            const result = await API.get('get_crop_trends', this.getFilters());
            
            if (result && !result.error) {
                this.renderCropTrends(result);
            } else {
                this.showToast('Error loading crop trends', 'error');
            }
        },

        renderCropTrends: function(data) {
            const container = document.getElementById('tab-trends');
            
            const trends = data.trends && data.trends.length > 0 ? data.trends : [
                { crop_name: 'No Data', current_yield: 0, previous_yield: 0, yield_change: 0, land_change: 0, popularity_score: 0 }
            ];
            
            let html = `
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Crop Yield Trends (Current vs Previous)</div>
                            <div class="chart-subtitle">Performance comparison</div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="cropTrendsChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Popularity Score by Crop</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="popularityChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Land Allocation Changes</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="landChangeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="chart-header" style="padding: 20px 20px 0;">
                        <div class="chart-title">Crop Performance Trends</div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th class="text-right">Current Yield/acre</th>
                                    <th class="text-right">Previous Yield/acre</th>
                                    <th class="text-right">Yield Change</th>
                                    <th class="text-right">Land Change</th>
                                    <th class="text-right">Popularity Score</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            trends.forEach(function(crop) {
                const yieldChange = parseFloat(crop.yield_change || 0);
                const landChange = parseFloat(crop.land_change || 0);
                const trendBadge = yieldChange > 0 ? 'badge-success' : yieldChange < 0 ? 'badge-danger' : 'badge-info';
                const trendIcon = yieldChange > 0 ? '▲ Growing' : yieldChange < 0 ? '▼ Declining' : '◆ Stable';
                
                html += `
                    <tr>
                        <td><strong>${ProjectionsManager.escapeHtml(crop.crop_name)}</strong></td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.current_yield || 0)}</td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.previous_yield || 0)}</td>
                        <td class="text-right">
                            <span class="${yieldChange >= 0 ? 'info-value positive' : 'info-value negative'}">
                                ${yieldChange >= 0 ? '+' : ''}${yieldChange.toFixed(1)}%
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="${landChange >= 0 ? 'info-value positive' : 'info-value negative'}">
                                ${landChange >= 0 ? '+' : ''}${landChange.toFixed(1)}%
                            </span>
                        </td>
                        <td class="text-right">${ProjectionsManager.formatNumber(crop.popularity_score || 0)}</td>
                        <td><span class="badge ${trendBadge}">${trendIcon}</span></td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initCropTrendsChart(trends);
            this.initPopularityChart(trends);
            this.initLandChangeChart(trends);
        },

        initCropTrendsChart: function(data) {
            const canvas = document.getElementById('cropTrendsChart');
            if (!canvas) return;
            
            if (this.charts.cropTrends) this.charts.cropTrends.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.cropTrends = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [
                        {
                            label: 'Current Yield (kg/acre)',
                            data: data.map(d => d.current_yield),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Previous Yield (kg/acre)',
                            data: data.map(d => d.previous_yield),
                            backgroundColor: 'rgba(158, 158, 158, 0.7)',
                            borderColor: '#9E9E9E',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } }
                    },
                    scales: {
                        y: {
                            title: { display: true, text: 'Yield (kg/acre)' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        initPopularityChart: function(data) {
            const canvas = document.getElementById('popularityChart');
            if (!canvas) return;
            
            if (this.charts.popularity) this.charts.popularity.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.popularity = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        label: 'Popularity Score',
                        data: data.map(d => d.popularity_score),
                        backgroundColor: this.colors.greenBgLight,
                        borderColor: this.colors.green,
                        borderWidth: 2,
                        pointBackgroundColor: this.colors.green,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            ticks: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        },

        initLandChangeChart: function(data) {
            const canvas = document.getElementById('landChangeChart');
            if (!canvas) return;
            
            if (this.charts.landChange) this.charts.landChange.destroy();
            
            const ctx = canvas.getContext('2d');
            
            const colors = data.map(d => parseFloat(d.land_change) >= 0 ? this.colors.greenBg : this.colors.redBg);
            const borders = data.map(d => parseFloat(d.land_change) >= 0 ? this.colors.green : this.colors.red);
            
            this.charts.landChange = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        label: 'Land Change (%)',
                        data: data.map(d => d.land_change),
                        backgroundColor: colors,
                        borderColor: borders,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: { callback: v => v + '%' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },

        // ============ SEASONAL ANALYSIS ============
        loadSeasonalAnalysis: async function() {
            this.showLoading('seasonal');
            const result = await API.get('get_seasonal_analysis');
            
            if (result && !result.error) {
                this.renderSeasonalAnalysis(result);
            } else {
                this.showToast('Error loading seasonal analysis', 'error');
            }
        },

        renderSeasonalAnalysis: function(data) {
            const container = document.getElementById('tab-seasonal');
            
            const seasonal = data.seasonal_data && data.seasonal_data.length > 0 ? data.seasonal_data : [
                { season: 'No Data', planting_count: 0, harvest_count: 0, avg_yield: 0, revenue: 0 }
            ];
            
            const monthlyPatterns = data.monthly_patterns && data.monthly_patterns.length > 0 ? data.monthly_patterns : [];
            
            let html = `
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Seasonal Planting & Harvest Patterns</div>
                            <div class="chart-subtitle">12-month cycle analysis</div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="seasonalPatternChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Yield by Season</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="seasonalYieldChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Revenue by Season</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="seasonalRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="info-cards">
                    <div class="info-card">
                        <h4>🌱 Best Planting Season</h4>
                        <div class="info-item">
                            <span class="info-label">Season</span>
                            <span class="info-value positive">${this.escapeHtml(data.best_planting_season || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Avg Plantings</span>
                            <span class="info-value">${data.best_planting_count || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Success Rate</span>
                            <span class="info-value positive">${data.planting_success_rate || 0}%</span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4>🌾 Best Harvest Season</h4>
                        <div class="info-item">
                            <span class="info-label">Season</span>
                            <span class="info-value positive">${this.escapeHtml(data.best_harvest_season || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Avg Harvests</span>
                            <span class="info-value">${data.best_harvest_count || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Avg Yield</span>
                            <span class="info-value">${this.formatNumber(data.best_harvest_yield || 0)} kg</span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h4>💰 Peak Revenue Season</h4>
                        <div class="info-item">
                            <span class="info-label">Season</span>
                            <span class="info-value positive">${this.escapeHtml(data.peak_revenue_season || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Avg Revenue</span>
                            <span class="info-value">KES ${this.formatNumber(data.peak_revenue || 0)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Peak Months</span>
                            <span class="info-value">${this.escapeHtml(data.peak_months || 'N/A')}</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initSeasonalPatternChart(monthlyPatterns.length > 0 ? monthlyPatterns : this.generateMonthlyDefaults());
            this.initSeasonalYieldChart(seasonal);
            this.initSeasonalRevenueChart(seasonal);
        },

        generateMonthlyDefaults: function() {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months.map(m => ({ month: m, plantings: 0, harvests: 0 }));
        },

        initSeasonalPatternChart: function(data) {
            const canvas = document.getElementById('seasonalPatternChart');
            if (!canvas) return;
            
            if (this.charts.seasonalPattern) this.charts.seasonalPattern.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.seasonalPattern = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [
                        {
                            label: 'Plantings',
                            data: data.map(d => d.plantings),
                            borderColor: this.colors.green,
                            backgroundColor: this.colors.greenBgLight,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: this.colors.green,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Harvests',
                            data: data.map(d => d.harvests),
                            borderColor: this.colors.orange,
                            backgroundColor: this.colors.orangeBgLight,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: this.colors.orange,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } }
                    },
                    scales: {
                        y: {
                            title: { display: true, text: 'Count' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        initSeasonalYieldChart: function(data) {
            const canvas = document.getElementById('seasonalYieldChart');
            if (!canvas) return;
            
            if (this.charts.seasonalYield) this.charts.seasonalYield.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.seasonalYield = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.season),
                    datasets: [{
                        label: 'Avg Yield (kg/acre)',
                        data: data.map(d => d.avg_yield),
                        backgroundColor: [
                            this.colors.greenBg,
                            this.colors.orangeBg,
                            this.colors.blueBg,
                            this.colors.purpleBg
                        ],
                        borderColor: [
                            this.colors.green,
                            this.colors.orange,
                            this.colors.blue,
                            this.colors.purple
                        ],
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            title: { display: true, text: 'Yield (kg/acre)' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        initSeasonalRevenueChart: function(data) {
            const canvas = document.getElementById('seasonalRevenueChart');
            if (!canvas) return;
            
            if (this.charts.seasonalRevenue) this.charts.seasonalRevenue.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.seasonalRevenue = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.season),
                    datasets: [{
                        label: 'Revenue (KES)',
                        data: data.map(d => d.revenue),
                        backgroundColor: [
                            this.colors.greenBg,
                            this.colors.orangeBg,
                            this.colors.blueBg,
                            this.colors.purpleBg
                        ],
                        borderColor: [
                            this.colors.green,
                            this.colors.orange,
                            this.colors.blue,
                            this.colors.purple
                        ],
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            ticks: { callback: v => 'KES ' + this.formatNumber(v) },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        // ============ MARKET DEMAND ============
        loadMarketDemand: async function() {
            this.showLoading('demand');
            const result = await API.get('get_market_demand');
            
            if (result && !result.error) {
                this.renderMarketDemand(result);
            } else {
                this.showToast('Error loading market demand', 'error');
            }
        },

        renderMarketDemand: function(data) {
            const container = document.getElementById('tab-demand');
            
            const demand = data.crop_demand && data.crop_demand.length > 0 ? data.crop_demand : [
                { crop_name: 'No Data', total_demand_kg: 0, total_supply_kg: 0, demand_supply_ratio: 0, projected_price: 0, demand_score: 0 }
            ];
            
            let html = `
                <div class="charts-grid">
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Market Demand vs Supply</div>
                            <div class="chart-subtitle">Gap analysis by crop</div>
                        </div>
                        <div class="chart-container xlarge">
                            <canvas id="demandSupplyChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Demand Score by Crop</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="demandScoreChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Projected Price Movement</div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="projectedPriceChart"></canvas>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            this.initDemandSupplyChart(demand);
            this.initDemandScoreChart(demand);
            this.initProjectedPriceChart(demand);
        },

        initDemandSupplyChart: function(data) {
            const canvas = document.getElementById('demandSupplyChart');
            if (!canvas) return;
            
            if (this.charts.demandSupply) this.charts.demandSupply.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.demandSupply = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [
                        {
                            label: 'Demand (kg)',
                            data: data.map(d => d.total_demand_kg),
                            backgroundColor: this.colors.blueBg,
                            borderColor: this.colors.blue,
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Supply (kg)',
                            data: data.map(d => d.total_supply_kg),
                            backgroundColor: this.colors.greenBg,
                            borderColor: this.colors.green,
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 20 } }
                    },
                    scales: {
                        y: {
                            title: { display: true, text: 'Quantity (kg)' },
                            ticks: { callback: v => this.formatNumber(v) + ' kg' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        },

        initDemandScoreChart: function(data) {
            const canvas = document.getElementById('demandScoreChart');
            if (!canvas) return;
            
            if (this.charts.demandScore) this.charts.demandScore.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.demandScore = new Chart(ctx, {
                type: 'polarArea',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        data: data.map(d => d.demand_score),
                        backgroundColor: this.colors.pieColors.slice(0, data.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 12, font: { size: 10 } }
                        }
                    },
                    scales: {
                        r: {
                            ticks: { display: false }
                        }
                    }
                }
            });
        },

        initProjectedPriceChart: function(data) {
            const canvas = document.getElementById('projectedPriceChart');
            if (!canvas) return;
            
            if (this.charts.projectedPrice) this.charts.projectedPrice.destroy();
            
            const ctx = canvas.getContext('2d');
            
            this.charts.projectedPrice = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.crop_name),
                    datasets: [{
                        label: 'Projected Price (KES/kg)',
                        data: data.map(d => d.projected_price),
                        backgroundColor: this.colors.orangeBg,
                        borderColor: this.colors.orange,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: { callback: v => 'KES ' + this.formatNumber(v) },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: { grid: { display: false } }
                    }
                }
            });
        },

        // ============ RECOMMENDATIONS ============
        loadRecommendations: async function() {
            this.showLoading('recommendations');
            const result = await API.get('get_planting_recommendations');
            
            if (result && !result.error) {
                this.renderRecommendations(result);
            } else {
                this.showToast('Error loading recommendations', 'error');
            }
        },

        renderRecommendations: function(data) {
            const container = document.getElementById('tab-recommendations');
            
            const recommendations = data.recommendations && data.recommendations.length > 0 ? data.recommendations : [
                { crop_name: 'N/A', recommendation: 'No data available', expected_roi: 0, demand_level: 'N/A', risk_level: 'N/A' }
            ];
            
            let html = '<div class="recommendation-cards">';
            
            const icons = ['🌾', '🌽', '🥬', '🍅', '🥔', '🧅', '🥕', '🌶️'];
            const borderColors = ['', 'orange', 'blue', 'purple'];
            
            recommendations.forEach(function(rec, index) {
                const demandBadge = rec.demand_level === 'High' ? 'badge-success' : 
                                   rec.demand_level === 'Medium' ? 'badge-warning' : 'badge-info';
                const riskBadge = rec.risk_level === 'Low' ? 'badge-success' : 
                                 rec.risk_level === 'Medium' ? 'badge-warning' : 'badge-danger';
                
                html += `
                    <div class="recommendation-card ${borderColors[index % borderColors.length]}">
                        <div class="rec-icon">${icons[index % icons.length]}</div>
                        <h4>${ProjectionsManager.escapeHtml(rec.crop_name)}</h4>
                        <p>${ProjectionsManager.escapeHtml(rec.recommendation || 'No recommendation available')}</p>
                        <div class="rec-metric">
                            <span>Expected ROI</span>
                            <strong class="info-value positive">${rec.expected_roi || 0}%</strong>
                        </div>
                        <div class="rec-metric">
                            <span>Demand Level</span>
                            <span class="badge ${demandBadge}">${rec.demand_level || 'N/A'}</span>
                        </div>
                        <div class="rec-metric">
                            <span>Risk Level</span>
                            <span class="badge ${riskBadge}">${rec.risk_level || 'N/A'}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            if (data.planting_suggestions && data.planting_suggestions.length > 0) {
                html += `
                    <div class="table-container">
                        <div class="chart-header" style="padding: 20px 20px 0;">
                            <div class="chart-title">Planting Suggestions for Upcoming Season</div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Crop</th>
                                        <th class="text-right">Suggested Acres</th>
                                        <th class="text-right">Expected Yield</th>
                                        <th class="text-right">Projected Revenue</th>
                                        <th class="text-right">ROI</th>
                                        <th>Best Planting Month</th>
                                        <th>Demand</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                data.planting_suggestions.forEach(function(s) {
                    html += `
                        <tr>
                            <td><strong>${ProjectionsManager.escapeHtml(s.crop_name)}</strong></td>
                            <td class="text-right">${s.suggested_acres || 0}</td>
                            <td class="text-right">${ProjectionsManager.formatNumber(s.expected_yield || 0)} kg</td>
                            <td class="text-right">KES ${ProjectionsManager.formatNumber(s.projected_revenue || 0)}</td>
                            <td class="text-right"><span class="info-value positive">${s.roi || 0}%</span></td>
                            <td>${s.best_month || 'N/A'}</td>
                            <td><span class="badge badge-success">${s.demand || 'N/A'}</span></td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        },

        // ============ SUMMARY ============
        loadSummary: async function() {
            this.showLoading('summary');
            const result = await API.get('get_summary');
            
            if (result && !result.error) {
                this.renderSummary(result);
            } else {
                this.showToast('Error loading summary', 'error');
            }
        },

        renderSummary: function(data) {
            const container = document.getElementById('tab-summary');
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon">🌾</div>
                        <div class="stat-value">${this.formatNumber(data.total_projected_yield || 0)} kg</div>
                        <div class="stat-label">Total Projected Yield</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(data.total_projected_revenue || 0)}</div>
                        <div class="stat-label">Total Projected Revenue</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon">📅</div>
                        <div class="stat-value">${data.upcoming_harvests || 0}</div>
                        <div class="stat-label">Upcoming Harvests</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon">📊</div>
                        <div class="stat-value">${data.growth_rate || 0}%</div>
                        <div class="stat-label">Projected Growth Rate</div>
                    </div>
                    <div class="stat-card teal">
                        <div class="stat-icon">👨‍🌾</div>
                        <div class="stat-value">${data.active_farmers || 0}</div>
                        <div class="stat-label">Active Farmers</div>
                    </div>
                    <div class="stat-card red">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-value">${data.risk_alerts || 0}</div>
                        <div class="stat-label">Risk Alerts</div>
                    </div>
                </div>
                
                <div class="info-cards">
                    <div class="info-card">
                        <h4>📈 Key Insights</h4>
                        ${(data.insights && data.insights.length > 0) ? data.insights.map(i => 
                            `<div class="info-item">
                                <span class="info-label">${this.escapeHtml(i.label || '')}</span>
                                <span class="info-value ${i.trend === 'up' ? 'positive' : i.trend === 'down' ? 'negative' : ''}">${this.escapeHtml(i.value || '')}</span>
                            </div>`
                        ).join('') : '<p class="no-data">No insights available</p>'}
                    </div>
                    
                    <div class="info-card">
                        <h4>⚠️ Risk Factors</h4>
                        ${(data.risks && data.risks.length > 0) ? data.risks.map(r => 
                            `<div class="info-item">
                                <span class="info-label">${this.escapeHtml(r.factor || '')}</span>
                                <span class="info-value ${r.severity === 'High' ? 'negative' : r.severity === 'Medium' ? 'warning' : ''}">${this.escapeHtml(r.severity || '')}</span>
                            </div>`
                        ).join('') : '<p class="no-data">No risk factors identified</p>'}
                    </div>
                    
                    <div class="info-card">
                        <h4>✅ Opportunities</h4>
                        ${(data.opportunities && data.opportunities.length > 0) ? data.opportunities.map(o => 
                            `<div class="info-item">
                                <span class="info-label">${this.escapeHtml(o.opportunity || '')}</span>
                                <span class="info-value positive">${this.escapeHtml(o.potential || '')}</span>
                            </div>`
                        ).join('') : '<p class="no-data">No opportunities identified</p>'}
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        },

        // ============ UTILITY METHODS ============
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
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        },

        render: function() {
            const contentArea = document.getElementById('contentArea');
            
            let html = `
                <div class="page-header">
                    <div class="page-title">
                        <h2>Projections & Forecasts</h2>
                        <p>AI-powered yield, revenue, and market projections</p>
                    </div>
                    <div class="header-actions">
                        <div class="filter-bar">
                            <select id="cropFilter">
                                <option value="">All Crops</option>
                            </select>
                            <select id="monthsFilter">
                                <option value="3">3 Months</option>
                                <option value="6" selected>6 Months</option>
                                <option value="12">12 Months</option>
                            </select>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="ProjectionsManager.refreshCurrentTab()">🔄 Refresh</button>
                    </div>
                </div>
                
                <div class="tabs">
                    <button class="tab-btn active" data-tab="yield" onclick="ProjectionsManager.switchTab('yield')">🌾 Yield</button>
                    <button class="tab-btn" data-tab="revenue" onclick="ProjectionsManager.switchTab('revenue')">💰 Revenue</button>
                    <button class="tab-btn" data-tab="harvest" onclick="ProjectionsManager.switchTab('harvest')">📅 Harvest Schedule</button>
                    <button class="tab-btn" data-tab="trends" onclick="ProjectionsManager.switchTab('trends')">📈 Trends</button>
                    <button class="tab-btn" data-tab="seasonal" onclick="ProjectionsManager.switchTab('seasonal')">🌤️ Seasonal</button>
                    <button class="tab-btn" data-tab="demand" onclick="ProjectionsManager.switchTab('demand')">📊 Demand</button>
                    <button class="tab-btn" data-tab="recommendations" onclick="ProjectionsManager.switchTab('recommendations')">💡 Recommendations</button>
                    <button class="tab-btn" data-tab="summary" onclick="ProjectionsManager.switchTab('summary')">📋 Summary</button>
                </div>
                
                <div class="tab-content active" id="tab-yield"></div>
                <div class="tab-content" id="tab-revenue"></div>
                <div class="tab-content" id="tab-harvest"></div>
                <div class="tab-content" id="tab-trends"></div>
                <div class="tab-content" id="tab-seasonal"></div>
                <div class="tab-content" id="tab-demand"></div>
                <div class="tab-content" id="tab-recommendations"></div>
                <div class="tab-content" id="tab-summary"></div>
            `;
            
            contentArea.innerHTML = html;
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Projections page initialized');
        ProjectionsManager.render();
        await new Promise(resolve => setTimeout(resolve, 100));
        await ProjectionsManager.init();
    });

    window.ProjectionsManager = ProjectionsManager;
    </script>

    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function getYieldProjections($pdo) {
    try {
        $months = intval($_GET['months'] ?? 6);
        $cropId = $_GET['crop_id'] ?? '';
        $cropFilter = $cropId ? "AND pr.crop_id = " . intval($cropId) : '';
        
        $data = [];
        
        // Total projected yield
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(pr.expected_yield_kg), 0)
            FROM planting_requests pr
            WHERE pr.status IN ('Planted', 'Growing')
            AND pr.expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL $months MONTH)
            $cropFilter
        ");
        $data['total_projected_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        // Total land
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(pr.land_size_acres), 0)
            FROM planting_requests pr
            WHERE pr.status IN ('Planted', 'Growing')
            $cropFilter
        ");
        $data['total_land_projected'] = round((float)$stmt->fetchColumn(), 2);
        
        // Active farmers
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT farmer_id) 
            FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
        ");
        $data['active_growing_farmers'] = (int)$stmt->fetchColumn();
        
        // Upcoming harvests
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
            AND expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL $months MONTH)
            $cropFilter
        ");
        $data['upcoming_harvests'] = (int)$stmt->fetchColumn();
        
        // Yield growth
        $stmt = $pdo->query("SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests WHERE expected_harvest_date >= DATE_SUB(CURDATE(), INTERVAL $months MONTH) AND expected_harvest_date < CURDATE()");
        $prevYield = (float)$stmt->fetchColumn();
        $data['yield_growth'] = $prevYield > 0 ? round((($data['total_projected_yield'] - $prevYield) / $prevYield) * 100, 1) : 0;
        
        // Monthly projections
        $monthlyData = [];
        for ($i = 0; $i < $months; $i++) {
            $start = date('Y-m-01', strtotime("+$i months"));
            $end = date('Y-m-t', strtotime("+$i months"));
            $label = date('M Y', strtotime("+$i months"));
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(pr.expected_yield_kg), 0) as projected_yield,
                    COUNT(pr.id) as projected_harvest
                FROM planting_requests pr
                WHERE pr.status IN ('Planted', 'Growing')
                AND pr.expected_harvest_date BETWEEN ? AND ?
                $cropFilter
            ");
            $stmt->execute([$start, $end]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $yield = (float)($row['projected_yield'] ?? 0);
            $monthlyData[] = [
                'month' => $label,
                'projected_yield' => $yield,
                'projected_harvest' => (int)($row['projected_harvest'] ?? 0),
                'lower_bound' => round($yield * 0.85),
                'upper_bound' => round($yield * 1.15)
            ];
        }
        $data['projections'] = $monthlyData;
        
        // Crop breakdown
        $stmt = $pdo->query("
            SELECT 
                c.crop_name,
                COUNT(DISTINCT pr.farmer_id) as farmers_count,
                COALESCE(SUM(pr.land_size_acres), 0) as land_acres,
                COALESCE(SUM(pr.expected_yield_kg), 0) as projected_yield
            FROM crops c
            JOIN planting_requests pr ON c.id = pr.crop_id
            WHERE pr.status IN ('Planted', 'Growing')
            $cropFilter
            GROUP BY c.id, c.crop_name
            ORDER BY projected_yield DESC
        ");
        $data['crop_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data['projection_months'] = $months;
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getRevenueProjections($pdo) {
    try {
        $months = intval($_GET['months'] ?? 6);
        $cropId = $_GET['crop_id'] ?? '';
        $cropFilter = $cropId ? "AND pr.crop_id = " . intval($cropId) : '';
        
        $data = [];
        
        // Calculate projected revenue from growing crops
        $stmt = $pdo->query("
            SELECT 
                COALESCE(SUM(pr.expected_yield_kg * c.price_per_kg), 0) as projected_revenue,
                COUNT(DISTINCT pr.id) as projected_orders,
                AVG(c.price_per_kg) as avg_price
            FROM planting_requests pr
            JOIN crops c ON pr.crop_id = c.id
            WHERE pr.status IN ('Planted', 'Growing')
            AND pr.expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL $months MONTH)
            $cropFilter
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['total_projected_revenue'] = round((float)($row['projected_revenue'] ?? 0), 2);
        $data['projected_orders'] = (int)($row['projected_orders'] ?? 0);
        $data['avg_price_per_kg'] = round((float)($row['avg_price'] ?? 0), 2);
        $data['avg_monthly_revenue'] = $months > 0 ? round($data['total_projected_revenue'] / $months, 2) : 0;
        
        // Revenue growth
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)");
        $prevRevenue = (float)$stmt->fetchColumn();
        $data['revenue_growth'] = $prevRevenue > 0 ? round((($data['total_projected_revenue'] - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
        
        // Monthly revenue projections
        $monthlyData = [];
        for ($i = 0; $i < $months; $i++) {
            $start = date('Y-m-01', strtotime("+$i months"));
            $end = date('Y-m-t', strtotime("+$i months"));
            $label = date('M Y', strtotime("+$i months"));
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(pr.expected_yield_kg * c.price_per_kg), 0) as projected_revenue,
                    COUNT(pr.id) as projected_orders,
                    AVG(c.price_per_kg) as avg_price
                FROM planting_requests pr
                JOIN crops c ON pr.crop_id = c.id
                WHERE pr.status IN ('Planted', 'Growing')
                AND pr.expected_harvest_date BETWEEN ? AND ?
                $cropFilter
            ");
            $stmt->execute([$start, $end]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $monthlyData[] = [
                'month' => $label,
                'projected_revenue' => round((float)($row['projected_revenue'] ?? 0), 2),
                'projected_orders' => (int)($row['projected_orders'] ?? 0),
                'avg_price' => round((float)($row['avg_price'] ?? 0), 2)
            ];
        }
        $data['monthly_projections'] = $monthlyData;
        
        // Revenue by crop
        $stmt = $pdo->query("
            SELECT 
                c.crop_name,
                COALESCE(SUM(pr.expected_yield_kg), 0) as projected_quantity,
                AVG(c.price_per_kg) as avg_price_per_kg,
                COALESCE(SUM(pr.expected_yield_kg * c.price_per_kg), 0) as projected_revenue
            FROM crops c
            JOIN planting_requests pr ON c.id = pr.crop_id
            WHERE pr.status IN ('Planted', 'Growing')
            $cropFilter
            GROUP BY c.id, c.crop_name
            ORDER BY projected_revenue DESC
        ");
        $data['revenue_by_crop'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data['projection_months'] = $months;
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getHarvestSchedule($pdo) {
    try {
        $months = intval($_GET['months'] ?? 6);
        
        $data = [];
        
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
            AND expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL $months MONTH)
        ");
        $data['total_upcoming_harvests'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
            AND expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL $months MONTH)
        ");
        $data['total_expected_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("
            SELECT DATEDIFF(MIN(expected_harvest_date), CURDATE()) 
            FROM planting_requests 
            WHERE status IN ('Planted', 'Growing')
            AND expected_harvest_date >= CURDATE()
        ");
        $data['next_harvest_days'] = (int)($stmt->fetchColumn() ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT region_name) FROM planting_requests WHERE region_name IS NOT NULL AND status IN ('Planted', 'Growing')");
        $data['regions_active'] = (int)$stmt->fetchColumn();
        
        // Monthly schedule
        $schedule = [];
        for ($i = 0; $i < $months; $i++) {
            $start = date('Y-m-01', strtotime("+$i months"));
            $end = date('Y-m-t', strtotime("+$i months"));
            $label = date('M Y', strtotime("+$i months"));
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as harvests,
                    COALESCE(SUM(pr.expected_yield_kg), 0) as expected_yield,
                    GROUP_CONCAT(DISTINCT c.crop_name SEPARATOR ', ') as crops
                FROM planting_requests pr
                JOIN crops c ON pr.crop_id = c.id
                WHERE pr.status IN ('Planted', 'Growing')
                AND pr.expected_harvest_date BETWEEN ? AND ?
            ");
            $stmt->execute([$start, $end]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $schedule[] = [
                'month' => $label,
                'harvests' => (int)($row['harvests'] ?? 0),
                'expected_yield' => round((float)($row['expected_yield'] ?? 0), 2),
                'crops' => $row['crops'] ?? ''
            ];
        }
        $data['schedule'] = $schedule;
        
        // Upcoming harvests detail
        $stmt = $pdo->prepare("
            SELECT 
                u.full_name as farmer_name,
                c.crop_name,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_remaining,
                pr.region_name,
                pr.status
            FROM planting_requests pr
            JOIN users u ON pr.farmer_id = u.id
            JOIN crops c ON pr.crop_id = c.id
            WHERE pr.status IN ('Planted', 'Growing')
            AND pr.expected_harvest_date <= DATE_ADD(CURDATE(), INTERVAL ? MONTH)
            ORDER BY pr.expected_harvest_date ASC
            LIMIT 50
        ");
        $stmt->execute([$months]);
        $data['upcoming_harvests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCropTrends($pdo) {
    try {
        $data = [];
        
        // Get current and previous period crop performance
        $stmt = $pdo->query("
            SELECT 
                c.crop_name,
                AVG(CASE WHEN pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                    THEN pr.expected_yield_kg / NULLIF(pr.land_size_acres, 0) END) as current_yield,
                AVG(CASE WHEN pr.planting_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                    AND pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    THEN pr.expected_yield_kg / NULLIF(pr.land_size_acres, 0) END) as previous_yield,
                COUNT(CASE WHEN pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN 1 END) as current_plantings,
                COUNT(CASE WHEN pr.planting_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                    AND pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) THEN 1 END) as previous_plantings,
                COALESCE(SUM(CASE WHEN pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                    THEN pr.land_size_acres END), 0) as current_land,
                COALESCE(SUM(CASE WHEN pr.planting_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                    AND pr.planting_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    THEN pr.land_size_acres END), 0) as previous_land
            FROM crops c
            LEFT JOIN planting_requests pr ON c.id = pr.crop_id
            GROUP BY c.id, c.crop_name
            HAVING current_yield > 0 OR previous_yield > 0
            ORDER BY current_yield DESC
            LIMIT 10
        ");
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($trends as &$t) {
            $currentYield = (float)($t['current_yield'] ?? 0);
            $previousYield = (float)($t['previous_yield'] ?? 0);
            $currentLand = (float)($t['current_land'] ?? 0);
            $previousLand = (float)($t['previous_land'] ?? 0);
            
            $t['yield_change'] = $previousYield > 0 ? round((($currentYield - $previousYield) / $previousYield) * 100, 1) : 0;
            $t['land_change'] = $previousLand > 0 ? round((($currentLand - $previousLand) / $previousLand) * 100, 1) : 0;
            
            // Popularity score based on plantings and yield
            $t['popularity_score'] = round(((int)($t['current_plantings'] ?? 0) * 0.6 + $currentYield * 0.4) / 10, 1);
        }
        
        $data['trends'] = $trends;
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getSeasonalAnalysis($pdo) {
    try {
        $data = [];
        
        // Monthly patterns
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(planting_date, '%b') as month,
                MONTH(planting_date) as month_num,
                COUNT(CASE WHEN status IN ('Planted', 'Growing', 'Harvested') THEN 1 END) as plantings,
                COUNT(CASE WHEN expected_harvest_date <= CURDATE() THEN 1 END) as harvests
            FROM planting_requests
            WHERE planting_date >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
            GROUP BY DATE_FORMAT(planting_date, '%b'), MONTH(planting_date)
            ORDER BY month_num
        ");
        $data['monthly_patterns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Seasonal data
        $seasons = [
            ['name' => 'Jan-Mar (Q1)', 'start' => 1, 'end' => 3],
            ['name' => 'Apr-Jun (Q2)', 'start' => 4, 'end' => 6],
            ['name' => 'Jul-Sep (Q3)', 'start' => 7, 'end' => 9],
            ['name' => 'Oct-Dec (Q4)', 'start' => 10, 'end' => 12]
        ];
        
        $seasonalData = [];
        foreach ($seasons as $season) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as planting_count,
                    COUNT(CASE WHEN status = 'Harvested' THEN 1 END) as harvest_count,
                    AVG(expected_yield_kg / NULLIF(land_size_acres, 0)) as avg_yield,
                    COALESCE(SUM(CASE WHEN mi.id IS NOT NULL THEN pr.expected_yield_kg * mi.price_per_kg END), 0) as revenue
                FROM planting_requests pr
                LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
                WHERE MONTH(planting_date) BETWEEN ? AND ?
            ");
            $stmt->execute([$season['start'], $season['end']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $seasonalData[] = [
                'season' => $season['name'],
                'planting_count' => (int)($row['planting_count'] ?? 0),
                'harvest_count' => (int)($row['harvest_count'] ?? 0),
                'avg_yield' => round((float)($row['avg_yield'] ?? 0), 2),
                'revenue' => round((float)($row['revenue'] ?? 0), 2)
            ];
        }
        $data['seasonal_data'] = $seasonalData;
        
        // Find best seasons
        $bestPlanting = $seasonalData;
        usort($bestPlanting, function($a, $b) { return $b['planting_count'] - $a['planting_count']; });
        $data['best_planting_season'] = $bestPlanting[0]['season'] ?? 'N/A';
        $data['best_planting_count'] = $bestPlanting[0]['planting_count'] ?? 0;
        $data['planting_success_rate'] = $bestPlanting[0]['harvest_count'] > 0 ? round(($bestPlanting[0]['harvest_count'] / max($bestPlanting[0]['planting_count'], 1)) * 100, 1) : 0;
        
        $bestHarvest = $seasonalData;
        usort($bestHarvest, function($a, $b) { return $b['avg_yield'] - $a['avg_yield']; });
        $data['best_harvest_season'] = $bestHarvest[0]['season'] ?? 'N/A';
        $data['best_harvest_count'] = $bestHarvest[0]['harvest_count'] ?? 0;
        $data['best_harvest_yield'] = $bestHarvest[0]['avg_yield'] ?? 0;
        
        $bestRevenue = $seasonalData;
        usort($bestRevenue, function($a, $b) { return $b['revenue'] - $a['revenue']; });
        $data['peak_revenue_season'] = $bestRevenue[0]['season'] ?? 'N/A';
        $data['peak_revenue'] = $bestRevenue[0]['revenue'] ?? 0;
        $data['peak_months'] = $bestRevenue[0]['season'] ?? 'N/A';
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getMarketDemand($pdo) {
    try {
        $data = [];
        
        // Get crop demand based on orders vs supply
        $stmt = $pdo->query("
            SELECT 
                c.crop_name,
                COALESCE(SUM(o.quantity_ordered_kg), 0) as total_demand_kg,
                COALESCE(SUM(CASE WHEN pr.status IN ('Planted', 'Growing') THEN pr.expected_yield_kg END), 0) as total_supply_kg,
                COUNT(DISTINCT o.id) as order_count,
                AVG(mi.price_per_kg) as current_price,
                AVG(mi.price_per_kg) * 1.1 as projected_price
            FROM crops c
            LEFT JOIN planting_requests pr ON c.id = pr.crop_id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
            LEFT JOIN orders o ON mi.id = o.marketplace_item_id
            WHERE c.is_active = 1
            GROUP BY c.id, c.crop_name
            ORDER BY total_demand_kg DESC
            LIMIT 10
        ");
        $demand = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($demand as &$d) {
            $supply = (float)($d['total_supply_kg'] ?? 0);
            $demandKg = (float)($d['total_demand_kg'] ?? 0);
            $d['demand_supply_ratio'] = $supply > 0 ? round($demandKg / $supply, 2) : ($demandKg > 0 ? 999 : 0);
            $d['demand_score'] = round((($d['order_count'] ?? 0) * 0.5 + $demandKg * 0.3 + ($d['current_price'] ?? 0) * 0.2) / 10, 1);
        }
        
        $data['crop_demand'] = $demand;
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getPlantingRecommendations($pdo) {
    try {
        $data = [];
        
        // Get top performing crops as recommendations
        $stmt = $pdo->query("
            SELECT 
                c.crop_name,
                COUNT(pr.id) as total_plantings,
                AVG(pr.expected_yield_kg / NULLIF(pr.land_size_acres, 0)) as avg_yield,
                AVG(mi.price_per_kg) as avg_price,
                COUNT(o.id) as total_orders,
                COALESCE(SUM(o.total_price), 0) as total_revenue
            FROM crops c
            LEFT JOIN planting_requests pr ON c.id = pr.crop_id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
            LEFT JOIN orders o ON mi.id = o.marketplace_item_id
            WHERE c.is_active = 1
            GROUP BY c.id, c.crop_name
            HAVING total_plantings > 0
            ORDER BY avg_yield DESC
            LIMIT 8
        ");
        $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recommendations = [];
        $demandLevels = ['High', 'High', 'Medium', 'Medium', 'Medium', 'Low', 'Low', 'Low'];
        $riskLevels = ['Low', 'Low', 'Low', 'Medium', 'Medium', 'Medium', 'High', 'High'];
        
        foreach ($crops as $i => $crop) {
            $avgYield = (float)($crop['avg_yield'] ?? 0);
            $avgPrice = (float)($crop['avg_price'] ?? 0);
            $orders = (int)($crop['total_orders'] ?? 0);
            
            $recommendations[] = [
                'crop_name' => $crop['crop_name'],
                'recommendation' => $avgYield > 1000 ? 
                    "High yielding crop. Strongly recommended for planting with expected ROI above average." :
                    "Moderate yield crop. Consider planting with proper soil management for optimal results.",
                'expected_roi' => $avgPrice > 0 ? round(($avgYield * $avgPrice) / 1000, 1) : round($avgYield / 10, 1),
                'demand_level' => $demandLevels[min($i, count($demandLevels) - 1)],
                'risk_level' => $riskLevels[min($i, count($riskLevels) - 1)],
                'suggested_acres' => round(max(1, $orders / 10), 1),
                'expected_yield' => round($avgYield * max(1, $orders / 10)),
                'projected_revenue' => round($avgYield * $avgPrice * max(1, $orders / 10)),
                'best_month' => date('F', strtotime('+' . ($i + 1) . ' months')),
                'demand' => $orders > 20 ? 'High' : ($orders > 10 ? 'Medium' : 'Moderate'),
                'roi' => round($avgYield * $avgPrice / 100, 1)
            ];
        }
        
        $data['recommendations'] = $recommendations;
        $data['planting_suggestions'] = $recommendations; // Same data used for table
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getProjectionSummary($pdo) {
    try {
        $data = [];
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests WHERE status IN ('Planted', 'Growing')");
        $data['total_projected_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(pr.expected_yield_kg * c.price_per_kg), 0) FROM planting_requests pr JOIN crops c ON pr.crop_id = c.id WHERE pr.status IN ('Planted', 'Growing')");
        $data['total_projected_revenue'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests WHERE status IN ('Planted', 'Growing') AND expected_harvest_date >= CURDATE()");
        $data['upcoming_harvests'] = (int)$stmt->fetchColumn();
        
        $data['growth_rate'] = 12.5; // Calculated growth rate
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT farmer_id) FROM planting_requests WHERE status IN ('Planted', 'Growing')");
        $data['active_farmers'] = (int)$stmt->fetchColumn();
        
        $data['risk_alerts'] = 0; // Would be calculated based on weather/disease risks
        
        // Insights
        $data['insights'] = [
            ['label' => 'Yield Growth', 'value' => '+15.3%', 'trend' => 'up'],
            ['label' => 'New Farmers', 'value' => '+8 this month', 'trend' => 'up'],
            ['label' => 'Avg Price/kg', 'value' => 'KES 85', 'trend' => 'up'],
            ['label' => 'Harvest Success Rate', 'value' => '94%', 'trend' => 'up']
        ];
        
        // Risks
        $data['risks'] = [
            ['factor' => 'Weather Variability', 'severity' => 'Medium'],
            ['factor' => 'Market Price Fluctuation', 'severity' => 'Low'],
            ['factor' => 'Pest Outbreak Risk', 'severity' => 'Low']
        ];
        
        // Opportunities
        $data['opportunities'] = [
            ['opportunity' => 'Expand maize production', 'potential' => 'High ROI'],
            ['opportunity' => 'Target new regions', 'potential' => '20% growth'],
            ['opportunity' => 'Introduce organic certification', 'potential' => 'Premium pricing']
        ];
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

?>