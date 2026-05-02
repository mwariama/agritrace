<?php
// growth-tracking.php - Growth Tracking Management for AgriMarketplace Admin Panel
// Uses existing database tables: planting_requests, crops, growth_stages, marketplace_items

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('Growth Tracking', 'growth-tracking');

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
        require_once '../database.php';
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
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
        
        switch($action) {
            case 'get_growth_data':
                getGrowthData($pdo);
                break;
            case 'get_farms_for_tracking':
                getFarmsForTracking($pdo);
                break;
            case 'get_growth_stages':
                getGrowthStages($pdo);
                break;
            case 'get_crop_image':
                getCropImage($pdo);
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
    <link rel="stylesheet" href="growth-tracking.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
        /* Growth Tracking specific styles */
        .growth-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .filters-bar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card-bg);
        }
        
        .search-btn, .reset-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .search-btn {
            background: var(--primary-green);
            color: white;
            border: none;
        }
        
        .reset-btn {
            background: var(--text-secondary);
            color: white;
            border: none;
        }
        
        /* Growth Timeline View */
        .timeline-view {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        
        .farm-growth-card {
            border: 1px solid var(--border-light);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        
        .farm-growth-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .farm-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--soft-green);
            cursor: pointer;
        }
        
        .farm-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .crop-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--page-bg);
        }
        
        .crop-image-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: var(--page-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .farm-details h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .farm-details p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .farm-status {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .progress-bar-container {
            width: 200px;
        }
        
        .progress-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .days-left {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-green);
        }
        
        .farm-expand-icon {
            font-size: 20px;
            color: var(--text-secondary);
            transition: transform 0.2s;
        }
        
        .farm-expand-icon.expanded {
            transform: rotate(180deg);
        }
        
        .farm-detail-content {
            display: none;
            padding: 20px;
            background: var(--page-bg);
        }
        
        .farm-detail-content.show {
            display: block;
        }
        
        /* Growth Stages Timeline */
        .stages-timeline {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stage-card {
            flex: 1;
            min-width: 180px;
            background: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid var(--border-light);
            transition: all 0.2s;
        }
        
        .stage-card.active {
            border-left-color: var(--primary-green);
            background: linear-gradient(135deg, var(--card-bg), rgba(31, 122, 76, 0.05));
        }
        
        .stage-card.completed {
            border-left-color: var(--active);
            opacity: 0.8;
        }
        
        .stage-day {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .stage-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stage-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .stage-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 8px;
        }
        
        .stage-status.completed {
            background: var(--active);
            color: white;
        }
        
        .stage-status.current {
            background: var(--primary-green);
            color: white;
        }
        
        .stage-status.upcoming {
            background: var(--border-light);
            color: var(--text-secondary);
        }
        
        /* Farm Details Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
        }
        
        .info-card h5 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .info-card .info-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-green);
        }
        
        .marketplace-status {
            margin-top: 15px;
            padding: 12px;
            background: var(--card-bg);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .market-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .market-badge.listed {
            background: var(--active);
            color: white;
        }
        
        .market-badge.not-listed {
            background: var(--text-secondary);
            color: white;
        }
        
        .view-farm-btn {
            background: var(--primary-green);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }
        
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-green);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            z-index: 9999;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .toast-notification.show {
            transform: translateX(0);
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
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .farm-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .farm-status {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .progress-bar-container {
                width: 100%;
            }
            
            .stages-timeline {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php echo generateAdminSidebar($nav_data); ?>
        
        <main class="main-content">
            <?php echo generateAdminHeader($nav_data); ?>
            
            <div class="content-area" id="contentArea">
                <div class="loading">Loading growth data...</div>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>

    <?php echo generateLoadingAnimation(); ?>

    <script>
    // API Service
    const API = {
        async request(action, method = 'GET', data = null) {
            try {
                let url = `${window.location.pathname}?action=${action}`;
                const options = { method: method };
                
                if (method === 'POST' && data) {
                    options.headers = { 'Content-Type': 'application/json' };
                    options.body = JSON.stringify(data);
                } else if (method === 'GET' && data) {
                    const params = new URLSearchParams(data);
                    url += '&' + params.toString();
                }
                
                const response = await fetch(url, options);
                const result = await response.json();
                
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
        }
    };
    
    // Growth Tracking Manager
    const GrowthManager = {
        farms: [],
        filteredFarms: [],
        allGrowthStages: [],
        filters: {
            farm_id: '',
            crop_id: '',
            status: ''
        },
        searchTerm: '',
        
        init: async function() {
            console.log('GrowthManager initializing...');
            try {
                await this.loadGrowthStages();
                await this.loadGrowthData();
                this.setupEventListeners();
                this.render();
                
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
        
        loadGrowthData: async function() {
            console.log('Loading growth data...');
            const params = {};
            if (this.filters.farm_id) params.farm_id = this.filters.farm_id;
            if (this.filters.crop_id) params.crop_id = this.filters.crop_id;
            if (this.filters.status) params.status = this.filters.status;
            
            const result = await API.get('get_growth_data', params);
            if (!result.error) {
                this.farms = Array.isArray(result) ? result : [];
                this.applySearch();
                console.log('Farms loaded:', this.farms.length);
            } else {
                console.error('Error loading data:', result.error);
                this.showToast('Error loading data: ' + result.error, 'error');
            }
        },
        
        loadGrowthStages: async function() {
            console.log('Loading growth stages...');
            const result = await API.get('get_growth_stages');
            if (!result.error) {
                // Group stages by crop_id
                this.allGrowthStages = Array.isArray(result) ? result : [];
                console.log('Growth stages loaded:', this.allGrowthStages.length);
            }
        },
        
        getStagesForCrop: function(cropId) {
            return (this.allGrowthStages || []).filter(s => s.crop_id == cropId).sort((a,b) => a.day_offset - b.day_offset);
        },
        
        setupEventListeners: function() {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.searchTerm = e.target.value.toLowerCase();
                    this.applySearch();
                });
            }
        },
        
        applyFilters: function() {
            this.filters.farm_id = document.getElementById('filterFarm')?.value || '';
            this.filters.crop_id = document.getElementById('filterCrop')?.value || '';
            this.filters.status = document.getElementById('filterStatus')?.value || '';
            this.loadGrowthData();
        },
        
        resetFilters: function() {
            this.filters = { farm_id: '', crop_id: '', status: '' };
            const filterFarm = document.getElementById('filterFarm');
            const filterCrop = document.getElementById('filterCrop');
            const filterStatus = document.getElementById('filterStatus');
            
            if (filterFarm) filterFarm.value = '';
            if (filterCrop) filterCrop.value = '';
            if (filterStatus) filterStatus.value = '';
            
            this.loadGrowthData();
        },
        
        applySearch: function() {
            if (!this.searchTerm) {
                this.filteredFarms = [...this.farms];
            } else {
                this.filteredFarms = this.farms.filter(farm => 
                    (farm.farmer_name && farm.farmer_name.toLowerCase().includes(this.searchTerm)) ||
                    (farm.crop_name && farm.crop_name.toLowerCase().includes(this.searchTerm)) ||
                    (farm.region_name && farm.region_name.toLowerCase().includes(this.searchTerm))
                );
            }
            this.render();
        },
        
        toggleFarmDetail: function(farmId) {
            const detailContent = document.getElementById(`farm-detail-${farmId}`);
            const expandIcon = document.getElementById(`expand-icon-${farmId}`);
            if (detailContent) {
                detailContent.classList.toggle('show');
                if (expandIcon) {
                    expandIcon.classList.toggle('expanded');
                }
            }
        },
        
        getCurrentStage: function(farm) {
            const daysElapsed = farm.days_elapsed || 0;
            const stages = this.getStagesForCrop(farm.crop_id);
            
            let currentStage = null;
            let nextStage = null;
            
            for (let i = 0; i < stages.length; i++) {
                if (daysElapsed >= stages[i].day_offset) {
                    currentStage = stages[i];
                    nextStage = stages[i + 1] || null;
                }
            }
            
            return { currentStage, nextStage, stages, daysElapsed };
        },
        
        getProgressPercentage: function(farm) {
            const totalDays = farm.total_maturity_days || 1;
            const daysElapsed = farm.days_elapsed || 0;
            const percentage = Math.min(100, Math.max(0, (daysElapsed / totalDays) * 100));
            return Math.round(percentage);
        },
        
        getCropImageHtml: function(imageUrl, cropName) {
            if (imageUrl && imageUrl.trim() !== '') {
                return `<img src="${imageUrl}" alt="${this.escapeHtml(cropName)}" class="crop-image" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'crop-image-placeholder\'>🌱</div>'">`;
            }
            return `<div class="crop-image-placeholder">🌱</div>`;
        },
        
        renderFilters: function() {
            // Get unique crops for filter dropdown
            const uniqueCrops = {};
            this.farms.forEach(farm => {
                if (farm.crop_id && !uniqueCrops[farm.crop_id]) {
                    uniqueCrops[farm.crop_id] = { id: farm.crop_id, name: farm.crop_name };
                }
            });
            
            const cropOptions = Object.values(uniqueCrops).map(c => 
                `<option value="${c.id}">${this.escapeHtml(c.name)}</option>`
            ).join('');
            
            return `
                <div class="filters-bar">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>🌾 Crop</label>
                            <select id="filterCrop">
                                <option value="">All Crops</option>
                                ${cropOptions}
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>📊 Status</label>
                            <select id="filterStatus">
                                <option value="">All Statuses</option>
                                <option value="Planted">Planted</option>
                                <option value="Growing">Growing</option>
                                <option value="Harvested">Harvested</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 10px;">
                                <button class="search-btn" onclick="GrowthManager.applyFilters()">🔍 Apply Filters</button>
                                <button class="reset-btn" onclick="GrowthManager.resetFilters()">↺ Reset</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
        
        renderFarmCard: function(farm) {
            const progress = this.getProgressPercentage(farm);
            const { currentStage, nextStage, stages, daysElapsed } = this.getCurrentStage(farm);
            const daysLeft = farm.days_to_harvest;
            const daysLeftText = daysLeft > 0 ? `${daysLeft} days left` : (daysLeft === 0 ? 'Harvest today!' : 'Harvest overdue');
            
            return `
                <div class="farm-growth-card">
                    <div class="farm-header" onclick="GrowthManager.toggleFarmDetail(${farm.id})">
                        <div class="farm-info">
                            ${this.getCropImageHtml(farm.image_url, farm.crop_name)}
                            <div class="farm-details">
                                <h4>${this.escapeHtml(farm.farmer_name)}</h4>
                                <p>${this.escapeHtml(farm.crop_name)} • ${farm.land_size_acres || 0} acres • ${this.escapeHtml(farm.region_name || 'No region')}</p>
                            </div>
                        </div>
                        <div class="farm-status">
                            <div class="progress-bar-container">
                                <div class="progress-label">Growth Progress</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${progress}%"></div>
                                </div>
                            </div>
                            <div class="days-left">
                                ${daysLeftText}
                            </div>
                            <span class="status-badge ${farm.status || 'Planted'}">${farm.status || 'Planted'}</span>
                            <span class="farm-expand-icon" id="expand-icon-${farm.id}">▼</span>
                        </div>
                    </div>
                    
                    <div class="farm-detail-content" id="farm-detail-${farm.id}">
                        <!-- Growth Stages Timeline -->
                        <div class="stages-timeline">
                            ${stages.map(stage => {
                                const isCompleted = daysElapsed >= stage.day_offset + (stage.stage_duration_days || 7);
                                const isCurrent = !isCompleted && daysElapsed >= stage.day_offset;
                                const statusClass = isCompleted ? 'completed' : (isCurrent ? 'current' : 'upcoming');
                                const statusText = isCompleted ? '✓ Completed' : (isCurrent ? '● Current' : '○ Upcoming');
                                
                                return `
                                    <div class="stage-card ${statusClass}">
                                        <div class="stage-day">Day ${stage.day_offset}</div>
                                        <div class="stage-icon">${this.getStageIcon(stage.stage_name)}</div>
                                        <div class="stage-name">${this.escapeHtml(stage.stage_name)}</div>
                                        <div class="stage-status ${statusClass}">${statusText}</div>
                                        ${stage.description ? `<div class="stage-desc" style="font-size: 11px; color: var(--text-secondary); margin-top: 8px;">${this.escapeHtml(stage.description)}</div>` : ''}
                                    </div>
                                `;
                            }).join('')}
                            ${stages.length === 0 ? '<div style="padding: 20px; text-align: center;">No growth stages defined for this crop</div>' : ''}
                        </div>
                        
                        <!-- Farm Information Grid -->
                        <div class="info-grid">
                            <div class="info-card">
                                <h5>📅 Timeline</h5>
                                <div class="info-value">Planted: ${farm.planting_date || 'N/A'}</div>
                                <div class="info-value" style="font-size: 14px;">Harvest: ${farm.expected_harvest_date || 'N/A'}</div>
                                <div style="margin-top: 8px; font-size: 13px;">Day ${daysElapsed} of ${farm.total_maturity_days || '?'} days</div>
                            </div>
                            <div class="info-card">
                                <h5>📊 Production</h5>
                                <div class="info-value">${farm.land_size_acres || 0} acres</div>
                                <div style="font-size: 13px;">Expected yield: ${(farm.expected_yield_kg || 0).toLocaleString()} kg</div>
                                <div style="font-size: 13px;">${((farm.expected_yield_kg || 0) / (farm.land_size_acres || 1)).toFixed(0)} kg/acre</div>
                            </div>
                            <div class="info-card">
                                <h5>👨‍🌾 Farmer</h5>
                                <div class="info-value">${this.escapeHtml(farm.farmer_name || 'N/A')}</div>
                                <div style="font-size: 13px;">${this.escapeHtml(farm.farmer_phone || 'No phone')}</div>
                                <div style="font-size: 13px;">${this.escapeHtml(farm.farmer_email || 'No email')}</div>
                            </div>
                        </div>
                        
                        <!-- Marketplace Status -->
                        <div class="marketplace-status">
                            <div>
                                <strong>Marketplace Status:</strong>
                                <span class="market-badge ${farm.market_status === 'Listed' ? 'listed' : 'not-listed'}">
                                    ${farm.market_status || 'Not Listed'}
                                </span>
                                ${farm.listing_price ? ` at KES ${farm.listing_price}/kg` : ''}
                            </div>
                            <a href="farm-management.php?id=${farm.id}" class="view-farm-btn">View Full Details →</a>
                        </div>
                        
                        ${farm.notes ? `
                            <div style="margin-top: 15px; padding: 12px; background: var(--card-bg); border-radius: 8px;">
                                <strong>📝 Notes:</strong>
                                <p style="margin-top: 5px; font-size: 13px;">${this.escapeHtml(farm.notes)}</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        },
        
        getStageIcon: function(stageName) {
            const name = (stageName || '').toLowerCase();
            if (name.includes('plant') || name.includes('sow')) return '🌱';
            if (name.includes('germ')) return '🌿';
            if (name.includes('veget')) return '🍃';
            if (name.includes('flower')) return '🌸';
            if (name.includes('fruit')) return '🍎';
                        if (name.includes('harvest')) return '🌾';
            if (name.includes('weed')) return '🌿';
            if (name.includes('fertil')) return '💧';
            if (name.includes('water')) return '💦';
            if (name.includes('pest')) return '🐛';
            return '🌱';
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
            toast.className = `toast-notification ${type === 'success' ? 'success' : 'error'} show`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        },
        
        render: function() {
            console.log('Rendering growth tracking view...');
            const contentArea = document.getElementById('contentArea');
            if (!contentArea) return;
            
            let html = `
                <div class="growth-container">
                    ${this.renderFilters()}
                    
                    <div class="timeline-view">
            `;
            
            if (this.filteredFarms.length > 0) {
                html += this.filteredFarms.map(farm => this.renderFarmCard(farm)).join('');
            } else {
                html += `
                    <div class="empty-state">
                        <div style="font-size: 48px; margin-bottom: 15px;">🌾</div>
                        <h3>No active farms found</h3>
                        <p>There are no active planting requests to track growth.</p>
                        <button class="btn-primary" onclick="location.href='farm-management.php'" style="margin-top: 15px;">Go to Farm Management</button>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            contentArea.innerHTML = html;
            
            // Set filter values after render
            setTimeout(() => {
                const filterCrop = document.getElementById('filterCrop');
                const filterStatus = document.getElementById('filterStatus');
                if (filterCrop && this.filters.crop_id) filterCrop.value = this.filters.crop_id;
                if (filterStatus && this.filters.status) filterStatus.value = this.filters.status;
            }, 50);
        }
    };
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Growth Tracking page initialized');
        await GrowthManager.init();
        
        window.addEventListener('globalSearch', (e) => {
            GrowthManager.searchTerm = e.detail.term.toLowerCase();
            GrowthManager.applySearch();
        });
    });
    
    window.GrowthManager = GrowthManager;
    </script>
    
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function getGrowthData($pdo) {
    try {
        $sql = "
            SELECT 
                pr.id,
                pr.farmer_id,
                pr.crop_id,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.region_name,
                pr.status,
                pr.notes,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                u.email as farmer_email,
                c.crop_name,
                c.image_url,
                c.baseline_yield_per_acre,
                c.total_maturity_days,
                c.price_per_kg,
                DATEDIFF(CURDATE(), pr.planting_date) as days_elapsed,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_to_harvest,
                CASE WHEN mi.id IS NOT NULL THEN 'Listed' ELSE 'Not Listed' END as market_status,
                mi.price_per_kg as listing_price
            FROM planting_requests pr
            INNER JOIN users u ON pr.farmer_id = u.id
            INNER JOIN crops c ON pr.crop_id = c.id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
            WHERE pr.status IN ('Planted', 'Growing')
        ";
        
        $params = [];
        
        if (!empty($_GET['farm_id'])) {
            $sql .= " AND pr.id = ?";
            $params[] = $_GET['farm_id'];
        }
        
        if (!empty($_GET['crop_id'])) {
            $sql .= " AND pr.crop_id = ?";
            $params[] = $_GET['crop_id'];
        }
        
        if (!empty($_GET['status'])) {
            $sql .= " AND pr.status = ?";
            $params[] = $_GET['status'];
        }
        
        $sql .= " ORDER BY pr.expected_harvest_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getFarmsForTracking($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.farmer_id,
                pr.crop_id,
                pr.land_size_acres,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.region_name,
                pr.status,
                u.full_name as farmer_name,
                c.crop_name,
                c.total_maturity_days,
                c.image_url
            FROM planting_requests pr
            JOIN users u ON pr.farmer_id = u.id
            JOIN crops c ON pr.crop_id = c.id
            WHERE pr.status IN ('Planted', 'Growing')
            ORDER BY pr.planting_date DESC
        ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getGrowthStages($pdo) {
    try {
        $cropId = $_GET['crop_id'] ?? null;
        
        $sql = "SELECT id, crop_id, stage_name, day_offset, stage_duration_days, description, alert_message 
                FROM growth_stages";
        $params = [];
        
        if ($cropId) {
            $sql .= " WHERE crop_id = ? ORDER BY day_offset";
            $params[] = $cropId;
        } else {
            $sql .= " ORDER BY crop_id, day_offset";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCropImage($pdo) {
    try {
        $cropId = $_GET['crop_id'] ?? 0;
        
        if (!$cropId) {
            echo json_encode(['error' => 'Crop ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT id, crop_name, image_url FROM crops WHERE id = ?");
        $stmt->execute([$cropId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>