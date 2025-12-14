<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Get item ID
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($itemId <= 0) {
    header("Location: manage_items.php");
    exit();
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get found item details
    $stmt = $pdo->prepare("
        SELECT fi.*, 
               u.first_name, u.last_name, u.email, u.student_id, u.course, u.contact_number,
               ic.category_name
        FROM found_items fi
        LEFT JOIN users u ON fi.user_id = u.user_id
        LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
        WHERE fi.found_id = ?
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header("Location: manage_items.php");
        exit();
    }

} catch(PDOException $e) {
    error_log("Database error in view_found_item.php: " . $e->getMessage());
    die("An error occurred while loading item details.");
}

// Format dates
$dateFound = !empty($item['date_found']) ? date('F d, Y', strtotime($item['date_found'])) : 'Not specified';
$createdDate = !empty($item['created_at']) ? date('F d, Y g:i A', strtotime($item['created_at'])) : 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Found Item - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* ===== Main ===== */
.main{margin-left:220px;padding:20px;flex:1;transition:0.3s;min-height:100vh;max-width:100%;}
.sidebar.folded ~ .main{margin-left:70px;}

/* ===== Header ===== */
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);position:sticky;top:0;z-index:100;}
.user-info{font-weight:bold;display:flex;align-items:center;gap:10px;color:#1e2a38;}
.user-info i{color:#1e90ff;font-size:18px;}

/* Search bar */
.search-bar{position:relative;width:250px;}
.search-bar input{width:100%;padding:8px 35px 8px 10px;border-radius:8px;border:1px solid #ccc;outline:none;}
.search-bar i{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#888;}
.search-results{position:absolute;top:38px;left:0;width:100%;max-height:300px;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:8px;overflow-y:auto;display:none;z-index:2000;}
.search-results .result-item{padding:10px 15px;cursor:pointer;transition:0.3s;}
.search-results .result-item:hover{background:#f0f4ff;}

/* Breadcrumb */
.breadcrumb{background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);font-size:14px;}
.breadcrumb a{color:#1e90ff;text-decoration:none;}
.breadcrumb a:hover{text-decoration:underline;}
.breadcrumb span{color:#666;}

/* Page Title */
.page-title{background:linear-gradient(135deg, #10b981 0%, #34d399 100%);color:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 5px 15px rgba(16,185,129,0.2);}
.page-title h1{font-size:24px;margin-bottom:5px;display:flex;align-items:center;gap:10px;}
.page-title p{font-size:14px;opacity:0.9;}

/* View Container */
.view-container{background:white;border-radius:12px;padding:0;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}

/* Item Header */
.item-header{background:linear-gradient(135deg, #10b981 0%, #34d399 100%);color:white;padding:25px;border-bottom:1px solid rgba(255,255,255,0.1);}
.item-header-content{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;}
.item-title h1{font-size:28px;margin-bottom:10px;display:flex;align-items:center;gap:10px;}
.item-meta{display:flex;flex-wrap:wrap;gap:15px;margin-top:10px;}
.meta-item{display:flex;align-items:center;gap:8px;font-size:14px;opacity:0.9;}
.status-badge{background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase;}

/* Item Actions */
.item-actions{display:flex;gap:10px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;}
.btn-edit{background:#ffc107;color:#212529;}
.btn-edit:hover{background:#e0a800;transform:translateY(-2px);}
.btn-back{background:#6c757d;color:white;}
.btn-back:hover{background:#5a6268;transform:translateY(-2px);}
.btn-delete{background:#dc3545;color:white;}
.btn-delete:hover{background:#c82333;transform:translateY(-2px);}
.btn-primary{background:#1e90ff;color:white;}
.btn-primary:hover{background:#1c86ee;transform:translateY(-2px);}

/* Item Body */
.item-body{display:grid;grid-template-columns:2fr 1fr;gap:20px;padding:25px;}
@media(max-width:900px){.item-body{grid-template-columns:1fr;}}

/* Photo Section */
.photo-section{margin-bottom:25px;}
.item-photo{width:100%;max-height:400px;object-fit:contain;border-radius:10px;border:1px solid #eaeaea;background:#f8f9fa;}
.no-photo{width:100%;height:300px;background:#f8f9fa;border-radius:10px;border:2px dashed #dee2e6;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#6c757d;}
.no-photo i{font-size:64px;margin-bottom:15px;opacity:0.5;}

/* Details Section */
.details-section{margin-bottom:25px;}
.details-section h2{font-size:20px;color:#1e2a38;margin-bottom:15px;display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:2px solid #10b981;}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:15px;}
.info-item{background:#f8f9fa;padding:15px;border-radius:8px;border-left:4px solid #10b981;}
.info-item h3{font-size:14px;color:#6c757d;margin-bottom:5px;display:flex;align-items:center;gap:8px;}
.info-item p{font-size:16px;color:#1e2a38;font-weight:500;}

/* Description Section */
.description-section{margin-bottom:25px;}
.description-box{background:#f8f9fa;padding:20px;border-radius:8px;border-left:4px solid #10b981;}
.description-box h3{font-size:18px;color:#1e2a38;margin-bottom:15px;display:flex;align-items:center;gap:10px;}
.description-text{font-size:15px;line-height:1.6;color:#333;}

/* Reporter Section */
.reporter-section{background:#f8f9fa;padding:20px;border-radius:10px;border:1px solid #eaeaea;}
.reporter-section h3{font-size:20px;color:#1e2a38;margin-bottom:20px;display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:2px solid #10b981;}

/* Reporter Info */
.reporter-info{display:flex;align-items:center;gap:15px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #dee2e6;}
.reporter-avatar{width:60px;height:60px;background:#10b981;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:bold;}
.reporter-details h4{font-size:18px;color:#1e2a38;margin-bottom:5px;}
.reporter-details p{font-size:14px;color:#6c757d;}

/* Contact Info */
.contact-info{margin-bottom:25px;}
.contact-info h4{font-size:16px;color:#1e2a38;margin-bottom:15px;display:flex;align-items:center;gap:10px;}
.contact-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #eee;}
.contact-item i{color:#10b981;width:20px;}

/* Additional Actions */
.additional-actions{margin-top:25px;padding-top:20px;border-top:1px solid #dee2e6;}
.additional-actions h4{font-size:16px;color:#1e2a38;margin-bottom:15px;display:flex;align-items:center;gap:10px;}
.action-buttons{display:flex;flex-direction:column;gap:10px;}

/* ===== LOGOUT MODAL STYLES ===== */
.logout-modal {
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
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 420px;
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

.modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header i {
    font-size: 24px;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.modal-body {
    padding: 30px 25px;
    text-align: center;
}

.warning-icon {
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

.warning-icon i {
    font-size: 30px;
    color: white;
}

.modal-body p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.5;
}

.logout-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    text-align: left;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

.detail-item i {
    color: #667eea;
    width: 20px;
    text-align: center;
}

.modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

.btn-cancel, .btn-logout {
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

.btn-cancel {
    background: #f1f3f5;
    color: #495057;
}

.btn-cancel:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
}

.btn-logout:hover {
    background: linear-gradient(135deg, #ff2b53, #ff341b);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
}

/* Delete Confirmation Modal */
.delete-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.delete-modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
}

.delete-modal-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    justify-content: center;
}

.delete-modal-cancel {
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.delete-modal-confirm {
    padding: 10px 20px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
    .item-body{grid-template-columns:1fr;}
    .item-header-content{flex-direction:column;}
    .item-actions{width:100%;justify-content:flex-start;}
}

@media(max-width:600px){
    .info-grid{grid-template-columns:1fr;}
    .modal-footer{flex-direction:column;}
    .btn-cancel, .btn-logout{width:100%;justify-content:center;}
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
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        <li id="logoutTrigger">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </li>
    </ul>
</div>

<!-- Main -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search items, users, reports...">
            <i class="fas fa-search"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a> &raquo;
        <a href="manage_items.php"><i class="fas fa-boxes"></i> Manage Items</a> &raquo;
        <span><i class="fas fa-eye"></i> View Found Item</span>
    </div>

    <!-- Page Title -->
    <div class="page-title">
        <h1><i class="fas fa-check-circle"></i> Found Item Details</h1>
        <p>View complete information about this found item</p>
    </div>

    <!-- View Container -->
    <div class="view-container">
        <!-- Item Header -->
        <div class="item-header">
            <div class="item-header-content">
                <div class="item-title">
                    <h1><i class="fas fa-box"></i> <?= htmlspecialchars($item['item_name']) ?></h1>
                    <div class="item-meta">
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Found on: <?= $dateFound ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="status-badge"><?= htmlspecialchars($item['status'] ?? 'Found') ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Reported: <?= $createdDate ?></span>
                        </div>
                    </div>
                </div>
                <div class="item-actions">
                    <a href="edit_found_item.php?id=<?= $itemId ?>" class="btn btn-edit">
                        <i class="fas fa-edit"></i> Edit Item
                    </a>
                    <a href="manage_items.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <!-- Item Body -->
        <div class="item-body">
            <!-- Left Column: Photo & Details -->
            <div>
                <!-- Photo Section -->
                <div class="photo-section">
                    <?php if(!empty($item['photo'])): 
                        $photoPath = '../uploads/found_items/' . $item['photo'];
                        $fullPath = __DIR__ . '/../../uploads/found_items/' . $item['photo'];
                    ?>
                        <?php if(file_exists($fullPath)): ?>
                            <img src="<?= $photoPath ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="item-photo">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image"></i>
                                <p>Photo file not found on server</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-photo">
                            <i class="fas fa-image"></i>
                            <p>No photo available for this item</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Details Section -->
                <div class="details-section">
                    <h2><i class="fas fa-info-circle"></i> Item Details</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <h3><i class="fas fa-tag"></i> Item Name</h3>
                            <p><?= htmlspecialchars($item['item_name']) ?></p>
                        </div>
                        <div class="info-item">
                            <h3><i class="fas fa-list"></i> Category</h3>
                            <p><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                        </div>
                        <div class="info-item">
                            <h3><i class="fas fa-map-marker-alt"></i> Place Found</h3>
                            <p><?= htmlspecialchars($item['place_found'] ?? 'Not specified') ?></p>
                        </div>
                        <div class="info-item">
                            <h3><i class="fas fa-calendar"></i> Date Found</h3>
                            <p><?= $dateFound ?></p>
                        </div>
                    </div>
                </div>

                <!-- Description Section -->
                <?php if(!empty($item['description'])): ?>
                <div class="description-section">
                    <div class="description-box">
                        <h3><i class="fas fa-align-left"></i> Description</h3>
                        <div class="description-text">
                            <?= nl2br(htmlspecialchars($item['description'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Finder Info -->
            <div class="reporter-section">
                <h3><i class="fas fa-user"></i> Finder Information</h3>
                
                <div class="reporter-info">
                    <div class="reporter-avatar">
                        <?= strtoupper(substr($item['first_name'] ?? 'U', 0, 1) . substr($item['last_name'] ?? 'N', 0, 1)) ?>
                    </div>
                    <div class="reporter-details">
                        <h4><?= htmlspecialchars(($item['first_name'] ?? 'Unknown') . ' ' . ($item['last_name'] ?? '')) ?></h4>
                        <p>Student ID: <?= htmlspecialchars($item['student_id'] ?? 'N/A') ?></p>
                        <p>Course: <?= htmlspecialchars($item['course'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <div class="contact-info">
                    <h4><i class="fas fa-address-book"></i> Contact Information</h4>
                    
                    <?php if(!empty($item['email'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($item['email']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($item['contact_number'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($item['contact_number']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(empty($item['email']) && empty($item['contact_number'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-exclamation-circle"></i>
                        <span style="color: #dc3545;">No contact information provided</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Additional Actions -->
                <div class="additional-actions">
                    <h4><i class="fas fa-cog"></i> Admin Actions</h4>
                    
                    <div class="action-buttons">
                        <a href="edit_found_item.php?id=<?= $itemId ?>" class="btn btn-edit">
                            <i class="fas fa-edit"></i> Edit This Item
                        </a>
                        
                        <a href="manage_items.php?type=found" class="btn btn-primary">
                            <i class="fas fa-list"></i> View All Found Items
                        </a>
                        
                        <button onclick="deleteItem()" class="btn btn-delete">
                            <i class="fas fa-trash"></i> Delete This Item
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal">
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
                    <span>User: <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?></span>
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <h3 style="margin-bottom: 15px; color: #dc3545;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Delete
        </h3>
        <p id="deleteMessage">Are you sure you want to delete this item?</p>
        <div style="background:#fff3cd;padding:15px;border-radius:5px;margin-bottom:20px;font-size:14px;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Warning:</strong> This action cannot be undone.
        </div>
        <div class="delete-modal-buttons">
            <button onclick="confirmDelete()" class="delete-modal-confirm">
                <i class="fas fa-trash"></i> Delete
            </button>
            <button onclick="closeDeleteModal()" class="delete-modal-cancel">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// ===== BASIC LOGOUT FUNCTIONS =====
function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== BASIC PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('View Found Item page loaded');
    
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
    
    // Basic sidebar toggle
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
    
    // Initialize search
    initSearch();
    
    // Initialize logout modal
    initLogoutModal();
});

// ===== LOGOUT MODAL FUNCTIONALITY =====
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

// ===== SEARCH FUNCTIONALITY =====
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });
}

async function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    
    try {
        searchResults.innerHTML = '<div class="result-item">Searching...</div>';
        searchResults.style.display = 'block';
        
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const results = await response.json();
        
        if (results && results.length > 0) {
            searchResults.innerHTML = results.map(item => `
                <div class="result-item" onclick="window.location.href='${item.url}'">
                    <strong>${escapeHtml(item.title)}</strong><br>
                    <small>${escapeHtml(item.type)} • ${escapeHtml(item.subtitle || '')}</small>
                </div>
            `).join('');
        } else {
            searchResults.innerHTML = '<div class="result-item">No results found</div>';
        }
        
        searchResults.style.display = 'block';
    } catch (error) {
        console.error('Search error:', error);
        searchResults.innerHTML = '<div class="result-item">Error searching</div>';
        searchResults.style.display = 'block';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== DELETE FUNCTIONALITY =====
function deleteItem() {
    const deleteModal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    
    if (deleteModal && deleteMessage) {
        deleteMessage.textContent = `Are you sure you want to delete "${'<?= htmlspecialchars($item['item_name']) ?>'}"?`;
        deleteModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function confirmDelete() {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_item.php';
    
    // Add CSRF token
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = 'csrf_token';
    csrfToken.value = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    form.appendChild(csrfToken);
    
    // Add item details
    const itemId = document.createElement('input');
    itemId.type = 'hidden';
    itemId.name = 'id';
    itemId.value = '<?= $itemId ?>';
    form.appendChild(itemId);
    
    const itemType = document.createElement('input');
    itemType.type = 'hidden';
    itemType.name = 'type';
    itemType.value = 'found';
    form.appendChild(itemType);
    
    document.body.appendChild(form);
    form.submit();
}

// Close delete modal when clicking outside
document.addEventListener('click', function(e) {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal && e.target === deleteModal) {
        closeDeleteModal();
    }
});

// Escape key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const logoutModal = document.getElementById('logoutModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (logoutModal && logoutModal.style.display === 'flex') {
            closeLogoutModal();
        }
        if (deleteModal && deleteModal.style.display === 'flex') {
            closeDeleteModal();
        }
    }
});

// Make photo clickable to view full size
document.addEventListener('DOMContentLoaded', function() {
    const itemPhoto = document.querySelector('.item-photo');
    if (itemPhoto) {
        itemPhoto.addEventListener('click', function() {
            window.open(this.src, '_blank');
        });
    }
});
</script>

</body>
</html>