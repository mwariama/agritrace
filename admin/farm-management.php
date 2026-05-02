<?ph
// Start output buffering
ob_start();

// Include navigation system (this handles authentication automatically)
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically and redirects to index.php if not logged in)
$nav_data = initializeAdminNavigation('Farm Management', 'farm-management');

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
            case 'get_farms':
            case 'farms':
                getFarms($pdo);
                break;
            case 'get_farm':
                getFarm($pdo);
                break;
            case 'get_farmers':
                getFarmers($pdo);
                break;
            case 'get_crops':
                getCrops($pdo);
                break;
            case 'get_stats':
                getStats($pdo);
                break;
                
            // POST endpoints
            case 'create_farm':
            case 'update_farm':
            case 'delete_farm':
            case 'update_farm_status':
                handlePostRequest($pdo, $action, $input);
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
    <link rel="stylesheet" href="farm-management.css">
    
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
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .status-badge.inactive {
            background: var(--text-secondary);
            color: white;
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
        
        /* Health badge styles */
        .health-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .health-badge.Excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .health-badge.Good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .health-badge.Fair {
            background: #fff3cd;
            color: #856404;
        }
        
        .health-badge.Poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        .growth-timeline {
            min-height: 200px;
        }
        
        .timeline-item {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .timeline-date {
            width: 110px;
            font-weight: 600;
            color: var(--primary-green);
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-stage {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .timeline-health {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .action-btn:hover {
            background: var(--page-bg);
        }
        
        .farm-stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .farm-stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        
        .farm-stat-icon {
            font-size: 32px;
        }
        
        .farm-stat-content h4 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-green);
        }
        
        .farm-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
        }
        
        .filter-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        .farms-table-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
            overflow-x: auto;
        }
        
        .farms-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .farms-table th {
            text-align: left;
            padding: 12px 8px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            background-color: var(--soft-green);
            border-bottom: 2px solid var(--soft-green);
        }
        
        .farms-table td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
        }
        
        .farms-table tbody tr {
            cursor: pointer;
        }
        
        .farms-table tbody tr:hover {
            background-color: var(--page-bg);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.Planted {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.Growing {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.Harvested {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .health-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 8px 14px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .pagination-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
        }
        
        .modal-lg {
            width: 800px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-secondary);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }
        
        .btn-primary, .btn-secondary, .btn-danger {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }
        
        .btn-danger {
            background: var(--blocked);
            color: white;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-card {
            background: var(--page-bg);
            border-radius: 8px;
            padding: 15px;
        }
        
        .detail-card h4 {
            margin-bottom: 12px;
            color: var(--primary-green);
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .detail-value {
            color: var(--text-primary);
        }
        
        .crop-info {
            background: var(--page-bg);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .info-value {
            font-weight: 600;
            color: var(--primary-green);
        }
        
        @media (max-width: 768px) {
            .farm-stats-cards {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .modal-lg {
                width: 95%;
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
                <div class="loading">Loading farms...</div>
            </div>
        </main>
    </div>

    <!-- Farm Detail Modal -->
    <div class="modal" id="farmModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>Farm Details</h3>
                <span class="close-modal" onclick="FarmManager.closeModal()">&times;</span>
            </div>
            <div id="farmDetailContent" style="padding: 20px;"></div>
        </div>
    </div>

    <!-- Add/Edit Farm Modal -->
    <div class="modal" id="editFarmModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="farmModalTitle">Add New Farm</h3>
                <span class="close-modal" onclick="FarmManager.closeEditModal()">&times;</span>
            </div>
            <form id="editFarmForm" onsubmit="event.preventDefault(); FarmManager.saveFarm();">
                <input type="hidden" id="editFarmId">
                <div style="padding: 20px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Farmer *</label>
                            <select id="editFarmerId" required>
                                <option value="">Select Farmer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Crop *</label>
                            <select id="editCropId" required onchange="FarmManager.updateCropDetails()">
                                <option value="">Select Crop</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Land Size (acres) *</label>
                            <input type="number" id="editLandSize" step="0.01" min="0.01" required oninput="FarmManager.updateCropDetails()">
                        </div>
                        
                        <div class="form-group">
                            <label>Planting Date *</label>
                            <input type="date" id="editPlantingDate" required onchange="FarmManager.updateCropDetails()">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Region/Source Location</label>
                        <input type="text" id="editRegion" placeholder="e.g., Nakuru, Kiambu...">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Latitude</label>
                            <input type="number" id="editLatitude" step="0.00000001" placeholder="-1.2833">
                        </div>
                        
                        <div class="form-group">
                            <label>Longitude</label>
                            <input type="number" id="editLongitude" step="0.00000001" placeholder="36.8167">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="editNotes" rows="3" placeholder="Additional notes about this farm..."></textarea>
                    </div>
                    
                    <div class="crop-info" id="cropInfo" style="display: none;">
                        <h4 style="margin-bottom: 10px;">Crop Information</h4>
                        <div class="info-grid">
                            <div>
                                <div class="info-label">Expected Yield:</div>
                                <div class="info-value" id="expectedYield">0 kg</div>
                            </div>
                            <div>
                                <div class="info-label">Harvest Date:</div>
                                <div class="info-value" id="harvestDate">-</div>
                            </div>
                            <div>
                                <div class="info-label">Maturity Days:</div>
                                <div class="info-value" id="maturityDays">-</div>
                            </div>
                            <div>
                                <div class="info-label">Baseline Yield:</div>
                                <div class="info-value" id="baselineYield">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="FarmManager.closeEditModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save Farm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close-modal" onclick="FarmManager.closeDeleteModal()">&times;</span>
            </div>
            <div class="delete-confirm" style="padding: 20px;">
                <p>Are you sure you want to delete this farm?</p>
                <p class="warning-text">This action cannot be undone. All related records will also be deleted.</p>
                <div class="form-actions">
                    <button class="btn-secondary" onclick="FarmManager.closeDeleteModal()">Cancel</button>
                    <button class="btn-danger" onclick="FarmManager.confirmDelete()">Delete Farm</button>
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
        baseUrl: (() => {
            const path = window.location.pathname;
            if (path.includes('/admin/')) {
                return '/admin';
            }
            return '';
        })(),
        
        async request(action, method = 'GET', data = null) {
            try {
                let url = `${window.location.pathname}?action=${action}`;
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    }
                };
                
                if (method === 'POST' && data) {
                    options.body = JSON.stringify(data);
                } else if (method === 'GET' && data) {
                    const params = new URLSearchParams(data);
                    url += '&' + params.toString();
                }
                
                const response = await fetch(url, options);
                const responseText = await response.text();
                
                try {
                    const result = JSON.parse(responseText);
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

    // Farm Manager
    const FarmManager = {
        farms: [],
        filteredFarms: [],
        farmers: [],
        crops: [],
        currentFilter: 'all',
        searchTerm: '',
        currentPage: 1,
        itemsPerPage: 10,
        selectedFarmId: null,
        stats: {
            total_farms: 0,
            active_farms: 0,
            total_land: 0,
            expected_yield: 0,
            total_farmers: 0
        },
        
        init: async function() {
            console.log('FarmManager initializing...');
            
            try {
                await this.loadFarmers();
                await this.loadCrops();
                await this.loadStats();
                await this.loadFarms();
                this.setupEventListeners();
                this.render();
                this.updateQuickStats();
            } catch (e) {
                console.error('Initialization error:', e);
                this.showToast('Failed to initialize: ' + e.message, 'error');
            }
        },
        
        loadFarms: async function() {
            console.log('Loading farms...');
            const result = await API.get('get_farms');
            if (!result.error) {
                this.farms = Array.isArray(result) ? result : [];
                this.filteredFarms = [...this.farms];
                console.log('Farms loaded:', this.farms.length);
            } else {
                console.error('Error loading farms:', result.error);
                this.showToast('Error loading farms: ' + result.error, 'error');
            }
        },
        
        loadFarmers: async function() {
            console.log('Loading farmers...');
            const result = await API.get('get_farmers');
            if (!result.error) {
                this.farmers = Array.isArray(result) ? result : [];
                console.log('Farmers loaded:', this.farmers.length);
            }
        },
        
        loadCrops: async function() {
            console.log('Loading crops...');
            const result = await API.get('get_crops');
            if (!result.error) {
                this.crops = Array.isArray(result) ? result : [];
                console.log('Crops loaded:', this.crops.length);
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
        
        updateQuickStats: function() {
            const statFarms = document.getElementById('statFarms');
            const statFarmers = document.getElementById('statFarmers');
            
            if (statFarms) {
                statFarms.textContent = this.stats.total_farms || this.farms.length || '0';
            }
            if (statFarmers) {
                statFarmers.textContent = this.stats.total_farmers || '0';
            }
        },
        
        setupEventListeners: function() {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.searchTerm = e.target.value.toLowerCase();
                    this.currentPage = 1;
                    this.filterFarms();
                });
            }
            
            document.addEventListener('click', () => {
                document.querySelectorAll('.context-menu').forEach(m => m.remove());
            });
        },
        
        filterFarms: function() {
            this.filteredFarms = this.farms.filter(farm => {
                const matchesSearch = this.searchTerm === '' || 
                    (farm.farmer_name && farm.farmer_name.toLowerCase().includes(this.searchTerm)) ||
                    (farm.crop_name && farm.crop_name.toLowerCase().includes(this.searchTerm)) ||
                    (farm.region_name && farm.region_name.toLowerCase().includes(this.searchTerm));
                
                if (!matchesSearch) return false;
                
                if (this.currentFilter === 'all') return true;
                if (this.currentFilter === 'active') return ['Planted', 'Growing'].includes(farm.status);
                if (this.currentFilter === 'harvested') return farm.status === 'Harvested';
                return farm.status === this.currentFilter;
            });
            this.render();
        },
        
        setFilter: function(filter) {
            this.currentFilter = filter;
            this.currentPage = 1;
            this.filterFarms();
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                const btnText = btn.textContent.toLowerCase().trim();
                btn.classList.toggle('active', 
                    (filter === 'all' && btnText === 'all farms') ||
                    (filter === 'active' && btnText === 'active') ||
                    (filter === 'Planted' && btnText === 'planted') ||
                    (filter === 'Growing' && btnText === 'growing') ||
                    (filter === 'Harvested' && btnText === 'harvested')
                );
            });
        },
        
        getPaginatedFarms: function() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredFarms.slice(start, start + this.itemsPerPage);
        },
        
        getTotalPages: function() {
            return Math.ceil(this.filteredFarms.length / this.itemsPerPage);
        },
        
        changePage: function(page) {
            if (page >= 1 && page <= this.getTotalPages()) {
                this.currentPage = page;
                this.render();
            }
        },
        
        viewFarm: async function(farmId) {
            console.log('Viewing farm:', farmId);
            this.selectedFarmId = farmId;
            const result = await API.get('get_farm', { farm_id: farmId });
            if (!result.error && result) {
                this.showFarmModal(result);
            } else {
                this.showToast('Error loading farm details', 'error');
            }
        },
        
        showFarmModal: function(farm) {
            const modal = document.getElementById('farmModal');
            const content = document.getElementById('farmDetailContent');
            
            if (!modal || !content) return;
            
            let html = `
                <div class="farm-detail-header" style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div class="farm-detail-icon" style="font-size: 48px;">🌾</div>
                    <div class="farm-detail-title">
                        <h2>Farm #${farm.id} - ${this.escapeHtml(farm.crop_name || 'Unknown Crop')}</h2>
                        <p>${this.escapeHtml(farm.farmer_name || 'Unknown Farmer')} • ${this.escapeHtml(farm.region_name || 'No region specified')}</p>
                    </div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-card">
                        <h4>Farm Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Farmer:</span>
                            <span class="detail-value">${this.escapeHtml(farm.farmer_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">${this.escapeHtml(farm.farmer_phone || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">${this.escapeHtml(farm.farmer_email || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Region:</span>
                            <span class="detail-value">${this.escapeHtml(farm.region_name || 'N/A')}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>Crop Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Crop:</span>
                            <span class="detail-value">${this.escapeHtml(farm.crop_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Land Size:</span>
                            <span class="detail-value">${farm.land_size_acres || 0} acres</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Expected Yield:</span>
                            <span class="detail-value">${farm.expected_yield_kg || 0} kg</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="status-badge ${farm.status || ''}">${farm.status || 'Unknown'}</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>Timeline</h4>
                        <div class="detail-item">
                            <span class="detail-label">Planted:</span>
                            <span class="detail-value">${farm.planting_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Harvest:</span>
                            <span class="detail-value">${farm.expected_harvest_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Days to Harvest:</span>
                            <span class="detail-value">${farm.days_to_harvest > 0 ? farm.days_to_harvest + ' days' : (farm.days_to_harvest === 0 ? 'Today' : 'Overdue')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Market Status:</span>
                            <span class="detail-value">${farm.is_listed === 'Listed' ? 'Listed at KES ' + (farm.listing_price || 0) : 'Not Listed'}</span>
                        </div>
                    </div>
                </div>
                
                ${farm.notes ? `
                    <div class="detail-card" style="margin-bottom: 20px;">
                        <h4>Notes</h4>
                        <p>${this.escapeHtml(farm.notes)}</p>
                    </div>
                ` : ''}
                
                <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <button class="btn-primary" onclick="FarmManager.editFarm(${farm.id})">✏️ Edit Farm</button>
                    <button class="btn-danger" onclick="FarmManager.deleteFarm(${farm.id})">🗑️ Delete Farm</button>
                </div>
            `;
            
            content.innerHTML = html;
            modal.classList.add('active');
        },
        
        editFarm: function(farmId = null) {
            console.log('Editing farm:', farmId);
            this.selectedFarmId = farmId;
            
            // Populate farmer dropdown
            const farmerSelect = document.getElementById('editFarmerId');
            if (farmerSelect) {
                farmerSelect.innerHTML = '<option value="">Select Farmer</option>' + 
                    (this.farmers.length > 0 ? this.farmers.map(f => 
                        `<option value="${f.id}">${this.escapeHtml(f.full_name)} (${f.phone_number || 'No phone'})</option>`
                    ).join('') : '<option value="" disabled>No farmers available</option>');
            }
            
            // Populate crop dropdown
            const cropSelect = document.getElementById('editCropId');
            if (cropSelect) {
                cropSelect.innerHTML = '<option value="">Select Crop</option>' + 
                    (this.crops.length > 0 ? this.crops.map(c => 
                        `<option value="${c.id}" data-yield="${c.baseline_yield_per_acre}" data-days="${c.total_maturity_days}">${this.escapeHtml(c.crop_name)}</option>`
                    ).join('') : '<option value="" disabled>No crops available</option>');
            }
            
            if (farmId) {
                const farm = this.farms.find(f => f.id === farmId);
                if (farm) {
                    document.getElementById('farmModalTitle').textContent = 'Edit Farm';
                    document.getElementById('editFarmId').value = farm.id;
                    document.getElementById('editFarmerId').value = farm.farmer_id;
                    document.getElementById('editCropId').value = farm.crop_id;
                    document.getElementById('editLandSize').value = farm.land_size_acres;
                    document.getElementById('editPlantingDate').value = farm.planting_date;
                    document.getElementById('editRegion').value = farm.region_name || '';
                    document.getElementById('editLatitude').value = farm.latitude || '';
                    document.getElementById('editLongitude').value = farm.longitude || '';
                    document.getElementById('editNotes').value = farm.notes || '';
                    
                    this.updateCropDetails();
                }
            } else {
                document.getElementById('farmModalTitle').textContent = 'Add New Farm';
                document.getElementById('editFarmForm').reset();
                document.getElementById('editFarmId').value = '';
                document.getElementById('cropInfo').style.display = 'none';
                
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('editPlantingDate').value = today;
            }
            
            document.getElementById('editFarmModal').classList.add('active');
        },
        
        updateCropDetails: function() {
            const cropSelect = document.getElementById('editCropId');
            if (!cropSelect) return;
            
            const selectedOption = cropSelect.options[cropSelect.selectedIndex];
            const landSize = parseFloat(document.getElementById('editLandSize')?.value) || 0;
            const plantingDate = document.getElementById('editPlantingDate')?.value;
            
            if (selectedOption && selectedOption.value && landSize > 0 && plantingDate) {
                const baselineYield = parseFloat(selectedOption.dataset.yield) || 0;
                const maturityDays = parseInt(selectedOption.dataset.days) || 0;
                const expectedYield = baselineYield * landSize;
                
                const harvestDate = new Date(plantingDate);
                harvestDate.setDate(harvestDate.getDate() + maturityDays);
                const formattedHarvestDate = harvestDate.toISOString().split('T')[0];
                
                document.getElementById('expectedYield').textContent = expectedYield.toFixed(2) + ' kg';
                document.getElementById('harvestDate').textContent = formattedHarvestDate;
                document.getElementById('maturityDays').textContent = maturityDays + ' days';
                document.getElementById('baselineYield').textContent = baselineYield + ' kg/acre';
                
                document.getElementById('cropInfo').style.display = 'block';
            } else {
                document.getElementById('cropInfo').style.display = 'none';
            }
        },
        
        saveFarm: async function() {
            const formData = {
                farm_id: document.getElementById('editFarmId')?.value || null,
                farmer_id: document.getElementById('editFarmerId')?.value,
                crop_id: document.getElementById('editCropId')?.value,
                land_size_acres: parseFloat(document.getElementById('editLandSize')?.value),
                planting_date: document.getElementById('editPlantingDate')?.value,
                region_name: document.getElementById('editRegion')?.value,
                latitude: document.getElementById('editLatitude')?.value ? parseFloat(document.getElementById('editLatitude').value) : null,
                longitude: document.getElementById('editLongitude')?.value ? parseFloat(document.getElementById('editLongitude').value) : null,
                notes: document.getElementById('editNotes')?.value
            };
            
            if (!formData.farmer_id || !formData.crop_id || !formData.land_size_acres || !formData.planting_date) {
                this.showToast('Please fill in all required fields', 'error');
                return;
            }
            
            if (formData.land_size_acres <= 0) {
                this.showToast('Land size must be greater than 0', 'error');
                return;
            }
            
            console.log('Saving farm:', formData);
            
            let result;
            if (formData.farm_id) {
                result = await API.post('update_farm', formData);
            } else {
                result = await API.post('create_farm', formData);
            }
            
            if (result && result.success) {
                this.closeEditModal();
                await this.loadFarms();
                await this.loadStats();
                this.render();
                this.updateQuickStats();
                this.showToast(formData.farm_id ? 'Farm updated successfully!' : 'Farm created successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to save farm'), 'error');
            }
        },
        
        updateFarmStatus: async function(farmId, newStatus) {
            if (!confirm(`Change farm status to ${newStatus}?`)) return;
            
            console.log('Updating farm status:', farmId, newStatus);
            
            const result = await API.post('update_farm_status', {
                farm_id: farmId,
                status: newStatus
            });
            
            if (result && result.success) {
                await this.loadFarms();
                await this.loadStats();
                this.render();
                this.updateQuickStats();
                
                if (this.selectedFarmId === farmId && document.getElementById('farmModal').classList.contains('active')) {
                    const farmData = await API.get('get_farm', { farm_id: farmId });
                    this.showFarmModal(farmData);
                }
                this.showToast('Status updated successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to update status'), 'error');
            }
        },
        
        deleteFarm: function(farmId) {
            this.selectedFarmId = farmId;
            document.getElementById('deleteModal').classList.add('active');
        },
        
        confirmDelete: async function() {
            console.log('Deleting farm:', this.selectedFarmId);
            
            const result = await API.post('delete_farm', { farm_id: this.selectedFarmId });
            
            if (result && result.success) {
                this.closeDeleteModal();
                this.closeModal();
                await this.loadFarms();
                await this.loadStats();
                this.render();
                this.updateQuickStats();
                this.showToast('Farm deleted successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result.error || 'Failed to delete farm'), 'error');
                this.closeDeleteModal();
            }
        },
        
        moreOptions: function(farmId, event) {
            event.stopPropagation();
            
            const farm = this.farms.find(f => f.id === farmId);
            if (!farm) return;
            
            document.querySelectorAll('.context-menu').forEach(m => m.remove());
            
            const menu = document.createElement('div');
            menu.className = 'context-menu';
            
            let statusOptions = '';
            if (farm.status !== 'Harvested') {
                statusOptions = `
                    <div class="context-menu-item" onclick="FarmManager.updateFarmStatus(${farmId}, 'Planted')">🌱 Set as Planted</div>
                    <div class="context-menu-item" onclick="FarmManager.updateFarmStatus(${farmId}, 'Growing')">🌿 Set as Growing</div>
                    <div class="context-menu-item" onclick="FarmManager.updateFarmStatus(${farmId}, 'Harvested')">🌾 Set as Harvested</div>
                    <div class="context-menu-divider"></div>
                `;
            }
            
            menu.innerHTML = `
                ${statusOptions}
                <div class="context-menu-item" onclick="FarmManager.viewFarm(${farmId})">👁️ View Details</div>
                <div class="context-menu-item" onclick="FarmManager.editFarm(${farmId})">✏️ Edit Farm</div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item delete" onclick="FarmManager.deleteFarm(${farmId})">🗑️ Delete Farm</div>
            `;
            
            menu.style.position = 'absolute';
            menu.style.top = event.pageY + 'px';
            menu.style.left = event.pageX + 'px';
            menu.style.zIndex = '2001';
            
            document.body.appendChild(menu);
            
            setTimeout(() => {
                const closeMenu = function(e) {
                    if (!menu.contains(e.target)) {
                        menu.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                };
                document.addEventListener('click', closeMenu);
            }, 100);
        },
        
        closeModal: function() {
            document.getElementById('farmModal')?.classList.remove('active');
        },
        
        closeEditModal: function() {
            document.getElementById('editFarmModal')?.classList.remove('active');
        },
        
        closeDeleteModal: function() {
            document.getElementById('deleteModal')?.classList.remove('active');
            this.selectedFarmId = null;
        },
        
        showToast: function(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast-notification ${type === 'success' ? 'success' : 'error'} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        render: function() {
            console.log('Rendering farms table...');
            
            const paginatedFarms = this.getPaginatedFarms();
            const totalPages = this.getTotalPages();
            const contentArea = document.getElementById('contentArea');
            
            if (!contentArea) return;
            
            let html = `
                <!-- Stats Cards -->
                <div class="farm-stats-cards">
                    <div class="farm-stat-card">
                        <div class="farm-stat-icon">🌾</div>
                        <div class="farm-stat-content">
                            <h4>Total Farms</h4>
                            <div class="stat-number">${this.stats.total_farms || this.farms.length || 0}</div>
                        </div>
                    </div>
                    <div class="farm-stat-card">
                        <div class="farm-stat-icon">🌱</div>
                        <div class="farm-stat-content">
                            <h4>Active Farms</h4>
                            <div class="stat-number">${this.stats.active_farms || 0}</div>
                        </div>
                    </div>
                    <div class="farm-stat-card">
                        <div class="farm-stat-icon">📏</div>
                        <div class="farm-stat-content">
                            <h4>Total Land</h4>
                            <div class="stat-number">${this.stats.total_land || 0} acres</div>
                        </div>
                    </div>
                    <div class="farm-stat-card">
                        <div class="farm-stat-icon">📊</div>
                        <div class="farm-stat-content">
                            <h4>Expected Yield</h4>
                            <div class="stat-number">${((this.stats.expected_yield || 0) / 1000).toFixed(1)}T</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <div class="farm-filters">
                        <button class="filter-btn ${this.currentFilter === 'all' ? 'active' : ''}" onclick="FarmManager.setFilter('all')">All Farms</button>
                        <button class="filter-btn ${this.currentFilter === 'active' ? 'active' : ''}" onclick="FarmManager.setFilter('active')">Active</button>
                        <button class="filter-btn ${this.currentFilter === 'Planted' ? 'active' : ''}" onclick="FarmManager.setFilter('Planted')">Planted</button>
                        <button class="filter-btn ${this.currentFilter === 'Growing' ? 'active' : ''}" onclick="FarmManager.setFilter('Growing')">Growing</button>
                        <button class="filter-btn ${this.currentFilter === 'Harvested' ? 'active' : ''}" onclick="FarmManager.setFilter('Harvested')">Harvested</button>
                    </div>
                    <button class="btn-primary" onclick="FarmManager.editFarm(null)" style="display: flex; align-items: center; gap: 8px;">
                        <span>➕</span> Add New Farm
                    </button>
                </div>
                
                <!-- Farms Table -->
                <div class="farms-table-container">
                    <table class="farms-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Farmer</th>
                                <th>Crop</th>
                                <th>Land (acres)</th>
                                <th>Planting Date</th>
                                <th>Harvest Date</th>
                                <th>Days Left</th>
                                <th>Status</th>
                                <th>Market</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (paginatedFarms.length > 0) {
                paginatedFarms.forEach(farm => {
                    const daysLeft = farm.days_to_harvest;
                    const daysDisplay = daysLeft > 0 ? daysLeft : (daysLeft === 0 ? 'Today' : 'Overdue');
                    
                    html += `
                        <tr onclick="FarmManager.viewFarm(${farm.id})">
                            <td>#${farm.id}</td
                            <td>${this.escapeHtml(farm.farmer_name || 'N/A')}</td
                            <td>${this.escapeHtml(farm.crop_name || 'N/A')}</td
                            <td>${farm.land_size_acres || 0}</td
                            <td>${farm.planting_date || 'N/A'}</td
                            <td>${farm.expected_harvest_date || 'N/A'}</td
                            <td style="color: ${daysLeft < 0 ? '#E45A5A' : 'inherit'}; font-weight: ${daysLeft < 0 ? '600' : 'normal'}">
                                ${daysDisplay}
                            </td
                            <td>
                                <span class="status-badge ${farm.status || 'Planted'}">${farm.status || 'Planted'}</span>
                            </td
                            <td>
                                <span class="status-badge ${farm.market_status === 'Listed' ? 'active' : 'inactive'}">
                                    ${farm.market_status || 'Not Listed'}
                                </span>
                            </td
                            <td onclick="event.stopPropagation()">
                                <div class="action-buttons">
                                    <button class="action-btn view" title="View Details" onclick="FarmManager.viewFarm(${farm.id})">👁️</button>
                                    <button class="action-btn edit" title="Edit Farm" onclick="FarmManager.editFarm(${farm.id})">✏️</button>
                                    <button class="action-btn more" title="More Options" onclick="FarmManager.moreOptions(${farm.id}, event)">⋯</button>
                                </div>
                            </td
                        </tr>
                    `;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            No farms found
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
                            <button class="pagination-btn" onclick="FarmManager.changePage(${this.currentPage - 1})" 
                                ${this.currentPage === 1 ? 'disabled' : ''}>←</button>
                            ${Array.from({ length: totalPages }, (_, i) => i + 1).map(page => `
                                <button class="pagination-btn ${page === this.currentPage ? 'active' : ''}" 
                                    onclick="FarmManager.changePage(${page})">${page}</button>
                            `).join('')}
                            <button class="pagination-btn" onclick="FarmManager.changePage(${this.currentPage + 1})"
                                ${this.currentPage === totalPages ? 'disabled' : ''}>→</button>
                        </div>
                    ` : ''}
                </div>
                
                <!-- Top Regions and Crops -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                    <div class="farms-table-container">
                        <h4 style="margin-bottom: 15px;">Top Regions</h4>
                        ${this.stats.top_regions && this.stats.top_regions.length > 0 ? `
                            <table class="farms-table" style="min-width: auto;">
                                <tbody>
                                    ${this.stats.top_regions.map(r => `
                                        <tr>
                                            <td>${this.escapeHtml(r.region_name || 'Unknown')}</td>
                                            <td style="text-align: right;">${r.count || 0} farms</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="color: var(--text-secondary); padding: 20px;">No region data</p>'}
                    </div>
                    
                    <div class="farms-table-container">
                        <h4 style="margin-bottom: 15px;">Top Crops</h4>
                        ${this.stats.top_crops && this.stats.top_crops.length > 0 ? `
                            <table class="farms-table" style="min-width: auto;">
                                <tbody>
                                    ${this.stats.top_crops.map(c => `
                                        <tr>
                                            <td>${this.escapeHtml(c.crop_name || 'Unknown')}</td>
                                            <td style="text-align: right;">${c.count || 0} farms</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="color: var(--text-secondary); padding: 20px;">No crop data</p>'}
                    </div>
                </div>
            `;
            
            contentArea.innerHTML = html;
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Farm Management page initialized');
        await FarmManager.init();
        
        // Listen for global search events
        window.addEventListener('globalSearch', (e) => {
            const searchTerm = e.detail.term;
            FarmManager.searchTerm = searchTerm.toLowerCase();
            FarmManager.currentPage = 1;
            FarmManager.filterFarms();
        });
    });

    // Make FarmManager globally available
    window.FarmManager = FarmManager;
    </script>

    <!-- Generate navigation scripts -->
    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function handlePostRequest($pdo, $action, $input) {
    switch($action) {
        case 'create_farm':
            createFarm($pdo, $input);
            break;
        case 'update_farm':
            updateFarm($pdo, $input);
            break;
        case 'delete_farm':
            deleteFarm($pdo, $input);
            break;
        case 'update_farm_status':
            updateFarmStatus($pdo, $input);
            break;
    }
}

function getFarms($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.farmer_id,
                pr.crop_id,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.latitude,
                pr.longitude,
                pr.region_name,
                pr.status,
                pr.notes,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                u.email as farmer_email,
                c.crop_name,
                c.baseline_yield_per_acre,
                c.total_maturity_days,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_to_harvest,
                CASE WHEN mi.id IS NOT NULL THEN 'Listed' ELSE 'Not Listed' END as market_status,
                mi.price_per_kg as listing_price,
                mi.listing_status
            FROM planting_requests pr
            INNER JOIN users u ON pr.farmer_id = u.id
            INNER JOIN crops c ON pr.crop_id = c.id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute();
        $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($farms);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getFarm($pdo) {
    try {
        $farmId = $_GET['farm_id'] ?? 0;
        
        if (!$farmId) {
            echo json_encode(['error' => 'Farm ID required']);
            return;
        }
        
        // Get farm details
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.farmer_id,
                pr.crop_id,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.latitude,
                pr.longitude,
                pr.region_name,
                pr.status,
                pr.notes,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                u.email as farmer_email,
                c.crop_name,
                c.baseline_yield_per_acre,
                c.total_maturity_days,
                c.price_per_kg,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_to_harvest,
                CASE WHEN mi.id IS NOT NULL THEN 'Listed' ELSE 'Not Listed' END as is_listed,
                mi.price_per_kg as listing_price
            FROM planting_requests pr
            INNER JOIN users u ON pr.farmer_id = u.id
            INNER JOIN crops c ON pr.crop_id = c.id
            LEFT JOIN marketplace_items mi ON pr.id = mi.planting_request_id
            WHERE pr.id = ?
        ");
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$farm) {
            echo json_encode(['error' => 'Farm not found']);
            return;
        }
        
        echo json_encode($farm);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getFarmers($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.is_active
            FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            WHERE ut.role_name = 'Farmer'
            ORDER BY u.full_name ASC
        ");
        $stmt->execute();
        $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($farmers);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCrops($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                crop_name,
                baseline_yield_per_acre,
                total_maturity_days,
                price_per_kg,
                is_active
            FROM crops
            WHERE is_active = 1
            ORDER BY crop_name ASC
        ");
        $stmt->execute();
        $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($crops);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getStats($pdo) {
    try {
        $stats = [];
        
        // Total farms
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests");
        $stats['total_farms'] = (int)$stmt->fetchColumn();
        
        // Active farms (Planted or Growing)
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests WHERE status IN ('Planted', 'Growing')");
        $stats['active_farms'] = (int)$stmt->fetchColumn();
        
        // Total land
        $stmt = $pdo->query("SELECT COALESCE(SUM(land_size_acres), 0) FROM planting_requests");
        $stats['total_land'] = round((float)$stmt->fetchColumn(), 2);
        
        // Expected yield
        $stmt = $pdo->query("SELECT COALESCE(SUM(expected_yield_kg), 0) FROM planting_requests WHERE status IN ('Planted', 'Growing')");
        $stats['expected_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        // Total farmers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            WHERE ut.role_name = 'Farmer'
        ");
        $stmt->execute();
        $stats['total_farmers'] = (int)$stmt->fetchColumn();
        
        // Due harvests (next 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM planting_requests 
            WHERE expected_harvest_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND status != 'Harvested'
        ");
        $stmt->execute();
        $stats['due_harvests'] = (int)$stmt->fetchColumn();
        
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

function createFarm($pdo, $data) {
    try {
        // Validate required fields
        $required = ['farmer_id', 'crop_id', 'land_size_acres', 'planting_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                echo json_encode(['success' => false, 'error' => "$field is required"]);
                return;
            }
        }
        
        // Get crop details for yield calculation
        $stmt = $pdo->prepare("SELECT baseline_yield_per_acre, total_maturity_days FROM crops WHERE id = ?");
        $stmt->execute([$data['crop_id']]);
        $crop = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$crop) {
            echo json_encode(['success' => false, 'error' => 'Invalid crop selected']);
            return;
        }
        
        // Calculate expected yield and harvest date
        $expected_yield = $crop['baseline_yield_per_acre'] * $data['land_size_acres'];
        $harvest_date = date('Y-m-d', strtotime($data['planting_date'] . ' + ' . $crop['total_maturity_days'] . ' days'));
        
        // Insert planting request
        $stmt = $pdo->prepare("
            INSERT INTO planting_requests (
                farmer_id, crop_id, land_size_acres, expected_yield_kg,
                planting_date, expected_harvest_date, latitude, longitude, 
                region_name, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Planted')
        ");
        
        $success = $stmt->execute([
            $data['farmer_id'],
            $data['crop_id'],
            $data['land_size_acres'],
            $expected_yield,
            $data['planting_date'],
            $harvest_date,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['region_name'] ?? null,
            $data['notes'] ?? null
        ]);
        
        if ($success) {
            $planting_id = $pdo->lastInsertId();
            
            // Log the action
            logSystemAction($pdo, $_SESSION['user_id'] ?? 1, 'CREATE', 'planting_requests', $planting_id, null, json_encode($data));
            
            echo json_encode(['success' => true, 'id' => $planting_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create farm']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateFarm($pdo, $data) {
    try {
        if (empty($data['farm_id'])) {
            echo json_encode(['success' => false, 'error' => 'Farm ID required']);
            return;
        }
        
        // Get current farm data
        $stmt = $pdo->prepare("SELECT * FROM planting_requests WHERE id = ?");
        $stmt->execute([$data['farm_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            echo json_encode(['success' => false, 'error' => 'Farm not found']);
            return;
        }
        
        // Get crop details if crop changed
        $crop_id = $data['crop_id'] ?? $current['crop_id'];
        $planting_date = $data['planting_date'] ?? $current['planting_date'];
        
        $stmt = $pdo->prepare("SELECT baseline_yield_per_acre, total_maturity_days FROM crops WHERE id = ?");
        $stmt->execute([$crop_id]);
        $crop = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate expected yield and harvest date
        $land_size = $data['land_size_acres'] ?? $current['land_size_acres'];
        $expected_yield = $crop['baseline_yield_per_acre'] * $land_size;
        $harvest_date = date('Y-m-d', strtotime($planting_date . ' + ' . $crop['total_maturity_days'] . ' days'));
        
        // Build update query dynamically
        $updates = [];
        $params = [];
        
        if (isset($data['farmer_id'])) {
            $updates[] = "farmer_id = ?";
            $params[] = $data['farmer_id'];
        }
        if (isset($data['crop_id'])) {
            $updates[] = "crop_id = ?";
            $params[] = $data['crop_id'];
        }
        if (isset($data['land_size_acres'])) {
            $updates[] = "land_size_acres = ?";
            $params[] = $data['land_size_acres'];
        }
        if (isset($data['planting_date'])) {
            $updates[] = "planting_date = ?";
            $params[] = $data['planting_date'];
        }
        
        $updates[] = "expected_yield_kg = ?";
        $params[] = $expected_yield;
        
        $updates[] = "expected_harvest_date = ?";
        $params[] = $harvest_date;
        
        if (array_key_exists('latitude', $data)) {
            $updates[] = "latitude = ?";
            $params[] = $data['latitude'];
        }
        if (array_key_exists('longitude', $data)) {
            $updates[] = "longitude = ?";
            $params[] = $data['longitude'];
        }
        if (isset($data['region_name'])) {
            $updates[] = "region_name = ?";
            $params[] = $data['region_name'];
        }
        if (isset($data['notes'])) {
            $updates[] = "notes = ?";
            $params[] = $data['notes'];
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            return;
        }
        
        $params[] = $data['farm_id'];
        $sql = "UPDATE planting_requests SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            // Log the action
            logSystemAction($pdo, $_SESSION['user_id'] ?? 1, 'UPDATE', 'planting_requests', $data['farm_id'], json_encode($current), json_encode($data));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update farm']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateFarmStatus($pdo, $data) {
    try {
        if (empty($data['farm_id']) || empty($data['status'])) {
            echo json_encode(['success' => false, 'error' => 'Farm ID and status required']);
            return;
        }
        
        $valid_statuses = ['Planted', 'Growing', 'Harvested'];
        if (!in_array($data['status'], $valid_statuses)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            return;
        }
        
        // Get current data for logging
        $stmt = $pdo->prepare("SELECT * FROM planting_requests WHERE id = ?");
        $stmt->execute([$data['farm_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("UPDATE planting_requests SET status = ? WHERE id = ?");
        $success = $stmt->execute([$data['status'], $data['farm_id']]);
        
        if ($success) {
            logSystemAction($pdo, $_SESSION['user_id'] ?? 1, 'UPDATE_STATUS', 'planting_requests', $data['farm_id'], json_encode($current), json_encode(['status' => $data['status']]));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update status']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteFarm($pdo, $data) {
    try {
        if (empty($data['farm_id'])) {
            echo json_encode(['success' => false, 'error' => 'Farm ID required']);
            return;
        }
        
        // Get current data for logging
        $stmt = $pdo->prepare("SELECT * FROM planting_requests WHERE id = ?");
        $stmt->execute([$data['farm_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete will cascade to related tables due to ON DELETE CASCADE
        $stmt = $pdo->prepare("DELETE FROM planting_requests WHERE id = ?");
        $success = $stmt->execute([$data['farm_id']]);
        
        if ($success) {
            logSystemAction($pdo, $_SESSION['user_id'] ?? 1, 'DELETE', 'planting_requests', $data['farm_id'], json_encode($current), null);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete farm']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function logSystemAction($pdo, $userId, $action, $entityType, $entityId, $oldData, $newData) {
    try {
        // Check if system_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (user_id, action, entity_type, entity_id, old_data, new_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $action, $entityType, $entityId, $oldData, $newData]);
        }
    } catch (Exception $e) {
        // Silently fail - logging shouldn't break the main operation
    }
}

?>