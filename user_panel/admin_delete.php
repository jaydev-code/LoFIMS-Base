<?php
// /var/www/html/LoFIMS_BASE/admin_panel/admin_delete.php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Get parameters from URL
$type = $_GET['type'] ?? ''; // 'lost' or 'found'
$id = (int)($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';

// Validate parameters
if (!in_array($type, ['lost', 'found']) || $id <= 0) {
    $_SESSION['error'] = "Invalid item type or ID";
    header("Location: dashboard.php");
    exit();
}

// Get item details for confirmation
try {
    if ($type === 'lost') {
        $stmt = $pdo->prepare("
            SELECT li.*, u.first_name, u.last_name, u.email, ic.category_name
            FROM lost_items li
            LEFT JOIN users u ON li.user_id = u.user_id
            LEFT JOIN item_categories ic ON li.category_id = ic.category_id
            WHERE li.lost_id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT fi.*, u.first_name, u.last_name, u.email, ic.category_name
            FROM found_items fi
            LEFT JOIN users u ON fi.user_id = u.user_id
            LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
            WHERE fi.found_id = ?
        ");
    }
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $_SESSION['error'] = "Item not found or already deleted";
        header("Location: dashboard.php");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching item in admin_delete.php: " . $e->getMessage());
    die("Error retrieving item details. Please try again.");
}

// Handle deletion when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete related claims first
        if ($type === 'lost') {
            $claimsStmt = $pdo->prepare("DELETE FROM claims WHERE lost_id = ?");
        } else {
            $claimsStmt = $pdo->prepare("DELETE FROM claims WHERE found_id = ?");
        }
        $claimsStmt->execute([$id]);
        
        // Delete the item itself
        if ($type === 'lost') {
            $deleteStmt = $pdo->prepare("DELETE FROM lost_items WHERE lost_id = ?");
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM found_items WHERE found_id = ?");
        }
        $result = $deleteStmt->execute([$id]);
        
        // Delete associated photo if exists
        if (!empty($item['photo'])) {
            $uploadDir = $type === 'lost' 
                ? '/var/www/html/LoFIMS_BASE/uploads/lost_items/'
                : '/var/www/html/LoFIMS_BASE/uploads/found_items/';
            
            $photoPath = $uploadDir . $item['photo'];
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }
        
        // Log the action to admin_logs (create table if not exists)
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address, created_at) 
                VALUES (?, 'DELETE', ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $type . '_item',
                $id,
                "Deleted {$type} item: '" . $item['item_name'] . "' (ID: {$id})",
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
        } catch(PDOException $logError) {
            // If admin_logs table doesn't exist, just continue
            error_log("Failed to log admin action: " . $logError->getMessage());
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = ucfirst($type) . " item deleted successfully!";
        header("Location: dashboard.php");
        exit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Database error: " . $e->getMessage();
        error_log("Error deleting item in admin_delete.php: " . $e->getMessage());
    }
}

// Generate CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete <?= ucfirst($type) ?> Item - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Use existing sidebar from admin panel */
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
        body{background:#f4f6fa;display:flex;min-height:100vh;color:#333;}
        
        .main {
            margin-left: 220px;
            padding: 20px;
            flex: 1;
            transition: 0.3s;
            min-height: 100vh;
            max-width: calc(100% - 220px);
            width: 100%;
        }
        
        .confirmation-box {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-body {
            padding: 30px;
        }
        .warning-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #ff9500, #ff5e3a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .warning-icon i {
            font-size: 36px;
            color: white;
        }
        .item-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .detail-item {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }
        .detail-label {
            font-weight: 600;
            min-width: 120px;
            color: #495057;
        }
        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #e9ecef;
        }
        .btn {
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
            text-decoration: none;
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
        .btn-danger {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: white;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #ff2b53, #ff341b);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
        }
        
        @media(max-width: 900px){
            .main {
                margin-left: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>
<?php 
// Include admin sidebar
require_once 'includes/sidebar.php'; 
?>

<div class="main">
    <div class="confirmation-box">
        <div class="modal-header">
            <i class="fas fa-trash-alt"></i>
            <h2>Delete <?= ucfirst($type) ?> Item</h2>
        </div>
        
        <div class="modal-body">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h3 style="text-align:center;color:#dc3545;margin-bottom:20px;">
                ⚠️ WARNING: Permanent Deletion
            </h3>
            
            <p style="text-align:center;color:#495057;margin-bottom:25px;">
                You are about to permanently delete this <?= $type ?> item.<br>
                <strong>This action cannot be undone!</strong>
            </p>
            
            <div class="item-details">
                <h4 style="margin-bottom:15px;color:#495057;">
                    <i class="fas fa-info-circle"></i> Item Details
                </h4>
                
                <div class="detail-item">
                    <span class="detail-label">Item Name:</span>
                    <span><?= htmlspecialchars($item['item_name']) ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Category:</span>
                    <span><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Reported By:</span>
                    <span><?= htmlspecialchars(($item['first_name'] ?? 'Unknown') . ' ' . ($item['last_name'] ?? '')) ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span><?= htmlspecialchars($item['email'] ?? 'N/A') ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Date Created:</span>
                    <span><?= date('M d, Y H:i', strtotime($item['created_at'])) ?></span>
                </div>
                
                <?php if(!empty($item['photo'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Has Photo:</span>
                    <span style="color:#dc3545;">Yes (photo file will also be deleted)</span>
                </div>
                <?php endif; ?>
                
                <?php 
                // Get related claims count
                $claimsCount = 0;
                try {
                    if ($type === 'lost') {
                        $claimsStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE lost_id = ?");
                    } else {
                        $claimsStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE found_id = ?");
                    }
                    $claimsStmt->execute([$id]);
                    $claimsCount = $claimsStmt->fetchColumn();
                } catch(PDOException $e) {
                    $claimsCount = 0;
                }
                ?>
                
                <?php if($claimsCount > 0): ?>
                <div class="detail-item">
                    <span class="detail-label" style="color:#dc3545;">Related Claims:</span>
                    <span style="color:#dc3545;">
                        <?= $claimsCount ?> claim(s) will also be deleted
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if(isset($error)): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:5px;margin-top:20px;">
                <i class="fas fa-exclamation-circle"></i> 
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="modal-footer">
            <a href="dashboard.php" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            
            <form method="POST" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Permanently
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Prevent accidental form submission
document.querySelector('form').addEventListener('submit', function(e) {
    if (!confirm('Are you absolutely sure? This cannot be undone!')) {
        e.preventDefault();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'dashboard.php';
    }
});
</script>
</body>
</html>