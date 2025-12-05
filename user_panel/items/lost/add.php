<?php
// DISABLE error display during form processing to avoid breaking layout
error_reporting(0); // Changed from E_ALL to 0

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
    // Log error but don't display it
    error_log("Database error in add.php: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM item_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
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
            // Handle file upload - SIMPLIFIED
            $photo = '';
            
            // Check if file was uploaded without errors
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                // Get the original filename
                $originalName = $_FILES['photo']['name'];
                
                // Get file extension safely
                $fileExt = '';
                if (strpos($originalName, '.') !== false) {
                    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                }
                
                // Check if it's an allowed file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!empty($fileExt) && in_array($fileExt, $allowedTypes)) {
                    $uploadDir = __DIR__ . '/../../../../uploads/lost_items/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Create unique filename
                    $fileName = time() . '_' . uniqid() . '.' . $fileExt;
                    $targetFile = $uploadDir . $fileName;
                    
                    // Move the file
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                        $photo = $fileName;
                    } else {
                        $error = "Failed to upload file. Please try again.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            // If no file uploaded or error 4 (no file), that's fine - photo remains empty
            
            // Only proceed if no error
            if (empty($error)) {
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO lost_items 
                    (user_id, item_name, category_id, description, photo, place_lost, date_reported, location_lost, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Lost')
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $item_name,
                    $category_id ?: NULL,
                    $description,
                    $photo,
                    $place_lost,
                    $date_reported,
                    $location_lost
                ]);
                
                $success = "Lost item reported successfully!";
                
                // Clear form
                $_POST = [];
            }
            
        } catch(PDOException $e) {
            $error = "A database error occurred. Please try again.";
            error_log("PDO Error in add.php: " . $e->getMessage());
        } catch(Exception $e) {
            $error = "An error occurred. Please try again.";
            error_log("General Error in add.php: " . $e->getMessage());
        }
    }
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
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <a href="../lost_items.php" class="btn btn-primary" style="margin-left: 15px;">
                <i class="fas fa-list"></i> View Lost Items
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
                           value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>"
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
                            <?php echo ($_POST['category_id'] ?? '') == $category['category_id'] ? 'selected' : ''; ?>>
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
                              placeholder="Describe the item (color, brand, distinguishing features)..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="place_lost">
                        <i class="fas fa-map-marker-alt"></i> Place Lost *
                    </label>
                    <input type="text" 
                           id="place_lost" 
                           name="place_lost" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['place_lost'] ?? ''); ?>"
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
                           value="<?php echo htmlspecialchars($_POST['location_lost'] ?? ''); ?>"
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
                           value="<?php echo $_POST['date_reported'] ?? date('Y-m-d'); ?>"
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
                           onchange="previewImage(this)">
                    <small style="color: #64748b; display: block; margin-top: 5px;">
                        Allowed: JPG, JPEG, PNG, GIF (Max: 5MB)
                    </small>
                    <div id="imagePreview"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Report Lost Item
                    </button>
                    <a href="../lost_items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
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

// Set today's date as default and max
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date_reported');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
        if (!dateInput.value) {
            dateInput.value = today;
        }
    }
});
</script>
</body>
</html>