<?php
// sidebar.php - NO session_start() here
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="logo" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li class="<?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" 
            data-page="dashboard.php">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </li>
        <li class="<?= ($current_page == 'manage_users.php') ? 'active' : '' ?>" 
            data-page="manage_users.php">
            <i class="fas fa-users"></i>
            <span>Manage Users</span>
        </li>
        <li class="<?= ($current_page == 'categories.php') ? 'active' : '' ?>" 
            data-page="categories.php">
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </li>
        <li class="<?= ($current_page == 'announcement.php') ? 'active' : '' ?>" 
            data-page="announcement.php">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </li>
        <li class="<?= ($current_page == 'report.php') ? 'active' : '' ?>" 
            data-page="report.php">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </li>
        <li data-page="logout.php">
            <i class="fas fa-right-from-bracket"></i>
            <span>Logout</span>
        </li>
    </ul>
</div>