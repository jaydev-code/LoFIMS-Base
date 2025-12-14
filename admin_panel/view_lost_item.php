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

    // Get lost item details with user and category info
    $stmt = $pdo->prepare("
        SELECT li.*, 
               u.first_name, u.last_name, u.email, u.student_id, u.course, u.contact_number,
               ic.category_name
        FROM lost_items li
        LEFT JOIN users u ON li.user_id = u.user_id
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.lost_id = ?
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header("Location: manage_items.php");
        exit();
    }

} catch(PDOException $e) {
    error_log("Database error in view_lost_item.php: " . $e->getMessage());
    die("An error occurred while loading item details.");
}

// Format dates
$dateLost = !empty($item['date_lost']) ? date('F d, Y', strtotime($item['date_lost'])) : 'Not specified';
$createdDate = !empty($item['created_at']) ? date('F d, Y g:i A', strtotime($item['created_at'])) : 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Lost Item - LoFIMS Admin</title>
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

/* View Container */
.view-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Item Header */
.item-header {
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    color: white;
    padding: 30px;
    position: relative;
}
.item-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}
.item-title {
    flex: 1;
}
.item-title h1 {
    font-size: 28px;
    margin-bottom: 10px;
    color: white;
}
.item-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    background: rgba(255,255,255,0.1);
    padding: 6px 12px;
    border-radius: 20px;
}
.item-actions {
    display: flex;
    gap: 10px;
}
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: 0.3s;
}
.btn-edit {
    background: #1e90ff;
    color: white;
}
.btn-edit:hover {
    background: #1c7ed6;
    transform: translateY(-2px);
}
.btn-back {
    background: #6c757d;
    color: white;
}
.btn-back:hover {
    background: #5a6268;
    transform: translateY(-2px);
}
.btn-delete {
    background: #dc3545;
    color: white;
}
.btn-delete:hover {
    background: #c82333;
    transform: translateY(-2px);
}

/* Item Body */
.item-body {
    padding: 30px;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

/* Photo Section */
.photo-section {
    text-align: center;
}
.item-photo {
    max-width: 100%;
    height: 400px;
    object-fit: contain;
    border-radius: 12px;
    background: #f8f9fa;
    padding: 10px;
    border: 1px solid #e9ecef;
}
.no-photo {
    width: 100%;
    height: 400px;
    background: #f8f9fa;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    border: 2px dashed #dee2e6;
}
.no-photo i {
    font-size: 48px;
    margin-bottom: 10px;
    color: #adb5bd;
}

/* Details Section */
.details-section h2 {
    color: #1e2a38;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}
.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #1e90ff;
}
.info-item h3 {
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 5px;
    font-weight: 600;
}
.info-item p {
    font-size: 16px;
    color: #333;
    font-weight: 500;
}

/* Description Section */
.description-section {
    grid-column: 1 / -1;
    margin-top: 20px;
}
.description-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border-left: 4px solid #ffc107;
}
.description-box h3 {
    color: #1e2a38;
    margin-bottom: 10px;
}
.description-text {
    line-height: 1.6;
    color: #555;
    white-space: pre-line;
}

/* Reporter Info */
.reporter-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 25px;
    border: 1px solid #e9ecef;
}
.reporter-section h3 {
    color: #1e2a38;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}
.reporter-info {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}
.reporter-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    font-weight: bold;
}
.reporter-details h4 {
    color: #333;
    margin-bottom: 5px;
}
.reporter-details p {
    color: #666;
    font-size: 14px;
}
.contact-info {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}
.contact-item i {
    color: #1e90ff;
    width: 20px;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-lost {
    background: #fff3cd;
    color: #856404;
}
.status-found {
    background: #d4edda;
    color: #155724;
}
.status-claimed {
    background: #cce5ff;
    color: #004085;
}
.status-returned {
    background: #d1ecf1;
    color: #0c5460;
}

/* Responsive */
@media(max-width: 900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
    .item-body{grid-template-columns:1fr;}
    .item-header-content{flex-direction:column;}
    .info-grid{grid-template-columns:1fr;}
}

@media(max-width: 600px){
    .header{flex-wrap:wrap;gap:10px;}
    .item-actions{flex-wrap:wrap;}
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
        <span><i class="fas fa-search"></i> View Lost Item</span>
    </div>

    <!-- View Container -->
    <div class="view-container">
        <!-- Item Header -->
        <div class="item-header">
            <div class="item-header-content">
                <div class="item-title">
                    <h1><i class="fas fa-search"></i> <?= htmlspecialchars($item['item_name']) ?></h1>
                    <div class="item-meta">
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Lost on: <?= $dateLost ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="status-badge status-lost">Lost</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Reported: <?= $createdDate ?></span>
                        </div>
                    </div>
                </div>
                <div class="item-actions">
                    <a href="edit_lost_item.php?id=<?= $itemId ?>" class="btn btn-edit">
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
                        $photoPath = '../uploads/lost_items/' . $item['photo'];
                        $fullPath = '/var/www/html/LoFIMS_BASE/uploads/lost_items/' . $item['photo'];
                    ?>
                        <?php if(file_exists($fullPath)): ?>
                            <img src="<?= $photoPath ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="item-photo">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image"></i>
                                <p>Photo file not found</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-photo">
                            <i class="fas fa-image"></i>
                            <p>No photo available</p>
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
                            <h3><i class="fas fa-map-marker-alt"></i> Location Lost</h3>
                            <p><?= htmlspecialchars($item['location_lost'] ?? 'Not specified') ?></p>
                        </div>
                        <div class="info-item">
                            <h3><i class="fas fa-calendar"></i> Date Lost</h3>
                            <p><?= $dateLost ?></p>
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

            <!-- Right Column: Reporter Info -->
            <div class="reporter-section">
                <h3><i class="fas fa-user"></i> Reporter Information</h3>
                
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
                    <h4 style="margin-bottom: 15px; color: #1e2a38;"><i class="fas fa-address-book"></i> Contact Information</h4>
                    
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
                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <h4 style="margin-bottom: 15px; color: #1e2a38;"><i class="fas fa-cog"></i> Admin Actions</h4>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="edit_lost_item.php?id=<?= $itemId ?>" class="btn btn-edit" style="text-align: center;">
                            <i class="fas fa-edit"></i> Edit This Item
                        </a>
                        
                        <a href="manage_items.php?type=lost" class="btn btn-back" style="text-align: center;">
                            <i class="fas fa-list"></i> View All Lost Items
                        </a>
                        
                        <button onclick="deleteItem()" class="btn btn-delete" style="text-align: center;">
                            <i class="fas fa-trash"></i> Delete This Item
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:400px;max-width:90%;">
        <h2><i class="fas fa-trash"></i> Delete Item</h2>
        <p style="margin:20px 0;">Are you sure you want to delete "<?= htmlspecialchars($item['item_name']) ?>"?</p>
        <div style="background:#fff3cd;padding:15px;border-radius:5px;margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Warning:</strong> This action cannot be undone.
        </div>
        <div style="display:flex;gap:10px;">
            <button onclick="confirmDelete()" style="background:#dc3545;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                <i class="fas fa-trash"></i> Delete
            </button>
            <button onclick="closeDeleteModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                Cancel
            </button>
        </div>
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

// ===== DELETE FUNCTIONALITY =====
function deleteItem() {
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDelete() {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'manage_items.php';
    
    // Add CSRF token (you should generate this properly)
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = 'csrf_token';
    csrfToken.value = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    form.appendChild(csrfToken);
    
    // Add item details
    const itemId = document.createElement('input');
    itemId.type = 'hidden';
    itemId.name = 'item_id';
    itemId.value = '<?= $itemId ?>';
    form.appendChild(itemId);
    
    const itemType = document.createElement('input');
    itemType.type = 'hidden';
    itemType.name = 'item_type';
    itemType.value = 'lost';
    form.appendChild(itemType);
    
    const deleteAction = document.createElement('input');
    deleteAction.type = 'hidden';
    deleteAction.name = 'delete_item';
    deleteAction.value = '1';
    form.appendChild(deleteAction);
    
    document.body.appendChild(form);
    form.submit();
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal && e.target === deleteModal) {
        closeDeleteModal();
    }
});

// Keyboard shortcut for escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>
</body>
</html>