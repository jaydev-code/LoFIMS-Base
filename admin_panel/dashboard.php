

<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistics
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
    $pendingClaims = $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();

    // Recent Activity
    $recentActivity = $pdo->query("
        (SELECT 'lost' as type, item_name as title, description, created_at, 'bg-danger' as color
         FROM lost_items ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'found' as type, item_name as title, description, created_at, 'bg-success' as color
         FROM found_items ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'claim' as type, CONCAT('Claim #', claim_id) as title, status, date_claimed as created_at, 
                CASE 
                    WHEN status = 'Pending' THEN 'bg-warning'
                    WHEN status = 'Approved' THEN 'bg-success'
                    ELSE 'bg-info'
                END as color
         FROM claims ORDER BY date_claimed DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Urgent items (lost items from last 24 hours)
    $urgentItems = $pdo->query("
        SELECT li.*, u.first_name, u.last_name, ic.category_name
        FROM lost_items li
        LEFT JOIN users u ON li.user_id = u.user_id
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND (li.status = 'Lost' OR li.status IS NULL OR li.status = '')
        ORDER BY li.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Announcements
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
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

/* Quick Actions */
.quick-actions{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.action-btn{flex:1 1 150px;min-width:120px;padding:20px;border-radius:12px;text-align:center;background:#1e90ff;color:white;font-weight:bold;font-size:16px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:10px;box-shadow:0 5px 15px rgba(30,144,255,0.3);position:relative;overflow:hidden;transition: transform 0.3s, box-shadow 0.3s;}
.action-btn i{font-size:24px;}
.action-btn:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(30,144,255,0.3);}
.action-btn.warning{background:#ffc107;color:#333;box-shadow:0 5px 15px rgba(255,193,7,0.3);}
.action-btn.warning:hover{box-shadow:0 8px 20px rgba(255,193,7,0.3);}

/* Dashboard Boxes */
.dashboard-boxes{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.box{flex:1 1 150px;min-width:120px;background:white;padding:30px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition: transform 0.3s, box-shadow 0.3s;cursor:pointer;}
.box:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.box h2{font-size:36px;color:#1e90ff;margin-bottom:10px;text-shadow:1px 1px 2px rgba(0,0,0,0.1);}
.box p{font-size:18px;color:#555;font-weight:500;}
.box.warning{border-left:4px solid #ffc107;background:#fff9e6;}
.box.warning h2{color:#ffc107;}

/* Panels */
.dashboard-layout{display:grid;grid-template-columns:2fr 1fr;gap:20px;}
.left-column,.right-column{display:flex;flex-direction:column;gap:20px;}
.recent-activity,.urgent-items,.charts,.announcements{background:white;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);position:relative;margin-bottom:20px;}
.activity-item{padding:12px 0;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px;border-radius:5px;cursor:pointer;}
.activity-item:last-child{border-bottom:none;}
.activity-item:hover{background:#f8f9fa;}
.activity-badge{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.bg-success{background:#28a745;}
.bg-danger{background:#dc3545;}
.bg-info{background:#17a2b8;}
.bg-warning{background:#ffc107;}

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
    .dashboard-layout{grid-template-columns:1fr;}
}

@media(max-width:600px){
    .quick-actions,.dashboard-boxes{flex-direction:column;}
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
        <li class="active">
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


        
        <!-- Updated Logout Button -->
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
        <div class="user-info user-info-center">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search items, users, reports...">
            <i class="fas fa-search"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <div class="quick-actions">
        <div class="action-btn" onclick="saveSidebarState(); window.location='manage_user.php'"><i class="fas fa-users"></i><span>Manage Users</span></div>
        <div class="action-btn" onclick="saveSidebarState(); window.location='reports.php'"><i class="fas fa-chart-line"></i><span>Reports</span></div>
        <div class="action-btn" onclick="saveSidebarState(); window.location='categories.php'"><i class="fas fa-tags"></i><span>Categories</span></div>
        <div class="action-btn" onclick="saveSidebarState(); window.location='announcements.php'"><i class="fas fa-bullhorn"></i><span>Announcements</span></div>
        <div class="action-btn warning" onclick="saveSidebarState(); window.location='claims.php?status=pending'">
            <i class="fas fa-handshake"></i><span>Pending Claims</span>
            <?php if($pendingClaims > 0): ?>
            <span style="position:absolute;top:10px;right:10px;background:#dc3545;color:white;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px;">
                <?= $pendingClaims ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dashboard Boxes -->
    <div class="dashboard-boxes">
        <div class="box" onclick="saveSidebarState(); window.location='manage_user.php'">
            <h2 id="totalUsers"><?= $totalUsers ?></h2>
            <p>Total Users</p>
        </div>
        <div class="box" onclick="saveSidebarState(); window.location='reports.php?type=lost'">
            <h2 id="totalLost"><?= $totalLost ?></h2>
            <p>Total Lost Items</p>
        </div>
        <div class="box" onclick="saveSidebarState(); window.location='reports.php?type=found'">
            <h2 id="totalFound"><?= $totalFound ?></h2>
            <p>Total Found Items</p>
        </div>
        <div class="box" onclick="saveSidebarState(); window.location='claims.php'">
            <h2 id="totalClaims"><?= $totalClaims ?></h2>
            <p>Total Claims</p>
        </div>
        <div class="box warning" onclick="saveSidebarState(); window.location='claims.php?status=pending'">
            <h2 id="pendingClaims"><?= $pendingClaims ?></h2>
            <p><i class="fas fa-exclamation-triangle"></i> Pending Claims</p>
            <small style="color:#666;font-size:12px;">Click to review</small>
        </div>
    </div>

    <!-- Panels -->
    <div class="dashboard-layout">
        <div class="left-column">
            <div class="charts">
                <canvas id="systemChart" style="max-height:300px;"></canvas>
            </div>
            <div class="recent-activity">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php foreach($recentActivity as $activity): ?>
                <div class="activity-item" onclick="handleActivityClick('<?= $activity['type'] ?>', '<?= $activity['title'] ?>')">
                    <div class="activity-badge <?= $activity['color'] ?>"></div>
                    <div>
                        <strong><?= htmlspecialchars($activity['title']) ?></strong><br>
                        <small>
                            <?php 
                            if($activity['type'] === 'claim') {
                                echo 'Status: ' . htmlspecialchars($activity['description'] ?? '');
                            } else {
                                echo htmlspecialchars(substr($activity['description'] ?? '',0,50)) . '...';
                            }
                            ?>
                        </small><br>
                        <span style="font-size:12px;color:#999;"><?= date("M d, Y H:i", strtotime($activity['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($recentActivity) === 0): ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        <i class="fas fa-inbox"></i><br>
                        No recent activity
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="right-column">
            <div class="urgent-items">
                <h3><i class="fas fa-exclamation-triangle"></i> Urgent Items (Last 24h)</h3>
                <?php if(count($urgentItems) > 0): ?>
                    <?php foreach($urgentItems as $item): ?>
                    <div class="activity-item" onclick="saveSidebarState(); window.location='reports.php?type=lost'">
                        <div class="activity-badge bg-danger"></div>
                        <div>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                            <small><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?> by <?= htmlspecialchars($item['first_name'].' '.$item['last_name']) ?></small><br>
                            <span style="font-size:12px;color:#999;">Lost <?= date("M d H:i", strtotime($item['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        <i class="fas fa-check-circle"></i><br>
                        No urgent items in last 24 hours
                    </div>
                <?php endif; ?>
            </div>
            <div class="announcements">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
                <?php foreach($announcements as $announce): ?>
                <div class="activity-item" onclick="saveSidebarState(); window.location='announcements.php'">
                    <div class="activity-badge bg-info"></div>
                    <div>
                        <strong><?= htmlspecialchars($announce['title']) ?></strong><br>
                        <span style="font-size:12px;color:#999;"><?= date("M d, Y", strtotime($announce['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($announcements) === 0): ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        <i class="fas fa-bullhorn"></i><br>
                        No announcements yet
                    </div>
                <?php endif; ?>
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
    console.log('Dashboard.php: Page loaded');
    
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
    
    // Initialize Chart.js
    initChart();
    
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

// Handle activity item clicks
function handleActivityClick(type, title) {
    saveSidebarState();
    
    switch(type) {
        case 'lost':
            window.location.href = 'reports.php?type=lost';
            break;
        case 'found':
            window.location.href = 'reports.php?type=found';
            break;
        case 'claim':
            const claimId = title.replace('Claim #', '').trim();
            if(claimId && !isNaN(claimId)) {
                window.location.href = 'claims.php?search=' + claimId;
            } else {
                window.location.href = 'claims.php';
            }
            break;
        default:
            window.location.href = 'reports.php';
    }
}

function initChart() {
    const ctx = document.getElementById('systemChart');
    if (!ctx) return;
    
    const totalUsers = parseInt(document.getElementById('totalUsers')?.textContent || 0);
    const totalLost = parseInt(document.getElementById('totalLost')?.textContent || 0);
    const totalFound = parseInt(document.getElementById('totalFound')?.textContent || 0);
    const totalClaims = parseInt(document.getElementById('totalClaims')?.textContent || 0);
    const pendingClaims = parseInt(document.getElementById('pendingClaims')?.textContent || 0);
    
    new Chart(ctx.getContext('2d'), {
        type:'doughnut',
        data:{
            labels:['Users','Lost Items','Found Items','Total Claims','Pending Claims'],
            datasets:[{
                label:'System Stats',
                data:[totalUsers, totalLost, totalFound, totalClaims, pendingClaims],
                backgroundColor:[
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(255, 107, 107, 0.8)',
                    'rgba(30, 144, 255, 0.8)',
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ],
                borderColor:[
                    'rgba(255, 193, 7, 1)',
                    'rgba(255, 107, 107, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(76, 175, 80, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth:2
            }]
        },
        options:{
            responsive:true, 
            maintainAspectRatio: true,
            plugins:{
                legend:{
                    position:'bottom',
                    labels:{
                        padding:20,
                        usePointStyle:true,
                        font: {
                            size: 12
                        }
                    }
                },
                title:{
                    display:true,
                    text:'System Overview',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed;
                            return label;
                        }
                    }
                }
            }, 
            cutout:'65%'
        }
    });
}

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check:');
    console.log('- saveSidebarState:', typeof saveSidebarState);
    console.log('- initChart:', typeof initChart);
    console.log('- initLogoutModal:', typeof initLogoutModal);
    
    // Check if logout button exists
    const logoutBtn = document.getElementById('logoutTrigger');
    console.log('Logout button exists:', !!logoutBtn);
    if (logoutBtn) {
        console.log('Logout button text:', logoutBtn.textContent);
    }
}, 1000);

// Auto-refresh dashboard every 60 seconds (optional)
// setTimeout(function() {
//     console.log('Dashboard auto-refreshing...');
//     window.location.reload();
// }, 60000);
</script>

</body>
</html>