<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if(!$q) { echo json_encode([]); exit; }

try {
    $qLike = "%$q%";
    $results = [];

    // Users
    $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE full_name LIKE ? LIMIT 5");
    $stmt->execute([$qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = ['type'=>'User','name'=>$row['full_name'],'link'=>'manage_users/user.php?id='.$row['user_id']];
    }

    // Lost Items
    $stmt = $pdo->prepare("SELECT item_id, item_name FROM lost_items WHERE item_name LIKE ? LIMIT 5");
    $stmt->execute([$qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = ['type'=>'Lost Item','name'=>$row['item_name'],'link'=>'lost_items/item.php?id='.$row['item_id']];
    }

    // Found Items
    $stmt = $pdo->prepare("SELECT item_id, item_name FROM found_items WHERE item_name LIKE ? LIMIT 5");
    $stmt->execute([$qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = ['type'=>'Found Item','name'=>$row['item_name'],'link'=>'found_items/item.php?id='.$row['item_id']];
    }

    // Claims
    $stmt = $pdo->prepare("SELECT claim_id, status FROM claims WHERE claim_id LIKE ? LIMIT 5");
    $stmt->execute([$qLike]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $results[] = ['type'=>'Claim','name'=>'Claim #'.$row['claim_id'],'extra'=>$row['status'],'link'=>'claims/claim.php?id='.$row['claim_id']];
    }

    echo json_encode($results);

} catch(PDOException $e){
    echo json_encode([]);
}
