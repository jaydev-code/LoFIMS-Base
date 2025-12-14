<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/notifications.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")
        ->execute([$_SESSION['user_id']]);
    header("Location: notifications.php");
    exit();
}

// Get all notifications for admin
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);

// Get admin info
$adminStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
$adminStmt->execute([$_SESSION['user_id']]);
$admin = $adminStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - LoFIMS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Use similar styles to claims.php */
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
        body{background:#f4f6fa;display:flex;min-height:100vh;overflow-x:hidden;color:#333;}
        
        .sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1e2a38;color:white;display:flex;flex-direction:column;z-index:1000;}
        .sidebar .logo{font-size:20px;font-weight:bold;text-align:center;padding:20px 0;background:#16212b;}
        .sidebar ul{list-style:none;padding:20px 0;flex:1;}
        .sidebar ul li{padding:15px 20px;cursor:pointer;display:flex;align-items:center;}
        .sidebar ul li:hover{background:#2c3e50;}
        .sidebar ul li.active{background:#2c3e50;border-left:3px solid #1e90ff;}
        .sidebar ul li i{margin-right:15px;}
        .sidebar ul li a{text-decoration:none;color:inherit;display:flex;align-items:center;width:100%;}
        
        .main{margin-left:220px;padding:20px;flex:1;min-height:100vh;}
        
        .header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .notifications-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: background 0.3s;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background: #f0f8ff;
            border-left: 4px solid #1e90ff;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: bold;
            color: #1e2a38;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: #666;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #888;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #1e90ff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1c7ed6;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="manage_user.php"><i class="fas fa-users"></i> Manage Users</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="manage_items.php"><i class="fas fa-boxes"></i> Manage Items</a></li>
        <li><a href="claims.php"><i class="fas fa-handshake"></i> Manage Claims</a></li>
        <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li class="active"><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
        <li><a href="auth/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <!-- Notifications Container -->
    <div class="notifications-container">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <?php if($unreadCount > 0): ?>
            <button class="btn btn-primary" onclick="markAllAsRead()">
                <i class="fas fa-check-double"></i> Mark All as Read
            </button>
            <?php endif; ?>
        </div>
        
        <?php if(empty($notifications)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>No notifications yet</h3>
            <p>Notifications will appear here when users submit claims or interact with the system.</p>
        </div>
        <?php else: ?>
        <div id="notificationsList">
            <?php foreach($notifications as $notification): ?>
            <div class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>" 
                 data-id="<?= $notification['notification_id'] ?>">
                <div class="notification-icon">
                    <?php switch($notification['type']): 
                        case 'claim_approved': ?>
                            <i class="fas fa-check-circle" style="color:#28a745;"></i>
                            <?php break; ?>
                        <?php case 'claim_rejected': ?>
                            <i class="fas fa-times-circle" style="color:#dc3545;"></i>
                            <?php break; ?>
                        <?php case 'claim_submitted': ?>
                            <i class="fas fa-handshake" style="color:#ffc107;"></i>
                            <?php break; ?>
                        <?php case 'item_claimed': ?>
                            <i class="fas fa-box" style="color:#17a2b8;"></i>
                            <?php break; ?>
                        <?php default: ?>
                            <i class="fas fa-info-circle" style="color:#6c757d;"></i>
                    <?php endswitch; ?>
                </div>
                
                <div class="notification-content">
                    <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                    <div class="notification-time"><?= formatNotificationTime($notification['created_at']) ?></div>
                </div>
                
                <?php if(!$notification['is_read']): ?>
                <div class="notification-actions">
                    <button class="btn btn-sm" onclick="markAsRead(<?= $notification['notification_id'] ?>, this)" 
                            style="background:#f8f9fa;">
                        <i class="fas fa-check"></i> Mark Read
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsRead(notificationId, button) {
    fetch('mark_notification_read.php?id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = button.closest('.notification-item');
                item.classList.remove('unread');
                button.remove();
                
                // Update badge count
                updateBadgeCount();
            }
        });
}

function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        window.location.href = 'notifications.php?mark_all_read=1';
    }
}

function updateBadgeCount() {
    // Simple badge update - you can enhance this
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = (currentCount - 1) + (currentCount - 1 > 9 ? '+' : '');
        } else {
            badge.remove();
        }
    }
}
</script>
</body>
</html>
