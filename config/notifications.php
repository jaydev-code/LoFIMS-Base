<?php
// config/notifications.php

/**
 * Send a notification to a user
 */
function sendNotification($pdo, $userId, $title, $message, $type = 'system', $relatedId = null) {
    try {
        // Get user email and phone if needed
        $userStmt = $pdo->prepare("SELECT email, phone FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, message, type, related_id, is_read, 
                email_address, sms_phone, notification_method, created_at
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'app', NOW())
        ");
        
        return $stmt->execute([
            $userId, 
            $title, 
            $message, 
            $type, 
            $relatedId,
            $user['email'] ?? null,
            $user['phone'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification when a claim is submitted
 */
function notifyClaimSubmitted($pdo, $claimId, $claimantId, $lostItemId = null, $foundItemId = null) {
    $title = "Claim Submitted";
    $message = "Your claim #$claimId has been submitted and is pending admin review.";

    // Notify claimant
    sendNotification($pdo, $claimantId, $title, $message, 'claim_submitted', $claimId);

    // Notify lost item owner if applicable
    if ($lostItemId) {
        $stmt = $pdo->prepare("SELECT user_id, item_name FROM lost_items WHERE lost_id = ?");
        $stmt->execute([$lostItemId]);
        $lostItem = $stmt->fetch();

        if ($lostItem && $lostItem['user_id'] != $claimantId) {
            $title = "Someone Claimed Your Lost Item";
            $message = "A user has submitted a claim for your lost item: '" . $lostItem['item_name'] . "'. The claim is pending admin approval.";
            sendNotification($pdo, $lostItem['user_id'], $title, $message, 'item_claimed', $lostItemId);
        }
    }

    // Notify found item owner if applicable
    if ($foundItemId) {
        $stmt = $pdo->prepare("SELECT user_id, item_name FROM found_items WHERE found_id = ?");
        $stmt->execute([$foundItemId]);
        $foundItem = $stmt->fetch();

        if ($foundItem && $foundItem['user_id'] != $claimantId) {
            $title = "Someone Claimed Your Found Item";
            $message = "A user has submitted a claim for the item you found: '" . $foundItem['item_name'] . "'. The claim is pending admin approval.";
            sendNotification($pdo, $foundItem['user_id'], $title, $message, 'item_claimed', $foundItemId);
        }
    }
}

/**
 * Send notification when claim is approved
 */
function notifyClaimApproved($pdo, $claimId, $claimantId, $lostItemId = null, $foundItemId = null) {
    $title = "Claim Approved!";
    $message = "Congratulations! Your claim #$claimId has been approved by the admin. Please contact the item owner/finder to arrange pickup.";

    // Notify claimant
    sendNotification($pdo, $claimantId, $title, $message, 'claim_approved', $claimId);

    // Notify lost item owner if applicable
    if ($lostItemId) {
        $stmt = $pdo->prepare("SELECT user_id, item_name FROM lost_items WHERE lost_id = ?");
        $stmt->execute([$lostItemId]);
        $lostItem = $stmt->fetch();

        if ($lostItem && $lostItem['user_id'] != $claimantId) {
            $title = "Lost Item Claim Approved";
            $message = "The claim for your lost item '" . $lostItem['item_name'] . "' has been approved. The claimant will contact you to arrange pickup.";
            sendNotification($pdo, $lostItem['user_id'], $title, $message, 'claim_approved', $claimId);
        }
    }

    // Notify found item owner if applicable
    if ($foundItemId) {
        $stmt = $pdo->prepare("SELECT user_id, item_name FROM found_items WHERE found_id = ?");
        $stmt->execute([$foundItemId]);
        $foundItem = $stmt->fetch();

        if ($foundItem && $foundItem['user_id'] != $claimantId) {
            $title = "Found Item Claim Approved";
            $message = "The claim for the item you found '" . $foundItem['item_name'] . "' has been approved. The claimant will contact you to arrange pickup.";
            sendNotification($pdo, $foundItem['user_id'], $title, $message, 'claim_approved', $claimId);
        }
    }
}

/**
 * Send notification when claim is rejected
 */
function notifyClaimRejected($pdo, $claimId, $claimantId, $reason = null) {
    $title = "Claim Rejected";
    $message = "Your claim #$claimId has been rejected by the admin.";

    if ($reason) {
        $message .= " Reason: " . htmlspecialchars($reason);
    } else {
        $message .= " Please contact the admin for more details.";
    }

    sendNotification($pdo, $claimantId, $title, $message, 'claim_rejected', $claimId);
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent notifications for a user
 */
function getRecentNotifications($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Format notification time (e.g., "2 hours ago")
 */
function formatNotificationTime($datetime) {
    if (empty($datetime)) return '';

    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('M d, Y', $time);
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    $icons = [
        'claim_submitted' => 'handshake',
        'claim_approved' => 'check-circle',
        'claim_rejected' => 'times-circle',
        'item_claimed' => 'box',
        'item_found_match' => 'search',
        'system' => 'info-circle'
    ];
    
    return $icons[$type] ?? 'info-circle';
}

/**
 * Get notification color based on type
 */
function getNotificationColor($type) {
    $colors = [
        'claim_submitted' => '#f39c12',  // Orange
        'claim_approved' => '#2ecc71',   // Green
        'claim_rejected' => '#e74c3c',   // Red
        'item_claimed' => '#9b59b6',     // Purple
        'item_found_match' => '#3498db', // Blue
        'system' => '#95a5a6'            // Gray
    ];
    
    return $colors[$type] ?? '#95a5a6';
}
?>
