<?php
// get_user.php - Get user data for editing
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'User ID required']);
    exit();
}

$userId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, role_id, is_active FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
