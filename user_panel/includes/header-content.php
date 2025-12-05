<?php
// Determine active page
$active_page = basename($_SERVER['PHP_SELF']);

// Map page titles to icons
$page_icons = [
    'dashboard.php' => 'fas fa-home',
    'lost_items.php' => 'fas fa-exclamation-circle',
    'found_items.php' => 'fas fa-check-circle',
    'found_item.php' => 'fas fa-check-circle',
    'claims.php' => 'fas fa-handshake',
    'announcements.php' => 'fas fa-bullhorn',
    'profile.php' => 'fas fa-user',
    'profile/view.php' => 'fas fa-user',
    'profile/edit.php' => 'fas fa-user-edit'
];

// Get page title from header.php or use active page name
$page_title = isset($page_title) ? $page_title : ucwords(str_replace(['_', '.php'], [' ', ''], $active_page));
$page_icon = $page_icons[$active_page] ?? 'fas fa-file';

// Make sure $user variable is set
if (!isset($user)) {
    // Try to get user from session if not already set
    session_start();
    require_once __DIR__ . '/../config/config.php';
    
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // If error, set empty user
            $user = ['first_name' => 'User', 'last_name' => ''];
        }
    } else {
        $user = ['first_name' => 'User', 'last_name' => ''];
    }
}
?>

<!-- Main Content -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> 
            Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search items, claims, or announcements...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="<?php echo $page_icon; ?>"></i>
            <?php echo htmlspecialchars($page_title); ?>
        </div>