<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$notificationId = $_GET['id'] ?? 0;

if ($notificationId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit();
}

try {
    // Verify notification belongs to user and mark as read
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'rows' => $stmt->rowCount()]);
} catch (PDOException $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
