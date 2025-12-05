<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';

// Return JSON response
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$item_id = (int)$_GET['id'];
$status = $_GET['status'];

// Validate status
$allowed_statuses = ['Lost', 'Recovered', 'Claimed'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Check if item belongs to user
    $check_stmt = $pdo->prepare("SELECT user_id FROM lost_items WHERE lost_id = ?");
    $check_stmt->execute([$item_id]);
    $item = $check_stmt->fetch();
    
    if (!$item || $item['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Update status
    $update_stmt = $pdo->prepare("UPDATE lost_items SET status = ?, updated_at = NOW() WHERE lost_id = ?");
    $update_stmt->execute([$status, $item_id]);
    
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}