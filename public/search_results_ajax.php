<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$user_id = $_SESSION['user_id'] ?? 0;

if (!$query || !$user_id) {
    echo '<p>No results found.</p>';
    exit;
}

try {
    // Search lost items
    $stmtLost = $pdo->prepare("SELECT item_name, description, created_at FROM lost_items WHERE user_id=? AND item_name LIKE ? LIMIT 5");
    $stmtLost->execute([$user_id, "%$query%"]);
    $lostItems = $stmtLost->fetchAll(PDO::FETCH_ASSOC);

    // Search found items
    $stmtFound = $pdo->prepare("SELECT item_name, description, created_at FROM found_items WHERE user_id=? AND item_name LIKE ? LIMIT 5");
    $stmtFound->execute([$user_id, "%$query%"]);
    $foundItems = $stmtFound->fetchAll(PDO::FETCH_ASSOC);

    // Search claims
    $stmtClaim = $pdo->prepare("SELECT claim_id, status, date_claimed FROM claims WHERE user_id=? AND claim_id LIKE ? LIMIT 5");
    $stmtClaim->execute([$user_id, "%$query%"]);
    $claims = $stmtClaim->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($lostItems as $item) {
        $results[] = [
            'title' => $item['item_name'],
            'desc' => $item['description'],
            'date' => date("M d, Y H:i", strtotime($item['created_at']))
        ];
    }

    foreach ($foundItems as $item) {
        $results[] = [
            'title' => $item['item_name'],
            'desc' => $item['description'],
            'date' => date("M d, Y H:i", strtotime($item['created_at']))
        ];
    }

    foreach ($claims as $claim) {
        $results[] = [
            'title' => "Claim #".$claim['claim_id'],
            'desc' => "Status: ".$claim['status'],
            'date' => date("M d, Y H:i", strtotime($claim['date_claimed']))
        ];
    }

    if (!$results) {
        echo '<p>No results found.</p>';
        exit;
    }

    // Output as HTML cards
    foreach ($results as $res) {
        echo '<div class="search-card">';
        echo '<h5>'.htmlspecialchars($res['title']).'</h5>';
        echo '<p>'.htmlspecialchars(substr($res['desc'],0,50)).'</p>';
        echo '<small>'.$res['date'].'</small>';
        echo '</div>';
    }

} catch(PDOException $e) {
    echo '<p style="color:red;">Error fetching search results.</p>';
}
