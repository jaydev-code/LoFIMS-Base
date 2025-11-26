<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // <-- fixed path
    exit;
}

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
