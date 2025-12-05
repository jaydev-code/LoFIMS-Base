<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all users
    $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $studentId = trim($_POST['student_id'] ?? '');
    $roleId = (int)$_POST['role_id'];
    $contactInfo = trim($_POST['contact_info'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = "First name, last name, and email are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format!";
    } else {
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            $emailExists = $checkStmt->fetchColumn();
            
            if ($emailExists > 0) {
                $error = "Email already exists! Please use a different email.";
            } else {
                // Generate a random password (8 characters)
                $temporaryPassword = bin2hex(random_bytes(4)); // 8-character random password
                $hashedPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                
                // Insert new user with ACTUAL DATABASE FIELDS
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, student_id, role_id, contact_info, contact_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $studentId, 
                    $roleId, 
                    $contactInfo, 
                    $contactNumber, 
                    $hashedPassword
                ]);
                
                $newUserId = $pdo->lastInsertId();
                
                // Success - redirect to avoid form resubmission
                header("Location: manage_user.php?success=user_added&id=" . $newUserId . "&temp_pass=" . urlencode($temporaryPassword));
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
        }
    }
}

// Handle Edit User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $userId = (int)$_POST['user_id'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $studentId = trim($_POST['student_id'] ?? '');
    $roleId = (int)$_POST['role_id'];
    $contactInfo = trim($_POST['contact_info'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = "First name, last name, and email are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format!";
    } else {
        try {
            // Check if email already exists for another user
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $checkStmt->execute([$email, $userId]);
            $emailExists = $checkStmt->fetchColumn();
            
            if ($emailExists > 0) {
                $error = "Email already exists for another user!";
            } else {
                // Update user with ACTUAL DATABASE FIELDS
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, student_id = ?, role_id = ?, contact_info = ?, contact_number = ? WHERE user_id = ?");
                $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $studentId, 
                    $roleId, 
                    $contactInfo, 
                    $contactNumber, 
                    $userId
                ]);
                
                // Success - redirect
                header("Location: manage_user.php?success=user_updated&id=" . $userId);
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error updating user: " . $e->getMessage();
        }
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];
    
    try {
        // Prevent deleting yourself
        if ($userId == $_SESSION['user_id']) {
            $error = "You cannot delete your own account!";
        } else {
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Success - redirect
            header("Location: manage_user.php?success=user_deleted");
            exit();
        }
    } catch(PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Update your existing success message handling to include user messages
if(isset($_GET['success'])) {
    $successMessage = '';
    switch($_GET['success']) {
        case 'user_added': 
            $tempPass = $_GET['temp_pass'] ?? '';
            $successMessage = "✅ User added successfully! Temporary password: <strong>$tempPass</strong> (User must change this on first login)"; 
            break;
        case 'user_updated': 
            $successMessage = "✅ User updated successfully!"; 
            break;
        case 'user_deleted': 
            $successMessage = "✅ User deleted successfully!"; 
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - LoFIMS Admin</title>
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

/* Table Styles */
.table-container{background:white;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.users-table{width:100%;border-collapse:collapse;}
.users-table th, .users-table td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;}
.users-table th{background:#f8f9fa;font-weight:600;color:#495057;}
.users-table tr:hover{background:#f8f9fa;}
.users-table tr:last-child td{border-bottom:none;}

/* Badge Styles */
.role-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.role-badge.admin{background:#d4edda;color:#155724;}
.role-badge.user{background:#fff3cd;color:#856404;}
.status-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.status-badge.active{background:#d4edda;color:#155724;}
.status-badge.inactive{background:#f8d7da;color:#721c24;}

/* Action Buttons */
.action-buttons{display:flex;gap:5px;}
.btn-icon{background:none;border:none;color:#6c757d;cursor:pointer;padding:5px;border-radius:4px;transition:0.2s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;}
.btn-icon:hover{background:#f8f9fa;color:#495057;}
.btn-icon.edit:hover{color:#1e90ff;}
.btn-icon.delete:hover{color:#dc3545;}
.btn-icon.view:hover{color:#28a745;}

/* Add User Button */
.add-user-btn{background:#1e90ff;color:white;border:none;padding:10px 20px;border-radius:8px;font-weight:bold;cursor:pointer;display:flex;align-items:center;gap:8px;transition:0.3s;margin-bottom:20px;}
.add-user-btn:hover{background:#1c7ed6;transform:translateY(-2px);box-shadow:0 5px 15px rgba(30,144,255,0.3);}

/* Responsive */
@media(max-width:900px){.sidebar{left:-220px;}.sidebar.show{left:0;}.main{margin-left:0;padding:15px;}}
@media(max-width:768px){
    .table-container{overflow-x:auto;}
    .users-table{min-width:800px;}
    .header{flex-wrap:wrap;gap:10px;}
    .search-bar{width:100%;}
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
            <input type="text" id="globalSearch" placeholder="Search users...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        <p>View and manage all system users</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($successMessage)): ?>
    <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $successMessage ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Add User Button -->
    <button class="add-user-btn" onclick="showAddUserModal()">
        <i class="fas fa-plus"></i> Add New User
    </button>

    <!-- Users Table -->
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Student ID</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;color:#666;">No users found</td>
                </tr>
                <?php else: ?>
                <?php foreach($users as $user): ?>
                <tr>
                    <td>#<?= htmlspecialchars($user['user_id']) ?></td>
                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></td>
                    <td>
                        <span class="role-badge <?= $user['role_id'] == 1 ? 'admin' : 'user' ?>">
                            <?= $user['role_id'] == 1 ? 'Admin' : 'User' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($user['contact_number'] ?? $user['contact_info'] ?? 'N/A') ?></td>
                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon edit" title="Edit" onclick="editUser(<?= $user['user_id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon delete" title="Delete" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['first_name'] . ' ' . $user['last_name'])) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:500px;max-width:90%;max-height:90%;overflow-y:auto;">
        <h2 id="modalTitle">Add New User</h2>
        <form id="userForm" method="POST">
            <input type="hidden" id="userId" name="user_id">
            <input type="hidden" id="formAction" name="add_user" value="1">
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">First Name *</label>
                <input type="text" name="first_name" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Last Name *</label>
                <input type="text" name="last_name" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Email *</label>
                <input type="email" name="email" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Student ID (Optional)</label>
                <input type="text" name="student_id" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Role *</label>
                <select name="role_id" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
                    <option value="2">User</option>
                    <option value="1">Admin</option>
                </select>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Contact Info (Optional)</label>
                <input type="text" name="contact_info" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;">Contact Number (Optional)</label>
                <input type="text" name="contact_number" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:5px;">
            </div>
            
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" style="background:#1e90ff;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-save"></i> Save User
                </button>
                <button type="button" onclick="closeModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:400px;max-width:90%;">
        <h2>Delete User</h2>
        <p id="deleteMessage">Are you sure you want to delete this user?</p>
        <form id="deleteForm" method="POST" style="margin-top:20px;">
            <input type="hidden" id="deleteUserId" name="user_id">
            <input type="hidden" name="delete_user" value="1">
            
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#dc3545;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
// ===== BASIC LOGOUT FUNCTIONS =====
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
    console.log('Manage Users: Page loaded');
    
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
    
    // Initialize table animations
    initTableAnimations();
    
    // Load user data from PHP
    window.users = <?= json_encode($users) ?>;
    
    // Setup modal event listeners
    setupModalEvents();
    
    // Initialize search
    initSearch();
});

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'manage_user.php';
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

function initTableAnimations() {
    document.querySelectorAll('.users-table tbody tr').forEach((row, index) => {
        row.style.animationDelay = `${index * 0.05}s`;
        row.classList.add('fade-in');
    });
}

// ===== SEARCH FUNCTIONALITY =====
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        searchTimeout = setTimeout(() => {
            performLocalSearch(query);
        }, 300);
    });
}

function showAllTableRows() {
    document.querySelectorAll('.users-table tbody tr').forEach(row => {
        row.style.display = '';
    });
    
    // Remove any "no results" messages
    document.querySelectorAll('.users-table tbody tr td[colspan]').forEach(td => {
        if (td.textContent.includes('No users found matching')) {
            td.parentNode.remove();
        }
    });
    
    // Show the original "No users found" message if it exists
    const originalMessage = document.querySelector('.users-table tbody tr td[colspan="8"]');
    if (originalMessage && originalMessage.textContent.includes('No users found') && !originalMessage.textContent.includes('matching')) {
        originalMessage.parentNode.style.display = '';
    }
}

function performLocalSearch(query) {
    const rows = document.querySelectorAll('.users-table tbody tr');
    const queryLower = query.toLowerCase();
    let foundAny = false;
    
    // Remove any previous "no results" messages
    document.querySelectorAll('.users-table tbody tr td[colspan]').forEach(td => {
        if (td.textContent.includes('No users found matching')) {
            td.parentNode.remove();
        }
    });
    
    // Hide the original "No users found" message
    const originalMessage = document.querySelector('.users-table tbody tr td[colspan="8"]');
    if (originalMessage && originalMessage.textContent.includes('No users found') && !originalMessage.textContent.includes('matching')) {
        originalMessage.parentNode.style.display = 'none';
    }
    
    // If search is empty, show all rows
    if (query.length === 0) {
        showAllTableRows();
        return;
    }
    
    rows.forEach(row => {
        // Skip rows that are message rows
        if (row.querySelector('td[colspan]')) {
            return;
        }
        
        // Get all text from the row (excluding action buttons)
        let rowText = '';
        const cells = row.querySelectorAll('td');
        
        // Check all cells except the last one (actions column)
        for (let i = 0; i < cells.length - 1; i++) {
            const cell = cells[i];
            
            // Check role badge separately
            if (cell.querySelector('.role-badge')) {
                const badgeText = cell.querySelector('.role-badge').textContent.toLowerCase();
                rowText += badgeText + ' ';
            }
            
            // Add regular cell text
            rowText += cell.textContent.toLowerCase() + ' ';
        }
        
        // Check if this row matches the search
        if (rowText.includes(queryLower)) {
            row.style.display = '';
            foundAny = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    // If no matches and search is not empty, show message
    if (!foundAny && query.length > 0) {
        const tbody = document.querySelector('.users-table tbody');
        const messageRow = document.createElement('tr');
        messageRow.innerHTML = `
            <td colspan="8" style="text-align:center;padding:30px;color:#666;">
                No users found matching "${query}"
            </td>
        `;
        tbody.appendChild(messageRow);
    }
}

// ===== USER MANAGEMENT FUNCTIONS =====
function showAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('formAction').name = 'add_user';
    document.getElementById('formAction').value = '1';
    document.getElementById('userModal').style.display = 'flex';
}

function editUser(userId) {
    const user = window.users.find(u => u.user_id == userId);
    if (user) {
        document.getElementById('modalTitle').textContent = 'Edit User';
        document.getElementById('userId').value = user.user_id;
        document.querySelector('[name="first_name"]').value = user.first_name;
        document.querySelector('[name="last_name"]').value = user.last_name;
        document.querySelector('[name="email"]').value = user.email;
        document.querySelector('[name="student_id"]').value = user.student_id || '';
        document.querySelector('[name="role_id"]').value = user.role_id;
        document.querySelector('[name="contact_info"]').value = user.contact_info || '';
        document.querySelector('[name="contact_number"]').value = user.contact_number || '';
        
        // Change form action to edit
        document.getElementById('formAction').name = 'edit_user';
        document.getElementById('formAction').value = '1';
        
        document.getElementById('userModal').style.display = 'flex';
    }
}

function viewUser(userId) {
    saveSidebarState();
    window.location.href = `view_user.php?id=${userId}`;
}

function deleteUser(userId, userName) {
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete user "${userName}"? This action cannot be undone.`;
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Setup modal event listeners
function setupModalEvents() {
    // Close modals when clicking outside
    const userModal = document.getElementById('userModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (userModal) {
        userModal.addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });
    }
    
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if(e.target === this) {
                closeDeleteModal();
            }
        });
    }
}

// Add animation for table rows
const style = document.createElement('style');
style.textContent = `
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.3s ease forwards;
    opacity: 0;
}
`;
document.head.appendChild(style);

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in manage_user.php:');
    console.log('- confirmLogout:', typeof confirmLogout);
    console.log('- saveSidebarState:', typeof saveSidebarState);
    console.log('- showAddUserModal:', typeof showAddUserModal);
    console.log('- editUser:', typeof editUser);
    console.log('- deleteUser:', typeof deleteUser);
    console.log('- initSearch:', typeof initSearch);
}, 1000);
</script>
</body>
</html>