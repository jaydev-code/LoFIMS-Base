<?php
// ENABLE ERROR REPORTING FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get admin data
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        session_destroy();
        header("Location: ../public/login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_items.php");
    exit();
}

$item_id = (int)$_GET['id'];

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
}

// Get the found item details
try {
    $stmt = $pdo->prepare("
        SELECT fi.*, ic.category_name, u.first_name, u.last_name
        FROM found_items fi 
        LEFT JOIN item_categories ic ON fi.category_id = ic.category_id 
        LEFT JOIN users u ON fi.user_id = u.user_id
        WHERE fi.found_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header("Location: manage_items.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $item_name = trim($_POST['item_name'] ?? '');
        $category_id = $_POST['category_id'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $place_found = trim($_POST['place_found'] ?? '');
        $date_found = $_POST['date_found'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'found';
        
        // Validate
        if (empty($item_name) || empty($place_found)) {
            $error = "Item name and place found are required!";
        } else {
            try {
                // Handle file upload
                $photo = $item['photo']; // Keep existing photo by default
                
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['photo']['name'];
                    $fileTmpPath = $_FILES['photo']['tmp_name'];
                    $fileSize = $_FILES['photo']['size'];
                    
                    // Get file extension
                    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedTypes)) {
                        // Use relative path
                        $uploadDir = __DIR__ . '/../../uploads/found_items/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        // Check if directory is writable
                        if (is_writable($uploadDir)) {
                            // Delete old photo if exists and we're uploading new one
                            if (!empty($item['photo'])) {
                                $oldPhotoPath = $uploadDir . $item['photo'];
                                if (file_exists($oldPhotoPath)) {
                                    @unlink($oldPhotoPath);
                                }
                            }
                            
                            // Create unique filename
                            $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                            $targetFile = $uploadDir . $fileName;
                            
                            // Check file size (5MB limit)
                            $maxFileSize = 5 * 1024 * 1024; // 5MB
                            if ($fileSize > $maxFileSize) {
                                $error = "File is too large. Maximum size is 5MB.";
                            } else {
                                // Try to move the uploaded file
                                if (move_uploaded_file($fileTmpPath, $targetFile)) {
                                    $photo = $fileName;
                                } else {
                                    $lastError = error_get_last();
                                    $error = "Failed to upload file.";
                                    if ($lastError) {
                                        $error .= " Error: " . $lastError['message'];
                                    }
                                }
                            }
                        } else {
                            $error = "Upload directory is not writable.";
                        }
                    } else {
                        $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    }
                } else if (isset($_FILES['photo'])) {
                    // Only show error if user tried to upload but there was an error
                    if ($_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                        switch ($_FILES['photo']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $error = "File is too large. Maximum size is 5MB.";
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error = "File upload was incomplete.";
                                break;
                            default:
                                $error = "Upload error. Please try again.";
                        }
                    }
                }
                
                // Handle photo removal checkbox
                if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
                    if (!empty($item['photo'])) {
                        $uploadDir = __DIR__ . '/../../uploads/found_items/';
                        $oldPhotoPath = $uploadDir . $item['photo'];
                        
                        if (file_exists($oldPhotoPath)) {
                            @unlink($oldPhotoPath);
                        }
                        $photo = '';
                    }
                }
                
                // Only update database if no errors
                if (empty($error)) {
                    // Update database
                    $stmt = $pdo->prepare("
                        UPDATE found_items 
                        SET item_name = ?, category_id = ?, description = ?, photo = ?, 
                            place_found = ?, date_found = ?, status = ?, updated_at = NOW()
                        WHERE found_id = ?
                    ");
                    
                    $result = $stmt->execute([
                        $item_name,
                        $category_id ?: NULL,
                        $description,
                        $photo,
                        $place_found,
                        $date_found,
                        $status,
                        $item_id
                    ]);
                    
                    if ($result) {
                        $success = "Found item updated successfully!";
                        
                        // Refresh item data
                        $stmt = $pdo->prepare("
                            SELECT fi.*, ic.category_name, u.first_name, u.last_name
                            FROM found_items fi 
                            LEFT JOIN item_categories ic ON fi.category_id = ic.category_id 
                            LEFT JOIN users u ON fi.user_id = u.user_id
                            WHERE fi.found_id = ?
                        ");
                        $stmt->execute([$item_id]);
                        $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Failed to update database.";
                    }
                }
                
            } catch(PDOException $e) {
                $error = "A database error occurred. Please try again.";
                error_log("PDO Error in edit_found_item.php: " . $e->getMessage());
            } catch(Exception $e) {
                $error = "An error occurred. Please try again.";
                error_log("General Error in edit_found_item.php: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Found Item - LoFIMS Admin</title>
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

/* Item Info */
.item-info{background:#f8f9fa;padding:15px;border-radius:10px;margin-bottom:20px;border-left:4px solid #10b981;}
.item-info p{margin-bottom:8px;color:#6c757d;}
.item-info strong{color:#1e2a38;}

/* Alerts */
.alert{padding:12px 20px;border-radius:8px;margin-bottom:20px;border:1px solid transparent;}
.alert-success{background:#d1fae5;color:#065f46;border-color:#a7f3d0;}
.alert-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
.alert i{margin-right:8px;}

/* Form Container */
.form-container{background:white;border-radius:12px;padding:30px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}

/* Form Layout */
.form-layout{display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;}
@media(max-width:900px){.form-layout{grid-template-columns:1fr;}}

/* Form Groups */
.form-group{margin-bottom:20px;}
.form-label{display:block;margin-bottom:8px;font-weight:600;color:#1e2a38;}
.form-label i{color:#10b981;margin-right:8px;width:20px;}
.form-control, .form-select, .form-textarea{width:100%;padding:12px 15px;border:1px solid #dee2e6;border-radius:8px;font-size:14px;transition:border-color 0.3s;}
.form-control:focus, .form-select:focus, .form-textarea:focus{outline:none;border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,0.1);}
.form-textarea{min-height:120px;resize:vertical;}

/* Status Badges */
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase;}
.status-found{background:#e8f5e9;color:#28a745;}
.status-claimed{background:#fff3cd;color:#856404;}
.status-returned{background:#d1ecf1;color:#0c5460;}
.status-lost{background:#ffebee;color:#dc3545;}

/* Photo Section */
.photo-section{margin-top:25px;padding-top:20px;border-top:1px solid #e9ecef;}
.preview-image{max-width:200px;max-height:200px;border-radius:8px;margin-top:10px;border:1px solid #cbd5e1;cursor:pointer;}
.preview-image:hover{opacity:0.9;}
.current-photo{margin-top:15px;}
.remove-photo-checkbox{display:flex;align-items:center;gap:8px;margin-top:10px;color:#6c757d;}

/* Form Actions */
.form-actions{display:flex;gap:15px;margin-top:30px;padding-top:20px;border-top:1px solid #e9ecef;}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;}
.btn-primary{background:#10b981;color:white;}
.btn-primary:hover{background:#059669;transform:translateY(-2px);}
.btn-secondary{background:#6c757d;color:white;}
.btn-secondary:hover{background:#5a6268;transform:translateY(-2px);}
.btn-danger{background:#ef4444;color:white;}
.btn-danger:hover{background:#dc2626;transform:translateY(-2px);}

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

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
}

@media(max-width:600px){
    .form-actions{flex-direction:column;}
    .btn{width:100%;justify-content:center;}
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
        
        <li class="active" onclick="saveSidebarState(); window.location.href='manage_items.php'">
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
            <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
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
        <a href="view_found_item.php?id=<?= $item_id ?>"><i class="fas fa-eye"></i> View Item</a> &raquo;
        <span><i class="fas fa-edit"></i> Edit Item</span>
    </div>

    <!-- Page Title -->
    <div class="page-title">
        <h1><i class="fas fa-edit"></i> Edit Found Item</h1>
        <p>Update the details for: <strong><?= htmlspecialchars($item['item_name']) ?></strong></p>
    </div>

    <!-- Item Info -->
    <div class="item-info">
        <p><strong>Reported by:</strong> <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></p>
        <p><strong>Reported on:</strong> <?= date('F d, Y', strtotime($item['created_at'])) ?></p>
        <p><strong>Current Status:</strong> 
            <span class="status-badge status-<?= htmlspecialchars($item['status']) ?>">
                <?= htmlspecialchars($item['status']) ?>
            </span>
        </p>
    </div>

    <!-- Alert Messages -->
    <?php if($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <div style="margin-top: 10px;">
            <a href="view_found_item.php?id=<?= $item_id ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Updated Item
            </a>
            <a href="manage_items.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Back to List
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Container -->
    <div class="form-container">
        <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-layout">
                <!-- Left Column -->
                <div>
                    <!-- Item Name -->
                    <div class="form-group">
                        <label class="form-label" for="item_name">
                            <i class="fas fa-tag"></i> Item Name *
                        </label>
                        <input type="text" 
                               id="item_name" 
                               name="item_name" 
                               class="form-control" 
                               value="<?= htmlspecialchars($item['item_name']) ?>"
                               required 
                               placeholder="e.g., iPhone 13, Wallet, Keys">
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label" for="category_id">
                            <i class="fas fa-list"></i> Category
                        </label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" 
                                <?= $item['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-info-circle"></i> Status
                        </label>
                        <select id="status" name="status" class="form-select">
                            <option value="found" <?= $item['status'] == 'found' ? 'selected' : '' ?>>Found</option>
                            <option value="claimed" <?= $item['status'] == 'claimed' ? 'selected' : '' ?>>Claimed</option>
                            <option value="returned" <?= $item['status'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                            <option value="lost" <?= $item['status'] == 'lost' ? 'selected' : '' ?>>Lost</option>
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Place Found -->
                    <div class="form-group">
                        <label class="form-label" for="place_found">
                            <i class="fas fa-map-marker-alt"></i> Place Found *
                        </label>
                        <input type="text" 
                               id="place_found" 
                               name="place_found" 
                               class="form-control" 
                               value="<?= htmlspecialchars($item['place_found']) ?>"
                               required 
                               placeholder="e.g., Library 2nd floor, Cafeteria, Parking lot">
                    </div>

                    <!-- Date Found -->
                    <div class="form-group">
                        <label class="form-label" for="date_found">
                            <i class="far fa-calendar"></i> Date Found
                        </label>
                        <input type="date" 
                               id="date_found" 
                               name="date_found" 
                               class="form-control" 
                               value="<?= $item['date_found'] ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label" for="description">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea id="description" 
                                  name="description" 
                                  class="form-control form-textarea" 
                                  placeholder="Describe the item (color, brand, distinguishing features)..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Photo Section -->
            <div class="photo-section">
                <label class="form-label">
                    <i class="fas fa-camera"></i> Item Photo
                </label>
                
                <?php if(!empty($item['photo'])): ?>
                    <div class="current-photo">
                        <p><strong>Current Photo:</strong></p>
                        <?php
                        $uploadDir = __DIR__ . '/../../uploads/found_items/';
                        $web_url = '../uploads/found_items/' . $item['photo'];
                        ?>
                        <?php if(file_exists($uploadDir . $item['photo'])): ?>
                            <img src="<?= htmlspecialchars($web_url) ?>" 
                                 alt="Current photo" 
                                 class="preview-image"
                                 onclick="window.open('<?= htmlspecialchars($web_url) ?>', '_blank')"
                                 title="Click to view full size">
                            <div class="remove-photo-checkbox">
                                <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                <label for="remove_photo">Remove current photo</label>
                            </div>
                        <?php else: ?>
                            <p style="color: #dc2626;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Photo file not found on server.
                            </p>
                        <?php endif; ?>
                    </div>
                    <p style="color: #6c757d; margin-top: 10px;">Or upload a new photo:</p>
                <?php endif; ?>
                
                <input type="file" 
                       id="photo" 
                       name="photo" 
                       class="form-control" 
                       accept=".jpg,.jpeg,.png,.gif"
                       onchange="previewImage(this)">
                <small style="color: #6c757d; display: block; margin-top: 5px;">
                    Allowed: JPG, JPEG, PNG, GIF (Max: 5MB)
                </small>
                <div id="imagePreview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Item
                </button>
                <a href="view_found_item.php?id=<?= $item_id ?>" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View Item
                </a>
                <a href="manage_items.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
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
                    <span>User: <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></span>
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
    console.log('Edit Found Item page loaded');
    
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
    
    // Set today's date as max for date input
    const dateInput = document.getElementById('date_found');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
    }
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

// ===== FORM VALIDATION & IMAGE PREVIEW =====
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name.toLowerCase();
        
        // Check file type client-side
        const allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif)$/i;
        if (!allowedExtensions.exec(fileName)) {
            alert('Only JPG, JPEG, PNG & GIF files are allowed.');
            input.value = '';
            return;
        }
        
        // Check file size (5MB = 5 * 1024 * 1024 bytes)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-image';
            img.style.cursor = 'pointer';
            img.title = 'Click to view full size';
            img.onclick = function() {
                window.open(e.target.result, '_blank');
            };
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(file);
    }
}

function validateForm() {
    const itemName = document.getElementById('item_name').value.trim();
    const placeFound = document.getElementById('place_found').value.trim();
    
    if (!itemName) {
        alert('Please enter the item name');
        document.getElementById('item_name').focus();
        return false;
    }
    
    if (!placeFound) {
        alert('Please enter where the item was found');
        document.getElementById('place_found').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
    }
    
    return true;
}
</script>
</body>
</html>