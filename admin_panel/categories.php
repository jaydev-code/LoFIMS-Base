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

    // Get all categories
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Count items per category - SIMPLE VERSION
    $categoryStats = $pdo->query("
        SELECT 
            c.category_id,
            c.category_name,
            (SELECT COUNT(*) FROM lost_items WHERE category_id = c.category_id) as lost_count,
            (SELECT COUNT(*) FROM found_items WHERE category_id = c.category_id) as found_count
        FROM item_categories c
        ORDER BY c.category_name
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $categoryName = trim($_POST['category_name']);
        
        if (!empty($categoryName)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO item_categories (category_name) VALUES (?)");
                $stmt->execute([$categoryName]);
                header("Location: categories.php?success=added");
                exit();
            } catch(PDOException $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['edit_category'])) {
        $categoryId = $_POST['category_id'];
        $categoryName = trim($_POST['category_name']);
        
        if (!empty($categoryName)) {
            try {
                $stmt = $pdo->prepare("UPDATE item_categories SET category_name = ? WHERE category_id = ?");
                $stmt->execute([$categoryName, $categoryId]);
                header("Location: categories.php?success=updated");
                exit();
            } catch(PDOException $e) {
                $error = "Error updating category: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $categoryId = $_POST['category_id'];
        
        try {
            // Check if category is being used
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM lost_items WHERE category_id = ?
                UNION ALL
                SELECT COUNT(*) as count FROM found_items WHERE category_id = ?
            ");
            $checkStmt->execute([$categoryId, $categoryId]);
            $results = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalUsage = $results[0]['count'] + $results[1]['count'];
            
            if ($totalUsage > 0) {
                $error = "Cannot delete category. It is being used by $totalUsage item(s).";
            } else {
                $stmt = $pdo->prepare("DELETE FROM item_categories WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                header("Location: categories.php?success=deleted");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories - LoFIMS Admin</title>
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

/* Categories Grid */
.categories-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:20px;margin-bottom:30px;}
.category-card{background:white;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition:0.3s;}
.category-card:hover{transform:translateY(-5px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.category-card h3{color:#1e2a38;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #f0f0f0;}
.category-stats{display:flex;gap:15px;margin-top:15px;}
.stat-item{flex:1;text-align:center;padding:10px;background:#f8f9fa;border-radius:8px;}
.stat-number{font-size:24px;font-weight:bold;color:#1e90ff;}
.stat-label{font-size:12px;color:#666;margin-top:5px;}

/* Form Styles */
.form-group{margin-bottom:15px;}
.form-group label{display:block;margin-bottom:5px;font-weight:500;color:#495057;}
.form-group input{width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;}
.form-group input:focus{outline:none;border-color:#1e90ff;box-shadow:0 0 0 3px rgba(30,144,255,0.1);}
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

/* Table Styles */
.categories-table{width:100%;border-collapse:collapse;background:white;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.categories-table th, .categories-table td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;}
.categories-table th{background:#f8f9fa;font-weight:600;color:#495057;}
.categories-table tr:hover{background:#f8f9fa;}
.categories-table tr:last-child td{border-bottom:none;}

/* Modal Styles */
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;}
.modal-content{background:white;padding:30px;border-radius:10px;width:500px;max-width:90%;max-height:90%;overflow-y:auto;}
.modal-header{margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #eee;}
.modal-header h2{margin:0;color:#1e2a38;}

/* Success/Error Messages */
.alert{padding:12px 15px;border-radius:6px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.alert-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}.sidebar.show{left:0;}.main{margin-left:0;padding:15px;}
    .categories-grid{grid-template-columns:1fr;}
}
@media(max-width:768px){
    .header{flex-wrap:wrap;gap:10px;}
    .search-bar{width:100%;}
    .categories-table{display:block;overflow-x:auto;}
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
            <input type="text" id="categorySearch" placeholder="Search categories...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-tags"></i> Item Categories</h1>
        <p>Manage categories for lost and found items</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php 
        switch($_GET['success']) {
            case 'added': echo "Category added successfully!"; break;
            case 'updated': echo "Category updated successfully!"; break;
            case 'deleted': echo "Category deleted successfully!"; break;
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

    <!-- Add Category Button -->
    <button class="btn btn-primary" onclick="showAddModal()" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> Add New Category
    </button>

    <!-- Categories Grid View -->
    <div class="categories-grid">
        <?php foreach($categoryStats as $stat): ?>
        <div class="category-card">
            <h3><?= htmlspecialchars($stat['category_name']) ?></h3>
            <p>Category ID: #<?= $stat['category_id'] ?></p>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number"><?= $stat['lost_count'] ?></div>
                    <div class="stat-label">Lost Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stat['found_count'] ?></div>
                    <div class="stat-label">Found Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stat['lost_count'] + $stat['found_count'] ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 15px;">
                <button class="btn btn-primary btn-sm" onclick="editCategory(<?= $stat['category_id'] ?>, '<?= htmlspecialchars($stat['category_name']) ?>')">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteCategory(<?= $stat['category_id'] ?>, '<?= htmlspecialchars($stat['category_name']) ?>')">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Categories Table View -->
    <div class="table-container" style="margin-top: 30px;">
        <table class="categories-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Lost Items</th>
                    <th>Found Items</th>
                    <th>Total Items</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($categoryStats)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:30px;color:#666;">No categories found</td>
                </tr>
                <?php else: ?>
                <?php foreach($categoryStats as $stat): ?>
                <tr>
                    <td>#<?= $stat['category_id'] ?></td>
                    <td><?= htmlspecialchars($stat['category_name']) ?></td>
                    <td><?= $stat['lost_count'] ?></td>
                    <td><?= $stat['found_count'] ?></td>
                    <td><strong><?= $stat['lost_count'] + $stat['found_count'] ?></strong></td>
                    <td>
                        <div class="action-buttons" style="display:flex;gap:5px;">
                            <button class="btn-icon edit" title="Edit" onclick="editCategory(<?= $stat['category_id'] ?>, '<?= htmlspecialchars($stat['category_name']) ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon delete" title="Delete" onclick="deleteCategory(<?= $stat['category_id'] ?>, '<?= htmlspecialchars($stat['category_name']) ?>')">
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

<!-- Add/Edit Category Modal -->
<div class="modal" id="categoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Category</h2>
        </div>
        <form id="categoryForm" method="POST">
            <input type="hidden" id="categoryId" name="category_id">
            <input type="hidden" id="formAction" name="add_category">
            
            <div class="form-group">
                <label for="categoryName">Category Name</label>
                <input type="text" id="categoryName" name="category_name" required maxlength="50" placeholder="Enter category name">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Category
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
            <h2>Delete Category</h2>
        </div>
        <div style="padding: 20px 0;">
            <p id="deleteMessage">Are you sure you want to delete this category?</p>
            <form id="deleteForm" method="POST" style="margin-top: 20px;">
                <input type="hidden" id="deleteCategoryId" name="category_id">
                <input type="hidden" name="delete_category" value="1">
                
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
<script src="assets/js/dashboard.js"></script>

<!-- Fallback JavaScript for categories.php -->
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
    console.log('Categories: Page loaded');
    
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
    const currentPage = window.location.pathname.split('/').pop() || 'categories.php';
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

// ===== CATEGORY MANAGEMENT FUNCTIONS =====
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('formAction').name = 'add_category';
    document.getElementById('categoryModal').style.display = 'flex';
}

function editCategory(id, name) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('formAction').name = 'edit_category';
    document.getElementById('categoryModal').style.display = 'flex';
}

function deleteCategory(id, name) {
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete the category "${name}"? This action cannot be undone.`;
    document.getElementById('deleteCategoryId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function viewCategory(id) {
    saveSidebarState();
    // Scroll to the category card
    const card = document.querySelector(`.category-card:has(button[onclick*="${id}"])`);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.style.boxShadow = '0 0 0 3px #1e90ff';
        setTimeout(() => card.style.boxShadow = '', 2000);
    }
    searchResults.style.display = 'none';
}

function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Category Search
const searchInput = document.getElementById('categorySearch');
const searchResults = document.querySelector('.search-results');

if (searchInput && searchResults) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        if(query.length < 1){ 
            searchResults.style.display='none'; 
            searchResults.innerHTML=''; 
            return; 
        }
        
        // Filter categories
        const categories = <?= json_encode($categoryStats) ?>;
        const filtered = categories.filter(cat => 
            cat.category_name.toLowerCase().includes(query)
        );
        
        if(filtered.length > 0){
            searchResults.innerHTML = filtered.map(cat => `
                <div class="result-item" onclick="viewCategory(${cat.category_id})">
                    <strong>${cat.category_name}</strong>
                    <small>(${cat.lost_count + cat.found_count} items)</small>
                </div>
            `).join('');
            searchResults.style.display='block';
        } else {
            searchResults.innerHTML = '<div class="result-item">No categories found</div>';
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
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if(e.target === this) closeDeleteModal();
});

// Button icon styles
const buttonStyle = document.createElement('style');
buttonStyle.textContent = `
.btn-sm { padding: 6px 12px; font-size: 14px; }
.btn-icon { background: none; border: none; color: #6c757d; cursor: pointer; padding: 5px; border-radius: 4px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
.btn-icon:hover { background: #f8f9fa; color: #495057; }
.btn-icon.edit:hover { color: #1e90ff; }
.btn-icon.delete:hover { color: #dc3545; }
`;
document.head.appendChild(buttonStyle);

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in categories.php:');
    console.log('- confirmLogout:', typeof confirmLogout);
    console.log('- saveSidebarState:', typeof saveSidebarState);
    
    if (typeof confirmLogout !== 'function') {
        console.error('confirmLogout function not found!');
    }
}, 1000);
</script>

</body>
</html>