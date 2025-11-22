<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Fetch lost items
try {
    $stmt = $pdo->query("
        SELECT li.lost_id, u.first_name, u.last_name, c.category_name, 
               li.item_name, li.description, li.date_reported, li.status
        FROM lost_items li
        LEFT JOIN users u ON li.user_id = u.user_id
        LEFT JOIN item_categories c ON li.category_id = c.category_id
        ORDER BY li.date_reported DESC
    ");
    $lostItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching lost items: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lost Items List</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; text-align: center; }
        h1 { background: #ff9999; color: white; padding: 20px 0; margin: 0 0 20px 0; }
        .add-btn {
            display: inline-block;
            padding: 10px 18px;
            background-color: #ff4d4d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .add-btn:hover { background-color: #ff1a1a; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #ffd6d6; }
        tr:nth-child(even) { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <h1>Lost Items List</h1>

    <!-- Add Lost Item Button -->
    <a class="add-btn" href="add_lost_item.php">Add Lost Item</a>

    <?php if (!empty($lostItems)): ?>
    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Category</th>
            <th>Item Name</th>
            <th>Description</th>
            <th>Date Reported</th>
            <th>Status</th>
        </tr>
        <?php foreach ($lostItems as $li): ?>
        <tr>
            <td><?= htmlspecialchars($li['lost_id']) ?></td>
            <td><?= htmlspecialchars($li['first_name'] . ' ' . $li['last_name']) ?></td>
            <td><?= htmlspecialchars($li['category_name'] ?? 'Unknown') ?></td>
            <td><?= htmlspecialchars($li['item_name']) ?></td>
            <td><?= htmlspecialchars($li['description']) ?></td>
            <td><?= htmlspecialchars($li['date_reported']) ?></td>
            <td><?= htmlspecialchars($li['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>No lost items to display.</p>
    <?php endif; ?>
</body>
</html>

