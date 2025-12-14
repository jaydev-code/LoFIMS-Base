<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if ($announcement) {
    echo json_encode([
        'success' => true,
        'id' => $announcement['id'],
        'title' => $announcement['title'],
        'content' => $announcement['content']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Announcement not found']);
}