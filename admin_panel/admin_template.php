<?php
// admin_template.php - Master template for all admin pages
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Get current page title from GET parameter or default
$page_title = isset($_GET['page']) ? ucfirst(str_replace('_', ' ', $_GET['page'])) : 'Dashboard';
$current_page = basename($_SERVER['PHP_SELF']);

// Get admin info for header
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $admin = ['first_name' => 'Admin', 'last_name' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoFIMS Admin | <?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <?php if ($current_page == 'dashboard.php'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main" id="mainContent">
        <!-- Include Header -->
        <?php include 'includes/header.php'; ?>
        
        <!-- Page-specific content will be included here -->
        <div class="content-wrapper">
            <?php 
            // Determine which page to include based on request
            $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
            
            // List of allowed pages for security
            $allowed_pages = [
                'dashboard' => 'dashboard.php',
                'manage_users' => 'manage_users.php',
                'reports' => 'reports.php',
                'categories' => 'categories.php',
                'announcements' => 'announcements.php'
            ];
            
            $page_file = $allowed_pages[$page] ?? 'dashboard.php';
            
            // Include the page content
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                include 'dashboard.php';
            }
            ?>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/admin.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if ($current_page == 'dashboard.php'): ?>
    <script src="assets/js/dashboard.js"></script>
    <?php elseif ($current_page == 'manage_users.php'): ?>
    <script src="assets/js/manage_users.js"></script>
    <?php elseif ($current_page == 'reports.php'): ?>
    <script src="assets/js/reports.js"></script>
    <?php endif; ?>
</body>
</html>