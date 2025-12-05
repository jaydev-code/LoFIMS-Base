<?php
// ENABLE ERROR REPORTING FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../../config/config.php';

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
    die("Database error: " . $e->getMessage());
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../../lost_items.php");
    exit();
}

$item_id = (int)$_GET['id'];

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
}

// Get the lost item details
try {
    $stmt = $pdo->prepare("
        SELECT li.*, ic.category_name
        FROM lost_items li 
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
        WHERE li.lost_id = ? AND li.user_id = ?
    ");
    $stmt->execute([$item_id, $_SESSION['user_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header("Location: ../../lost_items.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $place_lost = trim($_POST['place_lost'] ?? '');
    $date_reported = $_POST['date_reported'] ?? date('Y-m-d');
    $location_lost = trim($_POST['location_lost'] ?? '');
    
    // Validate
    if (empty($item_name) || empty($place_lost)) {
        $error = "Item name and place lost are required!";
    } else {
        try {
            // Handle file upload
            $photo = $item['photo']; // Keep existing photo by default
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $originalName = $_FILES['photo']['name'];
                $fileExt = '';
                
                if (strpos($originalName, '.') !== false) {
                    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                }
                
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!empty($fileExt) && in_array($fileExt, $allowedTypes)) {
                    $uploadDir = __DIR__ . '/../../../../uploads/lost_items/';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Delete old photo if exists
                    if (!empty($item['photo']) && file_exists($uploadDir . $item['photo'])) {
                        unlink($uploadDir . $item['photo']);
                    }
                    
                    $fileName = time() . '_' . uniqid() . '.' . $fileExt;
                    $targetFile = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                        $photo = $fileName;
                    } else {
                        $error = "Failed to upload file. Please try again.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            
            // Handle photo removal
            if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
                if (!empty($item['photo'])) {
                    $uploadDir = __DIR__ . '/../../../../uploads/lost_items/';
                    if (file_exists($uploadDir . $item['photo'])) {
                        unlink($uploadDir . $item['photo']);
                    }
                    $photo = '';
                }
            }
            
            if (empty($error)) {
                // Update database
                $stmt = $pdo->prepare("
                    UPDATE lost_items 
                    SET item_name = ?, category_id = ?, description = ?, photo = ?, 
                        place_lost = ?, date_reported = ?, location_lost = ?
                    WHERE lost_id = ? AND user_id = ?
                ");
                
                $stmt->execute([
                    $item_name,
                    $category_id ?: NULL,
                    $description,
                    $photo,
                    $place_lost,
                    $date_reported,
                    $location_lost,
                    $item_id,
                    $_SESSION['user_id']
                ]);
                
                $success = "Lost item updated successfully!";
                
                // Refresh item data
                $stmt = $pdo->prepare("
                    SELECT li.*, ic.category_name
                    FROM lost_items li 
                    LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
                    WHERE li.lost_id = ? AND li.user_id = ?
                ");
                $stmt->execute([$item_id, $_SESSION['user_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
            }
            
        } catch(PDOException $e) {
            $error = "A database error occurred. Please try again.";
            error_log("PDO Error in edit.php: " . $e->getMessage());
        } catch(Exception $e) {
            $error = "An error occurred. Please try again.";
            error_log("General Error in edit.php: " . $e->getMessage());
        }
    }
}

$current_page = 'edit_lost_item.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Lost Item - LoFIMS</title>
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
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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
        }
        
        .current-photo {
            margin-top: 15px;
        }
        
        .remove-photo-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            color: #64748b;
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
            <i class="fas fa-edit"></i>
            Edit Lost Item
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <a href="view.php?id=<?php echo $item['lost_id']; ?>" class="btn btn-primary" style="margin-left: 15px;">
                <i class="fas fa-eye"></i> View Item
            </a>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="form-group">
                    <label class="form-label" for="item_name">
                        <i class="fas fa-tag"></i> Item Name *
                    </label>
                    <input type="text" 
                           id="item_name" 
                           name="item_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($item['item_name']); ?>"
                           required 
                           placeholder="e.g., iPhone 13, Wallet, Keys">
                </div>

                <div class="form-group">
                    <label class="form-label" for="category_id">
                        <i class="fas fa-list"></i> Category
                    </label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>" 
                            <?php echo $item['category_id'] == $category['category_id'] ? 'selected' : ''; ?>>
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
                              placeholder="Describe the item (color, brand, distinguishing features)..."><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="place_lost">
                        <i class="fas fa-map-marker-alt"></i> Place Lost *
                    </label>
                    <input type="text" 
                           id="place_lost" 
                           name="place_lost" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($item['place_lost']); ?>"
                           required 
                           placeholder="e.g., Library 2nd floor, Cafeteria, Parking lot">
                </div>

                <div class="form-group">
                    <label class="form-label" for="location_lost">
                        <i class="fas fa-map"></i> Specific Location (Optional)
                    </label>
                    <input type="text" 
                           id="location_lost" 
                           name="location_lost" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($item['location_lost'] ?? ''); ?>"
                           placeholder="More specific details about where it was lost">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_reported">
                        <i class="far fa-calendar"></i> Date Lost
                    </label>
                    <input type="date" 
                           id="date_reported" 
                           name="date_reported" 
                           class="form-control" 
                           value="<?php echo $item['date_reported']; ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="photo">
                        <i class="fas fa-camera"></i> Item Photo
                    </label>
                    
                    <?php if(!empty($item['photo'])): ?>
                        <div class="current-photo">
                            <p><strong>Current Photo:</strong></p>
                            <img src="../../../../uploads/lost_items/<?php echo htmlspecialchars($item['photo']); ?>" 
                                 alt="Current photo" 
                                 class="preview-image"
                                 onerror="this.style.display='none'; document.getElementById('no-photo-msg').style.display='block';">
                            <p id="no-photo-msg" style="display: none; color: #64748b;">Photo not found</p>
                            <div class="remove-photo-checkbox">
                                <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                <label for="remove_photo">Remove current photo</label>
                            </div>
                        </div>
                        <p style="color: #64748b; margin-top: 10px;">Or upload a new photo:</p>
                    <?php endif; ?>
                    
                    <input type="file" 
                           id="photo" 
                           name="photo" 
                           class="form-control" 
                           accept=".jpg,.jpeg,.png,.gif"
                           onchange="previewImage(this)">
                    <small style="color: #64748b; display: block; margin-top: 5px;">
                        Allowed: JPG, JPEG, PNG, GIF (Max: 5MB)
                    </small>
                    <div id="imagePreview"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                    <a href="view.php?id=<?php echo $item['lost_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="../../lost_items.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Back to List
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
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
        
        // Check file size (5MB = 5 * 1024 * 1024 bytes)
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-image';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(file);
    }
}

function validateForm() {
    const itemName = document.getElementById('item_name').value.trim();
    const placeLost = document.getElementById('place_lost').value.trim();
    
    if (!itemName) {
        alert('Please enter the item name');
        return false;
    }
    
    if (!placeLost) {
        alert('Please enter where the item was lost');
        return false;
    }
    
    return true;
}

// Set today's date as max
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date_reported');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
    }
});
</script>
</body>
</html>