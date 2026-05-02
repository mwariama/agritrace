<?php
// orders.php - Orders & Transactions Management for AgriMarketplace Admin Panel

// Start output buffering
ob_start();

// Include navigation system
require_once 'admin_navigation.php';

// Initialize navigation (checks auth automatically)
$nav_data = initializeAdminNavigation('Orders & Transactions', 'orders');

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
            case 'get_orders':
                getOrders($pdo);
                break;
            case 'get_order_detail':
                getOrderDetail($pdo);
                break;
            case 'get_stats':
                getOrderStats($pdo);
                break;
            case 'get_transactions':
                getTransactions($pdo);
                break;
                
            // POST endpoints
            case 'update_escrow_status':
                updateEscrowStatus($pdo, $input);
                break;
            case 'update_tracking':
                updateTracking($pdo, $input);
                break;
            case 'add_payment':
                addPayment($pdo, $input);
                break;
            case 'bulk_update_status':
                bulkUpdateStatus($pdo, $input);
                break;
            case 'export_orders':
                exportOrders($pdo);
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
    
    <link rel="stylesheet" href="orders.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <meta name="theme-color" content="#1F7A4C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
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
            --pending: #FFC107;
            --paid: #2196F3;
            --delivered: #4CAF50;
            --cancelled: #F44336;
            --card-bg: #ffffff;
            --page-bg: #f5f6fa;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-light: #E0E0E0;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --purple: #7B1FA2;
            --teal: #009688;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
        }

        .content-area { padding: 20px; }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .page-title p { font-size: 14px; color: var(--text-secondary); }

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
            text-decoration: none;
        }

        .btn-primary { background: var(--primary-green); color: white; }
        .btn-primary:hover { background: var(--primary-green-light); }
        .btn-secondary { background: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-light); }
        .btn-secondary:hover { background: var(--page-bg); }
        .btn-outline { background: transparent; color: var(--primary-green); border: 1px solid var(--primary-green); }
        .btn-outline:hover { background: var(--soft-green); }
        .btn-success { background: var(--delivered); color: white; }
        .btn-success:hover { background: #388E3C; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #F57C00; }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #1976D2; }
        .btn-danger { background: var(--blocked); color: white; }
        .btn-danger:hover { background: #D32F2F; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-xs { padding: 4px 8px; font-size: 11px; border-radius: 4px; }

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
            font-family: 'Inter', sans-serif;
        }

        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { background: var(--primary-green); color: white; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-left: 4px solid transparent;
        }

        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.active-filter { border-left-color: var(--primary-green); background: var(--soft-green); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .stat-icon { font-size: 24px; }
        .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 3px; }
        .stat-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Filters */
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

        .filter-btn:hover { border-color: var(--primary-green); color: var(--primary-green); }
        .filter-btn.active { background: var(--primary-green); color: white; border-color: var(--primary-green); }

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

        .search-box input:focus { outline: none; border-color: var(--primary-green); background: var(--card-bg); }

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

        select.filter-select:focus { outline: none; border-color: var(--primary-green); }

        /* Charts */
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

        .chart-card.full-width { grid-column: 1 / -1; }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chart-title { font-size: 16px; font-weight: 600; }
        .chart-subtitle { font-size: 12px; color: var(--text-secondary); }

        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }

        .chart-container canvas { width: 100% !important; height: 100% !important; }

        /* Table */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }
        table thead { background: var(--page-bg); }

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

        table th:hover { color: var(--primary-green); }
        table th .sort-icon { margin-left: 4px; font-size: 10px; }

        table td {
            padding: 14px 15px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        table tbody tr { transition: background 0.2s; }
        table tbody tr:hover { background: #f8f9fa; }
        table tbody tr.selected { background: var(--soft-green); }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Checkbox */
        .checkbox-cell { width: 40px; }
        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary-green); }

        /* Badges */
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-pending { background: #fff8e1; color: #F57F17; }
        .badge-paid { background: #e3f2fd; color: #1565C0; }
        .badge-delivered { background: #e8f5e9; color: #2E7D32; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1565C0; }
        .badge-success { background: #e8f5e9; color: #2E7D32; }
        .badge-warning { background: #fff3e0; color: #E65100; }

        /* Action Buttons */
        .action-btns { display: flex; gap: 4px; }

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
        .action-btn.pay:hover { color: var(--paid); background: #e3f2fd; }
        .action-btn.deliver:hover { color: var(--delivered); background: #e8f5e9; }
        .action-btn.track:hover { color: var(--purple); background: #f3e5f5; }
        .action-btn.cancel:hover { color: var(--blocked); background: #ffebee; }

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

        .bulk-actions.show { display: flex; }
        .bulk-count { font-weight: 600; font-size: 14px; color: var(--primary-green); }

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
            max-width: 650px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.25s ease;
        }

        .modal-lg { max-width: 850px; }
        .modal-sm { max-width: 450px; }

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

        .modal-header h3 { font-size: 18px; font-weight: 600; }

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

        .close-modal:hover { background: var(--blocked); color: white; }

        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto; }
        .modal-footer { padding: 15px 25px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 10px; }

        /* Form */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(31, 122, 76, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-hint { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

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

        .detail-card h4 { font-size: 13px; font-weight: 600; margin-bottom: 12px; }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 13px;
            border-bottom: 1px solid var(--border-light);
        }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { color: var(--text-secondary); }
        .detail-value { font-weight: 500; }

        /* Timeline */
        .status-timeline {
            display: flex;
            align-items: center;
            gap: 0;
            padding: 20px 0;
            position: relative;
        }

        .timeline-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .timeline-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .timeline-dot.completed { background: var(--delivered); color: white; }
        .timeline-dot.active { background: var(--info); color: white; }
        .timeline-dot.pending { background: var(--border-light); color: var(--text-secondary); }

        .timeline-line {
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 3px;
            background: var(--delivered);
            z-index: 1;
        }

        .timeline-line.inactive { background: var(--border-light); }

        .timeline-label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }
        .timeline-date { font-size: 10px; color: var(--text-secondary); }

        /* Info Alert */
        .info-alert {
            background: #e3f2fd;
            border-left: 4px solid var(--info);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .info-alert.success { background: #e8f5e9; border-left-color: var(--delivered); }
        .info-alert.warning { background: #fff3e0; border-left-color: var(--warning); }
        .info-alert.danger { background: #ffebee; border-left-color: var(--blocked); }

        /* Payment Info */
        .payment-detail {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
        }

        /* Escrow Status Selector */
        .escrow-selector {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .escrow-option {
            padding: 10px 15px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            flex: 1;
            min-width: 100px;
        }

        .escrow-option:hover { border-color: var(--primary-green); }
        .escrow-option.selected { border-color: var(--primary-green); background: var(--soft-green); }
        .escrow-option .escrow-icon { font-size: 24px; display: block; margin-bottom: 5px; }
        .escrow-option .escrow-label { font-size: 12px; font-weight: 500; }

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

        .pagination-btn:hover:not(:disabled) { border-color: var(--primary-green); color: var(--primary-green); }
        .pagination-btn.active { background: var(--primary-green); color: white; border-color: var(--primary-green); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination-info { font-size: 12px; color: var(--text-secondary); padding: 0 10px; }

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

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Highlight amounts */
        .amount-highlight { font-weight: 700; color: var(--primary-green); }
        .tracking-id { font-family: 'Courier New', monospace; font-size: 11px; background: var(--page-bg); padding: 2px 6px; border-radius: 4px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .charts-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; }
            .search-box { min-width: 100%; }
            .detail-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .stat-value { font-size: 22px; }
            table th, table td { padding: 10px 8px; font-size: 12px; }
            .escrow-selector { flex-direction: column; }
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
                    <span>Loading orders...</span>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button class="close-modal" onclick="OrdersManager.closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <button class="close-modal" onclick="OrdersManager.closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="statusOrderId">
                <div class="form-group">
                    <label>Current Status: <span id="currentStatusDisplay"></span></label>
                    <label>New Status</label>
                    <div class="escrow-selector">
                        <div class="escrow-option" data-status="Pending" onclick="OrdersManager.selectEscrowStatus('Pending')">
                            <span class="escrow-icon">⏳</span>
                            <span class="escrow-label">Pending</span>
                        </div>
                        <div class="escrow-option" data-status="Paid" onclick="OrdersManager.selectEscrowStatus('Paid')">
                            <span class="escrow-icon">💰</span>
                            <span class="escrow-label">Paid</span>
                        </div>
                        <div class="escrow-option" data-status="Delivered" onclick="OrdersManager.selectEscrowStatus('Delivered')">
                            <span class="escrow-icon">✅</span>
                            <span class="escrow-label">Delivered</span>
                        </div>
                    </div>
                    <input type="hidden" id="newEscrowStatus">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="OrdersManager.closeModal('statusModal')">Cancel</button>
                <button class="btn btn-primary" onclick="OrdersManager.confirmUpdateStatus()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Add Payment Details</h3>
                <button class="close-modal" onclick="OrdersManager.closeModal('paymentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="paymentOrderId">
                <div class="form-group">
                    <label>M-Pesa Receipt Number *</label>
                    <input type="text" id="mpesaReceipt" placeholder="e.g., QWE1234567" required>
                </div>
                <div class="form-group">
                    <label>Checkout Request ID</label>
                    <input type="text" id="checkoutRequestId" placeholder="ws_CO_...">
                </div>
                <div class="form-group">
                    <label>Payment Phone Number</label>
                    <input type="text" id="paymentPhone" placeholder="+2547XXXXXXXX">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="OrdersManager.closeModal('paymentModal')">Cancel</button>
                <button class="btn btn-success" onclick="OrdersManager.confirmPayment()">Confirm Payment</button>
            </div>
        </div>
    </div>

    <!-- Tracking Modal -->
    <div class="modal" id="trackingModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Update Tracking</h3>
                <button class="close-modal" onclick="OrdersManager.closeModal('trackingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="trackingOrderId">
                <div class="form-group">
                    <label>Motorspeed Tracking ID</label>
                    <input type="text" id="trackingId" placeholder="MSP-XXXX-XXXX">
                </div>
                <div class="form-group">
                    <label>Delivery Address</label>
                    <textarea id="deliveryAddress" rows="2" placeholder="Full delivery address..."></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="trackingNotes" rows="2" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="OrdersManager.closeModal('trackingModal')">Cancel</button>
                <button class="btn btn-info" onclick="OrdersManager.confirmTracking()">Update Tracking</button>
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

    const OrdersManager = {
        orders: [],
        filteredOrders: [],
        transactions: [],
        currentTab: 'orders',
        currentFilter: 'all',
        searchTerm: '',
        sortField: 'created_at',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 20,
        selectedOrders: new Set(),
        stats: {},
        selectedEscrowStatus: '',
        charts: {},

        colors: {
            green: '#1F7A4C',
            greenBg: 'rgba(31, 122, 76, 0.7)',
            greenBgLight: 'rgba(31, 122, 76, 0.15)',
            blue: '#2196F3',
            blueBg: 'rgba(33, 150, 243, 0.7)',
            orange: '#FF9800',
            orangeBg: 'rgba(255, 152, 0, 0.7)',
            red: '#F44336',
            pieColors: ['#FFC107', '#2196F3', '#4CAF50', '#F44336']
        },

        init: async function() {
            console.log('Orders Manager initializing...');
            await this.loadStats();
            await this.loadOrders();
            this.render();
            this.setupEventListeners();
            
            <?php if (isset($_SESSION['user_name'])): ?>
            const adminName = document.getElementById('adminName');
            if (adminName) adminName.textContent = '<?php echo $_SESSION['user_name']; ?>';
            <?php endif; ?>
        },

        setupEventListeners: function() {
            let searchTimeout;
            document.addEventListener('input', (e) => {
                if (e.target.id === 'orderSearch') {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.searchTerm = e.target.value.toLowerCase();
                        this.currentPage = 1;
                        this.applyFilters();
                    }, 300);
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    e.target.classList.remove('active');
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
                }
            });
        },

        switchTab: function(tab) {
            this.currentTab = tab;
            this.destroyAllCharts();
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.toggle('active', content.id === 'tab-' + tab);
            });
            
            if (tab === 'transactions') {
                this.loadTransactions();
            } else {
                this.renderOrdersTab();
            }
        },

        destroyAllCharts: function() {
            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].destroy();
                    this.charts[key] = null;
                }
            });
        },

        loadOrders: async function() {
            const result = await API.get('get_orders');
            if (!result.error) {
                this.orders = Array.isArray(result) ? result : [];
                this.filteredOrders = [...this.orders];
                this.applySort();
            }
        },

        loadStats: async function() {
            const result = await API.get('get_stats');
            if (!result.error) this.stats = result;
        },

        loadTransactions: async function() {
            const result = await API.get('get_transactions');
            if (!result.error) {
                this.transactions = Array.isArray(result) ? result : [];
                this.renderTransactionsTab();
            }
        },

        applyFilters: function() {
            this.filteredOrders = this.orders.filter(order => {
                const matchesSearch = !this.searchTerm || 
                    (order.id && order.id.toString().includes(this.searchTerm)) ||
                    (order.buyer_name && order.buyer_name.toLowerCase().includes(this.searchTerm)) ||
                    (order.farmer_name && order.farmer_name.toLowerCase().includes(this.searchTerm)) ||
                    (order.crop_name && order.crop_name.toLowerCase().includes(this.searchTerm)) ||
                    (order.mpesa_receipt_no && order.mpesa_receipt_no.toLowerCase().includes(this.searchTerm)) ||
                    (order.motorspeed_tracking_id && order.motorspeed_tracking_id.toLowerCase().includes(this.searchTerm));
                
                if (!matchesSearch) return false;
                if (this.currentFilter === 'all') return true;
                return order.escrow_status && order.escrow_status.toLowerCase() === this.currentFilter;
            });
            
            this.applySort();
            if (this.currentTab === 'orders') {
                this.renderOrdersTab();
            }
        },

        setFilter: function(filter) {
            this.currentFilter = filter;
            this.currentPage = 1;
            this.applyFilters();
            
            document.querySelectorAll('#tab-orders .filter-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === filter);
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
            this.renderOrdersTab();
        },

        applySort: function() {
            const dir = this.sortDirection === 'asc' ? 1 : -1;
            this.filteredOrders.sort((a, b) => {
                let valA = a[this.sortField];
                let valB = b[this.sortField];
                if (valA === null || valA === undefined) valA = '';
                if (valB === null || valB === undefined) valB = '';
                if (typeof valA === 'number' && typeof valB === 'number') return (valA - valB) * dir;
                return String(valA).localeCompare(String(valB)) * dir;
            });
        },

        getPaginatedOrders: function() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredOrders.slice(start, start + this.itemsPerPage);
        },

        getTotalPages: function() {
            return Math.ceil(this.filteredOrders.length / this.itemsPerPage) || 1;
        },

        changePage: function(page) {
            const total = this.getTotalPages();
            if (page >= 1 && page <= total) {
                this.currentPage = page;
                this.renderOrdersTab();
            }
        },

        toggleSelectAll: function(checked) {
            const paginated = this.getPaginatedOrders();
            if (checked) {
                paginated.forEach(o => this.selectedOrders.add(o.id));
            } else {
                paginated.forEach(o => this.selectedOrders.delete(o.id));
            }
            this.renderOrdersTab();
        },

        toggleSelect: function(orderId) {
            if (this.selectedOrders.has(orderId)) {
                this.selectedOrders.delete(orderId);
            } else {
                this.selectedOrders.add(orderId);
            }
            this.updateBulkActions();
        },

        updateBulkActions: function() {
            const bulkBar = document.getElementById('bulkActions');
            const bulkCount = document.getElementById('bulkCount');
            
            if (this.selectedOrders.size > 0) {
                bulkBar.classList.add('show');
                bulkCount.textContent = `${this.selectedOrders.size} selected`;
            } else {
                bulkBar.classList.remove('show');
            }
        },

        // Order Detail
        viewDetail: async function(orderId) {
            const result = await API.get('get_order_detail', { order_id: orderId });
            if (result && !result.error) {
                this.showDetailModal(result);
            } else {
                this.showToast('Error loading order details', 'error');
            }
        },

        showDetailModal: function(order) {
            const content = document.getElementById('detailContent');
            
            const statusBadge = 'badge-' + (order.escrow_status || 'pending').toLowerCase();
            
            // Determine timeline steps
            const statusOrder = ['Pending', 'Paid', 'Delivered'];
            const currentStatusIndex = statusOrder.indexOf(order.escrow_status);
            
            let timelineHtml = '<div class="status-timeline">';
            statusOrder.forEach((status, index) => {
                const dotClass = index < currentStatusIndex ? 'completed' : 
                                index === currentStatusIndex ? 'active' : 'pending';
                const lineClass = index < currentStatusIndex ? '' : 'inactive';
                
                timelineHtml += `
                    <div class="timeline-step">
                        <div class="timeline-dot ${dotClass}">${index < currentStatusIndex ? '✓' : index === currentStatusIndex ? '●' : '○'}</div>
                        <div class="timeline-label">${status}</div>
                        ${index < 2 ? `<div class="timeline-line ${lineClass}" style="left: 65%; right: -35%;"></div>` : ''}
                    </div>
                `;
            });
            timelineHtml += '</div>';
            
            content.innerHTML = `
                ${timelineHtml}
                
                <div class="detail-grid">
                    <div class="detail-card">
                        <h4>📦 Order Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Order ID</span>
                            <span class="detail-value"><strong>#${order.id}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value"><span class="badge ${statusBadge}">${order.escrow_status || 'N/A'}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value">${this.formatNumber(order.quantity_ordered_kg)} kg</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Price per KG</span>
                            <span class="detail-value">KES ${this.formatNumber(order.price_per_kg || 0)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Price</span>
                            <span class="detail-value amount-highlight">KES ${this.formatNumber(order.total_price)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Order Date</span>
                            <span class="detail-value">${order.created_at || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>👤 Customer Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Buyer Name</span>
                            <span class="detail-value">${this.escapeHtml(order.buyer_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Buyer Phone</span>
                            <span class="detail-value">${order.buyer_phone || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Buyer Email</span>
                            <span class="detail-value">${order.buyer_email || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Payment Phone</span>
                            <span class="detail-value">${order.payment_phone_number || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Delivery Address</span>
                            <span class="detail-value">${this.escapeHtml(order.delivery_address || 'N/A')}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>🌾 Product Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Farmer</span>
                            <span class="detail-value">${this.escapeHtml(order.farmer_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Farmer Phone</span>
                            <span class="detail-value">${order.farmer_phone || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Crop</span>
                            <span class="detail-value">${this.escapeHtml(order.crop_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Region</span>
                            <span class="detail-value">${this.escapeHtml(order.region_name || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Listing ID</span>
                            <span class="detail-value">#${order.marketplace_item_id || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4>💳 Payment & Delivery</h4>
                        <div class="detail-item">
                            <span class="detail-label">M-Pesa Receipt</span>
                            <span class="detail-value"><span class="tracking-id">${order.mpesa_receipt_no || 'N/A'}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Checkout Request</span>
                            <span class="detail-value"><span class="tracking-id">${order.checkout_request_id || 'N/A'}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tracking ID</span>
                            <span class="detail-value"><span class="tracking-id">${order.motorspeed_tracking_id || 'N/A'}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Transaction Date</span>
                            <span class="detail-value">${order.transaction_date || 'N/A'}</span>
                        </div>
                        ${order.notes ? `
                        <div class="detail-item">
                            <span class="detail-label">Notes</span>
                            <span class="detail-value">${this.escapeHtml(order.notes)}</span>
                        </div>` : ''}
                    </div>
                </div>
            `;
            
            document.getElementById('detailModal').classList.add('active');
        },

        // Status Update
        openStatusModal: function(orderId) {
            const order = this.orders.find(o => o.id === orderId);
            if (!order) return;
            
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('currentStatusDisplay').innerHTML = `<span class="badge badge-${order.escrow_status.toLowerCase()}">${order.escrow_status}</span>`;
            document.getElementById('newEscrowStatus').value = '';
            
            document.querySelectorAll('.escrow-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.status === order.escrow_status);
            });
            
            this.selectedEscrowStatus = order.escrow_status;
            document.getElementById('statusModal').classList.add('active');
        },

        selectEscrowStatus: function(status) {
            this.selectedEscrowStatus = status;
            document.getElementById('newEscrowStatus').value = status;
            
            document.querySelectorAll('.escrow-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.status === status);
            });
        },

        confirmUpdateStatus: async function() {
            const orderId = document.getElementById('statusOrderId').value;
            const newStatus = this.selectedEscrowStatus;
            
            if (!newStatus) {
                this.showToast('Please select a status', 'warning');
                return;
            }
            
            const result = await API.post('update_escrow_status', {
                order_id: orderId,
                escrow_status: newStatus
            });
            
            if (result && result.success) {
                this.closeModal('statusModal');
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast('Order status updated to ' + newStatus, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to update status'), 'error');
            }
        },

        // Payment
        openPaymentModal: function(orderId) {
            document.getElementById('paymentOrderId').value = orderId;
            document.getElementById('mpesaReceipt').value = '';
            document.getElementById('checkoutRequestId').value = '';
            document.getElementById('paymentPhone').value = '';
            document.getElementById('paymentModal').classList.add('active');
            document.getElementById('mpesaReceipt').focus();
        },

        confirmPayment: async function() {
            const orderId = document.getElementById('paymentOrderId').value;
            const mpesaReceipt = document.getElementById('mpesaReceipt').value.trim();
            const checkoutRequestId = document.getElementById('checkoutRequestId').value.trim();
            const paymentPhone = document.getElementById('paymentPhone').value.trim();
            
            if (!mpesaReceipt) {
                this.showToast('Please enter M-Pesa receipt number', 'warning');
                return;
            }
            
            const result = await API.post('add_payment', {
                order_id: orderId,
                mpesa_receipt_no: mpesaReceipt,
                checkout_request_id: checkoutRequestId || null,
                payment_phone_number: paymentPhone || null
            });
            
            if (result && result.success) {
                this.closeModal('paymentModal');
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast('Payment confirmed successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to add payment'), 'error');
            }
        },

        // Tracking
        openTrackingModal: function(orderId) {
            const order = this.orders.find(o => o.id === orderId);
            document.getElementById('trackingOrderId').value = orderId;
            document.getElementById('trackingId').value = order?.motorspeed_tracking_id || '';
            document.getElementById('deliveryAddress').value = order?.delivery_address || '';
            document.getElementById('trackingNotes').value = order?.notes || '';
            document.getElementById('trackingModal').classList.add('active');
        },

        confirmTracking: async function() {
            const orderId = document.getElementById('trackingOrderId').value;
            const trackingId = document.getElementById('trackingId').value.trim();
            const deliveryAddress = document.getElementById('deliveryAddress').value.trim();
            const notes = document.getElementById('trackingNotes').value.trim();
            
            const result = await API.post('update_tracking', {
                order_id: orderId,
                motorspeed_tracking_id: trackingId || null,
                delivery_address: deliveryAddress || null,
                notes: notes || null
            });
            
            if (result && result.success) {
                this.closeModal('trackingModal');
                await this.loadOrders();
                this.applyFilters();
                this.showToast('Tracking updated successfully!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed to update tracking'), 'error');
            }
        },

        // Quick Actions
        quickPay: async function(orderId) {
            const receipt = prompt('Enter M-Pesa Receipt Number:');
            if (!receipt) return;
            
            const result = await API.post('add_payment', {
                order_id: orderId,
                mpesa_receipt_no: receipt.trim()
            });
            
            if (result && result.success) {
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast('Payment added!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed'), 'error');
            }
        },

        quickDeliver: async function(orderId) {
            if (!confirm('Mark order #' + orderId + ' as delivered?')) return;
            
            const result = await API.post('update_escrow_status', {
                order_id: orderId,
                escrow_status: 'Delivered'
            });
            
            if (result && result.success) {
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast('Order marked as delivered!', 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed'), 'error');
            }
        },

        // Bulk Actions
        bulkMarkPaid: async function() {
            if (this.selectedOrders.size === 0) return;
            if (!confirm(`Mark ${this.selectedOrders.size} order(s) as paid?`)) return;
            
            const result = await API.post('bulk_update_status', {
                order_ids: Array.from(this.selectedOrders),
                escrow_status: 'Paid'
            });
            
            if (result && result.success) {
                this.selectedOrders.clear();
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast(`${result.updated || 0} orders updated`, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed'), 'error');
            }
        },

        bulkMarkDelivered: async function() {
            if (this.selectedOrders.size === 0) return;
            if (!confirm(`Mark ${this.selectedOrders.size} order(s) as delivered?`)) return;
            
            const result = await API.post('bulk_update_status', {
                order_ids: Array.from(this.selectedOrders),
                escrow_status: 'Delivered'
            });
            
            if (result && result.success) {
                this.selectedOrders.clear();
                await this.loadOrders();
                await this.loadStats();
                this.applyFilters();
                this.showToast(`${result.updated || 0} orders updated`, 'success');
            } else {
                this.showToast('Error: ' + (result?.error || 'Failed'), 'error');
            }
        },

        // Export
        exportOrders: async function(format) {
            try {
                const result = await API.get('export_orders', { format: format });
                if (result && result.success) {
                    this.showToast('Export initiated', 'info');
                }
            } catch (e) {
                this.showToast('Export failed', 'error');
            }
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

        getStatusBadge: function(status) {
            const map = {
                'Pending': 'badge-pending',
                'Paid': 'badge-paid',
                'Delivered': 'badge-delivered'
            };
            return map[status] || 'badge-info';
        },

        // Render Methods
        render: function() {
            const contentArea = document.getElementById('contentArea');
            
            const html = `
                <div class="page-header">
                    <div class="page-title">
                        <h2>Orders & Transactions</h2>
                        <p>Manage orders, payments, and deliveries</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline btn-sm" onclick="OrdersManager.exportOrders('csv')">📊 Export CSV</button>
                        <button class="btn btn-outline btn-sm" onclick="OrdersManager.exportOrders('pdf')">📄 Export PDF</button>
                    </div>
                </div>
                
                <div class="tabs">
                    <button class="tab-btn active" data-tab="orders" onclick="OrdersManager.switchTab('orders')">📦 Orders</button>
                    <button class="tab-btn" data-tab="transactions" onclick="OrdersManager.switchTab('transactions')">💳 Transactions</button>
                    <button class="tab-btn" data-tab="analytics" onclick="OrdersManager.switchTab('analytics')">📊 Analytics</button>
                </div>
                
                <div class="tab-content active" id="tab-orders">
                    <div class="loading-overlay"><div class="spinner"></div><span>Loading orders...</span></div>
                </div>
                <div class="tab-content" id="tab-transactions">
                    <div class="loading-overlay"><div class="spinner"></div><span>Loading transactions...</span></div>
                </div>
                <div class="tab-content" id="tab-analytics">
                    <div class="loading-overlay"><div class="spinner"></div><span>Loading analytics...</span></div>
                </div>
            `;
            
            contentArea.innerHTML = html;
            
            // Render orders immediately
            this.renderOrdersTab();
        },

        renderOrdersTab: function() {
            const container = document.getElementById('tab-orders');
            if (!container) return;
            
            const paginated = this.getPaginatedOrders();
            const totalPages = this.getTotalPages();
            const startItem = ((this.currentPage - 1) * this.itemsPerPage) + 1;
            const endItem = Math.min(this.currentPage * this.itemsPerPage, this.filteredOrders.length);
            const totalItems = this.filteredOrders.length;
            
            let tableRows = '';
            if (paginated.length > 0) {
                paginated.forEach(order => {
                    const statusBadge = this.getStatusBadge(order.escrow_status);
                    const isSelected = this.selectedOrders.has(order.id);
                    const hasPayment = order.mpesa_receipt_no ? '✅' : '❌';
                    const hasTracking = order.motorspeed_tracking_id ? '✅' : '❌';
                    
                    tableRows += `
                        <tr class="${isSelected ? 'selected' : ''}">
                            <td class="checkbox-cell">
                                <input type="checkbox" ${isSelected ? 'checked' : ''} onclick="OrdersManager.toggleSelect(${order.id})">
                            </td>
                            <td><strong>#${order.id}</strong></td>
                            <td>${this.escapeHtml(order.buyer_name || 'N/A')}</td>
                            <td>${this.escapeHtml(order.farmer_name || 'N/A')}</td>
                            <td>${this.escapeHtml(order.crop_name || 'N/A')}</td>
                            <td class="text-right">${this.formatNumber(order.quantity_ordered_kg)} kg</td>
                            <td class="text-right amount-highlight">KES ${this.formatNumber(order.total_price)}</td>
                            <td><span class="badge ${statusBadge}">${order.escrow_status || 'Pending'}</span></td>
                            <td class="text-center">${hasPayment}</td>
                            <td class="text-center">${hasTracking}</td>
                            <td>${order.created_at || 'N/A'}</td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn view" title="View Details" onclick="OrdersManager.viewDetail(${order.id})">👁️</button>
                                    <button class="action-btn pay" title="Add Payment" onclick="OrdersManager.openPaymentModal(${order.id})">💳</button>
                                    <button class="action-btn deliver" title="Update Status" onclick="OrdersManager.openStatusModal(${order.id})">📝</button>
                                    <button class="action-btn track" title="Update Tracking" onclick="OrdersManager.openTrackingModal(${order.id})">🚚</button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tableRows = `
                    <tr>
                        <td colspan="12">
                            <div style="text-align: center; padding: 60px 20px;">
                                <div style="font-size: 64px; margin-bottom: 15px;">📦</div>
                                <h3>No Orders Found</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 20px;">${this.searchTerm ? 'No orders match your search criteria.' : 'Orders will appear here when buyers make purchases.'}</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            // Pagination
            let paginationHtml = '';
            if (totalPages > 1) {
                let pages = '';
                const maxVisible = 5;
                let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);
                
                for (let i = startPage; i <= endPage; i++) {
                    pages += `<button class="pagination-btn ${i === this.currentPage ? 'active' : ''}" onclick="OrdersManager.changePage(${i})">${i}</button>`;
                }
                
                paginationHtml = `
                    <div class="pagination">
                        <button class="pagination-btn" onclick="OrdersManager.changePage(1)" ${this.currentPage === 1 ? 'disabled' : ''}>«</button>
                        <button class="pagination-btn" onclick="OrdersManager.changePage(${this.currentPage - 1})" ${this.currentPage === 1 ? 'disabled' : ''}>‹</button>
                        ${pages}
                        <button class="pagination-btn" onclick="OrdersManager.changePage(${this.currentPage + 1})" ${this.currentPage === totalPages ? 'disabled' : ''}>›</button>
                        <button class="pagination-btn" onclick="OrdersManager.changePage(${totalPages})" ${this.currentPage === totalPages ? 'disabled' : ''}>»</button>
                        <span class="pagination-info">${startItem}-${endItem} of ${totalItems}</span>
                    </div>
                `;
            }
            
            container.innerHTML = `
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card ${this.currentFilter === 'all' ? 'active-filter' : ''}" onclick="OrdersManager.setFilter('all')">
                        <div class="stat-icon">📦</div>
                        <div class="stat-value">${this.stats.total_orders || 0}</div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card" onclick="OrdersManager.setFilter('pending')">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-value">${this.stats.pending_orders || 0}</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card" onclick="OrdersManager.setFilter('paid')">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">${this.stats.paid_orders || 0}</div>
                        <div class="stat-label">Paid</div>
                    </div>
                    <div class="stat-card" onclick="OrdersManager.setFilter('delivered')">
                        <div class="stat-icon">✅</div>
                        <div class="stat-value">${this.stats.delivered_orders || 0}</div>
                        <div class="stat-label">Delivered</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💵</div>
                        <div class="stat-value">KES ${this.formatNumber(this.stats.total_revenue || 0)}</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <span class="bulk-count" id="bulkCount">0 selected</span>
                    <button class="btn btn-info btn-sm" onclick="OrdersManager.bulkMarkPaid()">💰 Mark as Paid</button>
                    <button class="btn btn-success btn-sm" onclick="OrdersManager.bulkMarkDelivered()">✅ Mark as Delivered</button>
                    <button class="btn btn-outline btn-sm" onclick="OrdersManager.selectedOrders.clear(); OrdersManager.renderOrdersTab();">✖ Clear</button>
                </div>
                
                <!-- Filters -->
                <div class="filters-bar">
                    <div class="filter-group">
                        <button class="filter-btn ${this.currentFilter === 'all' ? 'active' : ''}" data-filter="all" onclick="OrdersManager.setFilter('all')">All</button>
                        <button class="filter-btn ${this.currentFilter === 'pending' ? 'active' : ''}" data-filter="pending" onclick="OrdersManager.setFilter('pending')">Pending</button>
                        <button class="filter-btn ${this.currentFilter === 'paid' ? 'active' : ''}" data-filter="paid" onclick="OrdersManager.setFilter('paid')">Paid</button>
                        <button class="filter-btn ${this.currentFilter === 'delivered' ? 'active' : ''}" data-filter="delivered" onclick="OrdersManager.setFilter('delivered')">Delivered</button>
                    </div>
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="orderSearch" placeholder="Search orders..." value="${this.escapeHtml(this.searchTerm)}">
                    </div>
                </div>
                
                <!-- Table -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell"><input type="checkbox" id="selectAllOrders" onchange="OrdersManager.toggleSelectAll(this.checked)"></th>
                                    <th onclick="OrdersManager.sortBy('id')">ID${this.getSortIcon('id')}</th>
                                    <th onclick="OrdersManager.sortBy('buyer_name')">Buyer${this.getSortIcon('buyer_name')}</th>
                                    <th onclick="OrdersManager.sortBy('farmer_name')">Farmer${this.getSortIcon('farmer_name')}</th>
                                    <th onclick="OrdersManager.sortBy('crop_name')">Crop${this.getSortIcon('crop_name')}</th>
                                    <th class="text-right" onclick="OrdersManager.sortBy('quantity_ordered_kg')">Qty${this.getSortIcon('quantity_ordered_kg')}</th>
                                    <th class="text-right" onclick="OrdersManager.sortBy('total_price')">Amount${this.getSortIcon('total_price')}</th>
                                    <th onclick="OrdersManager.sortBy('escrow_status')">Status${this.getSortIcon('escrow_status')}</th>
                                    <th class="text-center">Paid</th>
                                    <th class="text-center">Track</th>
                                    <th onclick="OrdersManager.sortBy('created_at')">Date${this.getSortIcon('created_at')}</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>${tableRows}</tbody>
                        </table>
                    </div>
                    ${paginationHtml}
                </div>
            `;
            
            this.updateBulkActions();
        },

        renderTransactionsTab: function() {
            const container = document.getElementById('tab-transactions');
            if (!container) return;
            
            let tableRows = '';
            if (this.transactions.length > 0) {
                this.transactions.forEach(tx => {
                    const statusBadge = this.getStatusBadge(tx.escrow_status);
                    
                    tableRows += `
                        <tr>
                            <td><strong>#${tx.id}</strong></td>
                            <td>${this.escapeHtml(tx.buyer_name || 'N/A')}</td>
                            <td>KES ${this.formatNumber(tx.total_price)}</td>
                            <td><span class="tracking-id">${tx.mpesa_receipt_no || 'N/A'}</span></td>
                            <td><span class="tracking-id">${tx.checkout_request_id || 'N/A'}</span></td>
                            <td>${tx.payment_phone_number || 'N/A'}</td>
                            <td><span class="badge ${statusBadge}">${tx.escrow_status || 'N/A'}</span></td>
                            <td>${tx.transaction_date || tx.created_at || 'N/A'}</td>
                        </tr>
                    `;
                });
            } else {
                tableRows = `
                    <tr>
                        <td colspan="8">
                            <div style="text-align: center; padding: 60px 20px;">
                                <div style="font-size: 64px;">💳</div>
                                <h3>No Transactions Yet</h3>
                                <p style="color: var(--text-secondary);">Completed payments will appear here.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            container.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💳</div>
                        <div class="stat-value">${this.transactions.length}</div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">KES ${this.formatNumber(this.transactions.reduce((sum, t) => sum + parseFloat(t.total_price || 0), 0))}</div>
                        <div class="stat-label">Transaction Volume</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Buyer</th>
                                    <th class="text-right">Amount</th>
                                    <th>M-Pesa Receipt</th>
                                    <th>Checkout ID</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>${tableRows}</tbody>
                        </table>
                    </div>
                </div>
            `;
        },

        renderAnalyticsTab: function() {
            const container = document.getElementById('tab-analytics');
            if (!container) return;
            
            container.innerHTML = `
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Order Status Distribution</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Revenue by Crop</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueByCropChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <div class="chart-title">Daily Orders</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="dailyOrdersChart"></canvas>
                        </div>
                    </div>
                </div>
            `;
            
            this.initOrderStatusChart();
            this.initRevenueByCropChart();
            this.initDailyOrdersChart();
        },

        initOrderStatusChart: function() {
            const canvas = document.getElementById('orderStatusChart');
            if (!canvas) return;
            if (this.charts.orderStatus) this.charts.orderStatus.destroy();
            
            const ctx = canvas.getContext('2d');
            const statuses = {
                'Pending': this.stats.pending_orders || 0,
                'Paid': this.stats.paid_orders || 0,
                'Delivered': this.stats.delivered_orders || 0
            };
            
            this.charts.orderStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statuses),
                    datasets: [{
                        data: Object.values(statuses),
                        backgroundColor: this.colors.pieColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
                    }
                }
            });
        },

        initRevenueByCropChart: function() {
            const canvas = document.getElementById('revenueByCropChart');
            if (!canvas) return;
            if (this.charts.revenueByCrop) this.charts.revenueByCrop.destroy();
            
            // Aggregate revenue by crop from orders
            const cropRevenue = {};
            this.orders.forEach(order => {
                const crop = order.crop_name || 'Unknown';
                cropRevenue[crop] = (cropRevenue[crop] || 0) + parseFloat(order.total_price || 0);
            });
            
            const sorted = Object.entries(cropRevenue).sort((a, b) => b[1] - a[1]).slice(0, 8);
            
            const ctx = canvas.getContext('2d');
            this.charts.revenueByCrop = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: sorted.map(s => s[0]),
                    datasets: [{
                        label: 'Revenue (KES)',
                        data: sorted.map(s => s[1]),
                        backgroundColor: this.colors.greenBg,
                        borderColor: this.colors.green,
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
                        x: { ticks: { callback: v => 'KES ' + this.formatNumber(v) } }
                    }
                }
            });
        },

        initDailyOrdersChart: function() {
            const canvas = document.getElementById('dailyOrdersChart');
            if (!canvas) return;
            if (this.charts.dailyOrders) this.charts.dailyOrders.destroy();
            
            // Aggregate orders by date
            const dailyData = {};
            this.orders.forEach(order => {
                const date = order.created_at || 'Unknown';
                dailyData[date] = (dailyData[date] || 0) + 1;
            });
            
            const sorted = Object.entries(dailyData).sort((a, b) => a[0].localeCompare(b[0])).slice(-14);
            
            const ctx = canvas.getContext('2d');
            this.charts.dailyOrders = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: sorted.map(s => s[0]),
                    datasets: [{
                        label: 'Orders',
                        data: sorted.map(s => s[1]),
                        borderColor: this.colors.blue,
                        backgroundColor: this.colors.greenBgLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { usePointStyle: true } } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        console.log('Orders page initialized');
        await OrdersManager.init();
    });

    window.OrdersManager = OrdersManager;
    </script>

    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API HANDLER FUNCTIONS ====================

function getOrders($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                o.id,
                o.buyer_id,
                o.marketplace_item_id,
                o.quantity_ordered_kg,
                o.total_price,
                o.checkout_request_id,
                o.mpesa_receipt_no,
                o.payment_phone_number,
                DATE_FORMAT(o.transaction_date, '%Y-%m-%d %H:%i') as transaction_date,
                o.motorspeed_tracking_id,
                o.escrow_status,
                o.delivery_address,
                o.notes,
                DATE_FORMAT(o.created_at, '%Y-%m-%d') as created_at,
                DATE_FORMAT(o.updated_at, '%Y-%m-%d %H:%i') as updated_at,
                ub.full_name as buyer_name,
                ub.phone_number as buyer_phone,
                ub.email as buyer_email,
                uf.full_name as farmer_name,
                uf.phone_number as farmer_phone,
                c.crop_name,
                mi.price_per_kg,
                pr.region_name,
                pr.expected_harvest_date
            FROM orders o
            JOIN users ub ON o.buyer_id = ub.id
            JOIN marketplace_items mi ON o.marketplace_item_id = mi.id
            JOIN planting_requests pr ON mi.planting_request_id = pr.id
            JOIN users uf ON pr.farmer_id = uf.id
            JOIN crops c ON pr.crop_id = c.id
            ORDER BY o.created_at DESC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getOrderDetail($pdo) {
    try {
        $orderId = $_GET['order_id'] ?? 0;
        if (!$orderId) { echo json_encode(['error' => 'Order ID required']); return; }
        
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i') as created_at,
                DATE_FORMAT(o.updated_at, '%Y-%m-%d %H:%i') as updated_at,
                DATE_FORMAT(o.transaction_date, '%Y-%m-%d %H:%i') as transaction_date,
                ub.full_name as buyer_name,
                ub.phone_number as buyer_phone,
                ub.email as buyer_email,
                uf.full_name as farmer_name,
                uf.phone_number as farmer_phone,
                c.crop_name,
                mi.price_per_kg,
                pr.region_name,
                pr.land_size_acres,
                pr.expected_yield_kg,
                DATE_FORMAT(pr.expected_harvest_date, '%Y-%m-%d') as expected_harvest_date
            FROM orders o
            JOIN users ub ON o.buyer_id = ub.id
            JOIN marketplace_items mi ON o.marketplace_item_id = mi.id
            JOIN planting_requests pr ON mi.planting_request_id = pr.id
            JOIN users uf ON pr.farmer_id = uf.id
            JOIN crops c ON pr.crop_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getOrderStats($pdo) {
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $stats['total_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'Pending'");
        $stats['pending_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'Paid'");
        $stats['paid_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'Delivered'");
        $stats['delivered_orders'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE escrow_status IN ('Paid', 'Delivered')");
        $stats['total_revenue'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COALESCE(AVG(total_price), 0) FROM orders");
        $stats['avg_order_value'] = round((float)$stmt->fetchColumn(), 2);
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(quantity_ordered_kg), 0) FROM orders");
        $stats['total_quantity'] = round((float)$stmt->fetchColumn(), 2);
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getTransactions($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                o.id,
                o.total_price,
                o.mpesa_receipt_no,
                o.checkout_request_id,
                o.payment_phone_number,
                o.escrow_status,
                DATE_FORMAT(o.transaction_date, '%Y-%m-%d %H:%i') as transaction_date,
                DATE_FORMAT(o.created_at, '%Y-%m-%d') as created_at,
                ub.full_name as buyer_name
            FROM orders o
            JOIN users ub ON o.buyer_id = ub.id
            WHERE o.mpesa_receipt_no IS NOT NULL
            ORDER BY o.transaction_date DESC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateEscrowStatus($pdo, $data) {
    try {
        $orderId = $data['order_id'] ?? 0;
        $status = $data['escrow_status'] ?? '';
        
        if (!$orderId || !in_array($status, ['Pending', 'Paid', 'Delivered'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET escrow_status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateTracking($pdo, $data) {
    try {
        $orderId = $data['order_id'] ?? 0;
        if (!$orderId) { echo json_encode(['success' => false, 'error' => 'Order ID required']); return; }
        
        $updates = [];
        $params = [];
        foreach (['motorspeed_tracking_id', 'delivery_address', 'notes'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) { echo json_encode(['success' => false, 'error' => 'No fields to update']); return; }
        
        $params[] = $orderId;
        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function addPayment($pdo, $data) {
    try {
        $orderId = $data['order_id'] ?? 0;
        $mpesaReceipt = $data['mpesa_receipt_no'] ?? '';
        
        if (!$orderId || !$mpesaReceipt) {
            echo json_encode(['success' => false, 'error' => 'Order ID and receipt number required']); return;
        }
        
        // Check receipt not used elsewhere
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE mpesa_receipt_no = ? AND id != ?");
        $stmt->execute([$mpesaReceipt, $orderId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Receipt number already used']); return;
        }
        
        $updates = [
            "mpesa_receipt_no = ?",
            "escrow_status = 'Paid'",
            "transaction_date = NOW()"
        ];
        $params = [$mpesaReceipt];
        
        if (!empty($data['checkout_request_id'])) {
            $updates[] = "checkout_request_id = ?";
            $params[] = $data['checkout_request_id'];
        }
        if (!empty($data['payment_phone_number'])) {
            $updates[] = "payment_phone_number = ?";
            $params[] = $data['payment_phone_number'];
        }
        
        $params[] = $orderId;
        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function bulkUpdateStatus($pdo, $data) {
    try {
        $ids = $data['order_ids'] ?? [];
        $status = $data['escrow_status'] ?? '';
        
        if (empty($ids) || !in_array($status, ['Pending', 'Paid', 'Delivered'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status], array_map('intval', $ids));
        
        $stmt = $pdo->prepare("UPDATE orders SET escrow_status = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function exportOrders($pdo) {
    try {
        echo json_encode(['success' => true, 'message' => 'Export initiated']);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

?>