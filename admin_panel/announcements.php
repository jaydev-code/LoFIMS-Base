<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// CSRF Protection - Generate token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token for POST requests
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed in announcements.php");
            die("Invalid request. Please try again.");
        }
    }
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all announcements
    $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get announcement stats
    $totalAnnouncements = count($announcements);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayAnnouncements = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $lastWeekAnnouncements = $stmt->fetchColumn();

} catch(PDOException $e){
    die("Error fetching data: " . $e->getMessage());
}

// Handle Add Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    validateCSRF();
    
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $error = "Title and content are required!";
    } elseif (strlen($title) > 255) {
        $error = "Title must be less than 255 characters!";
    } else {
        try {
            // Add new announcement with prepared statement
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            
            $newAnnouncementId = $pdo->lastInsertId();
            
            header("Location: announcements.php?success=added&id=" . $newAnnouncementId);
            exit();
        } catch(PDOException $e) {
            $error = "Error adding announcement: " . $e->getMessage();
        }
    }
}

// Handle Edit Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    validateCSRF();
    
    $announcementId = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $error = "Title and content are required!";
    } elseif (strlen($title) > 255) {
        $error = "Title must be less than 255 characters!";
    } else {
        try {
            // Update announcement with prepared statement
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $announcementId]);
            
            header("Location: announcements.php?success=updated&id=" . $announcementId);
            exit();
        } catch(PDOException $e) {
            $error = "Error updating announcement: " . $e->getMessage();
        }
    }
}

// Handle Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    validateCSRF();
    
    $announcementId = (int)$_POST['announcement_id'];
    
    try {
        // Get announcement title before deleting (for success message)
        $stmt = $pdo->prepare("SELECT title FROM announcements WHERE id = ?");
        $stmt->execute([$announcementId]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        $announcementTitle = $announcement['title'] ?? '';
        
        // Delete announcement with prepared statement
        $deleteStmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $deleteStmt->execute([$announcementId]);
        
        header("Location: announcements.php?success=deleted&title=" . urlencode($announcementTitle));
        exit();
    } catch(PDOException $e) {
        $error = "Error deleting announcement: " . $e->getMessage();
    }
}

// Handle success messages
$successMessage = '';
if (isset($_GET['success'])) {
    switch($_GET['success']) {
        case 'added': 
            $successMessage = "✅ Announcement added successfully!"; 
            break;
        case 'updated': 
            $successMessage = "✅ Announcement updated successfully!"; 
            break;
        case 'deleted': 
            $announcementTitle = $_GET['title'] ?? '';
            $successMessage = "✅ Announcement <strong>" . htmlspecialchars($announcementTitle, ENT_QUOTES, 'UTF-8') . "</strong> deleted successfully!"; 
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Announcements - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ===== General ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f6fa;display:flex;min-height:100vh;overflow-x:hidden;color:#333;}

/* ===== Sidebar ===== */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1e2a38;color:white;display:flex;flex-direction:column;transition:0.3s;z-index:1000;box-shadow:3px 0 15px rgba(0,0,0,0.1);}
.sidebar.folded{width:70px;}
.sidebar.show{left:0;}
.sidebar.hide{left:-220px;}
.sidebar .logo{font-size:20px;font-weight:bold;text-align:center;padding:20px 0;background:#16212b;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:0.3s;}
.sidebar.folded .logo span{display:none;}
.sidebar ul{list-style:none;padding:20px 0;flex:1;}
.sidebar ul li{padding:15px 20px;cursor:pointer;position:relative;display:flex;align-items:center;border-left:3px solid transparent;transition:0.3s;}
.sidebar ul li:hover{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li.active{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li i{margin-right:15px;width:20px;text-align:center;transition:0.3s;}
.sidebar.folded ul li span{display:none;}
.sidebar ul li .tooltip{position:absolute;left:100%;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:5px 10px;border-radius:5px;font-size:14px;white-space:nowrap;display:none;z-index:1001;}
.sidebar.folded ul li:hover .tooltip{display:block;}

/* ===== Main Content ===== */
.main {
    margin-left: 220px;
    padding: 20px;
    flex: 1;
    transition: 0.3s;
    min-height: 100vh;
    max-width: calc(100% - 220px);
    width: 100%;
    overflow-x: hidden;
}

.sidebar.folded ~ .main {
    margin-left: 70px;
    max-width: calc(100% - 70px);
}

/* ===== Header ===== */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
    width: 100%;
}

.user-info {
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e2a38;
}

.user-info i {
    color: #1e90ff;
    font-size: 18px;
}

/* Search bar */
.search-bar {
    position: relative;
    width: 250px;
    max-width: 100%;
}

.search-bar input {
    width: 100%;
    padding: 8px 35px 8px 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    outline: none;
}

.search-bar i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
}

.search-results {
    position: absolute;
    top: 38px;
    left: 0;
    width: 100%;
    max-height: 300px;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(6px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    overflow-y: auto;
    display: none;
    z-index: 2000;
}

.search-results .result-item {
    padding: 10px 15px;
    cursor: pointer;
    transition: 0.3s;
}

.search-results .result-item:hover {
    background: #f0f4ff;
}

/* Page Header */
.page-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.page-header h1 {
    color: #1e2a38;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header p {
    color: #666;
    margin-top: 5px;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.action-btn {
    flex: 1 1 150px;
    min-width: 120px;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    background: #1e90ff;
    color: white;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
    position: relative;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.action-btn i {
    font-size: 24px;
}

.action-btn:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}

.action-btn.warning {
    background: #ffc107;
    color: #333;
    box-shadow: 0 5px 15px rgba(255,193,7,0.3);
}

.action-btn.warning:hover {
    box-shadow: 0 8px 20px rgba(255,193,7,0.3);
}

/* Dashboard Boxes */
.dashboard-boxes {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.box {
    flex: 1 1 150px;
    min-width: 120px;
    background: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
}

.box:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.box h2 {
    font-size: 36px;
    color: #1e90ff;
    margin-bottom: 10px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

.box p {
    font-size: 18px;
    color: #555;
    font-weight: 500;
}

.box.warning {
    border-left: 4px solid #ffc107;
    background: #fff9e6;
}

.box.warning h2 {
    color: #ffc107;
}

/* Add Announcement Button */
.add-announcement-btn {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s;
    white-space: nowrap;
    margin-bottom: 20px;
}

.add-announcement-btn:hover {
    background: #1c7ed6;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
}

/* Announcements Container */
.announcements-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.announcements-container h3 {
    color: #1e2a38;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Announcements List */
.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.announcement-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #1e90ff;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.announcement-item:hover {
    background: #f0f8ff;
    transform: translateX(5px);
}

.announcement-item.new::after {
    content: 'NEW';
    position: absolute;
    top: 10px;
    right: -25px;
    background: #ff4757;
    color: white;
    padding: 5px 30px;
    font-size: 12px;
    font-weight: bold;
    transform: rotate(45deg);
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.announcement-title {
    color: #1e2a38;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    flex: 1;
    min-width: 200px;
}

.announcement-date {
    color: #666;
    font-size: 13px;
    background: white;
    padding: 5px 10px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.announcement-content {
    color: #444;
    line-height: 1.6;
    margin: 0 0 15px 0;
    font-size: 14px;
    max-height: 100px;
    overflow: hidden;
    position: relative;
}

.announcement-content.expanded {
    max-height: none;
    overflow: visible;
}

.read-more-btn {
    background: none;
    border: none;
    color: #1e90ff;
    cursor: pointer;
    font-size: 13px;
    padding: 5px 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.read-more-btn:hover {
    text-decoration: underline;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.btn-icon {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 6px;
    transition: 0.2s;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: #f8f9fa;
}

.btn-icon.edit:hover {
    color: #1e90ff;
}

.btn-icon.delete:hover {
    color: #dc3545;
}

.btn-icon.view:hover {
    color: #28a745;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    background: white;
    border-radius: 10px;
    border: 2px dashed #ddd;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ddd;
}

/* ===== ANNOUNCEMENT MODALS - SEPARATE STYLES ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 3000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* Announcement modals are larger for forms */
#announcementModal .modal-content,
#deleteModal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 100%;
    max-width: 700px; /* Large for forms */
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

/* Form Styles (for announcement forms only) */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 16px;
    transition: 0.2s;
    font-family: Arial, sans-serif;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.2);
}

.form-group textarea {
    min-height: 150px;
    resize: vertical;
}

.char-count {
    text-align: right;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.char-count.warning {
    color: #ffc107;
}

.char-count.danger {
    color: #dc3545;
}

/* ===== LOGOUT MODAL STYLES (EXACT SAME AS DASHBOARD) ===== */
#logoutModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

/* Logout modal is smaller and different */
#logoutModal > .modal-content {
    background: white;
    width: 90%;
    max-width: 420px !important; /* Smaller for logout */
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-origin: center;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

#logoutModal > .modal-content > .modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

#logoutModal > .modal-content > .modal-header i {
    font-size: 24px;
}

#logoutModal > .modal-content > .modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

#logoutModal > .modal-content > .modal-body {
    padding: 30px 25px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-body .warning-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ff9500, #ff5e3a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 8px 25px rgba(255, 94, 58, 0.3);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 12px 35px rgba(255, 94, 58, 0.4);
    }
}

#logoutModal > .modal-content > .modal-body .warning-icon i {
    font-size: 30px;
    color: white;
}

#logoutModal > .modal-content > .modal-body p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.5;
}

#logoutModal > .modal-content > .modal-body .logout-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    text-align: left;
}

#logoutModal > .modal-content > .modal-body .detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

#logoutModal > .modal-content > .modal-body .detail-item i {
    color: #667eea;
    width: 20px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel,
#logoutModal > .modal-content > .modal-footer .btn-logout {
    padding: 12px 25px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel {
    background: #f1f3f5;
    color: #495057;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

#logoutModal > .modal-content > .modal-footer .btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
}

#logoutModal > .modal-content > .modal-footer .btn-logout:hover {
    background: linear-gradient(135deg, #ff2b53, #ff341b);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
}

/* Responsive */
@media(max-width: 900px){
    .sidebar {
        left: -220px;
    }
    .sidebar.show {
        left: 0;
    }
    .main {
        margin-left: 0 !important;
        padding: 15px;
        max-width: 100% !important;
        width: 100% !important;
    }
    .dashboard-boxes {
        flex-direction: column;
    }
    .announcement-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .announcement-title {
        min-width: 100%;
    }
    #logoutModal > .modal-content > .modal-footer {
        flex-direction: column;
    }
    #logoutModal > .modal-content > .modal-footer .btn-cancel,
    #logoutModal > .modal-content > .modal-footer .btn-logout {
        width: 100%;
        justify-content: center;
    }
}

@media(max-width: 768px){
    .header {
        flex-wrap: wrap;
        gap: 10px;
    }
    .search-bar {
        max-width: 100%;
    }
    .action-buttons {
        flex-direction: row;
    }
    .btn-icon {
        width: 40px;
    }
    #logoutModal > .modal-content {
        width: 95%;
        max-width: 95% !important;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li onclick="saveSidebarState(); window.location.href='dashboard.php'">
            <i class="fas fa-home"></i><span>Dashboard</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='manage_user.php'">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='reports.php'">
            <i class="fas fa-chart-line"></i><span>Reports</span>
        </li>

        <li onclick="saveSidebarState(); window.location.href='manage_items.php'">
            <i class="fas fa-boxes"></i><span>Manage Items</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='claims.php'">
            <i class="fas fa-handshake"></i><span>Manage Claims</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='categories.php'">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li class="active">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        <li id="logoutTrigger">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </li>
    </ul>
</div>

<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info user-info-center">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search announcements...">
            <i class="fas fa-search"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Manage Announcements</h1>
                <p>Post updates and important information for users</p>
            </div>
            <button class="add-announcement-btn" onclick="showAddAnnouncementModal()">
                <i class="fas fa-plus"></i> New Announcement
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if(!empty($successMessage)): ?>
    <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $successMessage ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Boxes -->
    <div class="dashboard-boxes">
        <div class="box" onclick="saveSidebarState(); window.location.href='announcements.php?filter=today'">
            <h2><?= $totalAnnouncements ?></h2>
            <p>Total Announcements</p>
        </div>
        <div class="box" onclick="saveSidebarState(); window.location.href='announcements.php?filter=today'">
            <h2><?= $todayAnnouncements ?></h2>
            <p>Today's Announcements</p>
        </div>
        <div class="box" onclick="saveSidebarState(); window.location.href='announcements.php?filter=week'">
            <h2><?= $lastWeekAnnouncements ?></h2>
            <p>Last 7 Days</p>
        </div>
        <div class="box warning" onclick="showAddAnnouncementModal()">
            <h2><i class="fas fa-plus"></i></h2>
            <p>Create New</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <input type="text" id="searchAnnouncements" placeholder="Search announcements by title or content...">
        <i class="fas fa-search"></i>
    </div>

    <!-- Announcements List -->
    <div class="announcements-container">
        <h3><i class="fas fa-list"></i> All Announcements <span style="font-size:14px;color:#666;margin-left:10px;">(<?= $totalAnnouncements ?> total)</span></h3>
        
        <div class="announcements-list" id="announcementsList">
            <?php if(empty($announcements)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>No Announcements Yet</h3>
                <p>Click "New Announcement" to create your first announcement</p>
            </div>
            <?php else: ?>
            <?php foreach($announcements as $announcement): 
                $isNew = strtotime($announcement['created_at']) > strtotime('-24 hours');
                $content = htmlspecialchars($announcement['content'], ENT_QUOTES, 'UTF-8');
                $shortContent = strlen($content) > 200 ? substr($content, 0, 200) . '...' : $content;
                $isLong = strlen($content) > 200;
            ?>
            <div class="announcement-item <?= $isNew ? 'new' : '' ?>" 
                 data-announcement-id="<?= $announcement['id'] ?>"
                 data-announcement-title="<?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="announcement-header">
                    <h3 class="announcement-title"><?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="announcement-date">
                        <i class="far fa-clock"></i>
                        <?= htmlspecialchars(date('M d, Y \a\t g:i A', strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                
                <div class="announcement-content" id="content-<?= $announcement['id'] ?>">
                    <?= nl2br($shortContent) ?>
                </div>
                
                <?php if($isLong): ?>
                <button class="read-more-btn" onclick="toggleReadMore(<?= $announcement['id'] ?>, '<?= addslashes($content) ?>')">
                    <i class="fas fa-chevron-down"></i> Read More
                </button>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <button class="btn-icon edit" title="Edit" onclick="editAnnouncement(<?= $announcement['id'] ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" title="Delete" onclick="deleteAnnouncement(<?= $announcement['id'] ?>, '<?= addslashes($announcement['title']) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== LOGOUT MODAL (EXACT SAME AS DASHBOARD) ===== -->
<div id="logoutModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-sign-out-alt"></i>
            <h3>Confirm Logout</h3>
        </div>
        
        <div class="modal-body">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p>Are you sure you want to logout from the admin panel?</p>
            <div class="logout-details">
                <div class="detail-item">
                    <i class="fas fa-user"></i>
                    <span>User: <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span>Time: <span id="currentTime"></span></span>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-cancel" id="cancelLogout">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-logout" id="confirmLogoutBtn">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Announcement Modal -->
<div class="modal" id="announcementModal">
    <div class="modal-content">
        <h2><i class="fas fa-bullhorn"></i> <span id="modalTitle">New Announcement</span></h2>
        <form id="announcementForm" method="POST">
            <input type="hidden" id="announcementId" name="announcement_id" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-group">
                <label for="announcementTitle">Title *</label>
                <input type="text" id="announcementTitle" name="title" required 
                       placeholder="Enter announcement title" maxlength="255">
                <div class="char-count" id="titleCharCount">0/255 characters</div>
            </div>
            
            <div class="form-group">
                <label for="announcementContent">Content *</label>
                <textarea id="announcementContent" name="content" required 
                          placeholder="Enter announcement content..." rows="8"></textarea>
                <div class="char-count" id="contentCharCount">0 characters</div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:30px;">
                <button type="submit" class="btn" style="flex:1;background:#1e90ff;color:white;padding:12px;border:none;border-radius:6px;cursor:pointer;">
                    <i class="fas fa-save"></i> <span id="saveButtonText">Publish Announcement</span>
                </button>
                <button type="button" class="btn" onclick="closeModal()" style="flex:1; background:#6c757d;color:white;padding:12px;border:none;border-radius:6px;cursor:pointer;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <h2><i class="fas fa-trash"></i> Delete Announcement</h2>
        <p id="deleteMessage">Are you sure you want to delete this announcement?</p>
        <form id="deleteForm" method="POST" style="margin-top:20px;">
            <input type="hidden" id="deleteAnnouncementId" name="announcement_id" value="">
            <input type="hidden" name="delete_announcement" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn" style="flex:1;background:#dc3545;color:white;padding:12px;border:none;border-radius:6px;cursor:pointer;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn" onclick="closeDeleteModal()" style="flex:1; background:#6c757d;color:white;padding:12px;border:none;border-radius:6px;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== SIDEBAR FUNCTIONS =====
function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Announcements page loaded');
    
    // Load sidebar state
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const savedState = localStorage.getItem('sidebarFolded');
            if (savedState === 'true') {
                sidebar.classList.add('folded');
            } else {
                sidebar.classList.remove('folded');
            }
        }
    }
    
    // Initialize sidebar toggle
    initSidebarToggle();
    
    // Setup character counters
    setupCharacterCounters();
    
    // Initialize logout modal (EXACT SAME AS DASHBOARD)
    initLogoutModal();
    
    // Fix active menu highlighting
    setActiveMenuItem();
});

function setActiveMenuItem() {
    // Clear all active classes first
    const menuItems = document.querySelectorAll('.sidebar ul li');
    menuItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Set active class for announcements (7th item)
    const announcementsLi = document.querySelector('.sidebar ul li:nth-child(7)');
    if (announcementsLi) {
        announcementsLi.classList.add('active');
    }
}

function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const toggleSidebarLogo = document.getElementById('toggleSidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            if(window.innerWidth <= 900){
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('folded');
                localStorage.setItem('sidebarFolded', sidebar.classList.contains('folded'));
            }
        });
    }
    
    if (toggleSidebarLogo && sidebar) {
        toggleSidebarLogo.addEventListener('click', function() {
            if(window.innerWidth <= 900){
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('folded');
                localStorage.setItem('sidebarFolded', sidebar.classList.contains('folded'));
            }
        });
    }
    
    // Close sidebar on mobile click outside
    document.addEventListener('click', function(e) {
        if(window.innerWidth <= 900 && sidebar && !sidebar.contains(e.target)) {
            if (sidebarToggle && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
}

// ===== CHARACTER COUNTERS =====
function setupCharacterCounters() {
    const titleInput = document.getElementById('announcementTitle');
    const contentTextarea = document.getElementById('announcementContent');
    const titleCounter = document.getElementById('titleCharCount');
    const contentCounter = document.getElementById('contentCharCount');
    
    if (titleInput && titleCounter) {
        titleInput.addEventListener('input', function() {
            const length = this.value.length;
            titleCounter.textContent = `${length}/255 characters`;
            
            if (length > 250) {
                titleCounter.classList.add('warning');
            } else if (length > 255) {
                titleCounter.classList.add('danger');
            } else {
                titleCounter.classList.remove('warning', 'danger');
            }
        });
    }
    
    if (contentTextarea && contentCounter) {
        contentTextarea.addEventListener('input', function() {
            const length = this.value.length;
            contentCounter.textContent = `${length} characters`;
            
            if (length > 1000) {
                contentCounter.classList.add('warning');
            } else if (length > 2000) {
                contentCounter.classList.add('danger');
            } else {
                contentCounter.classList.remove('warning', 'danger');
            }
        });
    }
}

// ===== ANNOUNCEMENT MANAGEMENT FUNCTIONS =====
function showAddAnnouncementModal() {
    console.log('Opening add announcement modal');
    document.getElementById('modalTitle').textContent = 'New Announcement';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcementId').value = '';
    
    // Remove any existing hidden action inputs
    document.querySelectorAll('#announcementForm input[name="add_announcement"], #announcementForm input[name="edit_announcement"]').forEach(input => {
        input.remove();
    });
    
    // Create add_announcement input
    const addInput = document.createElement('input');
    addInput.type = 'hidden';
    addInput.name = 'add_announcement';
    addInput.value = '1';
    document.getElementById('announcementForm').appendChild(addInput);
    
    document.getElementById('saveButtonText').textContent = 'Publish Announcement';
    document.getElementById('announcementModal').style.display = 'flex';
    
    updateCharacterCounters();
    
    setTimeout(() => {
        document.getElementById('announcementTitle').focus();
    }, 100);
}

async function editAnnouncement(announcementId) {
    console.log('Fetching announcement data for ID:', announcementId);
    
    try {
        // Fetch announcement data via AJAX
        const response = await fetch(`get_announcement.php?id=${announcementId}`);
        const data = await response.json();
        
        if (data.success) {
            console.log('Announcement data loaded:', data);
            
            document.getElementById('modalTitle').textContent = 'Edit Announcement';
            document.getElementById('announcementId').value = data.id;
            document.getElementById('announcementTitle').value = data.title;
            document.getElementById('announcementContent').value = data.content;
            
            // Remove any existing hidden action inputs
            document.querySelectorAll('#announcementForm input[name="add_announcement"], #announcementForm input[name="edit_announcement"]').forEach(input => {
                input.remove();
            });
            
            // Create edit_announcement input
            const editInput = document.createElement('input');
            editInput.type = 'hidden';
            editInput.name = 'edit_announcement';
            editInput.value = '1';
            document.getElementById('announcementForm').appendChild(editInput);
            
            document.getElementById('saveButtonText').textContent = 'Update Announcement';
            document.getElementById('announcementModal').style.display = 'flex';
            
            updateCharacterCounters();
            
            setTimeout(() => {
                const input = document.getElementById('announcementTitle');
                input.focus();
                input.select();
            }, 100);
        } else {
            alert('Error loading announcement: ' + data.error);
        }
    } catch (error) {
        console.error('Error fetching announcement:', error);
        alert('Failed to load announcement data. Please try again.');
    }
}

function deleteAnnouncement(announcementId, announcementTitle) {
    console.log('Deleting announcement:', announcementId);
    const deleteModal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    
    document.getElementById('deleteAnnouncementId').value = announcementId;
    deleteMessage.textContent = `Are you sure you want to delete announcement "${announcementTitle}"?`;
    
    deleteModal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function updateCharacterCounters() {
    const titleInput = document.getElementById('announcementTitle');
    const contentTextarea = document.getElementById('announcementContent');
    const titleCounter = document.getElementById('titleCharCount');
    const contentCounter = document.getElementById('contentCharCount');
    
    if (titleInput && titleCounter) {
        const titleLength = titleInput.value.length;
        titleCounter.textContent = `${titleLength}/255 characters`;
        
        if (titleLength > 250) {
            titleCounter.classList.add('warning');
        } else if (titleLength > 255) {
            titleCounter.classList.add('danger');
        } else {
            titleCounter.classList.remove('warning', 'danger');
        }
    }
    
    if (contentTextarea && contentCounter) {
        const contentLength = contentTextarea.value.length;
        contentCounter.textContent = `${contentLength} characters`;
        
        if (contentLength > 1000) {
            contentCounter.classList.add('warning');
        } else if (contentLength > 2000) {
            contentCounter.classList.add('danger');
        } else {
            contentCounter.classList.remove('warning', 'danger');
        }
    }
}

// ===== READ MORE TOGGLE =====
function toggleReadMore(announcementId, fullContent) {
    const contentDiv = document.getElementById('content-' + announcementId);
    const button = contentDiv.nextElementSibling;
    
    if (contentDiv.classList.contains('expanded')) {
        // Collapse
        contentDiv.innerHTML = decodeHtml(fullContent).substring(0, 200) + '...';
        contentDiv.classList.remove('expanded');
        if (button) {
            button.innerHTML = '<i class="fas fa-chevron-down"></i> Read More';
        }
    } else {
        // Expand
        contentDiv.innerHTML = decodeHtml(fullContent);
        contentDiv.classList.add('expanded');
        if (button) {
            button.innerHTML = '<i class="fas fa-chevron-up"></i> Read Less';
        }
    }
}

function decodeHtml(html) {
    const txt = document.createElement("textarea");
    txt.innerHTML = html;
    return txt.value;
}

// ===== EXACT SAME LOGOUT MODAL AS DASHBOARD =====
function initLogoutModal() {
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelLogout');
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    
    if (!logoutTrigger || !logoutModal) {
        console.log('Logout modal elements not found');
        return;
    }
    
    console.log('Initializing logout modal');
    
    // Show modal when logout is clicked
    logoutTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Logout button clicked');
        
        // Update current time
        const now = new Date();
        document.getElementById('currentTime').textContent = 
            now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        // Show modal with animation
        logoutModal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Add keyboard shortcut (Esc to close)
        document.addEventListener('keydown', handleLogoutModalKeydown);
    });
    
    // Close modal when clicking cancel
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            console.log('Cancel logout clicked');
            closeLogoutModal();
        });
    }
    
    // Close modal when clicking outside
    logoutModal.addEventListener('click', function(e) {
        if (e.target === logoutModal) {
            console.log('Clicked outside modal - closing');
            closeLogoutModal();
        }
    });
    
    // Confirm logout
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            console.log('Confirm logout clicked');
            
            // Add loading state to button
            const originalHTML = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
            confirmBtn.disabled = true;
            
            // Save sidebar state before logout
            saveSidebarState();
            
            // Redirect after short delay for visual feedback
            setTimeout(() => {
                window.location.href = 'auth/logout.php';
            }, 800);
        });
    }
    
    console.log('Logout modal initialized successfully');
}

function closeLogoutModal() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.style.display = 'none';
        document.body.style.overflow = ''; // Re-enable scrolling
        document.removeEventListener('keydown', handleLogoutModalKeydown);
    }
}

function handleLogoutModalKeydown(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
    }
}

// ===== FORM VALIDATION =====
document.getElementById('announcementForm')?.addEventListener('submit', function(e) {
    const title = document.getElementById('announcementTitle').value.trim();
    const content = document.getElementById('announcementContent').value.trim();
    
    if (!title || !content) {
        e.preventDefault();
        alert('Please fill in both title and content fields');
        if (!title) {
            document.getElementById('announcementTitle').focus();
        } else {
            document.getElementById('announcementContent').focus();
        }
    } else if (title.length > 255) {
        e.preventDefault();
        alert('Title must be 255 characters or less');
        document.getElementById('announcementTitle').focus();
    }
});

// ===== MODAL EVENT HANDLERS =====
document.addEventListener('click', function(e) {
    const announcementModal = document.getElementById('announcementModal');
    const deleteModal = document.getElementById('deleteModal');
    const logoutModal = document.getElementById('logoutModal');
    
    if (announcementModal && e.target === announcementModal) {
        closeModal();
    }
    
    if (deleteModal && e.target === deleteModal) {
        closeDeleteModal();
    }
    
    if (logoutModal && e.target === logoutModal) {
        closeLogoutModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
        closeLogoutModal();
    }
});

// Add fade-in animations
setTimeout(() => {
    document.querySelectorAll('.announcement-item').forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in');
    });
}, 100);

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.5s ease forwards;
    opacity: 0;
}
`;
document.head.appendChild(style);
</script>

</body>
</html>