<?php
// listing.php - Admin Marketplace Listing Management for AgriMarketplace

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('Marketplace Listings', 'listing');

// Check if this is an API request
$isApiRequest = isset($_GET['action']) || isset($_POST['action']) || 
                ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isApiRequest) {
    ob_clean();
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
            // GET endpoints
            case 'get_listings':
                getListings($pdo);
                break;
            case 'get_listing_detail':
                getListingDetail($pdo);
                break;
            case 'get_stats':
                getStats($pdo);
                break;
            case 'get_eligible_plantings':
                getEligiblePlantings($pdo);
                break;
            case 'get_farmers_crops':
                getFarmersCrops($pdo);
                break;
                
            // POST endpoints
            case 'create_listing':
                createListing($pdo, $input);
                break;
            case 'update_listing':
                updateListing($pdo, $input);
                break;
            case 'update_status':
                updateListingStatus($pdo, $input);
                break;
            case 'update_price':
                updateListingPrice($pdo, $input);
                break;
            case 'delete_listing':
                deleteListing($pdo, $input);
                break;
            case 'bulk_update':
                bulkUpdateListings($pdo, $input);
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
    
    <link rel="stylesheet" href="listing.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <style>
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
            --purple: #7B1FA2;
            --teal: #009688;
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
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
        }

        .btn-primary:hover { background: var(--primary-green-light); }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover { background: var(--page-bg); }

        .btn-outline {
            background: transparent;
            color: var(--primary-green);
            border: 1px solid var(--primary-green);
        }

        .btn-outline:hover { background: var(--soft-green); }

        .btn-danger {
            background: var(--blocked);
            color: white;
        }

        .btn-danger:hover { background: #D32F2F; }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover { background: #F57C00; }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-xs {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: var(--page-bg);
            border-color: var(--primary-green);
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
            cursor: pointer;
        }

        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.active-filter { border: 2px solid var(--primary-green); }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters & Search */
        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .filter-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 7px 14px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .filter-btn:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }

        .filter-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 15px 9px 38px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 13px;
            background: var(--page-bg);
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-green);
            background: var(--card-bg);
        }

        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }

        select.filter-select {
            padding: 7px 12px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 12px;
            background: var(--card-bg);
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            cursor: pointer;
        }

        select.filter-select:focus {
            outline: none;
            border-color: var(--primary-green);
        }

        /* Table */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
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
            padding: 14px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--border-light);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        table th:hover {
            color: var(--primary-green);
        }

        table th .sort-icon {
            margin-left: 4px;
            font-size: 10px;
        }

        table td {
            padding: 14px 15px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        table tbody tr {
            transition: background 0.2s;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        table tbody tr.selected {
            background: var(--soft-green);
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Checkbox */
        .checkbox-cell {
            width: 40px;
        }

        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary-green);
        }

        /* Status Badges */
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-success { background: #e8f5e9; color: #2E7D32; }
        .badge-warning { background: #fff3e0; color: #E65100; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1565C0; }
        .badge-neutral { background: #f5f5f5; color: #757575; }
        .badge-purple { background: #f3e5f5; color: #7B1FA2; }

        /* Action Buttons in Table */
        .action-btns {
            display: flex;
            gap: 4px;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }

        .action-btn:hover { background: var(--page-bg); }
        .action-btn.view:hover { color: var(--info); background: #e3f2fd; }
        .action-btn.edit:hover { color: var(--warning); background: #fff3e0; }
        .action-btn.toggle:hover { color: var(--purple); background: #f3e5f5; }
        .action-btn.price:hover { color: var(--teal); background: #e0f2f1; }
        .action-btn.delete:hover { color: var(--blocked); background: #ffebee; }

        /* Bulk Actions */
        .bulk-actions {
            display: none;
            padding: 12px 20px;
            background: var(--soft-green);
            border-radius: 8px;
            margin-bottom: 15px;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-count {
            font-weight: 600;
            font-size: 14px;
            color: var(--primary-green);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.25s ease;
        }

        .modal-lg {
            max-width: 800px;
        }

        .modal-sm {
            max-width: 400px;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .close-modal {
            width: 34px;
            height: 34px;
            border: none;
            background: var(--page-bg);
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: var(--text-secondary);
        }

        .close-modal:hover {
            background: var(--blocked);
            color: white;
        }

        .modal-body {
            padding: 25px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(31, 122, 76, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-hint {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .form-error {
            font-size: 11px;
            color: var(--blocked);
            margin-top: 4px;
            display: none;
        }

        /* Info Alert */
        .info-alert {
            background: #e3f2fd;
            border-left: 4px solid var(--info);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .info-alert.success {
            background: #e8f5e9;
            border-left-color: var(--active);
        }

        .info-alert.warning {
            background: #fff3e0;
            border-left-color: var(--warning);
        }

        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-card {
            background: var(--page-bg);
            padding: 15px;
            border-radius: 8px;
        }

        .detail-card h4 {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 13px;
            border-bottom: 1px solid var(--border-light);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-secondary);
        }

        .detail-value {
            font-weight: 500;
        }

        /* Quick Edit Input */
        .quick-edit {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quick-edit input {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid var(--border-light);
            border-radius: 4px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            text-align: right;
        }

        .quick-edit input:focus {
            outline: none;
            border-color: var(--primary-green);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            padding: 20px;
        }

        .pagination-btn {
            padding: 8px 14px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .pagination-btn:hover:not(:disabled) {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }

        .pagination-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .pagination-info {
            font-size: 12px;
            color: var(--text-secondary);
            padding: 0 10px;
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
            max-width: 400px;
        }

        .toast.show { transform: translateX(0); }
        .toast.success { background: #2E7D32; }
        .toast.error { background: #c62828; }
        .toast.warning { background: #F57C00; }
        .toast.info { background: #1565C0; }

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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Price highlight */
        .price-highlight {
            font-weight: 600;
            color: var(--primary-green);
        }

        .total-highlight {
            font-weight: 700;
            color: var(--info);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .filters-bar { flex-direction: column; }
            .search-box { min-width: 100%; }
            .detail-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .stat-value { font-size: 22px; }
            table th, table td { padding: 10px 8px; font-size: 12px; }
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
                    <span>Loading marketplace listings...</span>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Listing Modal -->
    <div class="modal" id="listingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="listingModalTitle">Create New Listing</h3>
                <button class="close-modal" onclick="ListingManager.closeModal('listingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="listingForm" onsubmit="return false;">
                    <input type="hidden" id="editListingId">
                    
                    <div class="form-group">
                        <label>Select Planting Request *</label>
                        <select id="plantingRequestSelect" required onchange="ListingManager.onPlantingSelect()">
                            <option value="">-- Choose a planting request --</option>
                        </select>
                        <div class="form-hint">Only active plantings that aren't already listed</div>
                    </div>
                    
                    <div id="plantingDetailsBox" class="info-alert" style="display: none;">
                        <div><strong>👨‍🌾 Farmer:</strong> <span id="pdFarmer"></span></div>
                        <div><strong>🌾 Crop:</strong> <span id="pdCrop"></span></div>
                        <div><strong>📏 Land:</strong> <span id="pdLand"></span> acres</div>
                        <div><strong>📦 Expected Yield:</strong> <span id="pdYield"></span> kg</div>
                        <div><strong>📅 Harvest Date:</strong> <span id="pdHarvest"></span></div>
                        <div><strong>📍 Region:</strong> <span id="pdRegion"></span></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Available Quantity (kg) *</label>
                            <input type="number" id="availableQty" step="0.01" min="0.01" required placeholder="0.00">
                            <div class="form-hint">Cannot exceed expected yield</div>
                            <div class="form-error" id="qtyError">Quantity exceeds expected yield</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Price per KG (KES) *</label>
                            <input type="number" id="pricePerKg" step="0.01" min="0.01" required placeholder="0.00">
                            <div class="form-hint">Market selling price</div>
                        </div>
                    </div>
                    
                    <div class="info-alert success" id="totalValueBox" style="display: none;">
                        <strong>💰 Total Listing Value:</strong> KES <span id="totalValue">0.00</span>
                    </div>
                    
                    <div class="form-group">
                        <label>Listing Status</label>
                        <select id="listingStatusSelect">
                            <option value="Active">Active - Visible to buyers</option>
                            <option value="Hidden">Hidden - Not visible</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="ListingManager.closeModal('listingModal')">Cancel</button>
                <button class="btn btn-primary" onclick="ListingManager.saveListing()">
                    <span id="saveBtnText">Create Listing</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>Listing Details</h3>
                <button class="close-modal" onclick="ListingManager.closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Price Update Modal -->
    <div class="modal" id="priceModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Update Price</h3>
                <button class="close-modal" onclick="ListingManager.closeModal('priceModal')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="priceListingId">
                <div class="form-group">
                    <label>Current Price: KES <span id="currentPriceDisplay"></span>/kg</label>
                    <label>New Price per KG (KES) *</label>
                    <input type="number" id="newPrice" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="ListingManager.closeModal('priceModal')">Cancel</button>
                <button class="btn btn-primary" onclick="ListingManager.updatePrice()">Update Price</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-modal" onclick="ListingManager.closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <p style="margin-bottom: 10px;">Are you sure you want to delete this listing?</p>
                <div class="info-alert warning">
                    ⚠️ This action cannot be undone. Orders associated with this listing may be affected.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="ListingManager.closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete Listing</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
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
                const text = await response.text();
                
                let result;
                try { result = JSON.parse(text); }
                catch (e) { console.error('Parse error:', e, text); throw new Error('Invalid server response'); }
                
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

    const ListingManager = {
        listings: [],
        filteredListings: [],
        currentFilter: 'all',
        searchTerm: '',
        sortField: 'listed_date',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 20,
        selectedListings: new Set(),
        stats: {},
        eligiblePlantings: [],
        editListingId: null,
        deleteListingId: null,
        priceListingId: null,

        init: async function() {
            console.log('Listing Manager initializing...');
            await this.loadStats();
            await this.loadEligiblePlantings();
            await this.loadListings();
            this.render();
            this.setupEventListeners();
            
            <?php if (isset($_SESSION['user_name'])): ?>
            const adminName = document.getElementById('adminName');
            const adminAvatar = document.getElementById('adminAvatar');
            if (adminName) adminName.textContent = '<?php echo $_SESSION['user_name']; ?>';
            if (adminAvatar) adminAvatar.textContent = '<?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?>';
            <?php endif; ?>
        },

        setupEventListeners: function() {
            // Search debounce
            let searchTimeout;
            document.addEventListener('input', (e) => {
                if (e.target.id === 'listingSearch') {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.searchTerm = e.target.value.toLowerCase();
                        this.currentPage = 1;
                        this.filterListings();
                    }, 300);
                }
            });

            // Price/quantity live calculation
            document.addEventListener('input', (e) => {
                if (e.target.id === 'availableQty' || e.target.id === 'pricePerKg') {
                    this.updateTotalValue();
                }
            });

            // Modal close on outside click
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    e.target.classList.remove('active');
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
                }
            });
        },

        loadListings: async function() {
            const result = await API.get('get_listings');
            if (!result.error) {
                this.listings = Array.isArray(result) ? result : [];
                this.filteredListings = [...this.listings];
                this.applySort();
            }
        },

        loadStats: async function() {
            const result = await API.get('get_stats');
            if (!result.error) this.stats = result;
        },

        loadEligiblePlantings: async function() {
            const result = await API.get('get_eligible_plantings');
            if (!result.error) {
                this.eligiblePlantings = result;
                this.populatePlantingSelect();
            }
        },

        populatePlantingSelect: function(selectedId = null) {
            const select = document.getElementById('plantingRequestSelect');
            select.innerHTML = '<option value="">-- Choose a planting request --</option>';
            
            this.eligiblePlantings.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = `${p.farmer_name || 'Unknown'} - ${p.crop_name || 'N/A'} (${this.formatNumber(p.expected_yield_kg)} kg)`;
                option.dataset.farmer = p.farmer_name || '';
                option.dataset.crop = p.crop_name || '';
                option.dataset.land = p.land_size_acres || 0;
                option.dataset.yield = p.expected_yield_kg || 0;
                option.dataset.harvest = p.expected_harvest_date || '';
                option.dataset.region = p.region_name || 'N/A';
                if (selectedId && p.id == selectedId) option.selected = true;
                select.appendChild(option);
            });
        },

        onPlantingSelect: function() {
            const select = document.getElementById('plantingRequestSelect');
            const detailsBox = document.getElementById('plantingDetailsBox');
            const selectedOption = select.options[select.selectedIndex];
            
            if (!select.value) {
                detailsBox.style.display = 'none';
                return;
            }
            
            document.getElementById('pdFarmer').textContent = selectedOption.dataset.farmer;
            document.getElementById('pdCrop').textContent = selectedOption.dataset.crop;
            document.getElementById('pdLand').textContent = selectedOption.dataset.land;
            document.getElementById('pdYield').textContent = this.formatNumber(selectedOption.dataset.yield);
            document.getElementById('pdHarvest').textContent = selectedOption.dataset.harvest;
            document.getElementById('pdRegion').textContent = selectedOption.dataset.region;
            detailsBox.style.display = 'block';
            
            // Set max quantity
            const qtyInput = document.getElementById('availableQty');
            qtyInput.max = selectedOption.dataset.yield;
            qtyInput.placeholder = `Max: ${this.formatNumber(selectedOption.dataset.yield)} kg`;
            
            this.updateTotalValue();
        },

        updateTotalValue: function() {
            const qty = parseFloat(document.getElementById('availableQty').value) || 0;
            const price = parseFloat(document.getElementById('pricePerKg').value) || 0;
            const total = qty * price;
            const totalBox = document.getElementById('totalValueBox');
            const qtyError = document.getElementById('qtyError');
            
            document.getElementById('totalValue').textContent = this.formatNumber(total);
            totalBox.style.display = (qty > 0 && price > 0) ? 'block' : 'none';
            
            // Validate quantity
            const maxQty = parseFloat(document.getElementById('availableQty').max);
            if (maxQty && qty > maxQty) {
                qtyError.style.display = 'block';
            } else {
                qtyError.style.display = 'none';
            }
        },

        filterListings: function() {
            this.filteredListings = this.listings.filter(listing => {
                const matchesSearch = !this.searchTerm || 
                    (listing.farmer_name && listing.farmer_name.toLowerCase().includes(this.searchTerm)) ||
                    (listing.crop_name && listing.crop_name.toLowerCase().includes(this.searchTerm)) ||
                    (listing.region_name && listing.region_name.toLowerCase().includes(this.searchTerm)) ||
                    (listing.id && listing.id.toString().includes(this.searchTerm));
                
                if (!matchesSearch) return false;
                
                switch(this.currentFilter) {
                    case 'active': return listing.listing_status === 'Active';
                    case 'hidden': return listing.listing_status === 'Hidden';
                    case 'sold_out': return listing.listing_status === 'Sold Out';
                    case 'available': return listing.listing_status === 'Active' && parseFloat(listing.available_quantity_kg) > 0;
                    case 'low_stock': return listing.listing_status === 'Active' && parseFloat(listing.available_quantity_kg) > 0 && parseFloat(listing.available_quantity_kg) < 100;
                    default: return true;
                }
            });
            
            this.applySort();
            this.render();
        },

        setFilter: function(filter) {
            this.currentFilter = filter;
            this.currentPage = 1;
            this.filterListings();
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === filter);
            });
            
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.toggle('active-filter', card.dataset.filter === filter);
            });
        },

        sortBy: function(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'desc';
            }
            this.applySort();
            this.render();
        },

        applySort: function() {
            const dir = this.sortDirection === 'asc' ? 1 : -1;
            this.filteredListings.sort((a, b) => {
                let valA = a[this.sortField];
                let valB = b[this.sortField];
                
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();
                
                if (valA === null || valA === undefined) valA = '';
                if (valB === null || valB === undefined) valB = '';
                
                if (typeof valA === 'number' && typeof valB === 'number') {
                    return (valA - valB) * dir;
                }
                return String(valA).localeCompare(String(valB)) * dir;
            });
        },

        getPaginatedListings: function() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredListings.slice(start, start + this.itemsPerPage);
        },

        getTotalPages: function() {
            return Math.ceil(this.filteredListings.length / this.itemsPerPage) || 1;
        },

        changePage: function(page) {
            const total = this.getTotalPages();
            if (page >= 1 && page <= total) {
                this.currentPage = page;
                this.render();
            }
        },

        toggleSelectAll: function(checked) {
            const paginated = this.getPaginatedListings();
            if (checked) {
                paginated.forEach(l => this.selectedListings.add(l.id));
            } else {
                paginated.forEach(l => this.selectedListings.delete(l.id));
            }
            this.render();
        },

        toggleSelect: function(listingId) {
            if (this.selectedListings.has(listingId)) {
                this.selectedListings.delete(listingId);
            } else {
                this.selectedListings.add(listingId);
            }
            this.updateBulkActions();
        },

        updateBulkActions: function() {
            const bulkBar = document.getElementById('bulkActions');
            const bulkCount = document.getElementById('bulkCount');
            
            if (this.selectedListings.size > 0) {
                bulkBar.classList.add('show');
                bulkCount.textContent = `${this.selectedListings.size} selected`;
            } else {
                bulkBar.classList.remove('show');
            }
            
            // Update select all checkbox
            const paginated = this.getPaginatedListings();
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = paginated.length > 0 && paginated.every(l => this.selectedListings.has(l.id));
                selectAll.indeterminate = paginated.some(l => this.selectedListings.has(l.id)) && !selectAll.checked;
            }
        },

        // CRUD Operations
        openCreateModal: async function() {
            document.getElementById('listingModalTitle').textContent = 'Create New Listing';
            document.getElementById('editListingId').value = '';
            document.getElementById('listingForm').reset();
            document.getElementById('plantingDetailsBox').style.display = 'none';
            document.getElementById('totalValueBox').style.display = 'none';
            document.getElementById('listingStatusSelect').value = 'Active';
            document.getElementById('saveBtnText').textContent = 'Create Listing';
            
            await this.loadEligiblePlantings();
            this.populatePlantingSelect();
            
            document.getElementById('listingModal').classList.add('active');
        },

        openEditModal: async function(listingId) {
            const result = await API.get('get_listing_detail', { listing_id: listingId });
            if (result && !result.error) {
                document.getElementById('listingModalTitle').textContent = 'Edit Listing #' + listingId;
                document.getElementById('editListingId').value = result.id;
                document.getElementById('listingStatusSelect').value = result.listing_status;
                document.getElementById('availableQty').value = result.available_quantity_kg;
                document.getElementById('pricePerKg').value = result.price_per_kg;
                document.getElementById('saveBtnText').textContent = 'Update Listing';
                
                await this.loadEligiblePlantings();
                this.populatePlantingSelect(result.planting_request_id);
                
                // Show planting details
                const select = document.getElementById('plantingRequestSelect');
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && select.value) {
                    this.onPlantingSelect();
                }
                
                this.updateTotalValue();
                document.getElementById('listingModal').classList.add('active');
            } else {
                this.showToast('Error loading listing: ' + (result?.error || 'Unknown error'), 'error');
            }
        },

        saveListing: async function() {
            const listingId = document.getElementById('editListingId').value;
            const plantingRequestId = document.getElementById('plantingRequestSelect').value;
            const availableQty = parseFloat(document.getElementById('availableQty').value);
            const pricePerKg = parseFloat(document.getElementById('pricePerKg').value);
            const listingStatus = document.getElementById('listingStatusSelect').value;
            
            if (!plantingRequestId) {
                this.showToast('Please select a planting request', 'error');
                return;
            }
            if (!availableQty || availableQty <= 0) {
                this.showToast('Please enter a valid quantity', 'error');
                return;
            }
            if (!pricePerKg || pricePerKg <= 0) {
                this.showToast('Please enter a valid price', 'error');
                return;
            }
            
            // Validate quantity against expected yield
            const maxQty = parseFloat(document.getElementById('availableQty').max);
            if (maxQty && availableQty > maxQty) {
                this.showToast('Quantity exceeds expected yield', 'error');
                return;
            }
            
            const data = {
                listing_id: listingId || null,
                planting_request_id: plantingRequestId,
                available_quantity_kg: availableQty,
                price_per_kg: pricePerKg,
                listing_status: listingStatus
            };
            
            let result;
            if (listingId) {
                result = await API.post('update_listing', data);
            } else {
                result = await API.post('create_listing', data);
            }
            
            if (result && result.success) {
                this.closeModal('listingModal');
                await this.loadListings();
                await this.loadStats();
                this.filterListings();
                this.showToast(listingId ? 'Listing updated successfully!' : 'Listing created successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to save listing'), 'error');
            }
        },

        toggleStatus: async function(listingId, currentStatus) {
            const newStatus = currentStatus === 'Active' ? 'Hidden' : 'Active';
            
            const result = await API.post('update_status', {
                listing_id: listingId,
                listing_status: newStatus
            });
            
            if (result && result.success) {
                await this.loadListings();
                await this.loadStats();
                this.filterListings();
                this.showToast(`Listing ${newStatus === 'Active' ? 'activated' : 'hidden'} successfully!`, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to update status'), 'error');
            }
        },

        openPriceModal: function(listingId) {
            const listing = this.listings.find(l => l.id === listingId);
            if (!listing) return;
            
            this.priceListingId = listingId;
            document.getElementById('priceListingId').value = listingId;
            document.getElementById('currentPriceDisplay').textContent = this.formatNumber(listing.price_per_kg);
            document.getElementById('newPrice').value = listing.price_per_kg;
            document.getElementById('priceModal').classList.add('active');
            document.getElementById('newPrice').focus();
        },

        updatePrice: async function() {
            const listingId = document.getElementById('priceListingId').value;
            const newPrice = parseFloat(document.getElementById('newPrice').value);
            
            if (!newPrice || newPrice <= 0) {
                this.showToast('Please enter a valid price', 'error');
                return;
            }
            
            const result = await API.post('update_price', {
                listing_id: listingId,
                price_per_kg: newPrice
            });
            
            if (result && result.success) {
                this.closeModal('priceModal');
                await this.loadListings();
                this.filterListings();
                this.showToast('Price updated to KES ' + this.formatNumber(newPrice) + '/kg', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to update price'), 'error');
            }
        },

        openDeleteModal: function(listingId) {
            this.deleteListingId = listingId;
            document.getElementById('confirmDeleteBtn').onclick = () => this.confirmDelete();
            document.getElementById('deleteModal').classList.add('active');
        },

        confirmDelete: async function() {
            if (!this.deleteListingId) return;
            
            const result = await API.post('delete_listing', { listing_id: this.deleteListingId });
            
            if (result && result.success) {
                this.closeModal('deleteModal');
                this.selectedListings.delete(this.deleteListingId);
                await this.loadListings();
                await this.loadStats();
                this.filterListings();
                this.showToast('Listing deleted successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to delete listing'), 'error');
                this.closeModal('deleteModal');
            }
        },

        bulkActivate: async function() {
            if (this.selectedListings.size === 0) return;
            if (!confirm(`Activate ${this.selectedListings.size} listing(s)?`)) return;
            
            const result = await API.post('bulk_update', {
                listing_ids: Array.from(this.selectedListings),
                listing_status: 'Active'
            });
            
            if (result && result.success) {
                this.selectedListings.clear();
                await this.loadListings();
                await this.loadStats();
                this.filterListings();
                this.showToast(`${result.updated || 0} listings activated`, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Bulk update failed'), 'error');
            }
        },

        bulkHide: async function() {
            if (this.selectedListings.size === 0) return;
            if (!confirm(`Hide ${this.selectedListings.size} listing(s)?`)) return;
            
            const result = await API.post('bulk_update', {
                listing_ids: Array.from(this.selectedListings),
                listing_status: 'Hidden'
            });
            
            if (result && result.success) {
                this.selectedListings.clear();
                await this.loadListings();
                await this.loadStats();
                this.filterListings();
                this.showToast(`${result.updated || 0} listings hidden`, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Bulk update failed'), 'error');
            }
        },

        viewDetail: async function(listingId) {
            const result = await API.get('get_listing_detail', { listing_id: listingId });
            if (result && !result.error) {
                this.showDetail(result);
            } else {
                this.showToast('Error loading details', 'error');
            }
        },

        showDetail: function(listing) {
            const content = document.getElementById('detailContent');
            
            const statusBadge = listing.listing_status === 'Active' ? 'badge-success' :
                               listing.listing_status === 'Hidden' ? 'badge-neutral' : 'badge-danger';
            
            const totalValue = (parseFloat(listing.available_quantity_kg) * parseFloat(listing.price_per_kg)).toFixed(2);
            
            content.innerHTML = `
                <div class="detail-grid">
                    <div class="detail-card">
                        <h4>📋 Listing Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value"><span class="badge ${statusBadge}">${listing.listing_status}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Available Quantity</span>
                            <span class="detail-value">${this.formatNumber(listing.available_quantity_kg)} kg</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Price per KG</span>
                            <span class="detail-value price-highlight">KES ${this.formatNumber(listing.price_per_kg)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Value</span>
                            <span class="detail-value total-highlight">KES ${this.formatNumber(totalValue)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Listed Date</span>
                            <span class="detail-value">${listing.listed_date || 'N/A'}</span>
                        </div>
                        ${listing.sold_out_date ? `<div class="detail-item"><span class="detail-label">Sold Out</span><span class="detail-value">${listing.sold_out_date}</span></div>` : ''}
                    </div>
                    
                    <div class="detail-card">
                        <h4>🌾 Planting Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Farmer</span>
                            <span class="detail-value">${this.escapeHtml(listing.farmer_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Crop</span>
                            <span class="detail-value">${this.escapeHtml(listing.crop_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Land Size</span>
                            <span class="detail-value">${listing.land_size_acres || 0} acres</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Expected Yield</span>
                            <span class="detail-value">${this.formatNumber(listing.expected_yield_kg || 0)} kg</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Region</span>
                            <span class="detail-value">${this.escapeHtml(listing.region_name || 'N/A')}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>📅 Dates</h4>
                        <div class="detail-item">
                            <span class="detail-label">Planting Date</span>
                            <span class="detail-value">${listing.planting_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Harvest Date</span>
                            <span class="detail-value">${listing.expected_harvest_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Days to Harvest</span>
                            <span class="detail-value">${listing.days_to_harvest !== null ? listing.days_to_harvest + ' days' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Planting Status</span>
                            <span class="detail-value"><span class="badge badge-info">${listing.planting_status || 'N/A'}</span></span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>📦 Order Summary</h4>
                        <div class="detail-item">
                            <span class="detail-label">Total Orders</span>
                            <span class="detail-value">${listing.total_orders || 0}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity Sold</span>
                            <span class="detail-value">${this.formatNumber(listing.total_quantity_sold || 0)} kg</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Revenue</span>
                            <span class="detail-value total-highlight">KES ${this.formatNumber(listing.total_revenue || 0)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Pending Orders</span>
                            <span class="detail-value"><span class="badge badge-warning">${listing.pending_orders || 0}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Delivered Orders</span>
                            <span class="detail-value"><span class="badge badge-success">${listing.delivered_orders || 0}</span></span>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detailModal').classList.add('active');
        },

        closeModal: function(modalId) {
            document.getElementById(modalId)?.classList.remove('active');
        },

        showToast: function(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            setTimeout(() => toast.classList.remove('show'), 3500);
        },

        formatNumber: function(num) {
            if (num === null || num === undefined) return '0';
            return parseFloat(num).toLocaleString('en-KE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        getSortIcon: function(field) {
            if (this.sortField !== field) return ' ↕';
            return this.sortDirection === 'asc' ? ' ↑' : ' ↓';
        },

        render: function() {
            const contentArea = document.getElementById('contentArea');
            if (!contentArea) return;
            
            const paginated = this.getPaginatedListings();
            const totalPages = this.getTotalPages();
            const startItem = ((this.currentPage - 1) * this.itemsPerPage) + 1;
            const endItem = Math.min(this.currentPage * this.itemsPerPage, this.filteredListings.length);
            const totalListings = this.filteredListings.length;
            
            // Build table rows
            let tableRows = '';
            if (paginated.length > 0) {
                paginated.forEach(listing => {
                    const statusBadge = listing.listing_status === 'Active' ? 'badge-success' :
                                       listing.listing_status === 'Hidden' ? 'badge-neutral' : 'badge-danger';
                    
                    const totalValue = parseFloat(listing.available_quantity_kg || 0) * parseFloat(listing.price_per_kg || 0);
                    const isSelected = this.selectedListings.has(listing.id);
                    
                    tableRows += `
                        <tr class="${isSelected ? 'selected' : ''}">
                            <td class="checkbox-cell">
                                <input type="checkbox" ${isSelected ? 'checked' : ''} 
                                    onclick="ListingManager.toggleSelect(${listing.id})">
                            </td>
                            <td><strong>#${listing.id}</strong></td>
                            <td>
                                <div style="font-weight: 500;">${this.escapeHtml(listing.farmer_name || 'N/A')}</div>
                                <div style="font-size: 11px; color: var(--text-secondary);">${this.escapeHtml(listing.farmer_phone || '')}</div>
                            </td>
                            <td>${this.escapeHtml(listing.crop_name || 'N/A')}</td>
                            <td class="text-right">${this.formatNumber(listing.available_quantity_kg)} kg</td>
                            <td class="text-right price-highlight">KES ${this.formatNumber(listing.price_per_kg)}</td>
                            <td class="text-right total-highlight">KES ${this.formatNumber(totalValue)}</td>
                            <td><span class="badge ${statusBadge}">${listing.listing_status}</span></td>
                            <td>${listing.expected_harvest_date || 'N/A'}</td>
                            <td>${this.escapeHtml(listing.region_name || 'N/A')}</td>
                            <td>${listing.listed_date || 'N/A'}</td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn view" title="View Details" onclick="ListingManager.viewDetail(${listing.id})">👁️</button>
                                    <button class="action-btn edit" title="Edit" onclick="ListingManager.openEditModal(${listing.id})">✏️</button>
                                    <button class="action-btn toggle" title="${listing.listing_status === 'Active' ? 'Hide' : 'Activate'}" onclick="ListingManager.toggleStatus(${listing.id}, '${listing.listing_status}')">🔄</button>
                                    <button class="action-btn price" title="Update Price" onclick="ListingManager.openPriceModal(${listing.id})">💲</button>
                                    <button class="action-btn delete" title="Delete" onclick="ListingManager.openDeleteModal(${listing.id})">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tableRows = `
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <h3>No Listings Found</h3>
                                <p>${this.searchTerm ? 'No listings match your search criteria.' : 'Start by creating your first marketplace listing.'}</p>
                                ${!this.searchTerm ? '<button class="btn btn-primary" onclick="ListingManager.openCreateModal()">➕ Create First Listing</button>' : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            // Build pagination
            let paginationHtml = '';
            if (totalPages > 1) {
                let pages = '';
                const maxVisible = 5;
                let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                
                if (endPage - startPage < maxVisible - 1) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    pages += `<button class="pagination-btn ${i === this.currentPage ? 'active' : ''}" onclick="ListingManager.changePage(${i})">${i}</button>`;
                }
                
                paginationHtml = `
                    <div class="pagination">
                        <button class="pagination-btn" onclick="ListingManager.changePage(1)" ${this.currentPage === 1 ? 'disabled' : ''}>«</button>
                        <button class="pagination-btn" onclick="ListingManager.changePage(${this.currentPage - 1})" ${this.currentPage === 1 ? 'disabled' : ''}>‹</button>
                        ${pages}
                        <button class="pagination-btn" onclick="ListingManager.changePage(${this.currentPage + 1})" ${this.currentPage === totalPages ? 'disabled' : ''}>›</button>
                        <button class="pagination-btn" onclick="ListingManager.changePage(${totalPages})" ${this.currentPage === totalPages ? 'disabled' : ''}>»</button>
                        <span class="pagination-info">${startItem}-${endItem} of ${totalListings}</span>
                    </div>
                `;
            }
            
            const html = `
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <h2>Marketplace Listings</h2>
                        <p>Manage product listings visible to buyers</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline btn-sm" onclick="ListingManager.loadListings().then(() => { ListingManager.filterListings(); ListingManager.showToast('Refreshed', 'info'); })">🔄 Refresh</button>
                        <button class="btn btn-primary" onclick="ListingManager.openCreateModal()">➕ Create Listing</button>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card ${this.currentFilter === 'all' ? 'active-filter' : ''}" data-filter="all" onclick="ListingManager.setFilter('all')">
                        <div class="stat-icon">📋</div>
                        <div class="stat-value">${this.stats.total_listings || 0}</div>
                        <div class="stat-label">Total Listings</div>
                    </div>
                    <div class="stat-card ${this.currentFilter === 'active' ? 'active-filter' : ''}" data-filter="active" onclick="ListingManager.setFilter('active')">
                        <div class="stat-icon">✅</div>
                        <div class="stat-value">${this.stats.active_listings || 0}</div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-card ${this.currentFilter === 'hidden' ? 'active-filter' : ''}" data-filter="hidden" onclick="ListingManager.setFilter('hidden')">
                        <div class="stat-icon">👁️‍🗨️</div>
                        <div class="stat-value">${this.stats.hidden_listings || 0}</div>
                        <div class="stat-label">Hidden</div>
                    </div>
                    <div class="stat-card ${this.currentFilter === 'sold_out' ? 'active-filter' : ''}" data-filter="sold_out" onclick="ListingManager.setFilter('sold_out')">
                        <div class="stat-icon">🏁</div>
                        <div class="stat-value">${this.stats.sold_out_listings || 0}</div>
                        <div class="stat-label">Sold Out</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(this.stats.total_market_value || 0)}</div>
                        <div class="stat-label">Market Value</div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <span class="bulk-count" id="bulkCount">0 selected</span>
                    <button class="btn btn-primary btn-sm" onclick="ListingManager.bulkActivate()">✅ Activate All</button>
                    <button class="btn btn-secondary btn-sm" onclick="ListingManager.bulkHide()">👁️‍🗨️ Hide All</button>
                    <button class="btn btn-outline btn-sm" onclick="ListingManager.selectedListings.clear(); ListingManager.render();">✖ Clear Selection</button>
                </div>
                
                <!-- Filters -->
                <div class="filters-bar">
                    <div class="filter-group">
                        <button class="filter-btn ${this.currentFilter === 'all' ? 'active' : ''}" data-filter="all" onclick="ListingManager.setFilter('all')">All</button>
                        <button class="filter-btn ${this.currentFilter === 'active' ? 'active' : ''}" data-filter="active" onclick="ListingManager.setFilter('active')">Active</button>
                        <button class="filter-btn ${this.currentFilter === 'hidden' ? 'active' : ''}" data-filter="hidden" onclick="ListingManager.setFilter('hidden')">Hidden</button>
                        <button class="filter-btn ${this.currentFilter === 'sold_out' ? 'active' : ''}" data-filter="sold_out" onclick="ListingManager.setFilter('sold_out')">Sold Out</button>
                        <button class="filter-btn ${this.currentFilter === 'available' ? 'active' : ''}" data-filter="available" onclick="ListingManager.setFilter('available')">Has Stock</button>
                        <button class="filter-btn ${this.currentFilter === 'low_stock' ? 'active' : ''}" data-filter="low_stock" onclick="ListingManager.setFilter('low_stock')">Low Stock</button>
                    </div>
                    
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="listingSearch" placeholder="Search by ID, farmer, crop, region..." value="${this.escapeHtml(this.searchTerm)}">
                    </div>
                </div>
                
                <!-- Table -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="selectAll" onchange="ListingManager.toggleSelectAll(this.checked)">
                                    </th>
                                    <th onclick="ListingManager.sortBy('id')">ID${this.getSortIcon('id')}</th>
                                    <th onclick="ListingManager.sortBy('farmer_name')">Farmer${this.getSortIcon('farmer_name')}</th>
                                    <th onclick="ListingManager.sortBy('crop_name')">Crop${this.getSortIcon('crop_name')}</th>
                                    <th class="text-right" onclick="ListingManager.sortBy('available_quantity_kg')">Qty (kg)${this.getSortIcon('available_quantity_kg')}</th>
                                    <th class="text-right" onclick="ListingManager.sortBy('price_per_kg')">Price/kg${this.getSortIcon('price_per_kg')}</th>
                                    <th class="text-right">Total Value</th>
                                    <th onclick="ListingManager.sortBy('listing_status')">Status${this.getSortIcon('listing_status')}</th>
                                    <th onclick="ListingManager.sortBy('expected_harvest_date')">Harvest${this.getSortIcon('expected_harvest_date')}</th>
                                    <th onclick="ListingManager.sortBy('region_name')">Region${this.getSortIcon('region_name')}</th>
                                    <th onclick="ListingManager.sortBy('listed_date')">Listed${this.getSortIcon('listed_date')}</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                    ${paginationHtml}
                </div>
            `;
            
            contentArea.innerHTML = html;
            this.updateBulkActions();
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Listing page initialized');
        await ListingManager.init();
    });

    window.ListingManager = ListingManager;
    </script>

    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function getListings($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                mi.id,
                mi.planting_request_id,
                mi.available_quantity_kg,
                mi.price_per_kg,
                mi.listing_status,
                DATE_FORMAT(mi.listed_date, '%Y-%m-%d') as listed_date,
                DATE_FORMAT(mi.sold_out_date, '%Y-%m-%d') as sold_out_date,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                c.crop_name,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.region_name,
                pr.status as planting_status,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_to_harvest,
                (SELECT COUNT(*) FROM orders WHERE marketplace_item_id = mi.id) as total_orders,
                (SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders WHERE marketplace_item_id = mi.id) as total_quantity_sold
            FROM marketplace_items mi
            JOIN planting_requests pr ON mi.planting_request_id = pr.id
            JOIN users u ON pr.farmer_id = u.id
            JOIN crops c ON pr.crop_id = c.id
            ORDER BY mi.listed_date DESC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getListingDetail($pdo) {
    try {
        $listingId = $_GET['listing_id'] ?? 0;
        if (!$listingId) { echo json_encode(['error' => 'Listing ID required']); return; }
        
        $stmt = $pdo->prepare("
            SELECT 
                mi.*,
                DATE_FORMAT(mi.listed_date, '%Y-%m-%d %H:%i') as listed_date,
                DATE_FORMAT(mi.sold_out_date, '%Y-%m-%d') as sold_out_date,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                u.email as farmer_email,
                c.crop_name,
                c.baseline_yield_per_acre,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.region_name,
                pr.status as planting_status,
                DATEDIFF(pr.expected_harvest_date, CURDATE()) as days_to_harvest,
                (SELECT COUNT(*) FROM orders WHERE marketplace_item_id = mi.id) as total_orders,
                (SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders WHERE marketplace_item_id = mi.id) as total_quantity_sold,
                (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE marketplace_item_id = mi.id) as total_revenue,
                (SELECT COUNT(*) FROM orders WHERE marketplace_item_id = mi.id AND escrow_status = 'Pending') as pending_orders,
                (SELECT COUNT(*) FROM orders WHERE marketplace_item_id = mi.id AND escrow_status = 'Delivered') as delivered_orders
            FROM marketplace_items mi
            JOIN planting_requests pr ON mi.planting_request_id = pr.id
            JOIN users u ON pr.farmer_id = u.id
            JOIN crops c ON pr.crop_id = c.id
            WHERE mi.id = ?
        ");
        $stmt->execute([$listingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getStats($pdo) {
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items");
        $stats['total_listings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE listing_status = 'Active'");
        $stats['active_listings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE listing_status = 'Hidden'");
        $stats['hidden_listings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE listing_status = 'Sold Out'");
        $stats['sold_out_listings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(available_quantity_kg * price_per_kg), 0) FROM marketplace_items WHERE listing_status = 'Active'");
        $stats['total_market_value'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(available_quantity_kg), 0) FROM marketplace_items WHERE listing_status = 'Active'");
        $stats['total_available_quantity'] = round((float)$stmt->fetchColumn(), 2);
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getEligiblePlantings($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                pr.id,
                u.full_name as farmer_name,
                u.phone_number as farmer_phone,
                c.crop_name,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.planting_date, '%Y-%m-%d') as planting_date,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date,
                pr.region_name,
                pr.status
            FROM planting_requests pr
            JOIN users u ON pr.farmer_id = u.id
            JOIN crops c ON pr.crop_id = c.id
            WHERE pr.status IN ('Planted', 'Growing')
              AND pr.id NOT IN (SELECT planting_request_id FROM marketplace_items)
            ORDER BY pr.expected_harvest_date ASC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createListing($pdo, $data) {
    try {
        if (empty($data['planting_request_id']) || !isset($data['available_quantity_kg']) || !isset($data['price_per_kg'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        $plantingRequestId = $data['planting_request_id'];
        
        // Validate planting exists and not harvested
        $stmt = $pdo->prepare("SELECT * FROM planting_requests WHERE id = ?");
        $stmt->execute([$plantingRequestId]);
        $planting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$planting) {
            echo json_encode(['success' => false, 'error' => 'Planting request not found']);
            return;
        }
        if ($planting['status'] === 'Harvested') {
            echo json_encode(['success' => false, 'error' => 'Cannot list harvested crop']);
            return;
        }
        
        // Check not already listed
        $stmt = $pdo->prepare("SELECT id FROM marketplace_items WHERE planting_request_id = ?");
        $stmt->execute([$plantingRequestId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already listed']);
            return;
        }
        
        // Validate quantity
        if ($data['available_quantity_kg'] > $planting['expected_yield_kg']) {
            echo json_encode(['success' => false, 'error' => 'Quantity exceeds expected yield']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO marketplace_items (planting_request_id, available_quantity_kg, price_per_kg, listing_status, listed_date)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $plantingRequestId,
            $data['available_quantity_kg'],
            $data['price_per_kg'],
            $data['listing_status'] ?? 'Active'
        ]);
        
        echo json_encode(['success' => true, 'listing_id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateListing($pdo, $data) {
    try {
        $listingId = $data['listing_id'] ?? 0;
        if (!$listingId) { echo json_encode(['success' => false, 'error' => 'Listing ID required']); return; }
        
        $stmt = $pdo->prepare("SELECT mi.*, pr.expected_yield_kg FROM marketplace_items mi JOIN planting_requests pr ON mi.planting_request_id = pr.id WHERE mi.id = ?");
        $stmt->execute([$listingId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) { echo json_encode(['success' => false, 'error' => 'Listing not found']); return; }
        
        if (isset($data['available_quantity_kg']) && $data['available_quantity_kg'] > $current['expected_yield_kg']) {
            echo json_encode(['success' => false, 'error' => 'Quantity exceeds expected yield']); return;
        }
        
        $updates = [];
        $params = [];
        foreach (['available_quantity_kg', 'price_per_kg', 'listing_status'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['available_quantity_kg']) && $data['available_quantity_kg'] <= 0) {
            $updates[] = "listing_status = 'Sold Out'";
            $updates[] = "sold_out_date = NOW()";
        }
        
        if (empty($updates)) { echo json_encode(['success' => false, 'error' => 'No fields to update']); return; }
        
        $params[] = $listingId;
        $sql = "UPDATE marketplace_items SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateListingStatus($pdo, $data) {
    try {
        $listingId = $data['listing_id'] ?? 0;
        $status = $data['listing_status'] ?? '';
        
        if (!$listingId || !in_array($status, ['Active', 'Hidden', 'Sold Out'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }
        
        $sql = "UPDATE marketplace_items SET listing_status = ?";
        $params = [$status];
        
        if ($status === 'Sold Out') {
            $sql .= ", sold_out_date = NOW()";
        } elseif ($status === 'Active') {
            $sql .= ", sold_out_date = NULL";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $listingId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateListingPrice($pdo, $data) {
    try {
        $listingId = $data['listing_id'] ?? 0;
        $price = $data['price_per_kg'] ?? null;
        
        if (!$listingId || !isset($price) || $price < 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }
        
        $stmt = $pdo->prepare("UPDATE marketplace_items SET price_per_kg = ? WHERE id = ?");
        $stmt->execute([$price, $listingId]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteListing($pdo, $data) {
    try {
        $listingId = $data['listing_id'] ?? 0;
        if (!$listingId) { echo json_encode(['success' => false, 'error' => 'Listing ID required']); return; }
        
        // Check for orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE marketplace_item_id = ?");
        $stmt->execute([$listingId]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            // Soft delete
            $stmt = $pdo->prepare("UPDATE marketplace_items SET listing_status = 'Hidden', available_quantity_kg = 0 WHERE id = ?");
            $stmt->execute([$listingId]);
            echo json_encode(['success' => true, 'message' => 'Listing hidden (has orders)']);
        } else {
            $stmt = $pdo->prepare("DELETE FROM marketplace_items WHERE id = ?");
            $stmt->execute([$listingId]);
            echo json_encode(['success' => true, 'message' => 'Listing permanently deleted']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function bulkUpdateListings($pdo, $data) {
    try {
        $ids = $data['listing_ids'] ?? [];
        $status = $data['listing_status'] ?? '';
        
        if (empty($ids) || !in_array($status, ['Active', 'Hidden'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status], array_map('intval', $ids));
        
        $stmt = $pdo->prepare("UPDATE marketplace_items SET listing_status = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

?>