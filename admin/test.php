<?php
// test.php - Script to add an admin user to the database
// Run this file once to create the admin account

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../database.php';

// Admin credentials
$admin_email = 'admin@agritrace.co.ke';
$admin_password = 'password123';
$admin_full_name = 'System Administrator';
$admin_phone = '+254700000000';

// Function to create admin user
function createAdminUser($pdo, $email, $password, $full_name, $phone) {
    try {
        // First, check if user_types table exists and get Admin role ID
        $stmt = $pdo->prepare("SELECT id FROM user_types WHERE role_name = 'Admin'");
        $stmt->execute();
        $admin_type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin_type) {
            // Insert Admin role if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO user_types (role_name) VALUES ('Admin')");
            $stmt->execute();
            $admin_type_id = $pdo->lastInsertId();
            echo "✅ Created 'Admin' role in user_types table<br>";
        } else {
            $admin_type_id = $admin_type['id'];
            echo "✅ Found 'Admin' role with ID: {$admin_type_id}<br>";
        }
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            echo "⚠️ User with email '{$email}' already exists!<br>";
            echo "User ID: " . $existing_user['id'] . "<br>";
            
            // Update the user to ensure they have Admin role
            $stmt = $pdo->prepare("UPDATE users SET type_id = ?, full_name = ? WHERE id = ?");
            if ($stmt->execute([$admin_type_id, $full_name, $existing_user['id']])) {
                echo "✅ Updated existing user to Admin role<br>";
                return $existing_user['id'];
            }
            return null;
        }
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert the admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (type_id, full_name, email, phone_number, password_hash, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        
        if ($stmt->execute([$admin_type_id, $full_name, $email, $phone, $password_hash])) {
            $user_id = $pdo->lastInsertId();
            echo "✅ Admin user created successfully!<br>";
            echo "User ID: {$user_id}<br>";
            return $user_id;
        } else {
            echo "❌ Failed to create admin user<br>";
            return null;
        }
        
    } catch (PDOException $e) {
        echo "❌ Database Error: " . $e->getMessage() . "<br>";
        return null;
    }
}

// Function to verify admin login works
function verifyAdminLogin($pdo, $email, $password) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.password_hash, u.is_active, ut.role_name 
            FROM users u
            INNER JOIN user_types ut ON u.type_id = ut.id
            WHERE u.email = ? AND ut.role_name = 'Admin'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            echo "✅ Login verification successful!<br>";
            echo "   - User ID: " . $user['id'] . "<br>";
            echo "   - Name: " . $user['full_name'] . "<br>";
            echo "   - Email: " . $user['email'] . "<br>";
            echo "   - Role: " . $user['role_name'] . "<br>";
            return true;
        } else {
            echo "❌ Login verification failed!<br>";
            return false;
        }
    } catch (PDOException $e) {
        echo "❌ Verification Error: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Function to display database status
function displayDatabaseStatus($pdo) {
    echo "<hr>";
    echo "<h3>📊 Database Status</h3>";
    
    // Check user_types table
    $stmt = $pdo->query("SELECT * FROM user_types");
    $user_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>User Types:</strong><br>";
    foreach ($user_types as $type) {
        echo "  - ID: {$type['id']}, Role: {$type['role_name']}<br>";
    }
    
    // Count total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<strong>Total Users:</strong> " . $total_users['total'] . "<br>";
    
    // Count admin users
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM users u
        INNER JOIN user_types ut ON u.type_id = ut.id
        WHERE ut.role_name = 'Admin'
    ");
    $stmt->execute();
    $admin_count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<strong>Admin Users:</strong> " . $admin_count['total'] . "<br>";
}

// Main execution
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Setup - AgriMarketplace</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8f0e8 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1F7A4C 0%, #166338 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .credentials {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials h3 {
            color: #166338;
            margin-top: 0;
        }
        .cred-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #dcfce7;
        }
        .cred-label {
            font-weight: 600;
            width: 100px;
            color: #166338;
        }
        .cred-value {
            color: #333;
            font-family: monospace;
        }
        .success {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #991b1b;
        }
        .info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #1F7A4C;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }
        .button:hover {
            background: #166338;
        }
        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .button-secondary {
            background: #6b7280;
        }
        .button-secondary:hover {
            background: #4b5563;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .status-table th, .status-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .status-table th {
            background: #f9fafb;
            font-weight: 600;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌾 AgriMarketplace Admin Setup</h1>
            <p>Create administrator account for the system</p>
        </div>
        <div class="content">
            <?php
            // Get database connection
            try {
                $pdo = getDBConnection();
                
                if (!$pdo) {
                    echo '<div class="error">❌ Failed to connect to database. Please check your database configuration.</div>';
                } else {
                    echo '<div class="success">✅ Database connection successful!</div>';
                    
                    // Create admin user
                    echo '<h3>🔧 Creating Admin User...</h3>';
                    $user_id = createAdminUser($pdo, $admin_email, $admin_password, $admin_full_name, $admin_phone);
                    
                    if ($user_id) {
                        echo '<div class="success">';
                        echo '✅ <strong>Admin user has been successfully created/updated!</strong><br>';
                        echo '</div>';
                        
                        // Verify login works
                        echo '<h3>🔐 Verifying Admin Login...</h3>';
                        verifyAdminLogin($pdo, $admin_email, $admin_password);
                        
                        // Display credentials
                        echo '<div class="credentials">';
                        echo '<h3>📋 Admin Credentials</h3>';
                        echo '<div class="cred-item"><span class="cred-label">Email:</span><span class="cred-value">' . htmlspecialchars($admin_email) . '</span></div>';
                        echo '<div class="cred-item"><span class="cred-label">Password:</span><span class="cred-value">' . htmlspecialchars($admin_password) . '</span></div>';
                        echo '<div class="cred-item"><span class="cred-label">Name:</span><span class="cred-value">' . htmlspecialchars($admin_full_name) . '</span></div>';
                        echo '<div class="cred-item"><span class="cred-label">Phone:</span><span class="cred-value">' . htmlspecialchars($admin_phone) . '</span></div>';
                        echo '</div>';
                        
                        echo '<div class="info">';
                        echo '💡 <strong>Important:</strong> After logging in, please change your password from the profile page for security.<br>';
                        echo '🔒 Use these credentials to access the admin panel at <code>/admin/dashboard.php</code>';
                        echo '</div>';
                    } else {
                        echo '<div class="error">❌ Failed to create admin user. Please check your database structure.</div>';
                    }
                    
                    // Display database status
                    displayDatabaseStatus($pdo);
                    
                    // Check for required tables
                    echo '<hr>';
                    echo '<h3>📋 Required Tables Check</h3>';
                    $required_tables = ['user_types', 'users', 'crops', 'planting_requests', 'marketplace_items', 'orders'];
                    $all_exist = true;
                    
                    echo '<table class="status-table">';
                    echo '<tr><th>Table Name</th><th>Status</th></tr>';
                    foreach ($required_tables as $table) {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        $exists = $stmt->rowCount() > 0;
                        $all_exist = $all_exist && $exists;
                        $status = $exists ? '✅ Exists' : '❌ Missing';
                        $color = $exists ? '#22c55e' : '#ef4444';
                        echo "<tr><td><code>$table</code></td><td style='color: $color;'>$status</td></tr>";
                    }
                    echo '</table>';
                    
                    if (!$all_exist) {
                        echo '<div class="error">⚠️ Some required tables are missing. Please run the database setup script first.</div>';
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
            
            <hr>
            
            <div class="button-group">
                <a href="dashboard.php" class="button">🚀 Go to Admin Dashboard</a>
                <a href="../index.php" class="button button-secondary">🏠 Go to Homepage</a>
            </div>
            
            <div class="info" style="margin-top: 20px;">
                <strong>⚠️ Security Note:</strong><br>
                - Delete or move this file (<code>test.php</code>) after successful setup to prevent unauthorized access.<br>
                - Change the default password immediately after first login.<br>
                - Keep your database credentials secure.
            </div>
        </div>
    </div>
</body>
</html>