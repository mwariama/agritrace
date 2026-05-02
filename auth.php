<?php

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once 'database.php';

// Get request action
$action = $_GET['action'] ?? '';

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Route based on action
    switch($action) {
        case 'login':
            handleLogin($pdo);
            break;
        case 'signup':
            handleSignup($pdo);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'check':
            checkSession();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Auth error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}

// ==================== LOGIN HANDLER ====================

function handleLogin($pdo) {
    try {
        // Get POST data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Email and password are required']);
            return;
        }
        
        // Get user from database
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone_number,
                u.password_hash,
                u.is_active,
                u.last_login,
                ut.role_name as role
            FROM users u
            JOIN user_types ut ON u.type_id = ut.id
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("Login failed - user not found or inactive: $email");
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            return;
        }
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            error_log("Login successful for: $email");
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set session variables - THIS IS THE KEY PART
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Log the login
            logSystemAction($pdo, $user['id'], 'LOGIN', 'users', $user['id'], null, json_encode(['email' => $email]));
            
            // Prepare user data for response (minimal info)
            $userData = [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            echo json_encode([
                'success' => true,
                'user' => $userData,
                'message' => 'Login successful'
            ]);
            return;
            
        } else {
            error_log("Login failed - wrong password for: $email");
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            return;
        }
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Login failed']);
    }
}

// ==================== SIGNUP HANDLER ====================

function handleSignup($pdo) {
    try {
        // Get POST data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }
        
        $fullName = $input['full_name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone_number'] ?? '';
        $userType = $input['user_type'] ?? '';
        $password = $input['password'] ?? '';
        
        // Validate input
        if (empty($fullName) || empty($email) || empty($phone) || empty($userType) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            return;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            return;
        }
        
        // Validate phone
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if (strlen($phoneDigits) < 10) {
            echo json_encode(['success' => false, 'error' => 'Invalid phone number (must have at least 10 digits)']);
            return;
        }
        
        // Validate password strength
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email already registered']);
            return;
        }
        
        // Check if phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Phone number already registered']);
            return;
        }
        
        // Get user type ID
        $stmt = $pdo->prepare("SELECT id FROM user_types WHERE role_name = ?");
        $stmt->execute([$userType]);
        $typeId = $stmt->fetchColumn();
        
        if (!$typeId) {
            echo json_encode(['success' => false, 'error' => 'Invalid user type']);
            return;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (type_id, full_name, email, phone_number, password_hash, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $result = $stmt->execute([$typeId, $fullName, $email, $phone, $passwordHash]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            
            // Log the signup
            logSystemAction($pdo, $userId, 'SIGNUP', 'users', $userId, null, json_encode(['email' => $email]));
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully! You can now login.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create account']);
        }
        
    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Signup failed']);
    }
}

// ==================== LOGOUT HANDLER ====================

function handleLogout() {
    // Destroy the session
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

// ==================== CHECK SESSION ====================

function checkSession() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        echo json_encode([
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ]
        ]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
}

// ==================== HELPER FUNCTIONS ====================

function logSystemAction($pdo, $userId, $action, $entityType, $entityId, $oldData, $newData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, action, entity_type, entity_id, old_data, new_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $entityType, $entityId, $oldData, $newData]);
    } catch (Exception $e) {
        error_log('Failed to log action: ' . $e->getMessage());
    }
}
?>