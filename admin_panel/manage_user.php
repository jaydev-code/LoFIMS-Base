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

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}

// Course options
$courseOptions = ['bet-CpEt', 'bet-Eet', 'bet-Aet', 'bet-Ct', 'bet-Eset', 'bet-Hvac'];

// Category options with display labels
$categoryOptions = [
    'student' => 'Student',
    'instructor/prof' => 'Instructor/Professor',
    'faculty staff' => 'Faculty Staff',
    'others' => 'Others'
];

// Year options with display labels
$yearOptions = [
    '1' => '1st Year',
    '2' => '2nd Year',
    '3' => '3rd Year',
    '4' => '4th Year',
    'others' => 'Others'
];

// Get filter/sort parameters
$filterCourse = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$filterCategory = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters and sorting
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

// Add search filter
if (!empty($searchQuery)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ? OR course LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Add course filter
if (!empty($filterCourse)) {
    $sql .= " AND course = ?";
    $params[] = $filterCourse;
}

// Add category filter
if (!empty($filterCategory)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}

// Add year filter
if (!empty($filterYear)) {
    $sql .= " AND year = ?";
    $params[] = $filterYear;
}

// Add sorting
switch ($sortBy) {
    case 'name_asc':
        $sql .= " ORDER BY first_name ASC, last_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY first_name DESC, last_name DESC";
        break;
    case 'course_asc':
        $sql .= " ORDER BY course ASC";
        break;
    case 'course_desc':
        $sql .= " ORDER BY course DESC";
        break;
    case 'year_asc':
        $sql .= " ORDER BY year ASC";
        break;
    case 'year_desc':
        $sql .= " ORDER BY year DESC";
        break;
    case 'created_at_asc':
        $sql .= " ORDER BY created_at ASC";
        break;
    case 'created_at_desc':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

try {
    // Get all users with filters and sorting
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $studentId = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? 'bet-CpEt');
    $category = trim($_POST['category'] ?? 'student');
    $year = trim($_POST['year'] ?? '1');
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
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
                // Set password as last name in CAPITAL LETTERS
                $password = strtoupper($lastName);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with ALL fields including category
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, student_id, course, category, year, birthdate, role_id, contact_info, contact_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $studentId, 
                    $course, 
                    $category, 
                    $year, 
                    $birthdate, 
                    $roleId, 
                    $contactInfo, 
                    $contactNumber, 
                    $hashedPassword
                ]);
                
                $newUserId = $pdo->lastInsertId();
                
                // Success - redirect to avoid form resubmission
                header("Location: manage_user.php?success=user_added&id=" . $newUserId . "&password=" . urlencode($password));
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
    $course = trim($_POST['course'] ?? 'bet-CpEt');
    $category = trim($_POST['category'] ?? 'student');
    $year = trim($_POST['year'] ?? '1');
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
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
                // Update user with ALL fields including category
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, student_id = ?, course = ?, category = ?, year = ?, birthdate = ?, role_id = ?, contact_info = ?, contact_number = ? WHERE user_id = ?");
                $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $studentId, 
                    $course, 
                    $category, 
                    $year, 
                    $birthdate, 
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
            $password = $_GET['password'] ?? '';
            $successMessage = "✅ User added successfully! Password set to: <strong>$password</strong> (User's last name in CAPITAL LETTERS)"; 
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
<!-- Include Flatpickr CSS for better date picker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

/* Page Header */
.page-header{background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.page-header h1{color:#1e2a38;font-size:28px;display:flex;align-items:center;gap:10px;}
.page-header p{color:#666;margin-top:5px;}

/* Filters and Search Container */
.filters-container {background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.filters-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.filters-header h2 {margin:0;font-size:18px;color:#1e2a38;display:flex;align-items:center;gap:10px;}
.filter-row {display:grid;grid-template-columns:1fr 1fr 1fr 2fr auto;gap:15px;align-items:end;}
.filter-group {display:flex;flex-direction:column;gap:5px;}
.filter-group label {font-size:12px;font-weight:600;color:#495057;text-transform:uppercase;}
.filter-group select, .filter-group input {width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;background:white;}
.search-box {position:relative;}
.search-box input {padding-left:35px;}
.search-box i {position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#888;}
.filter-actions {display:flex;gap:10px;}
.filter-btn {padding:8px 20px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;transition:0.3s;}
.filter-btn.apply {background:#1e90ff;color:white;border:none;}
.filter-btn.apply:hover {background:#1c7ed6;}
.filter-btn.reset {background:#f8f9fa;color:#495057;border:1px solid #ddd;}
.filter-btn.reset:hover {background:#e9ecef;}

/* Sort Controls */
.sort-controls {display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.sort-label {font-weight:600;color:#495057;}
.sort-btn {background:#f8f9fa;border:1px solid #ddd;padding:6px 12px;border-radius:6px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:5px;transition:0.3s;}
.sort-btn:hover {background:#e9ecef;}
.sort-btn.active {background:#1e90ff;color:white;border-color:#1e90ff;}

/* Table Styles */
.table-container{background:white;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.users-table{width:100%;border-collapse:collapse;}
.users-table th, .users-table td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;}
.users-table th{background:#f8f9fa;font-weight:600;color:#495057;position:relative;cursor:pointer;}
.users-table th:hover {background:#e9ecef;}
.users-table th i {margin-left:5px;font-size:12px;opacity:0.5;}
.users-table th:hover i {opacity:1;}
.users-table th.sort-asc i, .users-table th.sort-desc i {opacity:1;color:#1e90ff;}
.users-table tr:hover{background:#f8f9fa;}
.users-table tr:last-child td{border-bottom:none;}

/* Badge Styles */
.role-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.role-badge.admin{background:#d4edda;color:#155724;}
.role-badge.user{background:#fff3cd;color:#856404;}
.course-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#e7f3ff;color:#0066cc;display:inline-block;}
.category-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#fff0e6;color:#d35400;display:inline-block;}
.year-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#f0f8ff;color:#663399;display:inline-block;}

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
    .users-table{min-width:1200px;}
    .header{flex-wrap:wrap;gap:10px;}
    .filter-row {grid-template-columns:1fr;gap:10px;}
    .sort-controls {flex-direction:column;align-items:stretch;}
    .sort-btn {justify-content:center;}
}

/* Form Grid */
.form-grid {display: grid;grid-template-columns: 1fr 1fr;gap: 15px;margin-bottom: 15px;}
.form-group {margin-bottom: 15px;}
.form-group label {display: block;margin-bottom: 5px;font-weight: 500;}
.form-group input, .form-group select {width: 100%;padding: 8px;border: 1px solid #ccc;border-radius: 5px;}
.form-group.full-width {grid-column: 1 / -1;}

/* Custom Date Input */
.date-input-container {position: relative;}
.date-input-container input {padding-right: 35px !important;}
.date-input-container .calendar-icon {position: absolute;right: 10px;top: 50%;transform: translateY(-50%);color: #666;cursor: pointer;z-index: 10;}
.date-input-container .calendar-icon:hover {color: #1e90ff;}

/* Age Display */
.age-display {font-size: 12px;color: #666;margin-top: 5px;font-style: italic;}

/* Date Quick Select */
.date-quick-select {display: flex;gap: 5px;margin-top: 5px;}
.date-quick-btn {padding: 3px 8px;font-size: 11px;background: #f0f0f0;border: 1px solid #ddd;border-radius: 3px;cursor: pointer;}
.date-quick-btn:hover {background: #e0e0e0;}

/* Stats Badge */
.stats-badge {display:inline-block;background:#f8f9fa;padding:5px 10px;border-radius:20px;font-size:13px;color:#495057;margin-left:10px;}
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
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h1><i class="fas fa-users"></i> Manage Users</h1>
                <p>View and manage all system users <span class="stats-badge">Total Users: <?= count($users) ?></span></p>
            </div>
            <button class="add-user-btn" onclick="showAddUserModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
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

    <!-- Filters Section -->
    <div class="filters-container">
        <div class="filters-header">
            <h2><i class="fas fa-filter"></i> Filter & Search Users</h2>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter_course">Course</label>
                    <select name="filter_course" id="filter_course">
                        <option value="">All Courses</option>
                        <?php foreach($courseOptions as $course): ?>
                            <option value="<?= $course ?>" <?= $filterCourse == $course ? 'selected' : '' ?>>
                                <?= $course ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_category">Category</label>
                    <select name="filter_category" id="filter_category">
                        <option value="">All Categories</option>
                        <?php foreach($categoryOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filterCategory == $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_year">Year</label>
                    <select name="filter_year" id="filter_year">
                        <option value="">All Years</option>
                        <?php foreach($yearOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filterYear == $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group search-box">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Search by name, email, student ID..." value="<?= htmlspecialchars($searchQuery) ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn apply">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button type="button" class="filter-btn reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
            <input type="hidden" name="sort" id="sortInput" value="<?= htmlspecialchars($sortBy) ?>">
        </form>
    </div>

    <!-- Sort Controls -->
    <div class="sort-controls">
        <span class="sort-label">Sort by:</span>
        <button class="sort-btn <?= $sortBy == 'created_at_desc' ? 'active' : '' ?>" onclick="setSort('created_at_desc')">
            <i class="fas fa-sort-amount-down"></i> Newest First
        </button>
        <button class="sort-btn <?= $sortBy == 'created_at_asc' ? 'active' : '' ?>" onclick="setSort('created_at_asc')">
            <i class="fas fa-sort-amount-up"></i> Oldest First
        </button>
        <button class="sort-btn <?= $sortBy == 'name_asc' ? 'active' : '' ?>" onclick="setSort('name_asc')">
            <i class="fas fa-sort-alpha-down"></i> Name A-Z
        </button>
        <button class="sort-btn <?= $sortBy == 'name_desc' ? 'active' : '' ?>" onclick="setSort('name_desc')">
            <i class="fas fa-sort-alpha-up"></i> Name Z-A
        </button>
        <button class="sort-btn <?= $sortBy == 'course_asc' ? 'active' : '' ?>" onclick="setSort('course_asc')">
            <i class="fas fa-sort-alpha-down"></i> Course A-Z
        </button>
        <button class="sort-btn <?= $sortBy == 'year_asc' ? 'active' : '' ?>" onclick="setSort('year_asc')">
            <i class="fas fa-sort-numeric-down"></i> Year Low-High
        </button>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th onclick="sortTable('id')">ID <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('name')">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('email')">Email <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('student_id')">Student ID <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('course')">Course <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('category')">Category <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('year')">Year <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('birthdate')">Birthdate <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('age')">Age <i class="fas fa-sort"></i></th>
                    <th>Contact</th>
                    <th onclick="sortTable('created_at')">Registered <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                <tr>
                    <td colspan="12" style="text-align:center;padding:30px;color:#666;">
                        <?php if(!empty($searchQuery) || !empty($filterCourse) || !empty($filterCategory) || !empty($filterYear)): ?>
                            No users found matching your filters. <a href="manage_user.php" style="color:#1e90ff;text-decoration:underline;">Clear filters</a>
                        <?php else: ?>
                            No users found. Click "Add New User" to add users.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($users as $user): 
                    $age = '';
                    if (!empty($user['birthdate'])) {
                        $birthDate = new DateTime($user['birthdate']);
                        $today = new DateTime();
                        $age = $today->diff($birthDate)->y;
                    }
                ?>
                <tr>
                    <td>#<?= htmlspecialchars($user['user_id']) ?></td>
                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></td>
                    <td>
                        <span class="course-badge">
                            <?= htmlspecialchars($user['course'] ?? 'N/A') ?>
                        </span>
                    </td>
                    <td>
                        <span class="category-badge">
                            <?= isset($user['category']) && isset($categoryOptions[$user['category']]) ? htmlspecialchars($categoryOptions[$user['category']]) : 'N/A' ?>
                        </span>
                    </td>
                    <td>
                        <span class="year-badge">
                            <?= isset($user['year']) && isset($yearOptions[$user['year']]) ? htmlspecialchars($yearOptions[$user['year']]) : 'N/A' ?>
                        </span>
                    </td>
                    <td><?= !empty($user['birthdate']) ? date('M d, Y', strtotime($user['birthdate'])) : 'N/A' ?></td>
                    <td><?= !empty($age) ? $age . ' years' : 'N/A' ?></td>
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
    <div style="background:white;padding:30px;border-radius:10px;width:650px;max-width:90%;max-height:90%;overflow-y:auto;">
        <h2 id="modalTitle">Add New User</h2>
        <form id="userForm" method="POST">
            <input type="hidden" id="userId" name="user_id">
            <input type="hidden" id="formAction" name="add_user" value="1">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                    <small style="color:#666;font-size:12px;">Password will be set to: <strong id="passwordPreview"></strong></small>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Student ID (Optional)</label>
                    <input type="text" name="student_id">
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Course *</label>
                    <select name="course" required>
                        <?php foreach($courseOptions as $course): ?>
                            <option value="<?= $course ?>" <?= $course == 'bet-CpEt' ? 'selected' : '' ?>>
                                <?= $course ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <?php foreach($categoryOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $value == 'student' ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Year *</label>
                    <select name="year" required>
                        <?php foreach($yearOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $value == '1' ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role_id" required>
                        <option value="2">User</option>
                        <option value="1">Admin</option>
                    </select>
                </div>
            </div>
            
            <!-- Birthdate with Enhanced Picker -->
            <div class="form-group full-width">
                <label>Birthdate (Optional)</label>
                <div class="date-input-container">
                    <input type="text" name="birthdate" id="birthdateInput" placeholder="Select birthdate" readonly>
                    <i class="fas fa-calendar calendar-icon" id="calendarToggle"></i>
                </div>
                <div id="ageDisplay" class="age-display"></div>
                <div class="date-quick-select">
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2000-01-01')">Jan 1, 2000</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2001-01-01')">Jan 1, 2001</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2002-01-01')">Jan 1, 2002</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2003-01-01')">Jan 1, 2003</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2004-01-01')">Jan 1, 2004</button>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Contact Info (Optional)</label>
                    <input type="text" name="contact_info">
                </div>
                
                <div class="form-group">
                    <label>Contact Number (Optional)</label>
                    <input type="text" name="contact_number">
                </div>
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

<!-- Include Flatpickr JS for enhanced date picker -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- JavaScript -->
<script>
// ===== ENHANCED DATE PICKER =====
let datePicker;

function initDatePicker() {
    const today = new Date();
    const maxDate = new Date();
    maxDate.setFullYear(today.getFullYear() - 10); // Minimum age 10 years
    
    // Configure date picker
    datePicker = flatpickr("#birthdateInput", {
        dateFormat: "Y-m-d",
        maxDate: maxDate,
        minDate: "1900-01-01",
        defaultDate: "2004-01-01", // Default to 2004
        onChange: function(selectedDates, dateStr, instance) {
            calculateAge(dateStr);
        }
    });
    
    // Add calendar icon click handler
    document.getElementById('calendarToggle').addEventListener('click', function() {
        datePicker.open();
    });
}

function calculateAge(birthdate) {
    if (!birthdate) {
        document.getElementById('ageDisplay').textContent = '';
        return;
    }
    
    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    document.getElementById('ageDisplay').textContent = `Age: ${age} years`;
}

function setQuickDate(dateString) {
    if (datePicker) {
        datePicker.setDate(dateString, true);
        calculateAge(dateString);
    }
}

// ===== PASSWORD PREVIEW =====
function updatePasswordPreview() {
    const lastNameInput = document.querySelector('[name="last_name"]');
    const passwordPreview = document.getElementById('passwordPreview');
    
    if (lastNameInput && passwordPreview) {
        lastNameInput.addEventListener('input', function() {
            const lastName = this.value.trim().toUpperCase();
            passwordPreview.textContent = lastName || '[LAST NAME]';
        });
        
        // Initial preview
        const initialLastName = lastNameInput.value.trim().toUpperCase();
        passwordPreview.textContent = initialLastName || '[LAST NAME]';
    }
}

// ===== FILTER AND SORT FUNCTIONS =====
function setSort(sortType) {
    document.getElementById('sortInput').value = sortType;
    document.getElementById('filterForm').submit();
}

function resetFilters() {
    window.location.href = 'manage_user.php';
}

function sortTable(column) {
    let currentSort = '<?= $sortBy ?>';
    let newSort = '';
    
    switch(column) {
        case 'name':
            newSort = currentSort === 'name_asc' ? 'name_desc' : 'name_asc';
            break;
        case 'course':
            newSort = currentSort === 'course_asc' ? 'course_desc' : 'course_asc';
            break;
        case 'year':
            newSort = currentSort === 'year_asc' ? 'year_desc' : 'year_asc';
            break;
        case 'created_at':
            newSort = currentSort === 'created_at_asc' ? 'created_at_desc' : 'created_at_asc';
            break;
        default:
            newSort = 'created_at_desc';
    }
    
    document.getElementById('sortInput').value = newSort;
    document.getElementById('filterForm').submit();
}

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
    window.courseOptions = <?= json_encode($courseOptions) ?>;
    window.categoryOptions = <?= json_encode($categoryOptions) ?>;
    window.yearOptions = <?= json_encode($yearOptions) ?>;
    
    // Initialize enhanced date picker
    initDatePicker();
    
    // Initialize password preview
    updatePasswordPreview();
    
    // Setup modal event listeners
    setupModalEvents();
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

// ===== USER MANAGEMENT FUNCTIONS =====
function showAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('formAction').name = 'add_user';
    document.getElementById('formAction').value = '1';
    document.getElementById('ageDisplay').textContent = '';
    
    // Reset date picker to 2004
    if (datePicker) {
        datePicker.setDate('2004-01-01', true);
        calculateAge('2004-01-01');
    }
    
    // Update password preview
    updatePasswordPreview();
    
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
        
        // Set course
        const courseSelect = document.querySelector('[name="course"]');
        if (courseSelect) {
            courseSelect.value = user.course || 'bet-CpEt';
        }
        
        // Set category
        const categorySelect = document.querySelector('[name="category"]');
        if (categorySelect) {
            categorySelect.value = user.category || 'student';
        }
        
        // Set year
        const yearSelect = document.querySelector('[name="year"]');
        if (yearSelect) {
            yearSelect.value = user.year || '1';
        }
        
        // Set birthdate
        const birthdateInput = document.getElementById('birthdateInput');
        if (birthdateInput && user.birthdate) {
            const birthdate = user.birthdate.split(' ')[0]; // Get only date part
            birthdateInput.value = birthdate;
            if (datePicker) {
                datePicker.setDate(birthdate, true);
            }
            calculateAge(birthdate);
        } else {
            birthdateInput.value = '';
            document.getElementById('ageDisplay').textContent = '';
        }
        
        document.querySelector('[name="role_id"]').value = user.role_id;
        document.querySelector('[name="contact_info"]').value = user.contact_info || '';
        document.querySelector('[name="contact_number"]').value = user.contact_number || '';
        
        // Change form action to edit
        document.getElementById('formAction').name = 'edit_user';
        document.getElementById('formAction').value = '1';
        
        document.getElementById('userModal').style.display = 'flex';
    }
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

/* Flatpickr custom styles */
.flatpickr-calendar {
    font-family: Arial, sans-serif !important;
}
.flatpickr-day.selected {
    background: #1e90ff !important;
    border-color: #1e90ff !important;
}
.flatpickr-day.today {
    border-color: #1e90ff !important;
}
`;
document.head.appendChild(style);

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in manage_user.php:');
    console.log('- initDatePicker:', typeof initDatePicker);
    console.log('- calculateAge:', typeof calculateAge);
    console.log('- setQuickDate:', typeof setQuickDate);
    console.log('- updatePasswordPreview:', typeof updatePasswordPreview);
    console.log('- setSort:', typeof setSort);
    console.log('- resetFilters:', typeof resetFilters);
}, 1000);
</script>
</body>
</html>