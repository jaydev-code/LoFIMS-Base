<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // <-- fixed path
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
