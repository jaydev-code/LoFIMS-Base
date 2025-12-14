<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// CSRF Protection - Generate token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token for POST requests
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed in categories.php");
            die("Invalid request. Please try again.");
        }
    }
}

// Validation functions
function validateCategoryName($name) {
    $name = trim($name ?? '');
    $name = strip_tags($name);
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    
    // Validate length
    if (strlen($name) < 2 || strlen($name) > 50) {
        return false;
    }
    
    // Validate characters (alphanumeric, spaces, hyphens, parentheses)
    if (!preg_match('/^[a-zA-Z0-9\s\-\(\)\.\&]+$/', $name)) {
        return false;
    }
    
    return $name;
}

function validateCategoryId($id) {
    $id = (int)($id ?? 0);
    return $id > 0 ? $id : 0;
}

function sanitizeOutput($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($type, $message = '', $params = []) {
    $query = [];
    
    if ($type === 'success') {
        $query['success'] = $params['action'] ?? '';
        if (isset($params['name'])) {
            $query['name'] = urlencode($params['name']);
        }
        if (isset($params['id'])) {
            $query['id'] = $params['id'];
        }
    } elseif ($type === 'error') {
        $_SESSION['error_message'] = $message;
    }
    
    $queryString = http_build_query($query);
    header("Location: categories.php" . ($queryString ? "?$queryString" : ""));
    exit();
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        error_log("Admin not found in categories.php - user_id: " . $_SESSION['user_id']);
        die("Administrator account not found.");
    }

    // Get all categories with statistics in a single efficient query
    $sql = "SELECT 
                ic.category_id,
                ic.category_name,
                COALESCE(SUM(CASE WHEN li.lost_id IS NOT NULL THEN 1 ELSE 0 END), 0) as lost_count,
                COALESCE(SUM(CASE WHEN fi.found_id IS NOT NULL THEN 1 ELSE 0 END), 0) as found_count
            FROM item_categories ic
            LEFT JOIN lost_items li ON ic.category_id = li.category_id
            LEFT JOIN found_items fi ON ic.category_id = fi.category_id
            GROUP BY ic.category_id, ic.category_name
            ORDER BY ic.category_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalCategories = count($categories);
    $totalLostItems = array_sum(array_column($categories, 'lost_count'));
    $totalFoundItems = array_sum(array_column($categories, 'found_count'));

} catch(PDOException $e){
    error_log("Database error in categories.php: " . $e->getMessage());
    die("An error occurred while loading categories. Please try again later.");
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    validateCSRF();
    
    $categoryName = validateCategoryName($_POST['category_name'] ?? '');
    
    if (!$categoryName) {
        $_SESSION['error_message'] = "Invalid category name! Name must be 2-50 characters and contain only letters, numbers, spaces, hyphens, parentheses, dots, and ampersands.";
        header("Location: categories.php");
        exit();
    }
    
    try {
        // Check if category already exists (case-insensitive)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM item_categories WHERE LOWER(category_name) = LOWER(?)");
        $checkStmt->execute([$categoryName]);
        $categoryExists = $checkStmt->fetchColumn();
        
        if ($categoryExists > 0) {
            $_SESSION['error_message'] = "Category already exists!";
            header("Location: categories.php");
            exit();
        }
        
        // Add new category
        $stmt = $pdo->prepare("INSERT INTO item_categories (category_name) VALUES (?)");
        $stmt->execute([$categoryName]);
        
        redirectWithMessage('success', '', [
            'action' => 'added',
            'name' => $categoryName
        ]);
        
    } catch(PDOException $e) {
        error_log("Error adding category: " . $e->getMessage());
        $_SESSION['error_message'] = "Error adding category. Please try again.";
        header("Location: categories.php");
        exit();
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    validateCSRF();
    
    $categoryId = validateCategoryId($_POST['category_id'] ?? 0);
    $categoryName = validateCategoryName($_POST['category_name'] ?? '');
    
    if (!$categoryName) {
        $_SESSION['error_message'] = "Invalid category name! Name must be 2-50 characters and contain only letters, numbers, spaces, hyphens, parentheses, dots, and ampersands.";
        header("Location: categories.php");
        exit();
    }
    
    if ($categoryId <= 0) {
        $_SESSION['error_message'] = "Invalid category ID!";
        header("Location: categories.php");
        exit();
    }
    
    try {
        // Check if category already exists (excluding current)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM item_categories WHERE LOWER(category_name) = LOWER(?) AND category_id != ?");
        $checkStmt->execute([$categoryName, $categoryId]);
        $categoryExists = $checkStmt->fetchColumn();
        
        if ($categoryExists > 0) {
            $_SESSION['error_message'] = "Category already exists!";
            header("Location: categories.php");
            exit();
        }
        
        // Verify category exists before update
        $verifyStmt = $pdo->prepare("SELECT category_id FROM item_categories WHERE category_id = ?");
        $verifyStmt->execute([$categoryId]);
        if (!$verifyStmt->fetch()) {
            $_SESSION['error_message'] = "Category not found!";
            header("Location: categories.php");
            exit();
        }
        
        // Update category
        $stmt = $pdo->prepare("UPDATE item_categories SET category_name = ? WHERE category_id = ?");
        $stmt->execute([$categoryName, $categoryId]);
        
        redirectWithMessage('success', '', [
            'action' => 'updated',
            'id' => $categoryId,
            'name' => $categoryName
        ]);
        
    } catch(PDOException $e) {
        error_log("Error updating category #$categoryId: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating category. Please try again.";
        header("Location: categories.php");
        exit();
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    validateCSRF();
    
    $categoryId = validateCategoryId($_POST['category_id'] ?? 0);
    
    if ($categoryId <= 0) {
        $_SESSION['error_message'] = "Invalid category ID!";
        header("Location: categories.php");
        exit();
    }
    
    try {
        // Get category name before deleting (for success message)
        $stmt = $pdo->prepare("SELECT category_name FROM item_categories WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $_SESSION['error_message'] = "Category not found!";
            header("Location: categories.php");
            exit();
        }
        
        $categoryName = $category['category_name'];
        
        // Check if category is in use with prepared statements
        $checkLost = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE category_id = ?");
        $checkLost->execute([$categoryId]);
        $lostCount = $checkLost->fetchColumn();
        
        $checkFound = $pdo->prepare("SELECT COUNT(*) FROM found_items WHERE category_id = ?");
        $checkFound->execute([$categoryId]);
        $foundCount = $checkFound->fetchColumn();
        
        $totalItems = $lostCount + $foundCount;
        
        if ($totalItems > 0) {
            $_SESSION['error_message'] = "Cannot delete category! It is being used by $totalItems item(s).";
            header("Location: categories.php");
            exit();
        }
        
        // Delete category
        $deleteStmt = $pdo->prepare("DELETE FROM item_categories WHERE category_id = ?");
        $deleteStmt->execute([$categoryId]);
        
        redirectWithMessage('success', '', [
            'action' => 'deleted',
            'name' => $categoryName
        ]);
        
    } catch(PDOException $e) {
        error_log("Error deleting category #$categoryId: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting category. Please try again.";
        header("Location: categories.php");
        exit();
    }
}

// Handle success messages
$successMessage = '';
if (isset($_GET['success'])) {
    $action = $_GET['success'] ?? '';
    $categoryName = isset($_GET['name']) ? urldecode($_GET['name']) : '';
    
    switch($action) {
        case 'added': 
            $successMessage = "✅ Category <strong>" . sanitizeOutput($categoryName) . "</strong> added successfully!"; 
            break;
        case 'updated': 
            $successMessage = "✅ Category <strong>" . sanitizeOutput($categoryName) . "</strong> updated successfully!"; 
            break;
        case 'deleted': 
            $successMessage = "✅ Category <strong>" . sanitizeOutput($categoryName) . "</strong> deleted successfully!"; 
            break;
    }
}

// Handle error messages from session
$error = '';
if (isset($_SESSION['error_message'])) {
    $error = sanitizeOutput($_SESSION['error_message']);
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories - LoFIMS Admin</title>
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

/* ===== Main Content ===== */
.main {
    margin-left: 220px;
    padding: 20px;
    flex: 1;
    transition: 0.3s;
    min-height: 100vh;
    max-width: calc(100% - 220px);
    width: 100%;
    overflow-x: hidden;
}

.sidebar.folded ~ .main {
    margin-left: 70px;
    max-width: calc(100% - 70px);
}

/* ===== Header ===== */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
    width: 100%;
}

.user-info {
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e2a38;
}

.user-info i {
    color: #1e90ff;
    font-size: 18px;
}

/* Page Header */
.page-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title h1 {
    color: #1e2a38;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.page-title p {
    color: #666;
    margin: 0;
}

/* Add Category Button */
.add-category-btn {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s;
    white-space: nowrap;
}

.add-category-btn:hover {
    background: #1c7ed6;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
}

/* Search bar */
.search-bar {
    position: relative;
    width: 250px;
    max-width: 100%;
    margin-bottom: 20px;
}

.search-bar input {
    width: 100%;
    padding: 10px 40px 10px 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
    outline: none;
    font-size: 14px;
}

.search-bar i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.category-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    border-left: 4px solid #1e90ff;
    display: flex;
    flex-direction: column;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.category-card h3 {
    color: #1e2a38;
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.category-card .category-id {
    color: #666;
    font-size: 12px;
    font-weight: normal;
    background: #f0f0f0;
    padding: 3px 10px;
    border-radius: 12px;
    margin-left: 5px;
}

/* Category Stats */
.category-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 15px;
    flex: 1;
}

.stat-item {
    text-align: center;
    padding: 12px 5px;
    border-radius: 8px;
    background: #f8f9fa;
    transition: 0.2s;
}

.stat-item:hover {
    background: #e9ecef;
}

.stat-item.lost {
    border-top: 3px solid #dc3545;
}

.stat-item.found {
    border-top: 3px solid #28a745;
}

.stat-item.total {
    border-top: 3px solid #1e90ff;
}

.stat-item h4 {
    font-size: 20px;
    color: #333;
    margin: 0;
    font-weight: 600;
}

.stat-item p {
    font-size: 12px;
    color: #666;
    margin: 5px 0 0;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: 0.3s;
    font-size: 14px;
    flex: 1;
    justify-content: center;
}

.btn-primary {
    background: #1e90ff;
    color: white;
}

.btn-primary:hover {
    background: #1c7ed6;
}

.btn-warning {
    background: #ffc107;
    color: #333;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

/* Empty State */
.empty-state {
    background: white;
    padding: 60px 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #666;
    margin-bottom: 10px;
    font-size: 20px;
}

.empty-state p {
    color: #999;
    margin: 0;
}

/* Modals for categories (not logout) */
#categoryModal,
#deleteModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 3000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* Styles for category modals only */
#categoryModal .modal-content,
#deleteModal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

#categoryModal .modal-content h2,
#deleteModal .modal-content h2 {
    color: #1e2a38;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 16px;
    transition: 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.2);
}

/* Success/Error Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    font-size: 18px;
}

/* Category Usage Warning */
.category-usage-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 12px 15px;
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #856404;
}

/* Stats Summary */
.stats-summary {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.stat-summary-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 200px;
}

.stat-summary-item i {
    font-size: 24px;
    color: #1e90ff;
}

.stat-summary-content h3 {
    font-size: 24px;
    color: #1e2a38;
    margin: 0;
}

.stat-summary-content p {
    font-size: 13px;
    color: #666;
    margin: 5px 0 0;
}

/* ===== LOGOUT MODAL - EXACT SAME AS OTHER PAGES ===== */
#logoutModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

/* Make logout modal content simpler like other pages */
#logoutModal > .modal-content {
    background: white;
    width: 90%;
    max-width: 420px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#logoutModal > .modal-content > .modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

#logoutModal > .modal-content > .modal-header i {
    font-size: 24px;
}

#logoutModal > .modal-content > .modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

#logoutModal > .modal-content > .modal-body {
    padding: 30px 25px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-body .warning-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ff9500, #ff5e3a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

#logoutModal > .modal-content > .modal-body .warning-icon i {
    font-size: 30px;
    color: white;
}

#logoutModal > .modal-content > .modal-body p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.5;
}

#logoutModal > .modal-content > .modal-body .logout-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    text-align: left;
}

#logoutModal > .modal-content > .modal-body .detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

#logoutModal > .modal-content > .modal-body .detail-item i {
    color: #667eea;
    width: 20px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel,
#logoutModal > .modal-content > .modal-footer .btn-logout {
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

#logoutModal > .modal-content > .modal-footer .btn-cancel {
    background: #f1f3f5;
    color: #495057;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

#logoutModal > .modal-content > .modal-footer .btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
}

#logoutModal > .modal-content > .modal-footer .btn-logout:hover {
    background: linear-gradient(135deg, #ff2b53, #ff341b);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
}

/* Responsive */
@media(max-width: 900px){
    .sidebar {
        left: -220px;
    }
    .sidebar.show {
        left: 0;
    }
    .main {
        margin-left: 0 !important;
        padding: 15px;
        max-width: 100% !important;
        width: 100% !important;
    }
    .categories-grid {
        grid-template-columns: 1fr;
    }
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    .add-category-btn {
        width: 100%;
        justify-content: center;
    }
    #logoutModal > .modal-content > .modal-footer {
        flex-direction: column;
    }
    #logoutModal > .modal-content > .modal-footer .btn-cancel,
    #logoutModal > .modal-content > .modal-footer .btn-logout {
        width: 100%;
        justify-content: center;
    }
}

@media(max-width: 768px){
    .header {
        flex-wrap: wrap;
        gap: 10px;
    }
    .search-bar {
        width: 100%;
    }
    .action-buttons {
        flex-direction: column;
    }
    .btn {
        width: 100%;
        justify-content: center;
    }
    .stats-summary {
        flex-direction: column;
    }
    .stat-summary-item {
        width: 100%;
    }
    #logoutModal > .modal-content {
        width: 95%;
        max-width: 95%;
    }
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
        
        <!-- CLAIMS NAVIGATION LINK -->
        <li onclick="saveSidebarState(); window.location.href='claims.php'">
            <i class="fas fa-handshake"></i><span>Manage Claims</span>
        </li>
        
        <li class="active">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        
        <!-- Updated Logout Button (same as other pages) -->
        <li id="logoutTrigger">
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
            <?= sanitizeOutput($admin['first_name'] . ' ' . $admin['last_name']) ?>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-tags"></i> Manage Categories</h1>
            <p>Organize lost and found items into categories</p>
        </div>
        <button class="add-category-btn" onclick="showAddCategoryModal()">
            <i class="fas fa-plus"></i> Add New Category
        </button>
    </div>

    <!-- Stats Summary -->
    <div class="stats-summary">
        <div class="stat-summary-item">
            <i class="fas fa-layer-group"></i>
            <div class="stat-summary-content">
                <h3 id="totalCategoriesCount"><?= $totalCategories ?></h3>
                <p>Total Categories</p>
            </div>
        </div>
        <div class="stat-summary-item">
            <i class="fas fa-search"></i>
            <div class="stat-summary-content">
                <h3 id="totalLostItems"><?= $totalLostItems ?></h3>
                <p>Total Lost Items</p>
            </div>
        </div>
        <div class="stat-summary-item">
            <i class="fas fa-check-circle"></i>
            <div class="stat-summary-content">
                <h3 id="totalFoundItems"><?= $totalFoundItems ?></h3>
                <p>Total Found Items</p>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <input type="text" id="searchCategories" placeholder="Search categories by name or ID...">
        <i class="fas fa-search"></i>
    </div>

    <!-- Success/Error Messages -->
    <?php if(!empty($successMessage)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $successMessage ?>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- Categories Grid -->
    <div class="categories-grid" id="categoriesContainer">
        <?php if(empty($categories)): ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <h3>No Categories Found</h3>
            <p>Start by adding your first category</p>
        </div>
        <?php else: ?>
        <?php foreach($categories as $category): 
            $lostCount = (int)($category['lost_count'] ?? 0);
            $foundCount = (int)($category['found_count'] ?? 0);
            $totalItems = $lostCount + $foundCount;
            $isUsed = ($totalItems > 0);
        ?>
        <div class="category-card" 
             data-category-id="<?= $category['category_id'] ?>" 
             data-category-name="<?= sanitizeOutput($category['category_name']) ?>"
             data-search-text="<?= sanitizeOutput(strtolower($category['category_name'] . ' ' . $category['category_id'])) ?>">
            <h3>
                <i class="fas fa-tag"></i>
                <?= sanitizeOutput($category['category_name']) ?>
                <span class="category-id">ID: <?= $category['category_id'] ?></span>
            </h3>
            
            <div class="category-stats">
                <div class="stat-item lost">
                    <h4><?= $lostCount ?></h4>
                    <p>Lost</p>
                </div>
                <div class="stat-item found">
                    <h4><?= $foundCount ?></h4>
                    <p>Found</p>
                </div>
                <div class="stat-item total">
                    <h4><?= $totalItems ?></h4>
                    <p>Total</p>
                </div>
            </div>
            
            <?php if($isUsed): ?>
            <div class="category-usage-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>This category contains <?= $totalItems ?> item(s)</span>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <button class="btn btn-warning" onclick="editCategory(<?= $category['category_id'] ?>, '<?= addslashes(sanitizeOutput($category['category_name'])) ?>')">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger" onclick="deleteCategory(<?= $category['category_id'] ?>, '<?= addslashes(sanitizeOutput($category['category_name'])) ?>', <?= $totalItems ?>)">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- EXACT SAME LOGOUT MODAL AS OTHER PAGES -->
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
                    <span>User: <?= sanitizeOutput($admin['first_name'] . ' ' . $admin['last_name']) ?></span>
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

<!-- Add/Edit Category Modal -->
<div class="modal" id="categoryModal">
    <div class="modal-content">
        <h2><i class="fas fa-tag"></i> <span id="modalTitle">Add New Category</span></h2>
        <form id="categoryForm" method="POST">
            <input type="hidden" id="categoryId" name="category_id">
            <input type="hidden" id="formAction" name="add_category" value="1">
            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($_SESSION['csrf_token'] ?? '') ?>">
            
            <div class="form-group">
                <label for="categoryName">Category Name *</label>
                <input type="text" id="categoryName" name="category_name" required 
                       placeholder="e.g., Electronics, Documents, Accessories"
                       maxlength="50"
                       pattern="[a-zA-Z0-9\s\-\(\)\.&]+"
                       title="Only letters, numbers, spaces, hyphens, parentheses, dots, and ampersands allowed">
                <small style="color:#666; font-size:12px; display:block; margin-top:5px;">
                    Enter a descriptive name for the category (2-50 characters, alphanumeric and basic symbols only)
                </small>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:30px;">
                <button type="submit" class="btn btn-primary" style="flex:1;" id="saveButton">
                    <i class="fas fa-save"></i> <span id="saveButtonText">Save Category</span>
                </button>
                <button type="button" class="btn" onclick="closeModal()" style="flex:1; background:#6c757d;color:white;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <h2><i class="fas fa-trash"></i> Delete Category</h2>
        <p id="deleteMessage">Are you sure you want to delete this category?</p>
        <div id="deleteWarning" style="display:none; background:#fff3cd; border:1px solid #ffeaa7; border-radius:6px; padding:12px; margin:15px 0; color:#856404;">
            <i class="fas fa-exclamation-triangle"></i> 
            <span id="warningText"></span>
        </div>
        <form id="deleteForm" method="POST" style="margin-top:20px;">
            <input type="hidden" id="deleteCategoryId" name="category_id">
            <input type="hidden" name="delete_category" value="1">
            <input type="hidden" name="csrf_token" value="<?= sanitizeOutput($_SESSION['csrf_token'] ?? '') ?>">
            
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-danger" style="flex:1;" id="deleteSubmitBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn" onclick="closeDeleteModal()" style="flex:1; background:#6c757d;color:white;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// CSRF token for JavaScript
const csrfToken = '<?= sanitizeOutput($_SESSION['csrf_token'] ?? '') ?>';

// ===== GLOBAL VARIABLES =====
let categoriesData = <?= json_encode($categories) ?>;
let currentSearchQuery = '';

// ===== LOGOUT MODAL FUNCTIONS (Same as other pages) =====
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

// ===== SIDEBAR FUNCTIONS =====
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
    console.log('✅ Categories page loaded');
    
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
    
    // Highlight active page
    highlightActivePage();
    
    // Basic sidebar toggle
    initSidebarToggle();
    
    // Initialize search
    initSearch();
    
    // Setup modal event handlers
    setupModalEvents();
    
    // Add animations to category cards
    animateCategoryCards();
    
    // Initialize logout modal
    initLogoutModal();
    
    // Auto-focus search if there are categories
    if (categoriesData.length > 5) {
        document.getElementById('searchCategories').focus();
    }
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

// ===== SEARCH FUNCTIONALITY =====
function initSearch() {
    const searchInput = document.getElementById('searchCategories');
    
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim().toLowerCase();
        currentSearchQuery = query;
        
        searchTimeout = setTimeout(() => {
            performCategorySearch(query);
        }, 300);
    });
    
    // Clear search on escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            currentSearchQuery = '';
            performCategorySearch('');
        }
        
        // Ctrl+F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            this.focus();
            this.select();
        }
    });
}

function performCategorySearch(query) {
    const categoryCards = document.querySelectorAll('.category-card');
    const categoriesContainer = document.getElementById('categoriesContainer');
    let foundAny = false;
    
    // If query is empty, show all with animation
    if (!query) {
        categoryCards.forEach((card, index) => {
            card.style.display = 'flex';
            card.style.animationDelay = `${index * 0.05}s`;
            card.classList.remove('search-hidden');
            card.classList.add('fade-in');
        });
        
        // Remove any search empty state
        const searchEmptyState = categoriesContainer.querySelector('.empty-state.search-empty');
        if (searchEmptyState) {
            searchEmptyState.remove();
        }
        
        // Show default empty state if no categories
        if (categoryCards.length === 0) {
            const defaultEmptyState = categoriesContainer.querySelector('.empty-state');
            if (defaultEmptyState) {
                defaultEmptyState.style.display = 'block';
            }
        }
        
        return;
    }
    
    // Search through categories
    categoryCards.forEach((card, index) => {
        const searchText = card.getAttribute('data-search-text') || '';
        const categoryName = card.querySelector('h3').textContent.toLowerCase();
        const categoryId = card.querySelector('.category-id').textContent.toLowerCase();
        
        if (searchText.includes(query) || categoryName.includes(query) || categoryId.includes(query)) {
            card.style.display = 'flex';
            card.style.animationDelay = `${index * 0.05}s`;
            card.classList.remove('search-hidden');
            card.classList.add('fade-in');
            foundAny = true;
        } else {
            card.style.display = 'none';
            card.classList.add('search-hidden');
        }
    });
    
    // Handle search empty state
    const defaultEmptyState = categoriesContainer.querySelector('.empty-state:not(.search-empty)');
    let searchEmptyState = categoriesContainer.querySelector('.empty-state.search-empty');
    
    if (!foundAny && query.length > 0) {
        // Hide default empty state
        if (defaultEmptyState) {
            defaultEmptyState.style.display = 'none';
        }
        
        // Create or update search empty state
        if (!searchEmptyState) {
            searchEmptyState = document.createElement('div');
            searchEmptyState.className = 'empty-state search-empty';
            categoriesContainer.appendChild(searchEmptyState);
        }
        
        searchEmptyState.innerHTML = `
            <i class="fas fa-search"></i>
            <h3>No Categories Found</h3>
            <p>No categories matching "${escapeHtml(query)}"</p>
            <button onclick="clearSearch()" style="margin-top:15px; padding:8px 16px; background:#1e90ff; color:white; border:none; border-radius:5px; cursor:pointer;">
                <i class="fas fa-times"></i> Clear Search
            </button>
        `;
        searchEmptyState.style.display = 'block';
    } else if (foundAny) {
        // Remove search empty state if it exists
        if (searchEmptyState) {
            searchEmptyState.remove();
        }
        // Show default empty state if it exists
        if (defaultEmptyState) {
            defaultEmptyState.style.display = 'block';
        }
    }
}

function clearSearch() {
    const searchInput = document.getElementById('searchCategories');
    searchInput.value = '';
    currentSearchQuery = '';
    performCategorySearch('');
    searchInput.focus();
}

// ===== CATEGORY MANAGEMENT FUNCTIONS =====
function showAddCategoryModal() {
    document.getElementById('modalTitle').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('formAction').name = 'add_category';
    document.getElementById('formAction').value = '1';
    document.getElementById('saveButtonText').textContent = 'Save Category';
    
    // Reset form validation
    const saveButton = document.getElementById('saveButton');
    saveButton.disabled = false;
    saveButton.innerHTML = '<i class="fas fa-save"></i> <span id="saveButtonText">Save Category</span>';
    
    document.getElementById('categoryModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Focus on input field
    setTimeout(() => {
        const input = document.getElementById('categoryName');
        input.focus();
        input.value = '';
    }, 100);
}

function editCategory(categoryId, categoryName) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = categoryId;
    document.getElementById('categoryName').value = categoryName;
    document.getElementById('formAction').name = 'edit_category';
    document.getElementById('formAction').value = '1';
    document.getElementById('saveButtonText').textContent = 'Update Category';
    
    // Reset form validation
    const saveButton = document.getElementById('saveButton');
    saveButton.disabled = false;
    saveButton.innerHTML = '<i class="fas fa-save"></i> <span id="saveButtonText">Update Category</span>';
    
    document.getElementById('categoryModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Focus on input field
    setTimeout(() => {
        const input = document.getElementById('categoryName');
        input.focus();
        input.select();
    }, 100);
}

function deleteCategory(categoryId, categoryName, itemCount) {
    const deleteModal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    const deleteWarning = document.getElementById('deleteWarning');
    const warningText = document.getElementById('warningText');
    const deleteSubmitBtn = document.getElementById('deleteSubmitBtn');
    
    document.getElementById('deleteCategoryId').value = categoryId;
    
    if (itemCount > 0) {
        // Category is in use - show warning and disable delete
        deleteMessage.textContent = `Cannot delete category "${categoryName}"`;
        warningText.textContent = `This category contains ${itemCount} item(s). Please reassign or delete these items first.`;
        deleteWarning.style.display = 'block';
        deleteSubmitBtn.disabled = true;
        deleteSubmitBtn.style.opacity = '0.5';
        deleteSubmitBtn.style.cursor = 'not-allowed';
        deleteSubmitBtn.innerHTML = '<i class="fas fa-ban"></i> Cannot Delete';
    } else {
        // Category is not in use - proceed with normal delete
        deleteMessage.textContent = `Are you sure you want to delete category "${categoryName}"? This action cannot be undone.`;
        deleteWarning.style.display = 'none';
        deleteSubmitBtn.disabled = false;
        deleteSubmitBtn.style.opacity = '1';
        deleteSubmitBtn.style.cursor = 'pointer';
        deleteSubmitBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    }
    
    deleteModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset delete modal state
    document.getElementById('deleteWarning').style.display = 'none';
    const deleteSubmitBtn = document.getElementById('deleteSubmitBtn');
    deleteSubmitBtn.disabled = false;
    deleteSubmitBtn.style.opacity = '1';
    deleteSubmitBtn.style.cursor = 'pointer';
    deleteSubmitBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
}

// ===== MODAL EVENT HANDLERS =====
function setupModalEvents() {
    // Close modals when clicking outside
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeModal();
        }
    });

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeDeleteModal();
        }
    });

    // Close modals with Escape key (including logout modal)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('categoryModal').style.display === 'flex') {
                closeModal();
            }
            if (document.getElementById('deleteModal').style.display === 'flex') {
                closeDeleteModal();
            }
            if (document.getElementById('logoutModal').style.display === 'flex') {
                closeLogoutModal();
            }
        }
    });

    // Form validation
    const categoryForm = document.getElementById('categoryForm');
    const categoryNameInput = document.getElementById('categoryName');
    
    if (categoryForm && categoryNameInput) {
        categoryForm.addEventListener('submit', function(e) {
            const categoryName = categoryNameInput.value.trim();
            const saveButton = document.getElementById('saveButton');
            
            if (!categoryName) {
                e.preventDefault();
                alert('Please enter a category name');
                categoryNameInput.focus();
                return false;
            }
            
            // Validate length
            if (categoryName.length < 2 || categoryName.length > 50) {
                e.preventDefault();
                alert('Category name must be between 2 and 50 characters');
                categoryNameInput.focus();
                categoryNameInput.select();
                return false;
            }
            
            // Validate pattern
            const pattern = /^[a-zA-Z0-9\s\-\(\)\.&]+$/;
            if (!pattern.test(categoryName)) {
                e.preventDefault();
                alert('Category name can only contain letters, numbers, spaces, hyphens, parentheses, dots, and ampersands');
                categoryNameInput.focus();
                categoryNameInput.select();
                return false;
            }
            
            // Disable button to prevent double submission
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            return true;
        });
        
        // Re-enable button if user changes input after error
        categoryNameInput.addEventListener('input', function() {
            const saveButton = document.getElementById('saveButton');
            if (saveButton.disabled) {
                saveButton.disabled = false;
                const action = document.getElementById('formAction').name;
                saveButton.innerHTML = action === 'add_category' 
                    ? '<i class="fas fa-save"></i> Save Category' 
                    : '<i class="fas fa-save"></i> Update Category';
            }
        });
    }
}

// ===== ANIMATIONS =====
function animateCategoryCards() {
    const cards = document.querySelectorAll('.category-card:not(.search-hidden)');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
        card.classList.add('fade-in');
    });
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.5s ease forwards;
    opacity: 0;
}

@keyframes slideIn {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.category-card:hover .stat-item {
    animation: slideIn 0.3s ease;
}

.category-card:hover .stat-item:nth-child(1) { animation-delay: 0.1s; }
.category-card:hover .stat-item:nth-child(2) { animation-delay: 0.2s; }
.category-card:hover .stat-item:nth-child(3) { animation-delay: 0.3s; }

/* Highlight animation for new/updated categories */
@keyframes highlightPulse {
    0% { box-shadow: 0 0 0 0 rgba(30, 144, 255, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(30, 144, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(30, 144, 255, 0); }
}

.category-highlight {
    animation: highlightPulse 2s;
}
`;
document.head.appendChild(style);

// ===== UTILITY FUNCTIONS =====
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== AUTO-SCROLL TO NEW/EDITED CATEGORY =====
function scrollToCategory(categoryId) {
    const card = document.querySelector(`.category-card[data-category-id="${categoryId}"]`);
    if (card) {
        // Remove search filter if active
        if (currentSearchQuery) {
            clearSearch();
        }
        
        // Scroll to card
        setTimeout(() => {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Add highlight animation
            card.classList.add('category-highlight');
            setTimeout(() => {
                card.classList.remove('category-highlight');
            }, 2000);
        }, 300);
    }
}

// Check if we should scroll to a specific category (after edit)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('success') && (urlParams.get('success') === 'updated' || urlParams.get('success') === 'added')) {
    if (urlParams.has('id')) {
        setTimeout(() => {
            scrollToCategory(urlParams.get('id'));
        }, 500);
    }
}

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('✅ Categories page functions loaded:');
    console.log('  - Categories loaded:', categoriesData.length);
    console.log('  - Search functionality:', typeof performCategorySearch);
    console.log('  - CRUD operations:', typeof showAddCategoryModal, typeof editCategory, typeof deleteCategory);
    console.log('  - Logout modal:', typeof initLogoutModal);
    
    // Test search
    const searchInput = document.getElementById('searchCategories');
    if (searchInput) {
        console.log('  - Search input available: ✓');
    }
}, 500);
</script>

</body>
</html>