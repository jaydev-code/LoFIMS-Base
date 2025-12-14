<?php
// config/config.php

// Check if session has already been started
if (session_status() === PHP_SESSION_NONE) {
    // Only set session ini settings if session hasn't started yet
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.gc_maxlifetime', 86400);
}

// ==================== DATABASE CONFIG ====================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'LoFIMS_BASE');
define('DB_USER', 'root');
define('DB_PASS', 'Eljay108598100018');

// ==================== SITE CONFIG ====================
define('SITE_NAME', 'LoFIMS');
define('SITE_URL', 'http://localhost/LoFIMS_BASE');
define('ADMIN_EMAIL', 'admin@lofims.com');

// ==================== DEBUG MODE ====================
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ==================== TIMEZONE ====================
date_default_timezone_set('Asia/Manila');

// ==================== DATABASE CONNECTION ====================
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// ==================== HELPER FUNCTIONS ====================
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generate_random_string($length = 8) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    if (function_exists('random_int')) {
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
    } else {
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }
    }
    
    return $randomString;
}
?>
