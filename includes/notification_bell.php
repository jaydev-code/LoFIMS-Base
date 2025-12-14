<?php
// includes/notification_bell.php

// Make sure config is loaded
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/notifications.php';

/**
 * Display notification bell HTML
 */
function displayNotificationBell($pdo, $userId, $userType = 'admin') {
    $unreadCount = getUnreadNotificationCount($pdo, $userId);
    $notifications = getRecentNotifications($pdo, $userId, 5);
    $notificationsPage = ($userType === 'admin') ? '../admin_panel/notifications.php' : '../user_panel/notifications.php';
    ?>
    
    <div class="notification-bell-container">
        <div class="notification-bell" id="notificationBell" onclick="toggleNotificationDropdown()">
            <i class="fas fa-bell"></i>
            <?php if($unreadCount > 0): ?>
            <span class="notification-badge"><?= min($unreadCount, 9) ?><?= $unreadCount > 9 ? '+' : '' ?></span>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
                <h4>Notifications</h4>
                <?php if($unreadCount > 0): ?>
                <button class="mark-all-read" onclick="markAllNotificationsAsRead()">
                    Mark all as read
                </button>
                <?php endif; ?>
            </div>
            
            <div class="notification-list">
                <?php if(empty($notifications)): ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
                <?php else: ?>
                <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>" 
                     onclick="viewNotification(<?= $notification['notification_id'] ?>, '<?= $notification['type'] ?>', <?= $notification['related_id'] ?>)">
                    <div class="notification-icon">
                        <?php switch($notification['type']): 
                            case 'claim_submitted': ?>
                                <i class="fas fa-handshake"></i>
                                <?php break; ?>
                            <?php case 'claim_approved': ?>
                                <i class="fas fa-check-circle"></i>
                                <?php break; ?>
                            <?php case 'claim_rejected': ?>
                                <i class="fas fa-times-circle"></i>
                                <?php break; ?>
                            <?php case 'item_claimed': ?>
                                <i class="fas fa-box"></i>
                                <?php break; ?>
                            <?php default: ?>
                                <i class="fas fa-info-circle"></i>
                        <?php endswitch; ?>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                        <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                        <div class="notification-time"><?= formatNotificationTime($notification['created_at']) ?></div>
                    </div>
                    <?php if(!$notification['is_read']): ?>
                    <div class="notification-unread-dot"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="notification-footer">
                <a href="<?= $notificationsPage ?>">View all notifications</a>
            </div>
        </div>
    </div>
    
    <style>
    /* Notification Bell Styles */
    .notification-bell-container {
        position: relative;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        background: #f8f9fa;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .notification-bell:hover {
        background: #e9ecef;
        transform: scale(1.05);
    }

    .notification-bell i {
        font-size: 18px;
        color: #495057;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4757;
        color: white;
        font-size: 11px;
        font-weight: bold;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        border: 2px solid white;
    }

    .notification-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        width: 350px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        z-index: 1000;
        display: none;
        overflow: hidden;
    }

    .notification-dropdown.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h4 {
        margin: 0;
        color: #1e2a38;
    }

    .mark-all-read {
        background: none;
        border: none;
        color: #1e90ff;
        cursor: pointer;
        font-size: 13px;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background 0.3s;
    }

    .mark-all-read:hover {
        background: #f0f8ff;
    }

    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-empty {
        padding: 30px 20px;
        text-align: center;
        color: #6c757d;
    }

    .notification-empty i {
        font-size: 40px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .notification-item {
        padding: 15px;
        border-bottom: 1px solid #f8f9fa;
        cursor: pointer;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transition: background 0.3s;
        position: relative;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #f0f8ff;
    }

    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-item.unread .notification-icon {
        background: #d1ecf1;
    }

    .notification-icon i {
        font-size: 16px;
        color: #495057;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-weight: 600;
        color: #1e2a38;
        margin-bottom: 3px;
        font-size: 14px;
    }

    .notification-message {
        color: #666;
        font-size: 13px;
        line-height: 1.4;
        margin-bottom: 5px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .notification-time {
        font-size: 11px;
        color: #adb5bd;
    }

    .notification-unread-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #1e90ff;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .notification-footer {
        padding: 12px;
        border-top: 1px solid #e9ecef;
        text-align: center;
    }

    .notification-footer a {
        color: #1e90ff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
    }

    .notification-footer a:hover {
        text-decoration: underline;
    }
    </style>
    
    <script>
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
        
        // Close other dropdowns
        document.querySelectorAll('.notification-dropdown.show').forEach(drop => {
            if (drop !== dropdown) drop.classList.remove('show');
        });
    }
    
    function viewNotification(notificationId, type, relatedId) {
        // Mark as read
        markNotificationAsRead(notificationId);
        
        // Navigate based on notification type
        switch(type) {
            case 'claim_submitted':
            case 'claim_approved':
            case 'claim_rejected':
                window.location.href = 'claims.php?view=' + relatedId;
                break;
            case 'item_claimed':
                // Redirect to item view
                window.location.href = 'view_item.php?id=' + relatedId;
                break;
            default:
                // Close dropdown
                document.getElementById('notificationDropdown').classList.remove('show');
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        const bell = document.getElementById('notificationBell');
        
        if (dropdown && bell && !dropdown.contains(e.target) && !bell.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
    </script>
    <?php
}
?>
