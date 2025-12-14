<?php
namespace Services;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/email_config.php';
require_once __DIR__ . '/../../config/sms_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EnhancedNotificationService {
    private $db;
    private $notificationTypes = [
        'claim_submitted' => [
            'email_subject' => 'New Claim Submitted',
            'sms_template' => 'New claim submitted for your item: {item_name}'
        ],
        'claim_approved' => [
            'email_subject' => 'Claim Approved',
            'sms_template' => 'Your claim has been approved! Item: {item_name}'
        ],
        'claim_rejected' => [
            'email_subject' => 'Claim Rejected',
            'sms_template' => 'Your claim has been rejected for: {item_name}'
        ],
        'item_claimed' => [
            'email_subject' => 'Your Item Has Been Claimed',
            'sms_template' => 'Your found item has been claimed: {item_name}'
        ],
        'item_found_match' => [
            'email_subject' => 'Potential Match Found for Your Lost Item',
            'sms_template' => 'Potential match found for your lost item: {item_name}'
        ],
        'system' => [
            'email_subject' => 'System Notification',
            'sms_template' => 'System notification: {message}'
        ]
    ];
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * Create notification and send via selected methods
     */
    public function createNotification($userId, $type, $title, $message, $relatedId = null, $methods = ['app']) {
        // Get user notification preferences
        $userPrefs = $this->getUserNotificationPreferences($userId);
        
        if (!$userPrefs) {
            return false;
        }
        
        // Determine which methods to use
        $notificationMethods = [];
        if (in_array('all', $methods) || in_array('app', $methods)) {
            $notificationMethods[] = 'app';
        }
        if (in_array('all', $methods) || in_array('email', $methods)) {
            $notificationMethods[] = 'email';
        }
        if (in_array('all', $methods) || in_array('sms', $methods)) {
            $notificationMethods[] = 'sms';
        }
        
        // Create base notification
        $notificationId = $this->insertNotification($userId, $type, $title, $message, $relatedId, $notificationMethods);
        
        // Send via each method
        foreach ($notificationMethods as $method) {
            switch ($method) {
                case 'email':
                    if ($userPrefs['email'] && EMAIL_ENABLED) {
                        $this->sendEmailNotification($userId, $notificationId, $type, $title, $message);
                    }
                    break;
                    
                case 'sms':
                    if ($userPrefs['sms'] && SMS_ENABLED && $userPrefs['phone']) {
                        $this->sendSmsNotification($userId, $notificationId, $type, $title, $message);
                    }
                    break;
            }
        }
        
        return $notificationId;
    }
    
    /**
     * Get user notification preferences
     */
    private function getUserNotificationPreferences($userId) {
        $stmt = $this->db->prepare("
            SELECT u.email, u.phone, 
                   CASE WHEN n.email_notifications IS NULL THEN 1 ELSE n.email_notifications END as email_notifications,
                   CASE WHEN n.sms_notifications IS NULL THEN 0 ELSE n.sms_notifications END as sms_notifications
            FROM users u
            LEFT JOIN notification_preferences n ON u.user_id = n.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        return [
            'email' => $result['email'],
            'phone' => $result['phone'],
            'email_notifications' => (bool)$result['email_notifications'],
            'sms_notifications' => (bool)$result['sms_notifications']
        ];
    }
    
    /**
     * Insert notification into database
     */
    private function insertNotification($userId, $type, $title, $message, $relatedId, $methods) {
        $method = in_array('all', $methods) ? 'all' : implode(',', $methods);
        
        $stmt = $this->db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, related_id, notification_method, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $title, $message, $type, $relatedId, $method]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($userId, $notificationId, $type, $title, $message) {
        $user = $this->getUserDetails($userId);
        
        if (!$user || !$user['email']) {
            return false;
        }
        
        $emailSubject = $this->notificationTypes[$type]['email_subject'] ?? $title;
        $emailBody = $this->generateEmailTemplate($title, $message, $type);
        
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(NO_REPLY_EMAIL, SITE_NAME);
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body    = $emailBody;
            $mail->AltBody = strip_tags($message);
            
            if ($mail->send()) {
                // Update notification record
                $this->updateNotificationSentStatus($notificationId, 'email');
                
                // Log email delivery
                $this->logNotificationDelivery($user['email'], 'email', $emailSubject, $notificationId);
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Send SMS notification
     */
    private function sendSmsNotification($userId, $notificationId, $type, $title, $message) {
        $user = $this->getUserDetails($userId);
        
        if (!$user || !$user['phone']) {
            return false;
        }
        
        $smsMessage = $this->generateSmsMessage($type, $title, $message);
        
        // Using Twilio or other SMS provider
        if (SMS_PROVIDER === 'twilio') {
            $sent = $this->sendViaTwilio($user['phone'], $smsMessage);
        } else {
            // Fallback to simulated SMS for development
            $sent = $this->simulateSms($user['phone'], $smsMessage);
        }
        
        if ($sent) {
            $this->updateNotificationSentStatus($notificationId, 'sms', $user['phone']);
            $this->logNotificationDelivery($user['phone'], 'sms', $smsMessage, $notificationId);
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate email template
     */
    private function generateEmailTemplate($title, $message, $type) {
        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; 
                         padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 30px; background: #f9f9f9; border: 1px solid #ddd; 
                          border-top: none; border-radius: 0 0 5px 5px; }
                .notification-type { 
                    display: inline-block; 
                    padding: 5px 10px; 
                    background: #e3f2fd; 
                    color: #1976d2; 
                    border-radius: 3px; 
                    font-size: 12px; 
                    margin-bottom: 15px; 
                }
                .btn { 
                    display: inline-block; 
                    background: #4CAF50; 
                    color: white; 
                    padding: 12px 25px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 15px 0; 
                }
                .footer { 
                    margin-top: 30px; 
                    padding-top: 20px; 
                    border-top: 1px solid #ddd; 
                    color: #666; 
                    font-size: 12px; 
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                    <h2>Notification</h2>
                </div>
                <div class='content'>
                    <div class='notification-type'>" . ucfirst(str_replace('_', ' ', $type)) . "</div>
                    <h3>" . htmlspecialchars($title) . "</h3>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    
                    <a href='http://" . $_SERVER['HTTP_HOST'] . "/user_panel/notifications.php' class='btn'>
                        View in Dashboard
                    </a>
                    
                    <div class='footer'>
                        <p>This is an automated message from " . SITE_NAME . ".</p>
                        <p>If you wish to change your notification preferences, please visit your account settings.</p>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $template;
    }
    
    /**
     * Generate SMS message
     */
    private function generateSmsMessage($type, $title, $message) {
        $smsTemplate = $this->notificationTypes[$type]['sms_template'] ?? '{title}: {message}';
        
        $sms = str_replace(
            ['{title}', '{message}'],
            [$title, substr(strip_tags($message), 0, 120)],
            $smsTemplate
        );
        
        // Truncate to SMS length limit
        if (strlen($sms) > 160) {
            $sms = substr($sms, 0, 157) . '...';
        }
        
        return $sms . ' - ' . SITE_NAME;
    }
    
    /**
     * Send via Twilio
     */
    private function sendViaTwilio($phone, $message) {
        try {
            // Uncomment when you have Twilio credentials
            /*
            $sid = TWILIO_SID;
            $token = TWILIO_TOKEN;
            $from = TWILIO_FROM_NUMBER;
            
            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create(
                $phone,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );
            */
            
            // For now, simulate success and log
            error_log("SMS would be sent to $phone: $message");
            return true;
            
        } catch (Exception $e) {
            error_log("Twilio SMS failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Simulate SMS for development
     */
    private function simulateSms($phone, $message) {
        // Log to file for development
        $log = "[" . date('Y-m-d H:i:s') . "] SMS to $phone: $message\n";
        file_put_contents(__DIR__ . '/../../logs/sms_simulator.log', $log, FILE_APPEND);
        
        // Also log to database for testing
        $stmt = $this->db->prepare("
            INSERT INTO sms_logs (phone_number, message, status, sent_at)
            VALUES (?, ?, 'simulated', NOW())
        ");
        $stmt->execute([$phone, $message]);
        
        return true;
    }
    
    /**
     * Update notification sent status
     */
    private function updateNotificationSentStatus($notificationId, $method, $contactInfo = null) {
        $column = $method . '_sent';
        $columnAt = $method . '_sent_at';
        $contactColumn = $method === 'sms' ? 'sms_phone' : 'email_address';
        
        $sql = "UPDATE notifications SET 
                $column = 1, 
                $columnAt = NOW()";
        
        if ($contactInfo) {
            $sql .= ", $contactColumn = ?";
        }
        
        $sql .= " WHERE notification_id = ?";
        
        $stmt = $this->db->prepare($sql);
        
        if ($contactInfo) {
            $stmt->execute([$contactInfo, $notificationId]);
        } else {
            $stmt->execute([$notificationId]);
        }
    }
    
    /**
     * Log notification delivery
     */
    private function logNotificationDelivery($recipient, $method, $content, $notificationId) {
        $stmt = $this->db->prepare("
            INSERT INTO notification_delivery_logs 
            (notification_id, recipient, method, content, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$notificationId, $recipient, $method, $content]);
    }
    
    /**
     * Get user details
     */
    private function getUserDetails($userId) {
        $stmt = $this->db->prepare("
            SELECT first_name, last_name, email, phone 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    // ===========================================
    // SPECIFIC NOTIFICATION METHODS
    // ===========================================
    
    /**
     * Notify when item is found (matching lost items)
     */
    public function notifyPotentialMatch($foundItemId) {
        $foundItem = $this->getFoundItemDetails($foundItemId);
        
        if (!$foundItem) {
            return false;
        }
        
        // Find potential matches in lost items
        $lostItems = $this->findMatchingLostItems($foundItem);
        
        $notificationsSent = 0;
        
        foreach ($lostItems as $lostItem) {
            $title = "Potential Match Found!";
            $message = "We found an item that matches your lost '{$lostItem['item_name']}':\n";
            $message .= "Found Item: {$foundItem['item_name']}\n";
            $message .= "Location: {$foundItem['location_found']}\n";
            $message .= "Date Found: {$foundItem['date_found']}\n\n";
            $message .= "If this looks like your item, please submit a claim.";
            
            $this->createNotification(
                $lostItem['owner_id'],
                'item_found_match',
                $title,
                $message,
                $foundItemId,
                ['all'] // Send via all enabled methods
            );
            
            $notificationsSent++;
        }
        
        return $notificationsSent;
    }
    
    /**
     * Notify when claim is submitted
     */
    public function notifyClaimSubmitted($claimId) {
        $claim = $this->getClaimDetails($claimId);
        
        if (!$claim) {
            return false;
        }
        
        // Notify the counterpart (owner if claim is for found item, finder if for lost item)
        $counterpartId = $claim['lost_item_id'] ? $claim['owner_id'] : $claim['finder_id'];
        $counterpartType = $claim['lost_item_id'] ? 'owner' : 'finder';
        
        $title = "New Claim Submitted";
        $message = "A new claim has been submitted for your {$counterpartType} item:\n";
        $message .= "Item: {$claim['item_name']}\n";
        $message .= "Claimant: {$claim['claimer_name']}\n";
        $message .= "Claim Date: " . date('Y-m-d H:i') . "\n\n";
        $message .= "Please review this claim in your dashboard.";
        
        $this->createNotification(
            $counterpartId,
            'claim_submitted',
            $title,
            $message,
            $claimId,
            ['all']
        );
        
        return true;
    }
    
    /**
     * Notify when claim status is updated
     */
    public function notifyClaimStatusUpdate($claimId, $newStatus) {
        $claim = $this->getClaimDetails($claimId);
        
        if (!$claim) {
            return false;
        }
        
        $statusText = ucfirst($newStatus);
        $title = "Claim {$statusText}";
        $message = "Your claim for '{$claim['item_name']}' has been {$newStatus}.\n";
        $message .= "Claim ID: {$claimId}\n";
        $message .= "Status: {$statusText}\n";
        
        if ($newStatus === 'approved') {
            $message .= "\nNext steps: Contact the counterpart to arrange item return.";
        } elseif ($newStatus === 'rejected') {
            $message .= "\nReason: {$claim['rejection_reason']}";
        }
        
        // Notify claimant
        $this->createNotification(
            $claim['claimer_id'],
            'claim_' . $newStatus,
            $title,
            $message,
            $claimId,
            ['all']
        );
        
        // Also notify counterpart
        $counterpartId = $claim['lost_item_id'] ? $claim['owner_id'] : $claim['finder_id'];
        $counterpartMessage = "The claim for '{$claim['item_name']}' has been {$newStatus}.";
        
        $this->createNotification(
            $counterpartId,
            'item_claimed',
            "Claim {$statusText}",
            $counterpartMessage,
            $claimId,
            ['all']
        );
        
        return true;
    }
    
    /**
     * Helper: Get found item details
     */
    private function getFoundItemDetails($foundItemId) {
        $stmt = $this->db->prepare("
            SELECT fi.*, c.category_name 
            FROM found_items fi
            LEFT JOIN item_categories c ON fi.category_id = c.category_id
            WHERE fi.found_item_id = ?
        ");
        $stmt->execute([$foundItemId]);
        return $stmt->fetch();
    }
    
    /**
     * Helper: Find matching lost items
     */
    private function findMatchingLostItems($foundItem) {
        $stmt = $this->db->prepare("
            SELECT li.*, u.user_id as owner_id
            FROM lost_items li
            JOIN users u ON li.owner_id = u.user_id
            WHERE li.status = 'lost'
            AND li.category_id = ?
            AND (li.item_name LIKE ? OR li.description LIKE ?)
            AND li.date_lost >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $searchTerm = "%{$foundItem['item_name']}%";
        $stmt->execute([
            $foundItem['category_id'],
            $searchTerm,
            $searchTerm
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Helper: Get claim details
     */
    private function getClaimDetails($claimId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   li.item_name as lost_item_name, li.owner_id,
                   fi.item_name as found_item_name, fi.finder_id,
                   CONCAT(u.first_name, ' ', u.last_name) as claimer_name,
                   u.user_id as claimer_id,
                   COALESCE(li.item_name, fi.item_name) as item_name
            FROM claims c
            LEFT JOIN lost_items li ON c.lost_item_id = li.lost_item_id
            LEFT JOIN found_items fi ON c.found_item_id = fi.found_item_id
            JOIN users u ON c.claimer_id = u.user_id
            WHERE c.claim_id = ?
        ");
        $stmt->execute([$claimId]);
        return $stmt->fetch();
    }
    
    /**
     * Batch send pending notifications
     */
    public function sendPendingNotifications() {
        // Find notifications that need email/SMS but haven't been sent
        $stmt = $this->db->prepare("
            SELECT n.*, u.email, u.phone 
            FROM notifications n
            JOIN users u ON n.user_id = u.user_id
            WHERE (n.notification_method LIKE '%email%' AND n.email_sent = 0)
               OR (n.notification_method LIKE '%sms%' AND n.sms_sent = 0)
            AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY n.created_at ASC
            LIMIT 50
        ");
        $stmt->execute();
        $pending = $stmt->fetchAll();
        
        $sentCount = 0;
        
        foreach ($pending as $notification) {
            $methods = explode(',', $notification['notification_method']);
            
            foreach ($methods as $method) {
                if ($method === 'email' && !$notification['email_sent']) {
                    $this->sendEmailNotification(
                        $notification['user_id'],
                        $notification['notification_id'],
                        $notification['type'],
                        $notification['title'],
                        $notification['message']
                    );
                }
                
                if ($method === 'sms' && !$notification['sms_sent'] && $notification['phone']) {
                    $this->sendSmsNotification(
                        $notification['user_id'],
                        $notification['notification_id'],
                        $notification['type'],
                        $notification['title'],
                        $notification['message']
                    );
                }
            }
            
            $sentCount++;
        }
        
        return $sentCount;
    }
}
?>
