<?php

ob_start();

// Include navigation system
require_once 'admin_navigation.php';


$nav_data = initializeAdminNavigation('Crop Schedule', 'crop-schedule');


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
            case 'crops':
                getCrops($pdo);
                break;
            case 'crop':
                getCropDetails($pdo);
                break;
            case 'stats':
                getCropStats($pdo);
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

// Handle POST requests for adding stages, adding crops, editing crops, deleting crops
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        require_once '../database.php';
        $pdo = getDBConnection();
        
        if ($_POST['action'] === 'add_stage') {
            $cropId = $_POST['crop_id'];
            $stageName = $_POST['stage_name'];
            $dayOffset = $_POST['day_offset'];
            
            $stmt = $pdo->prepare("
                INSERT INTO growth_stages (crop_id, stage_name, day_offset)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$cropId, $stageName, $dayOffset]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($_POST['action'] === 'delete_stage') {
            $stageId = $_POST['stage_id'];
            $stmt = $pdo->prepare("DELETE FROM growth_stages WHERE id = ?");
            $stmt->execute([$stageId]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($_POST['action'] === 'add_crop') {
            $cropName = $_POST['crop_name'];
            $baselineYield = $_POST['baseline_yield'];
            $maturityDays = $_POST['maturity_days'];
            $pricePerKg = $_POST['price_per_kg'];
            $description = $_POST['description'] ?? '';
            $imageUrl = $_POST['image_url'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Handle image upload
            if (isset($_FILES['crop_image']) && $_FILES['crop_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['crop_image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/../uploads/crops/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'crop_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $imageUrl = '/uploads/crops/' . $filename;
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO crops (crop_name, baseline_yield_per_acre, total_maturity_days, price_per_kg, description, image_url, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cropName, $baselineYield, $maturityDays, $pricePerKg, $description, $imageUrl, $isActive]);
            
            echo json_encode(['success' => true, 'crop_id' => $pdo->lastInsertId()]);
            exit;
        }
        
        if ($_POST['action'] === 'edit_crop') {
            $cropId = $_POST['crop_id'];
            $cropName = $_POST['crop_name'];
            $baselineYield = $_POST['baseline_yield'];
            $maturityDays = $_POST['maturity_days'];
            $pricePerKg = $_POST['price_per_kg'];
            $description = $_POST['description'] ?? '';
            $imageUrl = $_POST['image_url'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Handle image upload
            if (isset($_FILES['crop_image']) && $_FILES['crop_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['crop_image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/../uploads/crops/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Delete old image
                    $stmt = $pdo->prepare("SELECT image_url FROM crops WHERE id = ?");
                    $stmt->execute([$cropId]);
                    $old = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($old && !empty($old['image_url'])) {
                        $oldPath = __DIR__ . '/..' . parse_url($old['image_url'], PHP_URL_PATH);
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                    
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'crop_' . $cropId . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $imageUrl = '/uploads/crops/' . $filename;
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE crops 
                SET crop_name = ?, baseline_yield_per_acre = ?, total_maturity_days = ?, 
                    price_per_kg = ?, description = ?, image_url = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$cropName, $baselineYield, $maturityDays, $pricePerKg, $description, $imageUrl, $isActive, $cropId]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($_POST['action'] === 'delete_crop') {
            $cropId = $_POST['crop_id'];
            
            // Check if crop has plantings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM planting_requests WHERE crop_id = ?");
            $stmt->execute([$cropId]);
            $hasPlantings = $stmt->fetchColumn() > 0;
            
            if ($hasPlantings) {
                // Soft delete - just deactivate
                $stmt = $pdo->prepare("UPDATE crops SET is_active = 0 WHERE id = ?");
                $stmt->execute([$cropId]);
                echo json_encode(['success' => true, 'message' => 'Crop deactivated (has existing plantings)']);
            } else {
                // Hard delete - remove growth stages first, then crop
                $stmt = $pdo->prepare("DELETE FROM growth_stages WHERE crop_id = ?");
                $stmt->execute([$cropId]);
                $stmt = $pdo->prepare("DELETE FROM crops WHERE id = ?");
                $stmt->execute([$cropId]);
                echo json_encode(['success' => true, 'message' => 'Crop permanently deleted']);
            }
            exit;
        }
        
        if ($_POST['action'] === 'toggle_status') {
            $cropId = $_POST['crop_id'];
            $isActive = $_POST['is_active'];
            
            $stmt = $pdo->prepare("UPDATE crops SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $cropId]);
            
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Not an API request - display the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>AgriTrace - <?php echo htmlspecialchars($nav_data['page_title']); ?></title>
    
    <?php echo generateNavigationCSS(); ?>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#1F7A4C">
    
    <style>
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
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
        }

        .btn-primary:hover {
            background: #166B3A;
        }

        .crops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .crop-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
            transition: transform 0.2s;
        }

        .crop-card:hover {
            transform: translateY(-2px);
        }

        .crop-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: var(--soft-green);
        }

        .crop-image-placeholder {
            width: 100%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--soft-green);
            font-size: 48px;
            color: var(--primary-green);
        }

        .crop-info {
            padding: 16px;
        }

        .crop-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .crop-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-green);
        }

        .crop-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .crop-status.active {
            background: var(--active);
            color: white;
        }

        .crop-status.inactive {
            background: var(--text-secondary);
            color: white;
        }

        .crop-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 15px 0;
        }

        .crop-detail-item {
            text-align: center;
        }

        .crop-detail-label {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .crop-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-green);
        }

        .crop-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-light);
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-view {
            background: var(--primary-green);
            color: white;
        }

        .btn-view:hover {
            background: #166B3A;
        }

        .btn-edit {
            background: var(--accent-green);
            color: white;
        }

        .btn-edit:hover {
            background: #5A8F3C;
        }

        .btn-toggle {
            background: var(--pending);
            color: var(--text-primary);
        }

        .btn-toggle:hover {
            background: #E0B85C;
        }

        .btn-delete {
            background: var(--blocked);
            color: white;
        }

        .btn-delete:hover {
            background: #D32F2F;
        }

        .btn-stage {
            background: var(--info);
            color: white;
        }

        .btn-stage:hover {
            background: #1976D2;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            font-size: 16px;
            color: var(--text-secondary);
        }

        /* Modal Styles */
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
            width: 600px;
        }

        .modal-content.modal-lg {
            width: 700px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: var(--blocked);
            color: white;
        }

        .modal-body {
            padding: 20px;
        }

        .stages-list {
            margin-top: 20px;
        }

        .stage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--page-bg);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .stage-day {
            font-weight: 600;
            color: var(--primary-green);
            min-width: 60px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 13px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-green);
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px;
            border-top: 1px solid var(--border-light);
        }

        .info-alert {
            background: #fff3e0;
            border-left: 4px solid var(--pending);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .image-upload-group {
            margin-bottom: 18px;
        }

        .image-upload-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 13px;
        }

        .image-upload-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: var(--card-bg);
        }

        .current-image {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .current-image img {
            max-width: 100px;
            max-height: 60px;
            border-radius: 4px;
            margin-top: 4px;
            display: block;
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
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast-notification.show {
            transform: translateX(0);
        }

        .toast-notification.error {
            background: var(--blocked);
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .crops-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .modal-content {
                width: 95%;
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
                <div class="loading">Loading crops...</div>
            </div>
        </main>
    </div>

    <!-- Crop Detail Modal (Only shows Growth Stages) -->
    <div class="modal" id="cropModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalCropName">Growth Stages</h3>
                <span class="close-modal" onclick="closeModal('cropModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="stagesListContainer">
                    <div class="loading">Loading stages...</div>
                </div>
                <div style="margin-top: 20px;">
                    <h4>Add New Stage</h4>
                    <form id="addStageForm" onsubmit="event.preventDefault(); addGrowthStage();">
                        <div class="form-group">
                            <label>Stage Name</label>
                            <input type="text" id="newStageName" required placeholder="e.g., Germination, Vegetative, Flowering">
                        </div>
                        <div class="form-group">
                            <label>Day Offset</label>
                            <input type="number" id="newDayOffset" required placeholder="Day when this stage occurs" min="0">
                        </div>
                        <div class="modal-actions" style="padding: 0; margin-top: 10px;">
                            <button type="submit" class="btn-sm" style="background: var(--primary-green); color: white;">Add Stage</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-sm" style="background: var(--text-secondary); color: white;" onclick="closeModal('cropModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Crop Modal -->
    <div class="modal" id="addEditCropModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="addEditModalTitle">Add New Crop</h3>
                <span class="close-modal" onclick="closeModal('addEditCropModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="cropForm" onsubmit="event.preventDefault(); saveCrop();" enctype="multipart/form-data">
                    <input type="hidden" id="editCropId">
                    <input type="hidden" id="existingImageUrl" value="">
                    
                    <div class="form-group">
                        <label>Crop Name *</label>
                        <input type="text" id="cropName" required placeholder="e.g., Maize, Wheat, Cabbage">
                    </div>
                    
                    <div class="image-upload-group">
                        <label>📷 Crop Image</label>
                        <input type="file" id="cropImageFile" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-hint">Upload a photo of the crop (JPEG, PNG, GIF, WEBP - Max 5MB)</div>
                        <div class="current-image" id="currentImagePreview" style="display:none;"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Baseline Yield (kg/acre) *</label>
                            <input type="number" id="baselineYield" required step="0.01" min="0.01" placeholder="0.00">
                            <div class="form-hint">Expected yield per acre under normal conditions</div>
                        </div>
                        <div class="form-group">
                            <label>Maturity Days *</label>
                            <input type="number" id="maturityDays" required min="1" placeholder="90">
                            <div class="form-hint">Total days from planting to harvest</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Price per KG (KES) *</label>
                            <input type="number" id="pricePerKg" required step="0.01" min="0.01" placeholder="0.00">
                            <div class="form-hint">Market selling price per kilogram</div>
                        </div>
                        <div class="form-group">
                            <label>Image URL (Optional)</label>
                            <input type="text" id="imageUrl" placeholder="https://example.com/crop-image.jpg">
                            <div class="form-hint">Or paste an image URL instead of uploading</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="description" rows="3" placeholder="Brief description of the crop..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="isActive" checked>
                            <label for="isActive">Active Crop (visible for planting)</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-sm" style="background: var(--text-secondary); color: white;" onclick="closeModal('addEditCropModal')">Cancel</button>
                <button class="btn btn-sm" style="background: var(--primary-green); color: white;" onclick="saveCrop()">Save Crop</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal" id="deleteCropModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close-modal" onclick="closeModal('deleteCropModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteCropName"></strong>?</p>
                <div class="info-alert">
                    ⚠️ If this crop has existing plantings, it will be deactivated instead of permanently deleted.
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-sm" style="background: var(--text-secondary); color: white;" onclick="closeModal('deleteCropModal')">Cancel</button>
                <button class="btn btn-sm" style="background: var(--blocked); color: white;" onclick="confirmDeleteCrop()">Delete Crop</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast-notification"></div>
    
    <?php echo generateLoadingAnimation(); ?>

    <script>
    // API Service
    const API = {
        async get(endpoint) {
            try {
                const url = `${window.location.pathname}?endpoint=${endpoint}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return await response.json();
            } catch (error) {
                console.error('API Error:', error);
                return [];
            }
        },
        
        async postFormData(url, formData) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error('POST Error:', error);
                return { error: error.message };
            }
        }
    };

    let crops = [];
    let stats = {};
    let currentCropId = null;
    let currentCropName = null;
    let deleteCropId = null;
    const contentArea = document.getElementById('contentArea');

    async function loadCropData() {
        try {
            contentArea.innerHTML = '<div class="loading">Loading crops...</div>';
            
            const [cropsData, statsData] = await Promise.all([
                API.get('crops'),
                API.get('stats')
            ]);
            
            crops = cropsData || [];
            stats = statsData || {};
            
            renderCrops();
        } catch (error) {
            console.error('Error loading data:', error);
            contentArea.innerHTML = '<div class="loading">Error loading data. Please refresh.</div>';
        }
    }

    function openAddCropModal() {
        document.getElementById('addEditModalTitle').textContent = 'Add New Crop';
        document.getElementById('editCropId').value = '';
        document.getElementById('cropName').value = '';
        document.getElementById('baselineYield').value = '';
        document.getElementById('maturityDays').value = '';
        document.getElementById('pricePerKg').value = '';
        document.getElementById('imageUrl').value = '';
        document.getElementById('cropImageFile').value = '';
        document.getElementById('description').value = '';
        document.getElementById('isActive').checked = true;
        document.getElementById('existingImageUrl').value = '';
        document.getElementById('currentImagePreview').style.display = 'none';
        document.getElementById('addEditCropModal').classList.add('active');
    }

    function openEditCropModal(cropId) {
        const crop = crops.find(c => c.id === cropId);
        if (!crop) return;
        
        document.getElementById('addEditModalTitle').textContent = 'Edit Crop';
        document.getElementById('editCropId').value = crop.id;
        document.getElementById('cropName').value = crop.crop_name || '';
        document.getElementById('baselineYield').value = crop.baseline_yield_per_acre || '';
        document.getElementById('maturityDays').value = crop.total_maturity_days || '';
        document.getElementById('pricePerKg').value = crop.price_per_kg || '';
        document.getElementById('imageUrl').value = crop.image_url || '';
        document.getElementById('cropImageFile').value = '';
        document.getElementById('description').value = crop.description || '';
        document.getElementById('isActive').checked = crop.is_active == 1;
        document.getElementById('existingImageUrl').value = crop.image_url || '';
        
        // Show current image if exists
        const preview = document.getElementById('currentImagePreview');
        if (crop.image_url) {
            preview.innerHTML = `Current image: <img src="${crop.image_url}" alt="Current">`;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
        
        document.getElementById('addEditCropModal').classList.add('active');
    }

    async function saveCrop() {
        const cropId = document.getElementById('editCropId').value;
        const cropName = document.getElementById('cropName').value.trim();
        const baselineYield = document.getElementById('baselineYield').value;
        const maturityDays = document.getElementById('maturityDays').value;
        const pricePerKg = document.getElementById('pricePerKg').value;
        const imageUrl = document.getElementById('imageUrl').value.trim();
        const description = document.getElementById('description').value.trim();
        const isActive = document.getElementById('isActive').checked;
        const existingImageUrl = document.getElementById('existingImageUrl').value;
        const imageFile = document.getElementById('cropImageFile').files[0];
        
        if (!cropName || !baselineYield || !maturityDays || !pricePerKg) {
            showToast('Please fill in all required fields', 'error');
            return;
        }
        
        const action = cropId ? 'edit_crop' : 'add_crop';
        
        // Use FormData to support file upload
        const formData = new FormData();
        formData.append('action', action);
        formData.append('crop_id', cropId);
        formData.append('crop_name', cropName);
        formData.append('baseline_yield', baselineYield);
        formData.append('maturity_days', maturityDays);
        formData.append('price_per_kg', pricePerKg);
        formData.append('image_url', imageUrl);
        formData.append('description', description);
        formData.append('is_active', isActive ? 1 : 0);
        
        // If no new image uploaded, keep existing image URL
        if (!imageFile && existingImageUrl) {
            formData.append('image_url', existingImageUrl);
        }
        
        // Add image file if selected
        if (imageFile) {
            formData.append('crop_image', imageFile);
        }
        
        const result = await API.postFormData(window.location.pathname, formData);
        
        if (result.success) {
            closeModal('addEditCropModal');
            showToast(cropId ? 'Crop updated successfully!' : 'Crop added successfully!');
            await loadCropData();
        } else {
            showToast(result.error || 'Error saving crop', 'error');
        }
    }

    function openDeleteCropModal(cropId) {
        const crop = crops.find(c => c.id === cropId);
        if (!crop) return;
        
        deleteCropId = cropId;
        document.getElementById('deleteCropName').textContent = crop.crop_name;
        document.getElementById('deleteCropModal').classList.add('active');
    }

    async function confirmDeleteCrop() {
        if (!deleteCropId) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_crop');
        formData.append('crop_id', deleteCropId);
        
        const result = await API.postFormData(window.location.pathname, formData);
        
        if (result.success) {
            closeModal('deleteCropModal');
            showToast(result.message || 'Crop deleted successfully!');
            deleteCropId = null;
            await loadCropData();
        } else {
            showToast(result.error || 'Error deleting crop', 'error');
        }
    }

    async function toggleCropStatus(cropId, currentStatus) {
        const newStatus = currentStatus ? 0 : 1;
        const action = currentStatus ? 'deactivate' : 'activate';
        
        if (!confirm(`Are you sure you want to ${action} this crop?`)) return;
        
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('crop_id', cropId);
        formData.append('is_active', newStatus);
        
        const result = await API.postFormData(window.location.pathname, formData);
        
        if (result.success) {
            showToast(`Crop ${action}d successfully!`);
            await loadCropData();
        } else {
            showToast(result.error || `Error ${action}ing crop`, 'error');
        }
    }

    async function viewGrowthStages(cropId, cropName) {
        currentCropId = cropId;
        currentCropName = cropName;
        
        document.getElementById('modalCropName').innerHTML = `Growth Stages: ${escapeHtml(cropName)}`;
        document.getElementById('stagesListContainer').innerHTML = '<div class="loading">Loading stages...</div>';
        document.getElementById('newStageName').value = '';
        document.getElementById('newDayOffset').value = '';
        
        const crop = await API.get(`crop&crop_id=${cropId}`);
        
        if (crop && !crop.error) {
            displayStages(crop);
            document.getElementById('cropModal').classList.add('active');
        } else {
            showToast('Error loading growth stages', 'error');
        }
    }

    function displayStages(crop) {
        const container = document.getElementById('stagesListContainer');
        
        if (crop.growth_stages && crop.growth_stages.length > 0) {
            let html = '<div class="stages-list">';
            crop.growth_stages.forEach(stage => {
                html += `
                    <div class="stage-item">
                        <div class="stage-day">Day ${stage.day_offset}</div>
                        <div style="flex: 1;"><strong>${escapeHtml(stage.stage_name)}</strong></div>
                        <button class="btn-sm" style="background: var(--blocked); color: white; padding: 4px 12px;" onclick="deleteGrowthStage(${stage.id})">Delete</button>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">No growth stages defined for this crop.</p>';
        }
    }

    async function addGrowthStage() {
        const stageName = document.getElementById('newStageName').value.trim();
        const dayOffset = document.getElementById('newDayOffset').value;
        
        if (!stageName || !dayOffset) {
            showToast('Please fill in all fields', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'add_stage');
        formData.append('crop_id', currentCropId);
        formData.append('stage_name', stageName);
        formData.append('day_offset', dayOffset);
        
        const result = await API.postFormData(window.location.pathname, formData);
        
        if (result.success) {
            showToast('Growth stage added successfully');
            document.getElementById('newStageName').value = '';
            document.getElementById('newDayOffset').value = '';
            const crop = await API.get(`crop&crop_id=${currentCropId}`);
            if (crop && !crop.error) {
                displayStages(crop);
            }
        } else {
            showToast(result.error || 'Error adding stage', 'error');
        }
    }

    async function deleteGrowthStage(stageId) {
        if (!confirm('Are you sure you want to delete this growth stage?')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_stage');
        formData.append('stage_id', stageId);
        
        const result = await API.postFormData(window.location.pathname, formData);
        
        if (result.success) {
            showToast('Stage deleted successfully');
            const crop = await API.get(`crop&crop_id=${currentCropId}`);
            if (crop && !crop.error) {
                displayStages(crop);
            }
        } else {
            showToast(result.error || 'Error deleting stage', 'error');
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        if (modalId === 'cropModal') {
            currentCropId = null;
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast-notification ${type === 'error' ? 'error' : ''}`;
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderCrops() {
        if (!contentArea) return;
        
        let html = `
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-value">${stats.total_crops || crops.length}</div><div class="stat-label">Total Crops</div></div>
                <div class="stat-card"><div class="stat-value">${stats.active_crops || 0}</div><div class="stat-label">Active Crops</div></div>
                <div class="stat-card"><div class="stat-value">${stats.total_plantings || 0}</div><div class="stat-label">Total Plantings</div></div>
                <div class="stat-card"><div class="stat-value">${stats.avg_yield || 0} kg</div><div class="stat-label">Avg Yield/Acre</div></div>
            </div>
            <div class="actions-bar">
                <div></div>
                <button class="btn btn-primary" onclick="openAddCropModal()">➕ Add New Crop</button>
            </div>
            <div class="crops-grid">
        `;
        
        if (crops.length > 0) {
            crops.forEach(crop => {
                let imageHtml = (crop.image_url && crop.image_url.trim() !== '')
                    ? `<img src="${crop.image_url}" class="crop-image" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'crop-image-placeholder\'>🌾</div>'">`
                    : `<div class="crop-image-placeholder">🌾</div>`;
                
                html += `
                    <div class="crop-card">
                        ${imageHtml}
                        <div class="crop-info">
                            <div class="crop-header">
                                <span class="crop-name">${escapeHtml(crop.crop_name)}</span>
                                <span class="crop-status ${crop.is_active ? 'active' : 'inactive'}">${crop.is_active ? 'Active' : 'Inactive'}</span>
                            </div>
                            <div class="crop-details">
                                <div class="crop-detail-item"><div class="crop-detail-label">Yield/acre</div><div class="crop-detail-value">${crop.baseline_yield_per_acre} kg</div></div>
                                <div class="crop-detail-item"><div class="crop-detail-label">Maturity</div><div class="crop-detail-value">${crop.total_maturity_days} days</div></div>
                                <div class="crop-detail-item"><div class="crop-detail-label">Price/kg</div><div class="crop-detail-value">KES ${crop.price_per_kg}</div></div>
                                <div class="crop-detail-item"><div class="crop-detail-label">Stages</div><div class="crop-detail-value">${crop.stage_count || 0}</div></div>
                            </div>
                            <div class="crop-actions">
                                <button class="btn-sm btn-view" onclick="viewGrowthStages(${crop.id}, '${escapeHtml(crop.crop_name).replace(/'/g, "\\'")}')">📊 Stages</button>
                                <button class="btn-sm btn-edit" onclick="openEditCropModal(${crop.id})">✏️ Edit</button>
                                <button class="btn-sm btn-toggle" onclick="toggleCropStatus(${crop.id}, ${crop.is_active})">${crop.is_active ? '🔒 Deactivate' : '🔓 Activate'}</button>
                                <button class="btn-sm btn-delete" onclick="openDeleteCropModal(${crop.id})">🗑️ Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html += '<div style="grid-column: 1/-1; text-align: center; padding: 60px;">No crops found</div>';
        }
        
        html += `</div>`;
        contentArea.innerHTML = html;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        loadCropData();
    });

    // Make functions global
    window.openAddCropModal = openAddCropModal;
    window.openEditCropModal = openEditCropModal;
    window.saveCrop = saveCrop;
    window.openDeleteCropModal = openDeleteCropModal;
    window.confirmDeleteCrop = confirmDeleteCrop;
    window.toggleCropStatus = toggleCropStatus;
    window.viewGrowthStages = viewGrowthStages;
    window.addGrowthStage = addGrowthStage;
    window.deleteGrowthStage = deleteGrowthStage;
    window.closeModal = closeModal;
    </script>

    <?php echo generateNavigationScripts(); ?>
</body>
</html>
<?php

// ==================== API ENDPOINT FUNCTIONS ====================

function getCrops($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.crop_name,
                c.baseline_yield_per_acre,
                c.total_maturity_days,
                c.price_per_kg,
                c.image_url,
                c.description,
                c.is_active,
                (SELECT COUNT(*) FROM growth_stages WHERE crop_id = c.id) as stage_count
            FROM crops c
            ORDER BY c.crop_name
        ");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCropDetails($pdo) {
    try {
        $cropId = $_GET['crop_id'] ?? 0;
        
        if (!$cropId) {
            echo json_encode(['error' => 'Crop ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.crop_name,
                c.image_url
            FROM crops c
            WHERE c.id = ?
        ");
        $stmt->execute([$cropId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $stmt = $pdo->prepare("
                SELECT id, stage_name, day_offset
                FROM growth_stages
                WHERE crop_id = ?
                ORDER BY day_offset
            ");
            $stmt->execute([$cropId]);
            $result['growth_stages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode($result ?: []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getCropStats($pdo) {
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM crops");
        $stats['total_crops'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM crops WHERE is_active = 1");
        $stats['active_crops'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM planting_requests");
        $stats['total_plantings'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT AVG(baseline_yield_per_acre) FROM crops WHERE is_active = 1");
        $stats['avg_yield'] = round((float)$stmt->fetchColumn(), 2);
        
        echo json_encode($stats);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>