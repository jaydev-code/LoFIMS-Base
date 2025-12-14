<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);

    echo json_encode([
        'success' => true, 
        'count' => $stmt->rowCount(),
        'message' => $stmt->rowCount() . ' notifications marked as read'
    ]);
} catch (PDOException $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>