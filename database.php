<?php
function getDBConnection() {
   
    $host = 'sql305.infinityfree.com'; 
    $dbname = 'if0_41288892_agritrace'; 
    $username = 'if0_41288892'; 
    $password = 'TSU9kBkUOHFs'; 
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new PDOException("Connection failed: " . $e->getMessage());
    }
}
?>