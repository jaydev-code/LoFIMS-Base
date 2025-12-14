<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// PREVENT DOUBLE FORM SUBMISSION
$isDuplicate = false;
$submissionToken = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate unique token for this submission
    if (!isset($_POST['form_token'])) {
        $error = "Invalid form submission. Please try again.";
        $isDuplicate = true;
    } else {
        $submissionToken = $_POST['form_token'];
        if (isset($_SESSION['processed_tokens'][$submissionToken])) {
            $error = "This form has already been processed. Please do not refresh or resubmit.";
            $isDuplicate = true;
        } else {
            // Mark this token as processed
            if (!isset($_SESSION['processed_tokens'])) {
                $_SESSION['processed_tokens'] = [];
            }
            $_SESSION['processed_tokens'][$submissionToken] = time();
            
            // Clean old tokens (older than 1 hour)
            foreach ($_SESSION['processed_tokens'] as $token => $timestamp) {
                if (time() - $timestamp > 3600) {
                    unset($_SESSION['processed_tokens'][$token]);
                }
            }
        }
    }
}

// CORRECTED PATH: From user_panel/items/found/add.php to config/config.php
require_once __DIR__ . '/../../../config/config.php';

// Email function using SMTP via Postfix
function sendEmailSMTP($to, $subject, $body, $headers = '') {
    try {
        if (mail($to, $subject, $body, $headers)) {
            error_log("✅ Email sent successfully to: $to");
            return true;
        } else {
            error_log("❌ Failed to send email to: $to");
            return false;
        }
    } catch (Exception $e) {
        error_log("📧 Email error: " . $e->getMessage());
        return false;
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../auth/login.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../../../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Database error in add.php: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

$error = '';
$success = '';
$debug_info = '';
$lastInsertId = null;

// Initialize form variables
$item_name = '';
$category_id = '';
$description = '';
$place_found = '';
$date_found = date('Y-m-d');

// Handle form submission - ONLY IF NOT DUPLICATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isDuplicate) {
    // Debug session info
    $debug_info .= "=== DEBUG: FORM PROCESSING ===<br>";
    $debug_info .= "Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
    $debug_info .= "User Email from DB: " . ($user['email'] ?? 'NOT FOUND') . "<br>";
    $debug_info .= "Submission Token: " . ($submissionToken ? substr($submissionToken, 0, 10) . '...' : 'NOT SET') . "<br>";
    $debug_info .= "Duplicate Check: " . ($isDuplicate ? "YES - Skipping" : "NO - Processing") . "<br>";
    
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $place_found = trim($_POST['place_found'] ?? '');
    $date_found = $_POST['date_found'] ?? date('Y-m-d');
    
    $debug_info .= "=== FORM DATA RECEIVED ===<br>";
    $debug_info .= "Item: $item_name<br>";
    $debug_info .= "Place Found: $place_found<br>";
    
    // Validate required fields
    if (empty($item_name) || empty($place_found)) {
        $error = "Item name and place found are required fields!";
        $debug_info .= "Validation failed: Missing required fields<br>";
    } else {
        try {
            // Handle file upload
            $photo = '';
            
            // Check if file was uploaded
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $debug_info .= "File upload detected<br>";
                
                if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['photo']['name'];
                    $fileTmpPath = $_FILES['photo']['tmp_name'];
                    $fileSize = $_FILES['photo']['size'];
                    
                    $debug_info .= "File Info:<br>";
                    $debug_info .= "- Name: $originalName<br>";
                    $debug_info .= "- Size: $fileSize bytes<br>";
                    
                    // Get file extension
                    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Check allowed types
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        // Use absolute path for upload directory
                        $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/found_items/';
                        $debug_info .= "Upload Directory: $uploadDir<br>";
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($uploadDir)) {
                            $debug_info .= "Creating directory...<br>";
                            
                            // Create parent directory first if needed
                            $parentDir = dirname($uploadDir);
                            if (!is_dir($parentDir)) {
                                mkdir($parentDir, 0777, true);
                            }
                            
                            // Create the directory with full permissions
                            if (mkdir($uploadDir, 0777, true)) {
                                $debug_info .= "Directory created successfully<br>";
                            } else {
                                $error = "Cannot create upload directory. Please contact administrator.";
                                $debug_info .= "Failed to create directory<br>";
                            }
                        }
                        
                        if (empty($error)) {
                            // Check if directory is writable
                            if (!is_writable($uploadDir)) {
                                // Try to make it writable
                                chmod($uploadDir, 0777);
                                $debug_info .= "Made directory writable<br>";
                            }
                            
                            // Check file size (5MB limit)
                            $maxFileSize = 5 * 1024 * 1024;
                            if ($fileSize > $maxFileSize) {
                                $error = "File is too large. Maximum size is 5MB.";
                                $debug_info .= "File size exceeds limit<br>";
                            } else {
                                // Generate unique filename
                                $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                                $targetPath = $uploadDir . $fileName;
                                
                                $debug_info .= "Target Path: $targetPath<br>";
                                
                                // Move uploaded file
                                if (move_uploaded_file($fileTmpPath, $targetPath)) {
                                    $photo = $fileName;
                                    $debug_info .= "✅ File uploaded successfully as: $photo<br>";
                                } else {
                                    $error = "Failed to save uploaded file. Please try again.";
                                    $debug_info .= "move_uploaded_file() failed<br>";
                                }
                            }
                        }
                    } else {
                        $error = "Invalid file type. Only JPG, JPEG, PNG, GIF allowed.";
                        $debug_info .= "Invalid file extension<br>";
                    }
                } else {
                    // Handle specific upload errors
                    $uploadError = $_FILES['photo']['error'];
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
                    ];
                    $error = "Upload error: " . ($errorMessages[$uploadError] ?? 'Unknown error');
                }
            } else {
                $debug_info .= "No file uploaded (optional)<br>";
            }
            
            // Only proceed if no error
            if (empty($error)) {
                $debug_info .= "=== DATABASE INSERT PHASE ===<br>";
                
                // CORRECTED SQL: Using only columns that exist in your table
                $sql = "
                    INSERT INTO found_items 
                    (user_id, item_name, category_id, description, photo, date_found, place_found, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Found')
                ";
                
                $stmt = $pdo->prepare($sql);
                
                $params = [
                    $_SESSION['user_id'],
                    $item_name,
                    !empty($category_id) ? $category_id : NULL,
                    $description,
                    $photo,
                    $date_found,
                    $place_found
                ];
                
                try {
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        $lastInsertId = $pdo->lastInsertId();
                        $debug_info .= "=== INSERT SUCCESSFUL ===<br>";
                        $debug_info .= "New Item ID: $lastInsertId<br>";
                        
                        $success = "✅ Found item reported successfully! Your item ID is #$lastInsertId";
                        
                        // ==============================================
                        // NOTIFICATION SYSTEM FOR FOUND ITEMS
                        // ==============================================
                        
                        $debug_info .= "=== NOTIFICATION PROCESSING ===<br>";
                        
                        try {
                            // 1. Check user notification preferences
                            $debug_info .= "=== CHECKING USER PREFERENCES ===<br>";
                            $pref_stmt = $pdo->prepare("
                                SELECT email_notifications 
                                FROM notification_preferences 
                                WHERE user_id = ?
                            ");
                            $pref_stmt->execute([$_SESSION['user_id']]);
                            $preferences = $pref_stmt->fetch();
                            
                            $shouldSendEmail = true;
                            $emailPreference = 1; // Default to enabled
                            
                            if ($preferences) {
                                $emailPreference = $preferences['email_notifications'];
                                $debug_info .= "Found preferences: email_notifications = $emailPreference<br>";
                                if ($emailPreference == 0) {
                                    $shouldSendEmail = false;
                                    $debug_info .= "⚠️ Email notifications disabled by user preference<br>";
                                }
                            } else {
                                $debug_info .= "ℹ️ No preference found, using default (enabled)<br>";
                            }
                            
                            // 2. Create in-app notification for the finder
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications 
                                (user_id, title, message, type, related_id, created_at, notification_method) 
                                VALUES (?, ?, ?, ?, ?, NOW(), 'app')
                            ");
                            $stmt->execute([
                                $_SESSION['user_id'],
                                '✅ Found Item Reported Successfully',
                                "Your found item '{$item_name}' has been reported. We'll check for potential matches with lost items.",
                                'system',
                                $lastInsertId
                            ]);
                            
                            $debug_info .= "✓ Created in-app notification for finder<br>";
                            
                            // 3. Send email notification to finder if preferences allow
                            if ($shouldSendEmail && !empty($user['email'])) {
                                $debug_info .= "=== EMAIL NOTIFICATION ===<br>";
                                $debug_info .= "Email preference check: " . ($shouldSendEmail ? "ENABLED" : "DISABLED") . "<br>";
                                
                                $to = $user['email'];
                                $subject = "✅ Found Item Reported Successfully - LoFIMS";
                                $message = '
                                <!DOCTYPE html>
                                <html>
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <style>
                                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background: #10b981; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                        .content { padding: 30px; background: #f9f9f9; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; }
                                        .item-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #ddd; }
                                        .next-steps { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
                                        .btn { display: inline-block; background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <div class="header">
                                            <h2>✅ Found Item Reported</h2>
                                            <p>LoFIMS - Lost and Found Item Management System</p>
                                        </div>
                                        <div class="content">
                                            <p>Hello ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ',</p>
                                            <p>Thank you for reporting the found item! Your kindness helps reunite lost items with their owners.</p>
                                            
                                            <div class="item-details">
                                                <h3 style="color: #10b981; margin-top: 0;">📋 Item Details:</h3>
                                                <p><strong>Item Name:</strong> ' . htmlspecialchars($item_name) . '</p>
                                                <p><strong>Place Found:</strong> ' . htmlspecialchars($place_found) . '</p>
                                                <p><strong>Date Found:</strong> ' . htmlspecialchars($date_found) . '</p>
                                                <p><strong>Item ID:</strong> <strong>#' . $lastInsertId . '</strong></p>
                                                ' . (!empty($description) ? "<p><strong>Description:</strong> " . nl2br(htmlspecialchars($description)) . "</p>" : "") . '
                                            </div>
                                            
                                            <div class="next-steps">
                                                <h4 style="color: #1976d2; margin-top: 0;">🔍 What happens next?</h4>
                                                <ol>
                                                    <li>✅ Your item has been added to our found items database</li>
                                                    <li>🤖 We\'ll automatically check for matches with lost items</li>
                                                    <li>📧 You\'ll receive email alerts if matches are found</li>
                                                    <li>📱 You can review and approve/reject claims in your dashboard</li>
                                                </ol>
                                            </div>
                                            
                                            <p><a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/items/found/view.php?id=' . $lastInsertId . '" style="color: #10b981; text-decoration: none; font-weight: bold;">👁️ View Your Found Item Details</a></p>
                                            
                                            <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/dashboard.php" style="background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; font-weight: bold;">
                                                🚀 Go to Dashboard
                                            </a>
                                            
                                            <div class="footer">
                                                <p>📧 This is an automated message from LoFIMS.</p>
                                                <p>❓ If you need to update any information, please visit your dashboard.</p>
                                                <p>© ' . date('Y') . ' LoFIMS. All rights reserved.</p>
                                            </div>
                                        </div>
                                    </div>
                                </body>
                                </html>
                                ';
                                
                                // Headers for HTML email
                                $headers = "MIME-Version: 1.0" . "\r\n";
                                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                $headers .= "From: LoFIMS System <lofims.system@gmail.com>" . "\r\n";
                                $headers .= "Reply-To: LoFIMS Support <lofims.system@gmail.com>" . "\r\n";
                                $headers .= "X-Mailer: PHP/" . phpversion();
                                $headers .= "X-Priority: 1 (High)\r\n";
                                $headers .= "Importance: High\r\n";
                                
                                // Use SMTP email function
                                if (sendEmailSMTP($to, $subject, $message, $headers)) {
                                    $debug_info .= "✅ Email sent successfully to " . htmlspecialchars($user['email']) . "<br>";
                                    
                                    // Add email confirmation to success message
                                    if ($shouldSendEmail) {
                                        $success .= "<br><strong>📧 Email confirmation has been sent to:</strong> " . htmlspecialchars($user['email']);
                                    }
                                    
                                    // Update notification record
                                    $stmt = $pdo->prepare("
                                        UPDATE notifications 
                                        SET email_sent = 1, 
                                            email_sent_at = NOW(),
                                            email_address = ?,
                                            notification_method = 'all'
                                        WHERE related_id = ? 
                                        AND user_id = ?
                                        ORDER BY notification_id DESC LIMIT 1
                                    ");
                                    $stmt->execute([$user['email'], $lastInsertId, $_SESSION['user_id']]);
                                    
                                } else {
                                    $debug_info .= "⚠️ Email sending failed<br>";
                                    error_log("Failed to send email to: " . $user['email']);
                                }
                            } else {
                                if (empty($user['email'])) {
                                    $debug_info .= "ℹ️ No email address for user<br>";
                                } else {
                                    $debug_info .= "ℹ️ Email disabled by user preference<br>";
                                }
                            }
                            
                            // 4. Search for potential matches with lost items
                            $debug_info .= "=== SEARCHING FOR LOST ITEM MATCHES ===<br>";
                            
                            // Find matching lost items based on item name and category
                            $stmt = $pdo->prepare("
                                SELECT li.*, u.first_name, u.last_name, u.email, u.phone
                                FROM lost_items li
                                JOIN users u ON li.user_id = u.user_id
                                WHERE li.status = 'Lost'
                                AND (li.category_id = ? OR ? IS NULL)
                                AND (
                                    LOWER(li.item_name) LIKE LOWER(?)
                                    OR LOWER(li.description) LIKE LOWER(?)
                                    OR LOWER(li.place_lost) LIKE LOWER(?)
                                )
                                AND li.date_reported >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                LIMIT 5
                            ");
                            
                            $searchTerm = "%" . strtolower($item_name) . "%";
                            $searchLocation = "%" . strtolower($place_found) . "%";
                            $stmt->execute([
                                !empty($category_id) ? $category_id : NULL,
                                !empty($category_id) ? $category_id : NULL,
                                $searchTerm,
                                $searchTerm,
                                $searchLocation
                            ]);
                            
                            $matches = $stmt->fetchAll();
                            $matchCount = count($matches);
                            
                            $debug_info .= "Found " . $matchCount . " potential lost item matches<br>";
                            
                            if ($matchCount > 0) {
                                // Create notification for each potential owner
                                foreach ($matches as $match) {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO notifications 
                                        (user_id, title, message, type, related_id, created_at, notification_method) 
                                        VALUES (?, ?, ?, ?, ?, NOW(), 'all')
                                    ");
                                    
                                    $matchMessage = "🎯 Potential match found for your lost item!\n\n";
                                    $matchMessage .= "Your lost item: '" . $match['item_name'] . "'\n";
                                    $matchMessage .= "Found item: '" . $item_name . "'\n";
                                    $matchMessage .= "Found at: " . $place_found . "\n";
                                    $matchMessage .= "Found on: " . $date_found . "\n\n";
                                    $matchMessage .= "Check if this matches your lost item and submit a claim if it does.";
                                    
                                    $stmt->execute([
                                        $match['user_id'],
                                        '🎯 Potential Match Found!',
                                        $matchMessage,
                                        'item_found_match',
                                        $lastInsertId
                                    ]);
                                    
                                    $debug_info .= "✓ Created notification for user ID " . $match['user_id'] . "<br>";
                                    
                                    // Send email to potential owner if they have email notifications enabled
                                    if (!empty($match['email'])) {
                                        // Check if this user has email notifications enabled
                                        $ownerPrefStmt = $pdo->prepare("
                                            SELECT email_notifications 
                                            FROM notification_preferences 
                                            WHERE user_id = ?
                                        ");
                                        $ownerPrefStmt->execute([$match['user_id']]);
                                        $ownerPrefs = $ownerPrefStmt->fetch();
                                        
                                        $sendOwnerEmail = true;
                                        if ($ownerPrefs && isset($ownerPrefs['email_notifications']) && $ownerPrefs['email_notifications'] == 0) {
                                            $sendOwnerEmail = false;
                                            $debug_info .= "ℹ️ Email disabled for owner user ID " . $match['user_id'] . "<br>";
                                        }
                                        
                                        if ($sendOwnerEmail) {
                                            $ownerSubject = "🎯 Potential Match Found for Your Lost Item!";
                                            $ownerEmail = '
                                            <!DOCTYPE html>
                                            <html>
                                            <head>
                                                <meta charset="UTF-8">
                                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                                <style>
                                                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                                    .header { background: #ff9800; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                                    .match-details { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }
                                                    .btn { display: inline-block; background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                                                </style>
                                            </head>
                                            <body>
                                                <div class="container">
                                                    <div class="header">
                                                        <h2>🎯 Potential Match Found!</h2>
                                                        <p>Good news for your lost item!</p>
                                                    </div>
                                                    <div style="padding: 20px; border: 1px solid #ddd; border-top: none;">
                                                        <p>Hello ' . htmlspecialchars($match['first_name']) . ',</p>
                                                        <p>We found an item that might match your lost \'<strong>' . htmlspecialchars($match['item_name']) . '</strong>\'.</p>
                                                        
                                                        <div class="match-details">
                                                            <h3 style="color: #1976d2; margin-top: 0;">🔍 Found Item Details:</h3>
                                                            <p><strong>📦 Item Name:</strong> ' . htmlspecialchars($item_name) . '</p>
                                                            <p><strong>📍 Found At:</strong> ' . htmlspecialchars($place_found) . '</p>
                                                            <p><strong>📅 Found On:</strong> ' . htmlspecialchars($date_found) . '</p>
                                                            ' . (!empty($description) ? "<p><strong>📝 Description:</strong> " . nl2br(htmlspecialchars($description)) . "</p>" : "") . '
                                                        </div>
                                                        
                                                        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                                            <h4 style="color: #2e7d32; margin-top: 0;">📋 What to do next:</h4>
                                                            <ol>
                                                                <li>Compare the details with your lost item</li>
                                                                <li>If it matches, click the link below to view the item</li>
                                                                <li>Submit a claim with proof of ownership</li>
                                                                <li>The finder will review your claim</li>
                                                            </ol>
                                                        </div>
                                                        
                                                        <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/items/found/view.php?id=' . $lastInsertId . '" 
                                                           class="btn">
                                                           👁️ View Found Item Details
                                                        </a>
                                                        
                                                        <p style="margin-top: 20px; font-size: 14px; color: #666;">
                                                            <strong>Note:</strong> This is just a potential match. Please verify all details before claiming.
                                                        </p>
                                                        
                                                        <p style="margin-top: 30px; color: #666; font-size: 14px;">
                                                            Best regards,<br>
                                                            <strong>The LoFIMS Team</strong>
                                                        </p>
                                                    </div>
                                                </div>
                                            </body>
                                            </html>
                                            ';
                                            
                                            $ownerHeaders = "MIME-Version: 1.0" . "\r\n";
                                            $ownerHeaders .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                            $ownerHeaders .= "From: LoFIMS System <lofims.system@gmail.com>" . "\r\n";
                                            $ownerHeaders .= "Reply-To: LoFIMS Support <lofims.system@gmail.com>" . "\r\n";
                                            
                                            if (sendEmailSMTP($match['email'], $ownerSubject, $ownerEmail, $ownerHeaders)) {
                                                $debug_info .= "✅ Sent match email to " . htmlspecialchars($match['email']) . "<br>";
                                            } else {
                                                $debug_info .= "⚠️ Failed to send match email to " . htmlspecialchars($match['email']) . "<br>";
                                            }
                                        }
                                    }
                                }
                                
                                // Also notify finder about matches found
                                if ($matchCount > 0 && $shouldSendEmail && !empty($user['email'])) {
                                    $finderMatchSubject = "🎯 Potential Owners Found for Your Found Item!";
                                    $finderMatchEmail = '
                                    <!DOCTYPE html>
                                    <html>
                                    <head>
                                        <meta charset="UTF-8">
                                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                        <style>
                                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                            .header { background: #2196F3; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                        </style>
                                    </head>
                                    <body>
                                        <div class="container">
                                            <div class="header">
                                                <h2>🎯 Potential Owners Found!</h2>
                                            </div>
                                            <div style="padding: 20px; border: 1px solid #ddd; border-top: none;">
                                                <p>Hello ' . htmlspecialchars($user['first_name']) . ',</p>
                                                <p>Great news! We found <strong style="color: #2196F3;">' . $matchCount . '</strong> potential owners for the item you found: \'<strong>' . htmlspecialchars($item_name) . '</strong>\'.</p>
                                                
                                                <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px 0;">
                                                    <h3 style="color: #2e7d32; margin-top: 0;">🤝 What this means:</h3>
                                                    <ul>
                                                        <li>We\'ve notified the potential owners about your found item</li>
                                                        <li>They may submit claims for the item</li>
                                                        <li>You\'ll receive notification when claims are submitted</li>
                                                        <li>You can review claims in your dashboard</li>
                                                    </ul>
                                                </div>
                                                
                                                <p>Please check your dashboard regularly for claim notifications.</p>
                                                
                                                <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/dashboard.php" 
                                                   style="background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">
                                                   🚀 Go to Dashboard
                                                </a>
                                                
                                                <p style="margin-top: 20px;">Thank you for helping reunite lost items with their owners! 🙏</p>
                                                
                                                <p style="margin-top: 30px; color: #666; font-size: 14px;">
                                                    Best regards,<br>
                                                    <strong>The LoFIMS Team</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </body>
                                    </html>
                                    ';
                                    
                                    if (sendEmailSMTP($user['email'], $finderMatchSubject, $finderMatchEmail, $headers)) {
                                        $debug_info .= "✅ Sent match summary to finder<br>";
                                    } else {
                                        $debug_info .= "⚠️ Failed to send match summary to finder<br>";
                                    }
                                }
                            }
                            
                            // 5. Notify admin about new found item
                            $debug_info .= "=== ADMIN NOTIFICATION ===<br>";
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications 
                                (user_id, title, message, type, related_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            
                            $adminMessage = "📢 New found item reported:\n";
                            $adminMessage .= "Item: {$item_name}\n";
                            $adminMessage .= "Location: {$place_found}\n";
                            $adminMessage .= "Finder: " . $user['first_name'] . " " . $user['last_name'] . "\n";
                            $adminMessage .= "Potential matches found: {$matchCount}\n";
                            $adminMessage .= "Item ID: #{$lastInsertId}";
                            
                            $stmt->execute([
                                1, // Assuming admin has user_id = 1
                                '📢 New Found Item Reported',
                                $adminMessage,
                                'system',
                                $lastInsertId
                            ]);
                            
                            $debug_info .= "✓ Admin notified<br>";
                            
                        } catch (Exception $e) {
                            // Don't let notification errors break the main functionality
                            $debug_info .= "⚠️ Notification error (non-critical): " . htmlspecialchars($e->getMessage()) . "<br>";
                            error_log("Notification error in found item add.php: " . $e->getMessage());
                        }
                        
                        // Add success message details
                        $success .= "<br><br>🎉 <strong>What happens next:</strong>
                        <ul style='margin: 10px 0 0 20px;'>
                            <li>✅ Your item is now in our found items database</li>
                            <li>🔍 We're automatically searching for matching lost items</li>";
                        
                        if ($matchCount > 0) {
                            $success .= "<li>🎯 Found $matchCount potential owner(s) - they've been notified</li>";
                        } else {
                            $success .= "<li>📝 No immediate matches found - we'll keep searching</li>";
                        }
                        
                        $success .= "<li>🔔 Check your dashboard for claim notifications</li>
                        </ul>";
                        
                        // Clear form data for next entry
                        $item_name = '';
                        $category_id = '';
                        $description = '';
                        $place_found = '';
                        $date_found = date('Y-m-d');
                    } else {
                        $error = "Failed to save to database. Please try again.";
                        $debug_info .= "❌ Database insert failed<br>";
                    }
                    
                } catch(PDOException $e) {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    $debug_info .= "=== DATABASE ERROR ===<br>";
                    $debug_info .= "Error: " . htmlspecialchars($errorMessage) . "<br>";
                    
                    if ($errorCode == 23000) {
                        if (strpos($errorMessage, 'foreign key constraint') !== false) {
                            $error = "Invalid category selected.";
                        } elseif (strpos($errorMessage, 'Duplicate entry') !== false) {
                            $error = "This item may have already been reported.";
                        } else {
                            $error = "Database constraint error.";
                        }
                    } else {
                        $error = "A database error occurred.";
                    }
                }
            }
            
        } catch(Exception $e) {
            $error = "An unexpected error occurred. Please try again.";
            $debug_info .= "General Error: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }
    
    $debug_info .= "=== FORM PROCESSING COMPLETE ===<br>";
}

// Generate form token for this page load
$formToken = bin2hex(random_bytes(32));

$current_page = 'add_found_item.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Report Found Item - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Correct CSS path: from user_panel/items/found/ to user_panel/css/ -->
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .page-title {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .page-title i {
            color: #10b981;
            background: #ecfdf5;
            padding: 10px;
            border-radius: 10px;
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            font-size: 15px;
            cursor: pointer;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #cbd5e1;
            display: none;
        }
        
        .debug-info {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
            color: #e2e8f0;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .debug-toggle {
            background: #475569;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .required::after {
            content: " *";
            color: #ef4444;
        }
        
        .form-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            display: block;
        }
        
        .notification-summary {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .notification-summary h4 {
            margin-top: 0;
            color: #0369a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-summary ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .notification-summary li {
            margin-bottom: 5px;
        }
        
        .email-notice {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            color: #2e7d32;
        }
        
        .email-notice i {
            color: #4caf50;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php 
require_once '../../includes/sidebar.php'; 
?>

<!-- Main Content -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> 
            Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-box"></i>
            Report Found Item
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?php echo $success; ?>
            
            <!-- Notification Summary -->
            <div class="notification-summary">
                <h4><i class="fas fa-bell"></i> Notifications Sent:</h4>
                <ul>
                    <li>✅ In-app notification added to your dashboard</li>
                    <?php if(!empty($user['email']) && isset($shouldSendEmail) && $shouldSendEmail): ?>
                    <li>✅ Email confirmation sent to <strong><?php echo htmlspecialchars($user['email']); ?></strong></li>
                    <?php elseif(!empty($user['email'])): ?>
                    <li>⏸️ Email notification skipped (user preference disabled)</li>
                    <?php else: ?>
                    <li>ℹ️ No email address for user</li>
                    <?php endif; ?>
                    <li>✅ System notified about your found item</li>
                    <li>✅ Automatic matching with lost items enabled</li>
                    <?php if(isset($matchCount) && $matchCount > 0): ?>
                    <li>✅ Notified <?php echo $matchCount; ?> potential owner(s)</li>
                    <?php endif; ?>
                </ul>
                <?php if(!empty($user['email']) && isset($shouldSendEmail) && $shouldSendEmail): ?>
                <div class="email-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Email Status:</strong> Check your email inbox (and spam folder) for the confirmation. 
                    You'll also receive alerts if potential owners submit claims.
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="../../found_items.php" class="btn btn-success">
                    <i class="fas fa-list"></i> View My Found Items
                </a>
                <a href="add.php" class="btn btn-primary" style="margin-left: 10px;">
                    <i class="fas fa-plus"></i> Report Another Item
                </a>
                <a href="../lost/list.php" class="btn btn-warning" style="margin-left: 10px;">
                    <i class="fas fa-search"></i> Check Lost Items
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Debug Information -->
        <?php if(!empty($debug_info)): ?>
        <div class="alert alert-info">
            <button class="debug-toggle" onclick="toggleDebug()">
                <i class="fas fa-bug"></i> Toggle Debug Information
            </button>
            <div class="debug-info" id="debugInfo" style="display: none;">
                <?php echo nl2br(htmlspecialchars($debug_info)); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data" id="foundItemForm">
                <!-- FORM TOKEN FOR DUPLICATE PREVENTION -->
                <input type="hidden" name="form_token" value="<?php echo $formToken; ?>">
                
                <div class="email-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Email Notifications:</strong> After submitting, you'll receive email confirmation 
                    and automatic alerts if matching lost items are found.
                </div>
                
                <div class="form-group">
                    <label class="form-label required" for="item_name">
                        <i class="fas fa-tag"></i> Item Name
                    </label>
                    <input type="text" 
                           id="item_name" 
                           name="item_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($item_name); ?>"
                           required 
                           placeholder="e.g., iPhone 13, Wallet, Keys, Bag">
                </div>

                <div class="form-group">
                    <label class="form-label" for="category_id">
                        <i class="fas fa-list"></i> Category
                    </label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>" 
                            <?php echo ($category_id == $category['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              class="form-control form-textarea" 
                              placeholder="Describe the item (color, brand, model, distinguishing marks)..."><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label required" for="place_found">
                        <i class="fas fa-map-marker-alt"></i> Place Found
                    </label>
                    <input type="text" 
                           id="place_found" 
                           name="place_found" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($place_found); ?>"
                           required 
                           placeholder="e.g., Main Library 2nd floor, Student Cafeteria, Parking lot">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_found">
                        <i class="far fa-calendar"></i> Date Found
                    </label>
                    <input type="date" 
                           id="date_found" 
                           name="date_found" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_found); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="photo">
                        <i class="fas fa-camera"></i> Item Photo (Optional)
                    </label>
                    <input type="file" 
                           id="photo" 
                           name="photo" 
                           class="form-control" 
                           accept=".jpg,.jpeg,.png,.gif"
                           onchange="previewImage(event)">
                    <div class="form-hint">Max: 5MB, Formats: JPG, PNG, GIF</div>
                    <img id="imagePreview" class="preview-image" alt="Preview will appear here">
                </div>

                <div class="form-group">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Notification Information:</strong> After submitting, you'll receive:
                        <ul style="margin: 10px 0 0 20px;">
                            <li>📧 Email confirmation (sent to <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : 'your registered email'; ?>)</li>
                            <li>🔔 In-app notification in your dashboard</li>
                            <li>🎯 Automatic alerts if matching lost items are found</li>
                            <li>🤝 Notifications when potential owners submit claims</li>
                        </ul>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Report Found Item & Send Email
                    </button>
                    <a href="../../found_items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once '../../includes/footer.php'; 
?>

<script>
// Debug toggle function
function toggleDebug() {
    const debugInfo = document.getElementById('debugInfo');
    const button = event.target.closest('button') || event.target;
    
    if (debugInfo.style.display === 'none' || debugInfo.style.display === '') {
        debugInfo.style.display = 'block';
        button.innerHTML = '<i class="fas fa-bug"></i> Hide Debug Information';
    } else {
        debugInfo.style.display = 'none';
        button.innerHTML = '<i class="fas fa-bug"></i> Show Debug Information';
    }
}

// Image preview function
function previewImage(event) {
    const input = event.target;
    const preview = document.getElementById('imagePreview');
    const file = input.files[0];
    
    // Clear previous preview
    preview.style.display = 'none';
    preview.src = '';
    
    if (file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Please select a JPG, PNG, or GIF image.');
            input.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        // Create preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Set today's date as default
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date_found');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Focus on first input field
    document.getElementById('item_name').focus();
});

// FIXED: Simple form submission without setTimeout
let isSubmitting = false;

document.getElementById('foundItemForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    
    // Check if already submitting
    if (isSubmitting) {
        e.preventDefault();
        return false;
    }
    
    // Mark as submitting
    isSubmitting = true;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing & Sending Email...';
    
    // Allow form to submit normally
    return true;
});

// Reset submitting flag if form doesn't submit (e.g., validation error)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('foundItemForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Reset button if form has validation errors
    form.addEventListener('invalid', function(e) {
        isSubmitting = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Report Found Item & Send Email';
    }, true);
});

// Add placeholder for file input on page load
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('photo');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            // Find the form-hint element and update it
            const hint = this.nextElementSibling;
            if (hint && hint.classList.contains('form-hint')) {
                hint.textContent = 'Selected: ' + fileName + ' (Max: 5MB, Formats: JPG, PNG, GIF)';
            }
        });
    }
});
</script>
</body>
</html>