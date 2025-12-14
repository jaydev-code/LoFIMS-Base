<?php
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

    // Get item details
    $stmt = $pdo->prepare("
        SELECT li.*, ic.category_name
        FROM lost_items li
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.lost_id = ?
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header("Location: manage_items.php");
        exit();
    }

    // Get categories for dropdown
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name")->fetchAll();

} catch(PDOException $e) {
    error_log("Database error in edit_lost_item.php: " . $e->getMessage());
    die("An error occurred while loading item details.");
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $itemName = trim($_POST['item_name'] ?? '');
        $categoryId = $_POST['category_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $locationLost = trim($_POST['location_lost'] ?? '');
        $dateLost = $_POST['date_lost'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'lost';

        // Validate
        if (empty($itemName)) {
            $error = "Item name is required!";
        } elseif (empty($locationLost)) {
            $error = "Location lost is required!";
        } else {
            try {
                // Handle file upload
                $photo = $item['photo']; // Keep existing by default

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['photo']['name'];
                    $fileTmpPath = $_FILES['photo']['tmp_name'];
                    $fileSize = $_FILES['photo']['size'];

                    // Get file extension
                    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($fileExtension, $allowedTypes)) {
                        $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/lost_items/';

                        // Create directory if it doesn't exist
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        // Delete old photo if exists
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
                        $maxFileSize = 5 * 1024 * 1024;
                        if ($fileSize > $maxFileSize) {
                            $error = "File is too large. Maximum size is 5MB.";
                        } else {
                            if (move_uploaded_file($fileTmpPath, $targetFile)) {
                                $photo = $fileName;
                            } else {
                                $error = "Failed to upload file.";
                            }
                        }
                    } else {
                        $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    }
                }

                // Handle photo removal
                if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
                    if (!empty($item['photo'])) {
                        $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/lost_items/';
                        $oldPhotoPath = $uploadDir . $item['photo'];
                        if (file_exists($oldPhotoPath)) {
                            @unlink($oldPhotoPath);
                        }
                        $photo = '';
                    }
                }

                // Update database if no errors
                if (empty($error)) {
                    $stmt = $pdo->prepare("
                        UPDATE lost_items
                        SET item_name = ?, category_id = ?, description = ?, photo = ?,
                            location_lost = ?, date_lost = ?, status = ?, updated_at = NOW()
                        WHERE lost_id = ?
                    ");

                    $result = $stmt->execute([
                        $itemName,
                        $categoryId ?: null,
                        $description,
                        $photo,
                        $locationLost,
                        $dateLost,
                        $status,
                        $itemId
                    ]);

                    if ($result) {
                        $success = "Item updated successfully!";

                        // Refresh item data
                        $stmt = $pdo->prepare("
                            SELECT li.*, ic.category_name
                            FROM lost_items li
                            LEFT JOIN item_categories ic ON li.category_id = ic.category_id
                            WHERE li.lost_id = ?
                        ");
                        $stmt->execute([$itemId]);
                        $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Failed to update database.";
                    }
                }

            } catch(PDOException $e) {
                error_log("Database error in edit_lost_item.php - update: " . $e->getMessage());
                $error = "A database error occurred. Please try again.";
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
<title>Edit Lost Item - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ===== Reuse existing sidebar/main styles ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f6fa;display:flex;min-height:100vh;overflow-x:hidden;color:#333;}

/* Sidebar */
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

/* Main */
.main{margin-left:220px;padding:20px;flex:1;transition:0.3s;min-height:100vh;max-width:100%;}
.sidebar.folded ~ .main{margin-left:70px;}

/* Header */
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);position:sticky;top:0;z-index:100;}
.user-info{font-weight:bold;display:flex;align-items:center;gap:10px;color:#1e2a38;}
.user-info i{color:#1e90ff;font-size:18px;}

/* Breadcrumb */
.breadcrumb {
    background: white;
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #666;
}
.breadcrumb a {
    color: #1e90ff;
    text-decoration: none;
    transition: 0.3s;
}
.breadcrumb a:hover {
    color: #1c7ed6;
    text-decoration: underline;
}

/* Page Title */
.page-title {
    background: white;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    border-left: 5px solid #ff6b6b;
}

.page-title h1 {
    color: #1e2a38;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title p {
    color: #666;
    margin-top: 8px;
    font-size: 16px;
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid transparent;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

/* Form Container */
.form-container {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

/* Form Layout */
.form-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

@media(max-width: 900px) {
    .form-layout {
        grid-template-columns: 1fr;
    }
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 15px;
}

.form-group label i {
    color: #1e90ff;
    margin-right: 8px;
    width: 20px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.1);
}

.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    background: white;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.form-select:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.1);
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.5;
}

/* Photo Section */
.photo-section {
    grid-column: 1 / -1;
    background: #f8f9fa;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.current-photo {
    margin-bottom: 20px;
}

.photo-preview {
    max-width: 200px;
    max-height: 200px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #ced4da;
    background: white;
    padding: 5px;
}

.remove-photo-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    padding: 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.remove-photo-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

/* Form Actions */
.form-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #e9ecef;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.3s;
}

.btn-primary {
    background: #1e90ff;
    color: white;
}

.btn-primary:hover {
    background: #1c7ed6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 144, 255, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    transform: translateY(-2px);
}

/* Image Preview */
#imagePreview {
    margin-top: 15px;
}

#imagePreview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 5px;
    background: white;
}

/* Required field asterisk */
.required::after {
    content: " *";
    color: #dc3545;
}

/* Responsive */
@media(max-width: 900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
}

@media(max-width: 600px){
    .header{flex-wrap:wrap;gap:10px;}
    .form-actions{flex-wrap:wrap;}
    .btn{width:100%;justify-content:center;}
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
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a> &raquo;
        <a href="manage_items.php"><i class="fas fa-boxes"></i> Manage Items</a> &raquo;
        <a href="view_lost_item.php?id=<?= $itemId ?>"><i class="fas fa-search"></i> View Item</a> &raquo;
        <span><i class="fas fa-edit"></i> Edit Item</span>
    </div>

    <!-- Page Title -->
    <div class="page-title">
        <h1><i class="fas fa-edit"></i> Edit Lost Item</h1>
        <p>Update the details for: <strong><?= htmlspecialchars($item['item_name']) ?></strong></p>
    </div>

    <!-- Alert Messages -->
    <?php if($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
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
                        <label for="item_name"><i class="fas fa-tag"></i> Item Name <span class="required"></span></label>
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
                        <label for="category_id"><i class="fas fa-list"></i> Category</label>
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

                    <!-- Location Lost -->
                    <div class="form-group">
                        <label for="location_lost"><i class="fas fa-map-marker-alt"></i> Location Lost <span class="required"></span></label>
                        <input type="text"
                               id="location_lost"
                               name="location_lost"
                               class="form-control"
                               value="<?= htmlspecialchars($item['location_lost']) ?>"
                               required
                               placeholder="e.g., Library 2nd floor, Parking lot">
                    </div>

                    <!-- Date Lost -->
                    <div class="form-group">
                        <label for="date_lost"><i class="fas fa-calendar"></i> Date Lost</label>
                        <input type="date"
                               id="date_lost"
                               name="date_lost"
                               class="form-control"
                               value="<?= !empty($item['date_lost']) ? htmlspecialchars($item['date_lost']) : date('Y-m-d') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Status -->
                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="lost" <?= $item['status'] == 'lost' ? 'selected' : '' ?>>Lost</option>
                            <option value="found" <?= $item['status'] == 'found' ? 'selected' : '' ?>>Found</option>
                            <option value="claimed" <?= $item['status'] == 'claimed' ? 'selected' : '' ?>>Claimed</option>
                            <option value="returned" <?= $item['status'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description"
                                  name="description"
                                  class="form-control form-textarea"
                                  placeholder="Describe the item (color, brand, distinguishing features)..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Photo Section -->
                    <div class="photo-section">
                        <h3 style="margin-bottom: 20px; color: #1e2a38;"><i class="fas fa-camera"></i> Item Photo</h3>

                        <?php if(!empty($item['photo'])):
                            $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/lost_items/';
                            $webUrl = '../uploads/lost_items/' . $item['photo'];
                        ?>
                            <div class="current-photo">
                                <p><strong>Current Photo:</strong></p>
                                <?php if(file_exists($uploadDir . $item['photo'])): ?>
                                    <img src="<?= $webUrl ?>"
                                         alt="Current photo"
                                         class="photo-preview">
                                    <div class="remove-photo-checkbox">
                                        <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                        <label for="remove_photo">Remove current photo</label>
                                    </div>
                                <?php else: ?>
                                    <p style="color: #dc3545;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Photo file not found on server.
                                    </p>
                                <?php endif; ?>
                            </div>
                            <p style="color: #666; margin: 15px 0;">Or upload a new photo:</p>
                        <?php endif; ?>

                        <input type="file"
                               id="photo"
                               name="photo"
                               class="form-control"
                               accept=".jpg,.jpeg,.png,.gif"
                               onchange="previewImage(this)">
                        <small style="color: #666; display: block; margin-top: 10px;">
                            Allowed: JPG, JPEG, PNG, GIF (Max: 5MB)
                        </small>
                        <div id="imagePreview"></div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                    <a href="view_lost_item.php?id=<?= $itemId ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="manage_items.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Back to List
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// ===== BASIC PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
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

    // Logout functionality
    const logoutTrigger = document.getElementById('logoutTrigger');
    if (logoutTrigger) {
        logoutTrigger.addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'auth/logout.php';
            }
        });
    }
});

function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== FORM VALIDATION =====
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

        // Check file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        }

        reader.readAsDataURL(file);
    }
}

function validateForm() {
    const itemName = document.getElementById('item_name').value.trim();
    const locationLost = document.getElementById('location_lost').value.trim();

    if (!itemName) {
        alert('Please enter the item name');
        document.getElementById('item_name').focus();
        return false;
    }

    if (!locationLost) {
        alert('Please enter where the item was lost');
        document.getElementById('location_lost').focus();
        return false;
    }

    return true;
}
</script>
</body>
</html>