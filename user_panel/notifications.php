<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

// Get user info
$userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

// Get notifications for this user
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$unreadStmt->execute([$_SESSION['user_id']]);
$unreadResult = $unreadStmt->fetch();
$unreadCount = $unreadResult['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - LoFIMS</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a2530 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #3498db;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            padding: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu li.active a {
            background: rgba(52, 152, 219, 0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu i {
            width: 25px;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .logout-btn {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .welcome-text h1 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Notifications Container */
        .notifications-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .page-header h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f6397);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        /* Notification Items */
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .notification-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .notification-item.unread {
            background: linear-gradient(90deg, rgba(52, 152, 219, 0.1), transparent);
            border-left: 4px solid #3498db;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.5rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .notification-message {
            color: #5d6d7e;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .notification-time {
            font-size: 0.85rem;
            color: #95a5a6;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }

        .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .empty-state p {
            max-width: 500px;
            margin: 0 auto 20px;
            line-height: 1.6;
        }

        /* Notification Badge */
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h2,
            .sidebar-header p,
            .sidebar-menu li a span {
                display: none;
            }
            
            .sidebar-menu li a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.5rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding: 10px;
            }
            
            .sidebar-menu li a span {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <div class="welcome-text">
                        <h1>Hello, <?= htmlspecialchars($user['first_name']) ?>!</h1>
                        <p>Manage your notifications here</p>
                    </div>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Notifications Container -->
            <div class="notifications-container">
                <div class="page-header">
                    <h1>
                        <i class="fas fa-bell"></i> My Notifications
                        <?php if($unreadCount > 0): ?>
                            <span class="notification-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </h1>
                    
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
                    <p>You don't have any notifications at the moment. Notifications will appear here when there are updates on your items or claims.</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
                <?php else: ?>
                <div id="notificationsList">
                    <?php foreach($notifications as $notification): ?>
                    <div class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>"
                         data-id="<?= $notification['notification_id'] ?>">
                        <div class="notification-icon">
                            <?php 
                            $icon = 'info-circle';
                            $color = '#3498db';
                            
                            switch($notification['type']) {
                                case 'claim_approved':
                                    $icon = 'check-circle';
                                    $color = '#2ecc71';
                                    break;
                                case 'claim_rejected':
                                    $icon = 'times-circle';
                                    $color = '#e74c3c';
                                    break;
                                case 'claim_submitted':
                                    $icon = 'handshake';
                                    $color = '#f39c12';
                                    break;
                                case 'item_claimed':
                                    $icon = 'box';
                                    $color = '#9b59b6';
                                    break;
                                case 'item_found':
                                    $icon = 'search';
                                    $color = '#1abc9c';
                                    break;
                                case 'item_lost':
                                    $icon = 'exclamation-triangle';
                                    $color = '#e67e22';
                                    break;
                            }
                            ?>
                            <i class="fas fa-<?= $icon ?>" style="color: <?= $color ?>;"></i>
                        </div>

                        <div class="notification-content">
                            <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                            <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                            <div class="notification-time">
                                <?= date('F j, Y g:i A', strtotime($notification['created_at'])) ?>
                                (<?= formatNotificationTime($notification['created_at']) ?>)
                            </div>
                        </div>

                        <?php if(!$notification['is_read']): ?>
                        <div class="notification-actions">
                            <button class="btn btn-sm btn-outline" 
                                    onclick="markAsRead(<?= $notification['notification_id'] ?>, this)">
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
                    
                    // Show success message
                    showToast('Notification marked as read');
                } else {
                    alert('Error: ' + (data.error || 'Failed to mark as read'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
    }

    function markAllAsRead() {
        if (confirm('Are you sure you want to mark all notifications as read?')) {
            fetch('mark_all_notifications_read.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                            const actionBtn = item.querySelector('.notification-actions');
                            if (actionBtn) actionBtn.remove();
                        });
                        
                        // Update badge
                        updateBadgeCount();
                        
                        // Show success message
                        showToast(`Marked ${data.count} notifications as read`);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to mark all as read'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                });
        }
    }

    function updateBadgeCount() {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent);
            if (currentCount > 1) {
                badge.textContent = currentCount - 1;
            } else {
                badge.remove();
                // Hide mark all button if no unread notifications
                const markAllBtn = document.querySelector('.btn-primary[onclick="markAllAsRead()"]');
                if (markAllBtn) markAllBtn.remove();
            }
        }
    }

    function showToast(message) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2ecc71;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>