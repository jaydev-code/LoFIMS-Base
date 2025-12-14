<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
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

// Initialize variables to avoid undefined errors
$error = '';
$success = '';
$debug_info = '';
$lastInsertId = null;
$item_name = '';
$category_id = '';
$description = '';
$place_lost = '';
$date_reported = date('Y-m-d');
$location_lost = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug session info
    $debug_info .= "=== DEBUG: USER INFO ===<br>";
    $debug_info .= "Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
    $debug_info .= "User Email from DB: " . ($user['email'] ?? 'NOT FOUND') . "<br>";
    
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $place_lost = trim($_POST['place_lost'] ?? '');
    $date_reported = $_POST['date_reported'] ?? date('Y-m-d');
    $location_lost = trim($_POST['location_lost'] ?? '');
    
    // Debug: Show what we received
    $debug_info .= "=== FORM SUBMISSION START ===<br>";
    $debug_info .= "Item: $item_name<br>";
    $debug_info .= "Place Lost: $place_lost<br>";
    
    // Validate required fields
    if (empty($item_name) || empty($place_lost)) {
        $error = "Item name and place lost are required fields!";
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
                        $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/lost_items/';
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
                
                // Prepare insert statement
                $sql = "
                    INSERT INTO lost_items 
                    (user_id, item_name, category_id, description, photo, place_lost, date_reported, location_lost, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Lost')
                ";
                
                $stmt = $pdo->prepare($sql);
                
                // Execute with error handling
                try {
                    $result = $stmt->execute([
                        $_SESSION['user_id'],
                        $item_name,
                        !empty($category_id) ? $category_id : NULL,
                        $description,
                        $photo,
                        $place_lost,
                        $date_reported,
                        $location_lost
                    ]);
                    
                    if ($result) {
                        $lastInsertId = $pdo->lastInsertId();
                        $debug_info .= "=== INSERT SUCCESSFUL ===<br>";
                        $debug_info .= "New Item ID: $lastInsertId<br>";
                        
                        // ==============================================
                        // SUCCESS MESSAGE WITH EMAIL CONFIRMATION
                        // ==============================================
                        $success = "✅ Lost item reported successfully! Your item ID is #$lastInsertId";
                        
                        // ==============================================
                        // NOTIFICATION SYSTEM INTEGRATION
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
                            
                            // 2. Create in-app notification for the user
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications 
                                (user_id, title, message, type, related_id, created_at, notification_method) 
                                VALUES (?, ?, ?, ?, ?, NOW(), 'app')
                            ");
                            $stmt->execute([
                                $_SESSION['user_id'],
                                '✅ Lost Item Reported Successfully',
                                "Your item '{$item_name}' has been reported as lost. We'll notify you if a match is found.",
                                'system',
                                $lastInsertId
                            ]);
                            
                            $debug_info .= "✓ Created in-app notification<br>";
                            
                            // 3. Send email notification if user has email AND preferences allow
                            if ($shouldSendEmail && !empty($user['email'])) {
                                $debug_info .= "=== EMAIL NOTIFICATION ===<br>";
                                $debug_info .= "Email preference check: " . ($shouldSendEmail ? "ENABLED" : "DISABLED") . "<br>";
                                
                                $to = $user['email'];
                                $subject = "✅ Lost Item Reported Successfully - LoFIMS";
                                $message = '
                                <!DOCTYPE html>
                                <html>
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <style>
                                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                        .content { padding: 30px; background: #f9f9f9; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; }
                                        .item-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #ddd; }
                                        .next-steps { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
                                        .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <div class="header">
                                            <h2>✅ Lost Item Reported</h2>
                                            <p>LoFIMS - Lost and Found Item Management System</p>
                                        </div>
                                        <div class="content">
                                            <p>Hello ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ',</p>
                                            <p>Your lost item has been successfully reported in our system.</p>
                                            
                                            <div class="item-details">
                                                <h3 style="color: #4CAF50; margin-top: 0;">📋 Item Details:</h3>
                                                <p><strong>Item:</strong> ' . htmlspecialchars($item_name) . '</p>
                                                <p><strong>Place Lost:</strong> ' . htmlspecialchars($place_lost) . '</p>
                                                <p><strong>Specific Location:</strong> ' . htmlspecialchars($location_lost) . '</p>
                                                <p><strong>Date Reported:</strong> ' . htmlspecialchars($date_reported) . '</p>
                                                <p><strong>Reference ID:</strong> <strong>#' . $lastInsertId . '</strong></p>
                                                ' . (!empty($description) ? "<p><strong>Description:</strong> " . nl2br(htmlspecialchars($description)) . "</p>" : "") . '
                                            </div>
                                            
                                            <div class="next-steps">
                                                <h4 style="color: #1976d2; margin-top: 0;">🔍 What happens next?</h4>
                                                <ol>
                                                    <li>✅ Your item is now in our lost items database</li>
                                                    <li>🤖 We\'ll automatically check for matches with found items</li>
                                                    <li>📧 You\'ll receive email alerts if matches are found</li>
                                                    <li>📱 You can track status in your dashboard</li>
                                                </ol>
                                            </div>
                                            
                                            <p><a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/items/lost/view.php?id=' . $lastInsertId . '" style="color: #4CAF50; text-decoration: none; font-weight: bold;">👁️ View Your Lost Item Details</a></p>
                                            
                                            <p style="background: #fff3cd; padding: 10px; border-radius: 5px; border: 1px solid #ffecb5;">
                                                <strong>💡 Tip:</strong> If you find your item, please update its status in your dashboard.
                                            </p>
                                            
                                            <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/dashboard.php" style="background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; font-weight: bold;">
                                                🚀 Go to Dashboard
                                            </a>
                                            
                                            <div class="footer">
                                                <p>📧 This is an automated message from LoFIMS.</p>
                                                <p>❓ If you didn\'t report this item, please <a href="mailto:lofims.system@gmail.com" style="color: #4CAF50;">contact support</a> immediately.</p>
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
                                    $success .= "<br><strong>📧 Email confirmation has been sent to:</strong> " . htmlspecialchars($user['email']);
                                    
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
                            
                            // 4. Check for potential matches in found items
                            $debug_info .= "=== SEARCHING FOR MATCHES ===<br>";
                            
                            // Find matching found items based on item name and category
                            // FIXED: Changed location_found to place_found
                            $stmt = $pdo->prepare("
                                SELECT fi.*, u.first_name, u.last_name, u.email, u.phone
                                FROM found_items fi
                                JOIN users u ON fi.finder_id = u.user_id
                                WHERE fi.status = 'found'
                                AND (fi.category_id = ? OR ? IS NULL)
                                AND (
                                    LOWER(fi.item_name) LIKE LOWER(?)
                                    OR LOWER(fi.description) LIKE LOWER(?)
                                    OR LOWER(fi.place_found) LIKE LOWER(?)
                                )
                                AND fi.date_found >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                LIMIT 5
                            ");
                            
                            $searchTerm = "%" . strtolower($item_name) . "%";
                            $searchLocation = "%" . strtolower($place_lost) . "%";
                            $stmt->execute([
                                !empty($category_id) ? $category_id : NULL,
                                !empty($category_id) ? $category_id : NULL,
                                $searchTerm,
                                $searchTerm,
                                $searchLocation
                            ]);
                            
                            $matches = $stmt->fetchAll();
                            $matchCount = count($matches);
                            
                            $debug_info .= "Found " . $matchCount . " potential matches<br>";
                            
                            if ($matchCount > 0) {
                                // Create notification about potential matches
                                $stmt = $pdo->prepare("
                                    INSERT INTO notifications 
                                    (user_id, title, message, type, related_id, created_at, notification_method) 
                                    VALUES (?, ?, ?, ?, ?, NOW(), 'all')
                                ");
                                
                                $matchMessage = "🎯 We found " . $matchCount . " items that might match your lost '{$item_name}':\n\n";
                                foreach ($matches as $index => $match) {
                                    $matchMessage .= ($index + 1) . ". " . $match['item_name'] . " (found at " . $match['place_found'] . " on " . $match['date_found'] . ")\n";
                                }
                                $matchMessage .= "\nCheck the Found Items section to see if any match your lost item.";
                                
                                $stmt->execute([
                                    $_SESSION['user_id'],
                                    '🎯 Potential Matches Found!',
                                    $matchMessage,
                                    'item_found_match',
                                    $lastInsertId
                                ]);
                                
                                $debug_info .= "✓ Created match notification<br>";
                                
                                // Also send email about matches if preferences allow
                                if ($shouldSendEmail && !empty($user['email'])) {
                                    $matchSubject = "🎯 Potential Matches Found for Your Lost Item!";
                                    $matchEmail = '
                                    <!DOCTYPE html>
                                    <html>
                                    <head>
                                        <meta charset="UTF-8">
                                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                        <style>
                                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                            .header { background: #ff9800; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                            .match-item { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }
                                            .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                                        </style>
                                    </head>
                                    <body>
                                        <div class="container">
                                            <div class="header">
                                                <h2>🎯 Potential Matches Found!</h2>
                                                <p>We found items that might match your lost item</p>
                                            </div>
                                            <div style="padding: 20px; border: 1px solid #ddd; border-top: none;">
                                                <p>Hello ' . htmlspecialchars($user['first_name']) . ',</p>
                                                <p>Great news! We found <strong style="color: #ff9800;">' . $matchCount . '</strong> items that might match your lost \'<strong>' . htmlspecialchars($item_name) . '</strong>\'.</p>
                                                
                                                <h3>🔍 Potential Matches:</h3>
                                    ';
                                    
                                    foreach ($matches as $match) {
                                        $matchEmail .= '
                                            <div class="match-item">
                                                <p><strong>📦 Item:</strong> ' . htmlspecialchars($match['item_name']) . '</p>
                                                <p><strong>📍 Found at:</strong> ' . htmlspecialchars($match['place_found']) . '</p>
                                                <p><strong>📅 Found on:</strong> ' . htmlspecialchars($match['date_found']) . '</p>
                                                <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/items/found/view.php?id=' . $match['found_item_id'] . '" 
                                                   style="color: #4CAF50; text-decoration: none; font-weight: bold;">
                                                   👁️ View Item Details
                                                </a>
                                            </div>
                                        ';
                                    }
                                    
                                    $matchEmail .= '
                                            <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                                <h4 style="margin-top: 0;">📝 What to do next:</h4>
                                                <ol>
                                                    <li>Check each item carefully</li>
                                                    <li>If you find a match, click \'Claim This Item\'</li>
                                                    <li>Provide proof of ownership</li>
                                                    <li>We\'ll help arrange pickup</li>
                                                </ol>
                                            </div>
                                            
                                            <a href="http://' . $_SERVER['HTTP_HOST'] . '/user_panel/items/found/list.php" 
                                               class="btn">
                                               🔍 View All Found Items
                                            </a>
                                            
                                            <p style="margin-top: 30px; color: #666; font-size: 14px;">
                                                Best regards,<br>
                                                <strong>The LoFIMS Team</strong>
                                            </p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                                    ';
                                    
                                    // Headers for match email
                                    $matchHeaders = "MIME-Version: 1.0" . "\r\n";
                                    $matchHeaders .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                    $matchHeaders .= "From: LoFIMS System <lofims.system@gmail.com>" . "\r\n";
                                    $matchHeaders .= "Reply-To: LoFIMS Support <lofims.system@gmail.com>" . "\r\n";
                                    
                                    if (sendEmailSMTP($user['email'], $matchSubject, $matchEmail, $matchHeaders)) {
                                        $debug_info .= "✅ Match notification email sent<br>";
                                    } else {
                                        $debug_info .= "⚠️ Match notification email failed<br>";
                                    }
                                }
                            }
                            
                            // 5. Notify admin about new lost item
                            $debug_info .= "=== ADMIN NOTIFICATION ===<br>";
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications 
                                (user_id, title, message, type, related_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            
                            $adminMessage = "📢 New lost item reported:\n";
                            $adminMessage .= "Item: {$item_name}\n";
                            $adminMessage .= "Location: {$place_lost}\n";
                            $adminMessage .= "User: " . $user['first_name'] . " " . $user['last_name'] . "\n";
                            $adminMessage .= "Matches found: {$matchCount}\n";
                            $adminMessage .= "Item ID: #{$lastInsertId}";
                            
                            $stmt->execute([
                                1, // Assuming admin has user_id = 1
                                '📢 New Lost Item Reported',
                                $adminMessage,
                                'system',
                                $lastInsertId
                            ]);
                            
                            $debug_info .= "✓ Admin notified<br>";
                            
                        } catch (Exception $e) {
                            // Don't let notification errors break the main functionality
                            $debug_info .= "⚠️ Notification error (non-critical): " . htmlspecialchars($e->getMessage()) . "<br>";
                            error_log("Notification error in lost item add.php: " . $e->getMessage());
                        }
                        
                        // Add success message details
                        $success .= "<br><br>🎉 <strong>What happens next:</strong>
                        <ul style='margin: 10px 0 0 20px;'>
                            <li>✅ Your item is now in our lost items database</li>
                            <li>📧 You'll receive email notifications for any matches</li>
                            <li>🔔 Check your dashboard for updates</li>
                            <li>🔍 We're automatically searching for matches</li>
                        </ul>";
                        
                        // Clear form data for next entry
                        $item_name = '';
                        $category_id = '';
                        $description = '';
                        $place_lost = '';
                        $location_lost = '';
                        $date_reported = date('Y-m-d');
                        $_POST = [];
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

$current_page = 'add_lost_item.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Report Lost Item - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            color: #3b82f6;
            background: #eff6ff;
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
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

<?php require_once '../../includes/sidebar.php'; ?>

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
            <i class="fas fa-exclamation-circle"></i>
            Report Lost Item
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
                    <li>✅ System notified about your lost item</li>
                    <li>✅ Automatic matching with found items enabled</li>
                </ul>
                <?php if(!empty($user['email']) && isset($shouldSendEmail) && $shouldSendEmail): ?>
                <div class="email-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Email Status:</strong> Check your email inbox (and spam folder) for the confirmation. 
                    You'll also receive alerts if potential matches are found.
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="../../lost_items.php" class="btn btn-success">
                    <i class="fas fa-list"></i> View My Lost Items
                </a>
                <a href="add.php" class="btn btn-primary" style="margin-left: 10px;">
                    <i class="fas fa-plus"></i> Report Another Item
                </a>
                <a href="../found/list.php" class="btn btn-warning" style="margin-left: 10px;">
                    <i class="fas fa-search"></i> Check Found Items
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
            <form action="" method="POST" enctype="multipart/form-data" id="lostItemForm">
                <div class="email-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Email Notifications:</strong> After submitting, you'll receive email confirmation 
                    and automatic alerts if matching items are found.
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
                    <label class="form-label required" for="place_lost">
                        <i class="fas fa-map-marker-alt"></i> Place Lost
                    </label>
                    <input type="text" 
                           id="place_lost" 
                           name="place_lost" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($place_lost); ?>"
                           required 
                           placeholder="e.g., Main Library 2nd floor, Student Cafeteria">
                </div>

                <div class="form-group">
                    <label class="form-label" for="location_lost">
                        <i class="fas fa-map-pin"></i> Specific Location
                    </label>
                    <input type="text" 
                           id="location_lost" 
                           name="location_lost" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($location_lost); ?>"
                           placeholder="e.g., Near the water fountain, On table 5">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_reported">
                        <i class="far fa-calendar"></i> Date Lost
                    </label>
                    <input type="date" 
                           id="date_reported" 
                           name="date_reported" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_reported); ?>"
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
                            <li>🎯 Automatic alerts if matching items are found</li>
                            <li>📱 Status updates via your preferred method</li>
                        </ul>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Report Lost Item & Send Email
                    </button>
                    <a href="../../lost_items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

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
    const dateInput = document.getElementById('date_reported');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Focus on first input field
    document.getElementById('item_name').focus();
});

// Prevent double form submission
document.getElementById('lostItemForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing & Sending Email...';
    
    // Add a small delay to show the processing state
    setTimeout(() => {
        this.submit();
    }, 100);
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
    
    // Real-time email notification
    const emailInput = document.querySelector('input[type="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const emailNotice = document.querySelector('.email-notice');
            if (emailNotice && this.value) {
                emailNotice.innerHTML = `<i class="fas fa-envelope"></i>
                <strong>Email Notifications:</strong> Confirmation will be sent to: <strong>${this.value}</strong>`;
            }
        });
    }
});
</script>
</body>
</html>