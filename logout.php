<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is a timeout logout
$timeout = isset($_GET['timeout']) ? '?timeout=1' : '';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to index page with timeout parameter if applicable
header("Location: index.php" . $timeout);
exit();
?>