<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

// Initialize variables
$error = '';
$success = '';
$claim = null;
$user = null;
$claim_id = 0;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$claim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$claim_id) {
    header("Location: ../claims.php");
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: ../auth/login.php");
        exit();
    }
    
    // Get claim details - verify user owns it and it's pending
    $stmt = $pdo->prepare("
        SELECT c.*, 
               li.item_name as lost_item_name,
               fi.item_name as found_item_name
        FROM claims c
        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
        LEFT JOIN found_items fi ON c.found_id = fi.found_id
        WHERE c.claim_id = ? AND c.user_id = ? AND c.status = 'Pending'
    ");
    $stmt->execute([$claim_id, $_SESSION['user_id']]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        header("Location: ../claims.php");
        exit();
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimant_name = trim($_POST['claimant_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if (empty($claimant_name)) {
        $error = "Claimant name is required!";
    } else {
        try {
            // Handle file upload if provided
            $proof_photo = $claim['proof_photo']; // Keep existing by default
            
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['proof_photo']['name'];
                $fileTmpPath = $_FILES['proof_photo']['tmp_name'];
                $fileSize = $_FILES['proof_photo']['size'];
                
                // Get file extension
                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExtension, $allowedTypes)) {
                    $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/claims/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Delete old photo if exists
                    if (!empty($claim['proof_photo'])) {
                        $oldPhotoPath = $uploadDir . $claim['proof_photo'];
                        if (file_exists($oldPhotoPath)) {
                            @unlink($oldPhotoPath);
                        }
                    }
                    
                    // Create unique filename
                    $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                    $targetFile = $uploadDir . $fileName;
                    
                    // Check file size (5MB limit)
                    $maxFileSize = 5 * 1024 * 1024;
                    if ($fileSize > $maxFileSize) {
                        $error = "File is too large. Maximum size is 5MB.";
                    } else {
                        if (move_uploaded_file($fileTmpPath, $targetFile)) {
                            $proof_photo = $fileName;
                        } else {
                            $error = "Failed to upload file.";
                        }
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            
            // Handle photo removal
            if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
                if (!empty($claim['proof_photo'])) {
                    $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/claims/';
                    $oldPhotoPath = $uploadDir . $claim['proof_photo'];
                    if (file_exists($oldPhotoPath)) {
                        @unlink($oldPhotoPath);
                    }
                    $proof_photo = '';
                }
            }
            
            // Update database if no errors
            if (empty($error)) {
                $stmt = $pdo->prepare("
                    UPDATE claims
                    SET claimant_name = ?, 
                        notes = ?, 
                        proof_photo = ?,
                        updated_at = NOW()
                    WHERE claim_id = ? AND user_id = ?
                ");
                
                $result = $stmt->execute([
                    $claimant_name,
                    $notes,
                    $proof_photo,
                    $claim_id,
                    $_SESSION['user_id']
                ]);
                
                if ($result) {
                    $success = "Claim updated successfully!";
                    
                    // Refresh claim data
                    $stmt = $pdo->prepare("
                        SELECT c.*, 
                               li.item_name as lost_item_name,
                               fi.item_name as found_item_name
                        FROM claims c
                        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
                        LEFT JOIN found_items fi ON c.found_id = fi.found_id
                        WHERE c.claim_id = ? AND c.user_id = ?
                    ");
                    $stmt->execute([$claim_id, $_SESSION['user_id']]);
                    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update database.";
                }
            }
            
        } catch(PDOException $e) {
            error_log("Database error in edit.php: " . $e->getMessage());
            $error = "A database error occurred. Please try again.";
        }
    }
}

$current_page = 'claims.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Claim #<?php echo $claim_id; ?> - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .edit-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin: 20px auto;
            max-width: 800px;
        }
        
        .page-title {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .form-group {
            margin-bottom: 25px;
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
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .photo-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
        }
        
        .current-photo {
            margin-bottom: 20px;
        }
        
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            object-fit: cover;
            background: white;
            padding: 5px;
        }
        
        .remove-photo-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        #imagePreview {
            margin-top: 15px;
        }
        
        #imagePreview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 5px;
            background: white;
        }
        
        .security-notice {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .claim-info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .claim-info-box h4 {
            margin-top: 0;
            color: #1e293b;
        }
        
        @media (max-width: 768px) {
            .edit-container {
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

<?php require_once '../includes/sidebar.php'; ?>

<div class="main">
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> 
            Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
        </div>
    </div>

    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-edit"></i>
            Edit Claim #<?php echo $claim_id; ?>
            <a href="view.php?id=<?php echo $claim_id; ?>" class="btn btn-secondary" style="float: right;">
                <i class="fas fa-arrow-left"></i> Back to Claim
            </a>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <div>
                <strong>Security Notice:</strong> You can only edit your proof and information. 
                The item you're claiming remains the same and its details are hidden for security.
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if($claim): ?>
        <div class="edit-container">
            <!-- Claim Information (Read-only) -->
            <div class="claim-info-box">
                <h4><i class="fas fa-info-circle"></i> Claim Information</h4>
                <p><strong>Claim ID:</strong> #<?php echo $claim_id; ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($claim['status']); ?></p>
                <?php if($claim['lost_item_name']): ?>
                <p><strong>Lost Item:</strong> <?php echo htmlspecialchars($claim['lost_item_name']); ?></p>
                <?php endif; ?>
                <?php if($claim['found_item_name']): ?>
                <p><strong>Found Item:</strong> <?php echo htmlspecialchars($claim['found_item_name']); ?></p>
                <?php endif; ?>
                <p><strong>Created:</strong> <?php echo date('F d, Y h:i A', strtotime($claim['created_at'])); ?></p>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <!-- Claimant Name -->
                <div class="form-group">
                    <label class="form-label" for="claimant_name">
                        <i class="fas fa-user"></i> Claimant Name
                    </label>
                    <input type="text" 
                           id="claimant_name" 
                           name="claimant_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($claim['claimant_name']); ?>"
                           required
                           placeholder="Enter your full name as claimant">
                </div>

                <!-- Additional Notes -->
                <div class="form-group">
                    <label class="form-label" for="notes">
                        <i class="fas fa-sticky-note"></i> Additional Notes
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control form-textarea" 
                              placeholder="Provide additional information that helps prove this item belongs to you..."><?php echo htmlspecialchars($claim['notes'] ?? ''); ?></textarea>
                    <small style="color: #64748b; display: block; margin-top: 5px;">
                        Describe unique features, serial numbers, or any identifying marks
                    </small>
                </div>

                <!-- Proof Photo -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-camera"></i> Proof of Ownership
                    </label>
                    
                    <?php if(!empty($claim['proof_photo'])): ?>
                    <div class="photo-section">
                        <div class="current-photo">
                            <p><strong>Current Proof Photo:</strong></p>
                            <?php 
                            $uploadDir = '/var/www/html/LoFIMS_BASE/uploads/claims/';
                            $webUrl = '../../uploads/claims/' . $claim['proof_photo'];
                            ?>
                            <?php if(file_exists($uploadDir . $claim['proof_photo'])): ?>
                                <img src="<?php echo $webUrl; ?>" 
                                     alt="Current proof photo" 
                                     class="photo-preview">
                                <div class="remove-photo-checkbox">
                                    <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                    <label for="remove_photo">Remove current proof photo</label>
                                </div>
                            <?php else: ?>
                                <p style="color: #dc3545;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Proof photo file not found on server.
                                </p>
                            <?php endif; ?>
                        </div>
                        <p style="color: #666; margin: 15px 0;">Or upload a new proof photo:</p>
                    </div>
                    <?php endif; ?>
                    
                    <input type="file" 
                           id="proof_photo" 
                           name="proof_photo" 
                           class="form-control" 
                           accept=".jpg,.jpeg,.png,.gif"
                           onchange="previewImage(this)">
                    <small style="color: #666; display: block; margin-top: 10px;">
                        Allowed: JPG, JPEG, PNG, GIF (Max: 5MB)
                    </small>
                    <div id="imagePreview"></div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Claim
                    </button>
                    <a href="view.php?id=<?php echo $claim_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="../claims.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Back to Claims
                    </a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> 
            Claim not found or you don't have permission to edit it.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Image preview function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name.toLowerCase();

        // Check file type client-side
        const allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif)$/i;
        if (!allowedExtensions.exec(fileName)) {
            alert('Only JPG, JPEG, PNG & GIF files are allowed.');
            input.value = '';
            return;
        }

        // Check file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        }

        reader.readAsDataURL(file);
    }
}

// Form validation
function validateForm() {
    const claimantName = document.getElementById('claimant_name').value.trim();
    
    if (!claimantName) {
        alert('Please enter your name as the claimant.');
        document.getElementById('claimant_name').focus();
        return false;
    }
    
    return true;
}
</script>
</body>
</html>
