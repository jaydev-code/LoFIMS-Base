<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// CORRECT PATH: ../../config/config.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit();
}

// Get lost items for the user
$lostItemsStmt = $pdo->prepare("
    SELECT li.lost_id, li.item_name, li.category_id, li.date_reported, li.description, ic.category_name
    FROM lost_items li
    LEFT JOIN item_categories ic ON li.category_id = ic.category_id
    WHERE li.user_id = ? AND li.status IN ('Lost', 'Pending')
    ORDER BY li.date_reported DESC
");
$lostItemsStmt->execute([$_SESSION['user_id']]);
$lostItems = $lostItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get found items (that are not claimed and not posted by the user)
$foundItemsStmt = $pdo->prepare("
    SELECT fi.found_id, fi.item_name, fi.category_id, fi.date_found, fi.description, ic.category_name
    FROM found_items fi
    LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
    WHERE fi.status IN ('Found', 'Unclaimed', 'Pending') 
    AND fi.user_id != ?
    ORDER BY fi.date_found DESC
");
$foundItemsStmt->execute([$_SESSION['user_id']]);
$foundItems = $foundItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$categoriesStmt = $pdo->prepare("SELECT * FROM item_categories ORDER BY category_name");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$error = '';
$success = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lostId = $_POST['lost_id'] ?? 0;
    $foundId = $_POST['found_id'] ?? 0;
    $proofPhoto = $_FILES['proof_photo'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $claimantName = trim($_POST['claimant_name'] ?? '');
    
    $debug_info .= "=== FORM SUBMISSION START ===<br>";
    $debug_info .= "Lost ID: $lostId<br>";
    $debug_info .= "Found ID: $foundId<br>";
    $debug_info .= "Claimant: $claimantName<br>";
    
    // Basic validation
    if ($lostId <= 0 && $foundId <= 0) {
        $error = "Please select either a lost item or a found item to claim.";
        $debug_info .= "Validation failed: No item selected<br>";
    }
    
    if (empty($claimantName)) {
        $error = "Please enter your name as the claimant.";
        $debug_info .= "Validation failed: No claimant name<br>";
    }
    
    // Validate proof photo
    if ($proofPhoto && $proofPhoto['error'] === UPLOAD_ERR_OK) {
        $debug_info .= "File upload detected<br>";
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower(pathinfo($proofPhoto['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Invalid file type. Only JPG, JPEG, PNG, GIF allowed.";
            $debug_info .= "Invalid file extension: $fileExtension<br>";
        }
        
        // Check file size (5MB limit)
        $maxFileSize = 5 * 1024 * 1024;
        if ($proofPhoto['size'] > $maxFileSize) {
            $error = "File is too large. Maximum size is 5MB.";
            $debug_info .= "File size exceeds limit<br>";
        }
    } else {
        $error = "Proof photo is required.";
        $debug_info .= "No file uploaded<br>";
    }
    
    // Check if claim already exists
    if (empty($error)) {
        if ($lostId > 0) {
            $checkStmt = $pdo->prepare("SELECT claim_id FROM claims WHERE lost_id = ? AND status = 'Pending'");
            $checkStmt->execute([$lostId]);
            if ($checkStmt->fetch()) {
                $error = "This lost item already has a pending claim.";
                $debug_info .= "Duplicate claim detected for lost item<br>";
            }
        }
        
        if ($foundId > 0) {
            $checkStmt = $pdo->prepare("SELECT claim_id FROM claims WHERE found_id = ? AND status = 'Pending'");
            $checkStmt->execute([$foundId]);
            if ($checkStmt->fetch()) {
                $error = "This found item already has a pending claim.";
                $debug_info .= "Duplicate claim detected for found item<br>";
            }
        }
    }
    
    // If no errors, process the claim
    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Handle file upload
            $uploadDir = '../../uploads/claims/';
            $debug_info .= "Upload Directory: $uploadDir<br>";
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                $debug_info .= "Creating directory...<br>";
                
                // Create parent directory first if needed
                $parentDir = dirname($uploadDir);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0777, true);
                }
                
                // Create the directory
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
                
                // Generate unique filename
                $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                $targetPath = $uploadDir . $fileName;
                
                $debug_info .= "Target Path: $targetPath<br>";
                
                // Move uploaded file
                if (move_uploaded_file($proofPhoto['tmp_name'], $targetPath)) {
                    $proofPhotoName = $fileName;
                    $debug_info .= "File uploaded successfully as: $proofPhotoName<br>";
                } else {
                    $error = "Failed to save uploaded file. Please try again.";
                    $debug_info .= "move_uploaded_file() failed<br>";
                }
            }
            
            // Insert claim
            if (empty($error)) {
                $debug_info .= "=== DATABASE INSERT PHASE ===<br>";
                
                $sql = "
                    INSERT INTO claims 
                    (lost_id, found_id, user_id, proof_photo, notes, claimant_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
                ";
                
                $stmt = $pdo->prepare($sql);
                
                try {
                    $result = $stmt->execute([
                        $lostId > 0 ? $lostId : NULL,
                        $foundId > 0 ? $foundId : NULL,
                        $_SESSION['user_id'],
                        $proofPhotoName,
                        $notes,
                        $claimantName
                    ]);
                    
                    if ($result) {
                        $lastInsertId = $pdo->lastInsertId();
                        $debug_info .= "=== INSERT SUCCESSFUL ===<br>";
                        $debug_info .= "New Claim ID: $lastInsertId<br>";
                        
                        $success = "Claim submitted successfully! Your claim ID is #$lastInsertId";
                        
                        // Clear form data
                        $_POST = [];
                        
                        $pdo->commit();
                    }
                    
                } catch(PDOException $e) {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    $debug_info .= "=== DATABASE ERROR ===<br>";
                    $debug_info .= "Error: " . htmlspecialchars($errorMessage) . "<br>";
                    
                    if ($errorCode == 23000) {
                        if (strpos($errorMessage, 'foreign key constraint') !== false) {
                            $error = "Invalid item selected.";
                        } elseif (strpos($errorMessage, 'Duplicate entry') !== false) {
                            $error = "This claim may have already been submitted.";
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
            $debug_info .= "General Error: " . $e->getMessage() . "<br>";
        }
    }
    
    $debug_info .= "=== FORM PROCESSING COMPLETE ===<br>";
}

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$current_page = 'add_claim.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Submit Claim - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
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
            max-width: 1000px;
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
        
        /* Items Grid */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }
        
        .item-card {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .item-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .item-card.selected {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .item-card input[type="radio"] {
            margin-right: 10px;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .item-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 16px;
        }
        
        .item-category {
            background: #e2e8f0;
            color: #475569;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .item-description {
            color: #64748b;
            font-size: 14px;
            margin: 10px 0;
            line-height: 1.4;
        }
        
        .item-date {
            color: #94a3b8;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #94a3b8;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #3b82f6;
        }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            color: #475569;
        }
        
        .info-box i {
            color: #3b82f6;
            margin-right: 8px;
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
            
            .items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php 
require_once __DIR__ . '/../includes/sidebar.php'; 
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
            <i class="fas fa-handshake"></i>
            Submit New Claim
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
            <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            <div style="margin-top: 10px;">
                <a href="../claims.php" class="btn btn-success">
                    <i class="fas fa-list"></i> View Claims
                </a>
                <a href="add.php" class="btn btn-primary" style="margin-left: 10px;">
                    <i class="fas fa-plus"></i> Submit Another Claim
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
            <form action="" method="POST" enctype="multipart/form-data" id="claimForm">
                <!-- Lost Items Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-search"></i>
                        <span>Select Your Lost Item</span>
                    </div>
                    
                    <?php if(empty($lostItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h4>No Lost Items Found</h4>
                        <p>You haven't reported any lost items yet.</p>
                        <a href="../lost/add.php" class="btn btn-secondary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Report Lost Item
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="items-grid" id="lostItemsList">
                        <?php foreach($lostItems as $item): ?>
                        <div class="item-card" onclick="selectItem('lost_<?php echo $item['lost_id']; ?>')">
                            <div class="item-header">
                                <div class="item-title">
                                    <input type="radio" 
                                           id="lost_<?php echo $item['lost_id']; ?>" 
                                           name="lost_id" 
                                           value="<?php echo $item['lost_id']; ?>" 
                                           <?php echo (isset($_POST['lost_id']) && $_POST['lost_id'] == $item['lost_id']) ? 'checked' : ''; ?>
                                           onchange="updateSelection()">
                                    <label for="lost_<?php echo $item['lost_id']; ?>" style="cursor: pointer;">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </label>
                                </div>
                                <div class="item-category">
                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?>
                                </div>
                            </div>
                            <?php if(!empty($item['description'])): ?>
                            <div class="item-description">
                                <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>
                                <?php echo strlen($item['description']) > 100 ? '...' : ''; ?>
                            </div>
                            <?php endif; ?>
                            <div class="item-date">
                                <i class="far fa-calendar"></i>
                                Reported: <?php echo date('M d, Y', strtotime($item['date_reported'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Found Items Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-check-circle"></i>
                        <span>Select Found Item to Claim</span>
                    </div>
                    
                    <?php if(empty($foundItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h4>No Found Items Available</h4>
                        <p>There are currently no found items available for claiming.</p>
                        <p style="margin-top: 10px; font-size: 14px;">
                            Check back later or browse the 
                            <a href="../../../public/found_items.php" style="color: #3b82f6;">
                                public found items
                            </a>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="items-grid" id="foundItemsList">
                        <?php foreach($foundItems as $item): ?>
                        <div class="item-card" onclick="selectItem('found_<?php echo $item['found_id']; ?>')">
                            <div class="item-header">
                                <div class="item-title">
                                    <input type="radio" 
                                           id="found_<?php echo $item['found_id']; ?>" 
                                           name="found_id" 
                                           value="<?php echo $item['found_id']; ?>" 
                                           <?php echo (isset($_POST['found_id']) && $_POST['found_id'] == $item['found_id']) ? 'checked' : ''; ?>
                                           onchange="updateSelection()">
                                    <label for="found_<?php echo $item['found_id']; ?>" style="cursor: pointer;">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </label>
                                </div>
                                <div class="item-category">
                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?>
                                </div>
                            </div>
                            <?php if(!empty($item['description'])): ?>
                            <div class="item-description">
                                <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>
                                <?php echo strlen($item['description']) > 100 ? '...' : ''; ?>
                            </div>
                            <?php endif; ?>
                            <div class="item-date">
                                <i class="far fa-calendar"></i>
                                Found: <?php echo date('M d, Y', strtotime($item['date_found'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> You can claim either your lost item OR a found item. Select only one option above.
                    </div>
                </div>

                <!-- Claim Details Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i>
                        <span>Claim Details</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="claimant_name">
                            <i class="fas fa-user"></i> Your Name as Claimant
                        </label>
                        <input type="text" 
                               id="claimant_name" 
                               name="claimant_name" 
                               class="form-control" 
                               value="<?php echo isset($_POST['claimant_name']) ? htmlspecialchars($_POST['claimant_name']) : htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                               required
                               maxlength="100">
                        <div class="form-hint">This is the name that will be shown to the item owner</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="notes">
                            <i class="fas fa-sticky-note"></i> Additional Information
                        </label>
                        <textarea id="notes" 
                                  name="notes" 
                                  class="form-control form-textarea" 
                                  placeholder="Provide any additional information that can help verify your claim..."
                                  maxlength="1000"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        <div class="form-hint">Describe specific details, marks, or features that identify the item</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="proof_photo">
                            <i class="fas fa-camera"></i> Proof of Ownership/Identity
                        </label>
                        <input type="file" 
                               id="proof_photo" 
                               name="proof_photo" 
                               class="form-control" 
                               accept=".jpg,.jpeg,.png,.gif"
                               required
                               onchange="previewImage(event)">
                        <div class="form-hint">Max: 5MB, Formats: JPG, PNG, GIF - Upload proof that you own the item</div>
                        <img id="imagePreview" class="preview-image" alt="Preview will appear here">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Claim
                    </button>
                    <a href="../claims.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
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

// Select item function
function selectItem(itemId) {
    const radio = document.getElementById(itemId);
    if (radio) {
        radio.checked = true;
        
        // If selecting a found item, uncheck lost items
        if (itemId.startsWith('found_')) {
            document.querySelectorAll('input[name="lost_id"]').forEach(r => {
                r.checked = false;
                r.closest('.item-card').classList.remove('selected');
            });
        }
        // If selecting a lost item, uncheck found items
        else if (itemId.startsWith('lost_')) {
            document.querySelectorAll('input[name="found_id"]').forEach(r => {
                r.checked = false;
                r.closest('.item-card').classList.remove('selected');
            });
        }
        
        updateSelection();
    }
}

// Update selection visual state
function updateSelection() {
    // Reset all cards
    document.querySelectorAll('.item-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Mark selected cards
    const selectedLost = document.querySelector('input[name="lost_id"]:checked');
    const selectedFound = document.querySelector('input[name="found_id"]:checked');
    
    if (selectedLost) {
        selectedLost.closest('.item-card').classList.add('selected');
    }
    
    if (selectedFound) {
        selectedFound.closest('.item-card').classList.add('selected');
    }
}

// Form validation
document.getElementById('claimForm').addEventListener('submit', function(e) {
    const lostSelected = document.querySelector('input[name="lost_id"]:checked');
    const foundSelected = document.querySelector('input[name="found_id"]:checked');
    const claimantName = document.getElementById('claimant_name').value.trim();
    const proofPhoto = document.getElementById('proof_photo').files[0];
    
    let errors = [];
    
    if (!lostSelected && !foundSelected) {
        errors.push("Please select either a lost item or a found item to claim.");
    }
    
    if (!claimantName) {
        errors.push("Please enter your name as the claimant.");
    }
    
    if (!proofPhoto) {
        errors.push("Proof photo is required.");
    }
    
    if (errors.length > 0) {
        e.preventDefault();
        alert("Please fix the following errors:\n\n• " + errors.join("\n• "));
        return false;
    }
    
    // Disable button and show loading
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});

// Set default claimant name if not set
document.addEventListener('DOMContentLoaded', function() {
    const claimantInput = document.getElementById('claimant_name');
    if (!claimantInput.value) {
        claimantInput.value = "<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>";
    }
    
    // Initialize selection state
    updateSelection();
    
    // Handle URL parameters for pre-selection
    const urlParams = new URLSearchParams(window.location.search);
    const itemId = urlParams.get('item_id');
    const itemType = urlParams.get('type');
    
    if (itemId && itemType) {
        setTimeout(() => {
            const elementId = itemType + '_' + itemId;
            const element = document.getElementById(elementId);
            if (element) {
                element.checked = true;
                updateSelection();
                // Scroll to the item
                element.closest('.item-card').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        }, 500);
    }
});
</script>
</body>
</html>