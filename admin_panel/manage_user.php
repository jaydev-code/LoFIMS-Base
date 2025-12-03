<?php
// manage_user.php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Get admin info
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
    
    // Get all users
    $users = $pdo->query("SELECT user_id, first_name, last_name, email, role_id, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="logo" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li class="active"><a href="manage_user.php"><i class="fas fa-users"></i> Manage Users</a></li>
        <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main">
    <div class="header">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
        </div>
    </div>
    
    <h1>Manage Users</h1>
    
    <div class="dashboard-boxes">
        <div class="box">
            <h2><?= $totalUsers ?></h2>
            <p>Total Users</p>
        </div>
        <div class="box">
            <h2><?= $totalLost ?></h2>
            <p>Lost Items</p>
        </div>
        <div class="box">
            <h2><?= $totalFound ?></h2>
            <p>Found Items</p>
        </div>
        <div class="box">
            <h2><?= $totalClaims ?></h2>
            <p>Claims</p>
        </div>
    </div>
    
    <table style="width:100%; background:white; border-radius:10px; padding:20px; margin-top:20px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?= $user['user_id'] ?></td>
                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= $user['role_id'] == 1 ? 'Admin' : 'User' ?></td>
                <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="dashboard.js"></script>
<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('folded');
});
</script>
</body>
</html>