<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all announcements - CORRECT QUERY FOR YOUR TABLE STRUCTURE
    $announcements = $pdo->query("
        SELECT * 
        FROM announcements 
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Add admin info to each announcement (since no user_id in table)
    foreach($announcements as &$announcement) {
        $announcement['first_name'] = $admin['first_name'];
        $announcement['last_name'] = $admin['last_name'];
    }
    
    // Count statistics
    $totalAnnouncements = count($announcements);
    $todayAnnouncements = $pdo->query("
        SELECT COUNT(*) FROM announcements WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        
        if (!empty($title) && !empty($content)) {
            try {
                // INSERT using correct column names
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, content, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$title, $content]);
                header("Location: announcements.php?success=added");
                exit();
            } catch(PDOException $e) {
                $error = "Error adding announcement: " . $e->getMessage();
            }
        } else {
            $error = "Title and content are required!";
        }
    }
    
    if (isset($_POST['edit_announcement'])) {
        $announcementId = $_POST['announcement_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        
        if (!empty($title) && !empty($content)) {
            try {
                // UPDATE using 'id' column (NOT 'announcement_id')
                $stmt = $pdo->prepare("
                    UPDATE announcements 
                    SET title = ?, content = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $announcementId]);
                header("Location: announcements.php?success=updated");
                exit();
            } catch(PDOException $e) {
                $error = "Error updating announcement: " . $e->getMessage();
            }
        } else {
            $error = "Title and content are required!";
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcementId = $_POST['announcement_id'];
        
        try {
            // DELETE using 'id' column
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$announcementId]);
            header("Location: announcements.php?success=deleted");
            exit();
        } catch(PDOException $e) {
            $error = "Error deleting announcement: " . $e->getMessage();
        }
    }
}

// Get announcements again after any modifications
if (!isset($error)) {
    try {
        $announcements = $pdo->query("
            SELECT * 
            FROM announcements 
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Add admin info again
        foreach($announcements as &$announcement) {
            $announcement['first_name'] = $admin['first_name'];
            $announcement['last_name'] = $admin['last_name'];
        }
    } catch(PDOException $e) {
        $announcements = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - LoFIMS Admin</title>
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
.search-results{position:absolute;top:38px;left:0;width:100%;max-height:300px;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:8px;overflow-y:auto;display:none;z-index:2000;}
.search-results .result-item{padding:10px 15px;cursor:pointer;transition:0.3s;}
.search-results .result-item:hover{background:#f0f4ff;}

/* Page Header */
.page-header{background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.page-header h1{color:#1e2a38;font-size:28px;display:flex;align-items:center;gap:10px;}
.page-header p{color:#666;margin-top:5px;}

/* Stats Cards */
.stats-cards{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:30px;}
.stat-card{flex:1 1 200px;background:white;padding:25px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition:0.3s;}
.stat-card:hover{transform:translateY(-5px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.stat-card h2{font-size:32px;color:#1e90ff;margin-bottom:10px;}
.stat-card p{color:#555;font-weight:500;margin-bottom:10px;}
.stat-card .card-icon{font-size:24px;margin-bottom:15px;color:#1e90ff;}

/* Announcements List */
.announcements-list{display:flex;flex-direction:column;gap:20px;margin-bottom:30px;}
.announcement-card{background:white;border-radius:12px;padding:25px;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition:0.3s;}
.announcement-card:hover{box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.announcement-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;padding-bottom:15px;border-bottom:2px solid #f0f0f0;}
.announcement-title{font-size:20px;font-weight:600;color:#1e2a38;margin:0;}
.announcement-meta{display:flex;gap:15px;font-size:14px;color:#666;margin-top:5px;}
.announcement-content{color:#444;line-height:1.6;margin-bottom:15px;}
.announcement-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:15px;border-top:1px solid #f0f0f0;}

/* Status Badges */
.status-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;display:inline-block;}
.status-badge.active{background:#d4edda;color:#155724;}

/* Form Styles */
.form-group{margin-bottom:15px;}
.form-group label{display:block;margin-bottom:5px;font-weight:500;color:#495057;}
.form-group input, .form-group textarea{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;font-family:Arial,sans-serif;}
.form-group textarea{min-height:150px;resize:vertical;}
.form-group input:focus, .form-group textarea:focus{outline:none;border-color:#1e90ff;box-shadow:0 0 0 3px rgba(30,144,255,0.1);}
.form-actions{display:flex;gap:10px;margin-top:20px;}

/* Button Styles */
.btn{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:6px;border:none;font-weight:500;cursor:pointer;transition:0.3s;}
.btn-primary{background:#1e90ff;color:white;}
.btn-primary:hover{background:#1c7ed6;}
.btn-success{background:#28a745;color:white;}
.btn-success:hover{background:#218838;}
.btn-danger{background:#dc3545;color:white;}
.btn-danger:hover{background:#c82333;}
.btn-secondary{background:#6c757d;color:white;}
.btn-secondary:hover{background:#5a6268;}
.btn-sm{padding:6px 12px;font-size:14px;}

/* No Announcements */
.no-announcements{text-align:center;padding:50px 20px;background:white;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.no-announcements i{font-size:48px;color:#ddd;margin-bottom:20px;}
.no-announcements h3{color:#666;margin-bottom:10px;}

/* Modal Styles */
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;}
.modal-content{background:white;padding:30px;border-radius:10px;width:600px;max-width:90%;max-height:90%;overflow-y:auto;}
.modal-header{margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #eee;}
.modal-header h2{margin:0;color:#1e2a38;}

/* Success/Error Messages */
.alert{padding:12px 15px;border-radius:6px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}.sidebar.show{left:0;}.main{margin-left:0;padding:15px;}
    .announcement-header{flex-direction:column;gap:10px;}
    .announcement-footer{flex-direction:column;gap:10px;align-items:flex-start;}
}
@media(max-width:768px){
    .header{flex-wrap:wrap;gap:10px;}
    .search-bar{width:100%;}
    .stats-cards{flex-direction:column;}
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
        
        <li onclick="saveSidebarState(); window.location.href='categories.php'">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        <li onclick="confirmLogout()">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </li>
    </ul>
</div>

<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="announcementSearch" placeholder="Search announcements...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
        <p>Manage system announcements and notifications</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php 
        switch($_GET['success']) {
            case 'added': echo "Announcement added successfully!"; break;
            case 'updated': echo "Announcement updated successfully!"; break;
            case 'deleted': echo "Announcement deleted successfully!"; break;
        }
        ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="card-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h2 id="totalAnnouncements"><?= $totalAnnouncements ?></h2>
            <p>Total Announcements</p>
            <small>All announcements in system</small>
        </div>
        
        <div class="stat-card">
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <h2 id="todayAnnouncements"><?= $todayAnnouncements ?></h2>
            <p>Today's Announcements</p>
            <small>Posted today</small>
        </div>
        
        <div class="stat-card" onclick="showAddModal()" style="cursor:pointer;background:#f8f9fa;border:2px dashed #ddd;">
            <div class="card-icon" style="color:#6c757d;">
                <i class="fas fa-plus"></i>
            </div>
            <h2 style="color:#6c757d;">+ Add New</h2>
            <p>Create Announcement</p>
            <small>Click to add new announcement</small>
        </div>
    </div>

    <!-- Add Announcement Button -->
    <button class="btn btn-primary" onclick="showAddModal()" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> Add New Announcement
    </button>

    <!-- Announcements List -->
    <div class="announcements-list">
        <?php if(empty($announcements)): ?>
        <div class="no-announcements">
            <i class="fas fa-bullhorn"></i>
            <h3>No Announcements Yet</h3>
            <p>Get started by creating your first announcement.</p>
            <button class="btn btn-primary" onclick="showAddModal()" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Create First Announcement
            </button>
        </div>
        <?php else: ?>
        <?php foreach($announcements as $announcement): ?>
        <div class="announcement-card">
            <div class="announcement-header">
                <div>
                    <h3 class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></h3>
                    <div class="announcement-meta">
                        <span><i class="far fa-user"></i> <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></span>
                        <span><i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($announcement['created_at'])) ?></span>
                        <span><i class="fas fa-tag"></i> 
                            <span class="status-badge active">
                                Active
                            </span>
                        </span>
                    </div>
                </div>
                <div style="display:flex;gap:5px;">
                    <button class="btn btn-primary btn-sm" onclick="editAnnouncement(<?= $announcement['id'] ?>)">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(<?= $announcement['id'] ?>, '<?= htmlspecialchars(addslashes($announcement['title'])) ?>')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            
            <div class="announcement-content">
                <?= nl2br(htmlspecialchars($announcement['content'])) ?>
            </div>
            
            <div class="announcement-footer">
                <div>
                    <small><i class="far fa-calendar"></i> Created: <?= date('M d, Y', strtotime($announcement['created_at'])) ?></small>
                </div>
                <div>
                    <small>ID: #<?= $announcement['id'] ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Announcement Modal -->
<div class="modal" id="announcementModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Announcement</h2>
        </div>
        <form id="announcementForm" method="POST">
            <input type="hidden" id="announcementId" name="announcement_id">
            <input type="hidden" id="formAction" name="add_announcement">
            
            <div class="form-group">
                <label for="announcementTitle">Title *</label>
                <input type="text" id="announcementTitle" name="title" required maxlength="200" placeholder="Enter announcement title">
            </div>
            
            <div class="form-group">
                <label for="announcementContent">Content *</label>
                <textarea id="announcementContent" name="content" required placeholder="Enter announcement content..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Announcement
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Announcement</h2>
        </div>
        <div style="padding: 20px 0;">
            <p id="deleteMessage">Are you sure you want to delete this announcement?</p>
            <form id="deleteForm" method="POST" style="margin-top: 20px;">
                <input type="hidden" id="deleteAnnouncementId" name="announcement_id">
                <input type="hidden" name="delete_announcement" value="1">
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- IMPORTANT: Load the JavaScript file -->
<script src="./assets/js/dashboard.js"></script>

<!-- Fallback JavaScript for announcements.php -->
<script>
// ===== BASIC LOGOUT FUNCTIONS (FALLBACK) =====
function confirmLogout() {
    if (confirm('Are you sure you want to logout? You will be redirected to home page.')) {
        saveSidebarState();
        window.location.href = 'auth/logout.php';
    }
}

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
    console.log('Announcements: Page loaded');
    
    // Load sidebar state
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const savedState = localStorage.getItem('sidebarFolded');
            console.log('Saved sidebar state:', savedState);
            if (savedState === 'true') {
                sidebar.classList.add('folded');
            } else {
                sidebar.classList.remove('folded');
            }
        }
    }
    
    // Highlight active page
    highlightActivePage();
    
    // Basic sidebar toggle
    initSidebarToggle();
});

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'announcements.php';
    const menuItems = document.querySelectorAll('.sidebar ul li');
    
    menuItems.forEach(item => {
        const onclick = item.getAttribute('onclick') || '';
        if (onclick.includes(currentPage)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
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

// ===== ANNOUNCEMENT MANAGEMENT FUNCTIONS =====
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Announcement';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcementId').value = '';
    document.getElementById('formAction').name = 'add_announcement';
    document.getElementById('announcementModal').style.display = 'flex';
}

function editAnnouncement(id) {
    // Find the announcement in the PHP data
    const announcements = <?= json_encode($announcements) ?>;
    const announcement = announcements.find(a => a.id == id);
    
    if (announcement) {
        document.getElementById('modalTitle').textContent = 'Edit Announcement';
        document.getElementById('announcementId').value = id;
        document.getElementById('announcementTitle').value = announcement.title;
        document.getElementById('announcementContent').value = announcement.content;
        document.getElementById('formAction').name = 'edit_announcement';
        document.getElementById('announcementModal').style.display = 'flex';
    } else {
        alert('Announcement not found!');
    }
}

function deleteAnnouncement(id, title) {
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete the announcement "${title}"? This action cannot be undone.`;
    document.getElementById('deleteAnnouncementId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function viewAnnouncement(id) {
    // Scroll to the announcement card
    const card = document.querySelector(`.announcement-card:has(button[onclick*="${id}"])`);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.style.boxShadow = '0 0 0 3px #1e90ff';
        setTimeout(() => card.style.boxShadow = '', 2000);
    }
    searchResults.style.display = 'none';
}

function closeModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Announcement Search
const searchInput = document.getElementById('announcementSearch');
const searchResults = document.querySelector('.search-results');

if (searchInput && searchResults) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        if(query.length < 1){ 
            searchResults.style.display='none'; 
            searchResults.innerHTML=''; 
            return; 
        }
        
        // Filter announcements
        const announcements = <?= json_encode($announcements) ?>;
        const filtered = announcements.filter(ann => 
            ann.title.toLowerCase().includes(query) || 
            ann.content.toLowerCase().includes(query)
        );
        
        if(filtered.length > 0){
            searchResults.innerHTML = filtered.map(ann => `
                <div class="result-item" onclick="viewAnnouncement(${ann.id})">
                    <strong>${ann.title}</strong>
                    <small>Posted: ${new Date(ann.created_at).toLocaleDateString()}</small>
                </div>
            `).join('');
            searchResults.style.display='block';
        } else {
            searchResults.innerHTML = '<div class="result-item">No announcements found</div>';
            searchResults.style.display='block';
        }
    });
    
    document.addEventListener('click', e => { 
        if(!searchResults.contains(e.target) && e.target!==searchInput) {
            searchResults.style.display='none'; 
        }
    });
}

// Close modals when clicking outside
const announcementModal = document.getElementById('announcementModal');
if (announcementModal) {
    announcementModal.addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
}

const deleteModal = document.getElementById('deleteModal');
if (deleteModal) {
    deleteModal.addEventListener('click', function(e) {
        if(e.target === this) closeDeleteModal();
    });
}

// Character counter for textarea
const textarea = document.getElementById('announcementContent');
if (textarea) {
    const counter = document.createElement('div');
    counter.style.fontSize = '12px';
    counter.style.color = '#666';
    counter.style.textAlign = 'right';
    counter.style.marginTop = '5px';
    textarea.parentNode.appendChild(counter);
    
    textarea.addEventListener('input', function() {
        const length = this.value.length;
        counter.textContent = `${length} characters`;
        counter.style.color = length > 1000 ? '#dc3545' : '#666';
    });
}

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in announcements.php:');
    console.log('- confirmLogout:', typeof confirmLogout);
    console.log('- saveSidebarState:', typeof saveSidebarState);
    console.log('- showAddModal:', typeof showAddModal);
    console.log('- editAnnouncement:', typeof editAnnouncement);
    console.log('- deleteAnnouncement:', typeof deleteAnnouncement);
    
    if (typeof confirmLogout !== 'function') {
        console.error('confirmLogout function not found!');
    }
}, 1000);
</script>

</body>
</html>