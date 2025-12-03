<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) exit('Unauthorized');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

try {
    $resultsHtml = '';

    if ($query !== '') {
        // Search lost items
        $stmtLost = $pdo->prepare("SELECT * FROM lost_items WHERE (item_name LIKE ? OR description LIKE ?) AND user_id=? ORDER BY created_at DESC LIMIT 5");
        $stmtLost->execute(["%$query%", "%$query%", $_SESSION['user_id']]);
        $lostItems = $stmtLost->fetchAll(PDO::FETCH_ASSOC);

        if ($lostItems) {
            $resultsHtml .= '<h4><i class="fas fa-pencil-alt"></i> Lost Items</h4>';
            foreach ($lostItems as $item) {
                $resultsHtml .= '<div class="activity-item">
                                    <div class="activity-badge bg-danger"></div>
                                    <div>
                                        <strong>'.htmlspecialchars($item['item_name']).'</strong><br>
                                        <small>'.htmlspecialchars(substr($item['description'],0,50)).'...</small><br>
                                        <span style="font-size:12px; color:#999;">Lost: '.date("M d, Y H:i", strtotime($item['created_at'])).'</span>
                                    </div>
                                 </div>';
            }
        }

        // Search found items
        $stmtFound = $pdo->prepare("SELECT * FROM found_items WHERE (item_name LIKE ? OR description LIKE ?) AND user_id=? ORDER BY created_at DESC LIMIT 5");
        $stmtFound->execute(["%$query%", "%$query%", $_SESSION['user_id']]);
        $foundItems = $stmtFound->fetchAll(PDO::FETCH_ASSOC);

        if ($foundItems) {
            $resultsHtml .= '<h4><i class="fas fa-box"></i> Found Items</h4>';
            foreach ($foundItems as $item) {
                $resultsHtml .= '<div class="activity-item">
                                    <div class="activity-badge bg-success"></div>
                                    <div>
                                        <strong>'.htmlspecialchars($item['item_name']).'</strong><br>
                                        <small>'.htmlspecialchars(substr($item['description'],0,50)).'...</small><br>
                                        <span style="font-size:12px; color:#999;">Found: '.date("M d, Y H:i", strtotime($item['created_at'])).'</span>
                                    </div>
                                 </div>';
            }
        }

        // Search claims
        $stmtClaims = $pdo->prepare("SELECT * FROM claims WHERE claim_id LIKE ? AND user_id=? ORDER BY date_claimed DESC LIMIT 5");
        $stmtClaims->execute(["%$query%", $_SESSION['user_id']]);
        $claims = $stmtClaims->fetchAll(PDO::FETCH_ASSOC);

        if ($claims) {
            $resultsHtml .= '<h4><i class="fas fa-hand-holding"></i> Claims</h4>';
            foreach ($claims as $claim) {
                $resultsHtml .= '<div class="activity-item">
                                    <div class="activity-badge bg-warning"></div>
                                    <div>
                                        <strong>Claim #'.htmlspecialchars($claim['claim_id']).'</strong><br>
                                        <small>Status: '.htmlspecialchars($claim['status']).'</small><br>
                                        <span style="font-size:12px; color:#999;">Date: '.date("M d, Y", strtotime($claim['date_claimed'])).'</span>
                                    </div>
                                 </div>';
            }
        }

        if (empty($resultsHtml)) $resultsHtml = '<p style="color:#666;">No results found for "'.htmlspecialchars($query).'"</p>';
    } else {
        $resultsHtml = '<p style="color:#666;">Type something to search.</p>';
    }

    echo $resultsHtml;

} catch(PDOException $e){
    echo '<p style="color:red;">Error fetching results.</p>';
}

