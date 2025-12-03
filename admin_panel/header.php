<?php
// header.php
// REMOVED: session_start(); // Already started in main file

// Ensure $pdo is available
if(!isset($pdo)){
    require_once __DIR__ . '/../../config/config.php';
}

// Get user info
$userName = "Administrator";
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $userName = $row['first_name'] . ' ' . $row['last_name'];
    }
}
?>
<div class="header">
    <div class="header-left">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="header-center">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span id="userNameDisplay"><?= htmlspecialchars($userName) ?></span>
        </div>
    </div>
    
    <div class="header-right">
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search items, users, reports...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
        
        <button class="toggle-btn" id="notificationsBtn" title="Notifications">
            <i class="fas fa-bell"></i>
        </button>
    </div>
</div>