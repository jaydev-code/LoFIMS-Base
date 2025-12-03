<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // adjust path if needed
    exit;
}

// Fetch found items
try {
    $stmt = $pdo->query("
        SELECT f.found_id, u.first_name, u.last_name, c.category_name,
               f.item_name, f.description, f.date_found, f.place_found, f.status
        FROM found_items f
        JOIN users u ON f.user_id = u.user_id
        JOIN item_categories c ON f.category_id = c.category_id
        ORDER BY f.date_found DESC
    ");
    $foundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching found items: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Found Items List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; text-align: center; }
        h1 { color: #66b3ff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #cce6ff; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .add-btn {
            display: inline-block; padding: 10px 18px; background-color: #ff4d4d;
            color: white; text-decoration: none; border-radius: 8px; font-weight: bold;
            margin-bottom: 15px; transition: 0.2s;
        }
        .add-btn:hover { background-color: #ff1a1a; }
    </style>
</head>
<body>
    <h1>Found Items</h1>
    <a class="add-btn" href="add_found_item.php">Add Found Item</a>

    <?php if (!empty($foundItems)): ?>
    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Category</th>
            <th>Item Name</th>
            <th>Description</th>
            <th>Date Found</th>
            <th>Place Found</th>
            <th>Status</th>
        </tr>
        <?php foreach($foundItems as $fi): ?>
        <tr>
            <td><?= htmlspecialchars($fi['found_id']) ?></td>
            <td><?= htmlspecialchars($fi['first_name'].' '.$fi['last_name']) ?></td>
            <td><?= htmlspecialchars($fi['category_name']) ?></td>
            <td><?= htmlspecialchars($fi['item_name']) ?></td>
            <td><?= htmlspecialchars($fi['description']) ?></td>
            <td><?= htmlspecialchars($fi['date_found']) ?></td>
            <td><?= htmlspecialchars($fi['place_found']) ?></td>
            <td><?= htmlspecialchars($fi['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>No found items to display.</p>
    <?php endif; ?>
</body>
</html>
