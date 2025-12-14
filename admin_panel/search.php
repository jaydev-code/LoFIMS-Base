<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only allow search for logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if(strlen($q) < 2) { 
    echo json_encode([]); 
    exit; 
}

try {
    $qLike = "%$q%";
    $results = [];

    // Search Users (by name, email, student ID)
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, student_id 
        FROM users 
        WHERE first_name LIKE ? 
           OR last_name LIKE ? 
           OR email LIKE ? 
           OR student_id LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$qLike, $qLike, $qLike, $qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $fullName = $row['first_name'] . ' ' . $row['last_name'];
        $displayName = $fullName . (empty($row['student_id']) ? '' : ' (' . $row['student_id'] . ')');
        $results[] = [
            'type' => 'User',
            'title' => $displayName,
            'subtitle' => $row['email'],
            'icon' => 'fa-user',
            'url' => 'manage_user.php?search=' . urlencode($row['first_name'] . ' ' . $row['last_name'])
        ];
    }

    // Search Lost Items
    $stmt = $pdo->prepare("
        SELECT li.lost_id, li.item_name, li.description, ic.category_name,
               u.first_name, u.last_name
        FROM lost_items li
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        LEFT JOIN users u ON li.user_id = u.user_id
        WHERE li.item_name LIKE ? 
           OR li.description LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$qLike, $qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = [
            'type' => 'Lost Item',
            'title' => $row['item_name'],
            'subtitle' => ($row['category_name'] ?? 'Uncategorized') . ' • ' . ($row['first_name'] . ' ' . $row['last_name']),
            'icon' => 'fa-search',
            'url' => 'reports.php?type=lost&search=' . urlencode($row['item_name'])
        ];
    }

    // Search Found Items
    $stmt = $pdo->prepare("
        SELECT fi.found_id, fi.item_name, fi.description, ic.category_name,
               u.first_name, u.last_name
        FROM found_items fi
        LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
        LEFT JOIN users u ON fi.user_id = u.user_id
        WHERE fi.item_name LIKE ? 
           OR fi.description LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$qLike, $qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = [
            'type' => 'Found Item',
            'title' => $row['item_name'],
            'subtitle' => ($row['category_name'] ?? 'Uncategorized') . ' • ' . ($row['first_name'] . ' ' . $row['last_name']),
            'icon' => 'fa-check-circle',
            'url' => 'reports.php?type=found&search=' . urlencode($row['item_name'])
        ];
    }

    // Search Claims
    $stmt = $pdo->prepare("
        SELECT c.claim_id, c.status, c.created_at,
               li.item_name as lost_item,
               fi.item_name as found_item,
               u.first_name as claimant_first,
               u.last_name as claimant_last
        FROM claims c
        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
        LEFT JOIN found_items fi ON c.found_id = fi.found_id
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.claim_id LIKE ?
           OR li.item_name LIKE ?
           OR fi.item_name LIKE ?
           OR u.first_name LIKE ?
           OR u.last_name LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$qLike, $qLike, $qLike, $qLike, $qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $claimTitle = 'Claim #' . $row['claim_id'];
        if($row['lost_item'] && $row['found_item']) {
            $claimTitle .= ': ' . $row['lost_item'] . ' → ' . $row['found_item'];
        }
        
        $results[] = [
            'type' => 'Claim',
            'title' => $claimTitle,
            'subtitle' => $row['status'] . ' • ' . ($row['claimant_first'] . ' ' . $row['claimant_last']),
            'icon' => 'fa-handshake',
            'url' => 'claims.php?search=' . urlencode($row['claim_id'])
        ];
    }

    // Search Categories
    $stmt = $pdo->prepare("
        SELECT category_id, category_name 
        FROM item_categories 
        WHERE category_name LIKE ? 
        LIMIT 5
    ");
    $stmt->execute([$qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = [
            'type' => 'Category',
            'title' => $row['category_name'],
            'subtitle' => 'Item Category',
            'icon' => 'fa-tag',
            'url' => 'categories.php?search=' . urlencode($row['category_name'])
        ];
    }

    // Search Announcements
    $stmt = $pdo->prepare("
        SELECT id, title, created_at 
        FROM announcements 
        WHERE title LIKE ? 
           OR content LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$qLike, $qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = [
            'type' => 'Announcement',
            'title' => $row['title'],
            'subtitle' => date('M d, Y', strtotime($row['created_at'])),
            'icon' => 'fa-bullhorn',
            'url' => 'announcements.php?search=' . urlencode($row['title'])
        ];
    }

    echo json_encode($results);

} catch(PDOException $e){
    error_log("Search error: " . $e->getMessage());
    echo json_encode([]);
}