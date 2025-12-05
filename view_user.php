<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

$userId = $_GET['id'] ?? 0;

try {
    // Get admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("User not found!");
    }
    
} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View User - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Add your existing admin styles here */
body {
    background: #f4f6fa;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.back-btn {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    margin-bottom: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.user-details {
    margin-top: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.detail-label {
    font-weight: bold;
    width: 150px;
    color: #495057;
}

.detail-value {
    flex: 1;
    color: #333;
}

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-admin {
    background: #d4edda;
    color: #155724;
}

.badge-user {
    background: #fff3cd;
    color: #856404;
}
</style>
</head>
<body>

<button class="back-btn" onclick="window.history.back()">
    <i class="fas fa-arrow-left"></i> Back to Users
</button>

<div class="container">
    <h1><i class="fas fa-user"></i> User Details</h1>
    
    <div class="user-details">
        <div class="detail-row">
            <div class="detail-label">User ID:</div>
            <div class="detail-value">#<?= htmlspecialchars($user['user_id']) ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Full Name:</div>
            <div class="detail-value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Email:</div>
            <div class="detail-value"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Student ID:</div>
            <div class="detail-value"><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Role:</div>
            <div class="detail-value">
                <span class="badge <?= $user['role_id'] == 1 ? 'badge-admin' : 'badge-user' ?>">
                    <?= $user['role_id'] == 1 ? 'Admin' : 'User' ?>
                </span>
            </div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Contact Number:</div>
            <div class="detail-value"><?= htmlspecialchars($user['contact_number'] ?? 'N/A') ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Contact Info:</div>
            <div class="detail-value"><?= htmlspecialchars($user['contact_info'] ?? 'N/A') ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Registered:</div>
            <div class="detail-value"><?= date('M d, Y h:i A', strtotime($user['created_at'])) ?></div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Last Updated:</div>
            <div class="detail-value"><?= !empty($user['updated_at']) ? date('M d, Y h:i A', strtotime($user['updated_at'])) : 'Never' ?></div>
        </div>
    </div>
    
    <div style="margin-top: 30px; display: flex; gap: 10px;">
        <button class="back-btn" onclick="window.location.href='manage_user.php'">
            <i class="fas fa-users"></i> Back to All Users
        </button>
        
        <button class="back-btn" style="background: #1e90ff;" onclick="window.location.href='manage_user.php?edit=<?= $user['user_id'] ?>'">
            <i class="fas fa-edit"></i> Edit This User
        </button>
    </div>
</div>

<script>
// Basic sidebar state save
function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}
</script>

</body>
</html>
