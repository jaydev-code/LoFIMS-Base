<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

try {
    // Fetch categories
    $stmt = $pdo->query("SELECT category_id, category_name FROM item_categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- Main content -->
<div class="main">
    <!-- Header -->
    <?php include __DIR__ . '/header.php'; ?>

    <h1>Item Categories</h1>

    <table class="user-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Category Name</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($categories as $index => $cat): ?>
            <tr style="animation-delay: <?= ($index * 0.05) ?>s;">
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($cat['category_name']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="dashboard.js"></script>
</body>
</html>
