<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Check if CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get admin info
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Initialize variables
$success = '';
$error = '';
$item_name = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $type = $_POST['type'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if (empty($type) || $id <= 0) {
            $error = "Invalid item specified.";
        } else {
            try {
                if ($type === 'lost') {
                    // Get item name for message
                    $stmt = $pdo->prepare("SELECT item_name FROM lost_items WHERE lost_id = ?");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item) {
                        $item_name = $item['item_name'];
                        
                        // Delete associated claims first
                        $stmt = $pdo->prepare("DELETE FROM claims WHERE lost_id = ?");
                        $stmt->execute([$id]);
                        
                        // Delete the lost item
                        $stmt = $pdo->prepare("DELETE FROM lost_items WHERE lost_id = ?");
                        $stmt->execute([$id]);
                        
                        $success = "Lost item '{$item_name}' has been deleted successfully.";
                    } else {
                        $error = "Lost item not found.";
                    }
                    
                } elseif ($type === 'found') {
                    // Get item name for message
                    $stmt = $pdo->prepare("SELECT item_name FROM found_items WHERE found_id = ?");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item) {
                        $item_name = $item['item_name'];
                        
                        // Delete associated claims first
                        $stmt = $pdo->prepare("DELETE FROM claims WHERE found_id = ?");
                        $stmt->execute([$id]);
                        
                        // Delete the found item
                        $stmt = $pdo->prepare("DELETE FROM found_items WHERE found_id = ?");
                        $stmt->execute([$id]);
                        
                        $success = "Found item '{$item_name}' has been deleted successfully.";
                    } else {
                        $error = "Found item not found.";
                    }
                    
                } else {
                    $error = "Invalid item type.";
                }
                
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
} else {
    // If not POST, redirect to manage_items
    header("Location: manage_items.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Item - LoFIMS Admin</title>
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

/* Page Title */
.page-title{background:linear-gradient(135deg, #dc3545 0%, #c82333 100%);color:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 5px 15px rgba(220,53,69,0.2);}
.page-title h1{font-size:24px;margin-bottom:5px;display:flex;align-items:center;gap:10px;}
.page-title p{font-size:14px;opacity:0.9;}

/* Result Container */
.result-container{background:white;border-radius:12px;padding:30px;box-shadow:0 4px 12px rgba(0,0,0,0.1);text-align:center;}

/* Alert Messages */
.alert{padding:20px;border-radius:10px;margin-bottom:25px;border:2px solid transparent;}
.alert-success{background:#d1fae5;color:#065f46;border-color:#a7f3d0;}
.alert-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
.alert i{font-size:24px;margin-bottom:10px;display:block;}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;}
.btn-primary{background:#1e90ff;color:white;}
.btn-primary:hover{background:#1c86ee;transform:translateY(-2px);}
.btn-secondary{background:#6c757d;color:white;}
.btn-secondary:hover{background:#5a6268;transform:translateY(-2px);}

/* Icon Large */
.icon-large{font-size:64px;margin-bottom:20px;}
.icon-success{color:#28a745;}
.icon-error{color:#dc3545;}

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
}

@media(max-width:600px){
    .result-container{padding:20px;}
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
        
        <li onclick="saveSidebarState(); window.location.href='auth/logout.php'">
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
            <input type="text" placeholder="Search...">
            <i class="fas fa-search"></i>
        </div>
    </div>

    <!-- Page Title -->
    <div class="page-title">
        <h1><i class="fas fa-trash"></i> Delete Item</h1>
        <p>Item deletion result</p>
    </div>

    <!-- Result Container -->
    <div class="result-container">
        <?php if($success): ?>
            <div class="icon-large icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <h3 style="margin-bottom: 10px;">Success!</h3>
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        <?php elseif($error): ?>
            <div class="icon-large icon-error">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <h3 style="margin-bottom: 10px;">Error!</h3>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <div class="icon-large">
                <i class="fas fa-info-circle" style="color: #6c757d;"></i>
            </div>
            <p>No action performed.</p>
        <?php endif; ?>
        
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
            <a href="manage_items.php" class="btn btn-primary">
                <i class="fas fa-boxes"></i> Back to Manage Items
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>
        </div>
        
        <?php if($success): ?>
        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; font-size: 14px; color: #6c757d;">
            <p><i class="fas fa-info-circle"></i> The item has been permanently removed from the system.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ===== BASIC PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Delete Item page loaded');
    
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
    
    // Auto-redirect after 5 seconds if success
    <?php if($success): ?>
    setTimeout(function() {
        window.location.href = 'manage_items.php';
    }, 5000);
    <?php endif; ?>
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
</script>
</body>
</html>