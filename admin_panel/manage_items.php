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

// Generate CSRF token if not exists
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

// Handle search and filters
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, lost, found
$status = $_GET['status'] ?? 'all';

// Fetch items
try {
    // Count total items
    $countQuery = "
        SELECT 
            (SELECT COUNT(*) FROM lost_items) as total_lost,
            (SELECT COUNT(*) FROM found_items) as total_found
    ";
    $counts = $pdo->query($countQuery)->fetch(PDO::FETCH_ASSOC);
    
    // Initialize arrays
    $lostItems = [];
    $foundItems = [];
    
    // Build base queries
    if ($type === 'all' || $type === 'lost') {
        // Fetch lost items with claim counts using JOIN
        $lostQuery = "
            SELECT li.*, u.first_name, u.last_name, ic.category_name,
                   COUNT(c.claim_id) as claim_count
            FROM lost_items li
            LEFT JOIN users u ON li.user_id = u.user_id
            LEFT JOIN item_categories ic ON li.category_id = ic.category_id
            LEFT JOIN claims c ON li.lost_id = c.lost_id
            GROUP BY li.lost_id
            ORDER BY li.created_at DESC
            LIMIT 50
        ";
        
        $lostStmt = $pdo->query($lostQuery);
        $lostItems = $lostStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($type === 'all' || $type === 'found') {
        // Fetch found items with claim counts using JOIN
        $foundQuery = "
            SELECT fi.*, u.first_name, u.last_name, ic.category_name,
                   COUNT(c.claim_id) as claim_count
            FROM found_items fi
            LEFT JOIN users u ON fi.user_id = u.user_id
            LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
            LEFT JOIN claims c ON fi.found_id = c.found_id
            GROUP BY fi.found_id
            ORDER BY fi.created_at DESC
            LIMIT 50
        ";
        
        $foundStmt = $pdo->query($foundQuery);
        $foundItems = $foundStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Items - LoFIMS Admin</title>
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

/* Page Title */
.page-title{background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 5px 15px rgba(102,126,234,0.2);}
.page-title h1{font-size:24px;margin-bottom:5px;display:flex;align-items:center;gap:10px;}
.page-title p{font-size:14px;opacity:0.9;}

/* Stats Cards */
.stats-cards{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.stat-card{flex:1 1 150px;min-width:120px;background:white;padding:20px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition: transform 0.3s, box-shadow 0.3s;cursor:pointer;}
.stat-card:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.stat-card.lost{border-left:4px solid #dc3545;}
.stat-card.found{border-left:4px solid #28a745;}
.stat-card h3{font-size:32px;color:#1e90ff;margin-bottom:10px;text-shadow:1px 1px 2px rgba(0,0,0,0.1);}
.stat-card p{font-size:16px;color:#555;font-weight:500;}

/* Search and Filter Bar */
.search-filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.search-box{flex:1;min-width:250px;position:relative;}
.search-box input{width:100%;padding:8px 35px 8px 10px;border-radius:8px;border:1px solid #ccc;outline:none;font-size:14px;}
.search-box i{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#888;}
.filter-select{padding:8px 12px;border:1px solid #ccc;border-radius:8px;font-size:14px;min-width:120px;background:white;}

/* Tabs */
.tabs{display:flex;background:#f8f9fa;border-radius:8px;padding:4px;margin-bottom:20px;}
.tab{flex:1;padding:10px 15px;text-align:center;cursor:pointer;border-radius:6px;font-weight:600;transition:all 0.3s;}
.tab:hover{background:rgba(0,0,0,0.05);}
.tab.active{background:white;color:#1e2a38;box-shadow:0 2px 8px rgba(0,0,0,0.1);}

/* Items Container */
.items-container{background:white;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:20px;}
.items-header{display:flex;align-items:center;gap:10px;font-weight:600;color:#1e2a38;font-size:18px;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;}

/* Items Table */
.items-table{width:100%;border-collapse:collapse;}
.items-table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;color:#1e2a38;border-bottom:2px solid #eaeaea;font-size:14px;}
.items-table td{padding:12px;border-bottom:1px solid #eaeaea;vertical-align:middle;font-size:14px;}
.items-table tr:hover{background:#f8f9fa;}

/* Status Badges */
.status-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;}
.status-lost{background:#ffebee;color:#dc3545;}
.status-found{background:#e8f5e9;color:#28a745;}
.status-claimed{background:#fff3cd;color:#856404;}
.status-returned{background:#d1ecf1;color:#0c5460;}

/* Action Buttons */
.action-buttons{display:flex;gap:6px;}
.action-link{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;text-decoration:none;transition:all 0.3s;cursor:pointer;}
.action-link:hover{transform:scale(1.1);}
.btn-view{background:#17a2b8;color:white;}
.btn-view:hover{background:#138496;}
.btn-edit{background:#ffc107;color:#212529;}
.btn-edit:hover{background:#e0a800;}
.btn-delete{background:#dc3545;color:white;border:none;cursor:pointer;font-size:14px;}
.btn-delete:hover{background:#c82333;}

/* No Items Message */
.no-items{text-align:center;padding:40px;color:#666;}
.no-items i{font-size:48px;margin-bottom:15px;color:#ddd;}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;}
.btn-primary{background:#1e90ff;color:white;}
.btn-primary:hover{background:#1c86ee;}
.btn-secondary{background:#6c757d;color:white;}
.btn-secondary:hover{background:#5a6268;}

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

/* Delete Modal */
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
    padding: 25px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
}

.delete-modal-buttons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
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
    .search-filter-bar{flex-direction:column;align-items:stretch;}
    .search-box{min-width:100%;}
}

@media(max-width:600px){
    .stats-cards{flex-direction:column;}
    .tabs{flex-direction:column;}
    .items-table{display:block;overflow-x:auto;}
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
        
        <!-- Logout Button -->
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

    <!-- Page Title -->
    <div class="page-title">
        <h1><i class="fas fa-boxes"></i> Manage Items</h1>
        <p>View and manage all lost and found items in the system</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card lost" onclick="window.location.href='?type=lost'">
            <h3><?= $counts['total_lost'] ?? 0 ?></h3>
            <p><i class="fas fa-search"></i> Lost Items</p>
        </div>
        <div class="stat-card found" onclick="window.location.href='?type=found'">
            <h3><?= $counts['total_found'] ?? 0 ?></h3>
            <p><i class="fas fa-check-circle"></i> Found Items</p>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" 
                   id="searchInput" 
                   placeholder="Search items..." 
                   value="<?= htmlspecialchars($search) ?>"
                   onkeypress="if(event.key === 'Enter') performSearch()">
            <i class="fas fa-search" onclick="performSearch()"></i>
        </div>
        
        <select class="filter-select" id="typeFilter" onchange="updateFilter()">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Items</option>
            <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost Items</option>
            <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found Items</option>
        </select>
        
        <button class="btn btn-primary" onclick="performSearch()">
            <i class="fas fa-search"></i> Search
        </button>
        
        <button class="btn btn-secondary" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear
        </button>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab <?= $type === 'all' ? 'active' : '' ?>" onclick="setType('all')">
            All Items
        </div>
        <div class="tab <?= $type === 'lost' ? 'active' : '' ?>" onclick="setType('lost')">
            Lost Items (<?= $counts['total_lost'] ?? 0 ?>)
        </div>
        <div class="tab <?= $type === 'found' ? 'active' : '' ?>" onclick="setType('found')">
            Found Items (<?= $counts['total_found'] ?? 0 ?>)
        </div>
    </div>

    <!-- Lost Items Table -->
    <?php if ($type === 'all' || $type === 'lost'): ?>
    <div class="items-container">
        <div class="items-header">
            <i class="fas fa-search" style="color: #dc3545;"></i>
            Lost Items
        </div>
        
        <?php if (empty($lostItems)): ?>
            <div class="no-items">
                <i class="fas fa-inbox"></i>
                <h3>No lost items found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
        <?php else: ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Reported By</th>
                        <th>Date Lost</th>
                        <th>Status</th>
                        <th>Claims</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lostItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($item['date_reported'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($item['status'] ?? 'lost') ?>">
                                <?= htmlspecialchars($item['status'] ?? 'lost') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item['claim_count'] > 0): ?>
                                <span style="background: #ffc107; color: #212529; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                                    <?= $item['claim_count'] ?> claim(s)
                                </span>
                            <?php else: ?>
                                <span style="color: #666;">No claims</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <!-- View Button -->
                                <a href="view_lost_item.php?id=<?= $item['lost_id'] ?>" 
                                   class="action-link btn-view" 
                                   title="View Item">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button -->
                                <a href="edit_lost_item.php?id=<?= $item['lost_id'] ?>" 
                                   class="action-link btn-edit" 
                                   title="Edit Item">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Delete Button -->
                                <button class="action-link btn-delete" 
                                        onclick="confirmDelete('lost', <?= $item['lost_id'] ?>)" 
                                        title="Delete Item"
                                        type="button">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Found Items Table -->
    <?php if ($type === 'all' || $type === 'found'): ?>
    <div class="items-container">
        <div class="items-header">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            Found Items
        </div>
        
        <?php if (empty($foundItems)): ?>
            <div class="no-items">
                <i class="fas fa-inbox"></i>
                <h3>No found items found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
        <?php else: ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Found By</th>
                        <th>Date Found</th>
                        <th>Place Found</th>
                        <th>Status</th>
                        <th>Claims</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($foundItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($item['date_found'])) ?></td>
                        <td><?= htmlspecialchars($item['place_found'] ?? 'Unknown') ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($item['status'] ?? 'found') ?>">
                                <?= htmlspecialchars($item['status'] ?? 'found') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item['claim_count'] > 0): ?>
                                <span style="background: #ffc107; color: #212529; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                                    <?= $item['claim_count'] ?> claim(s)
                                </span>
                            <?php else: ?>
                                <span style="color: #666;">No claims</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <!-- View Button -->
                                <a href="view_found_item.php?id=<?= $item['found_id'] ?>" 
                                   class="action-link btn-view" 
                                   title="View Item">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button -->
                                <a href="edit_found_item.php?id=<?= $item['found_id'] ?>" 
                                   class="action-link btn-edit" 
                                   title="Edit Item">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Delete Button -->
                                <button class="action-link btn-delete" 
                                        onclick="confirmDelete('found', <?= $item['found_id'] ?>)" 
                                        title="Delete Item"
                                        type="button">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
        <div class="delete-modal-buttons">
            <button onclick="cancelDelete()" class="delete-modal-cancel">
                Cancel
            </button>
            <button onclick="confirmDeleteAction()" class="delete-modal-confirm">
                Delete
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
    console.log('Manage Items page loaded');
    
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

// ===== FILTER FUNCTIONS =====
let deleteType = '';
let deleteId = 0;

function setType(type) {
    saveSidebarState();
    const search = document.getElementById('searchInput').value;
    
    let url = 'manage_items.php?type=' + type;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    window.location.href = url;
}

function performSearch() {
    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('typeFilter').value;
    
    let url = 'manage_items.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (type !== 'all') url += `type=${type}&`;
    
    // Remove trailing & or ?
    url = url.replace(/[&?]$/, '');
    
    window.location.href = url;
}

function updateFilter() {
    performSearch();
}

function clearFilters() {
    window.location.href = 'manage_items.php';
}

// ===== DELETE FUNCTIONS =====
function confirmDelete(type, id) {
    deleteType = type;
    deleteId = id;
    
    const itemType = type === 'lost' ? 'lost' : 'found';
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete this ${itemType} item? This action cannot be undone.`;
    
    document.getElementById('deleteModal').style.display = 'flex';
}

function cancelDelete() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteType = '';
    deleteId = 0;
}

function confirmDeleteAction() {
    if (!deleteType || !deleteId) return;
    
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_item.php';
    
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'type';
    typeInput.value = deleteType;
    form.appendChild(typeInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = deleteId;
    form.appendChild(idInput);
    
    // Add CSRF token if available
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

function confirmDeleteAction() {
    if (!deleteType || !deleteId) return;
    
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_item.php'; // This should work now
    
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'type';
    typeInput.value = deleteType;
    form.appendChild(typeInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = deleteId;
    form.appendChild(idInput);
    
    // Add CSRF token if available
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Close delete modal when clicking outside
document.addEventListener('click', function(e) {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal && e.target === deleteModal) {
        cancelDelete();
    }
});

// Escape key to close delete modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const logoutModal = document.getElementById('logoutModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (logoutModal && logoutModal.style.display === 'flex') {
            closeLogoutModal();
        }
        if (deleteModal && deleteModal.style.display === 'flex') {
            cancelDelete();
        }
    }
});
</script>

</body>
</html>