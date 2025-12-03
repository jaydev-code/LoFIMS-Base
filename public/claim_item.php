<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT c.claim_id, u.first_name, u.last_name,
               li.item_name AS lost_item_name, f.item_name AS found_item_name,
               c.date_claimed, c.status
        FROM claims c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
        LEFT JOIN found_items f ON c.found_id = f.found_id
        ORDER BY c.date_claimed DESC
    ");
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching claims: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Claims List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; text-align: center; }
        h1 { color: #66cc66; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #ccffcc; }
        tr:nth-child(even) { background-color: #f2fff2; }
    </style>
</head>
<body>
    <h1>Claims</h1>

    <?php if (!empty($claims)): ?>
    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Lost Item</th>
            <th>Found Item</th>
            <th>Date Claimed</th>
            <th>Status</th>
        </tr>
        <?php foreach($claims as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['claim_id']) ?></td>
            <td><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
            <td><?= htmlspecialchars($c['lost_item_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['found_item_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['date_claimed']) ?></td>
            <td><?= htmlspecialchars($c['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>No claims to display.</p>
    <?php endif; ?>
</body>
</html>
