<?php
session_start();
// PATH FIX: Go up 3 levels from /user_panel/items/found/ to /config/config.php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    // PATH FIX: Go up 3 levels to auth/login.php
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
    $place_found = trim($_POST['place_found'] ?? '');
    $date_found = $_POST['date_found'] ?? date('Y-m-d');
    
    // Validate
    if (empty($item_name) || empty($place_found)) {
        $error = "Item name and place found are required!";
    } else {
        try {
            // Handle file upload
            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                // PATH FIX: Go up 4 levels to /uploads/found_items/
                $uploadDir = __DIR__ . '/../../../../uploads/found_items/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['photo']['name']);
                $targetFile = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $fileExt = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                
                if (in_array($fileExt, $allowedTypes)) {
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                        $photo = $fileName;
                    } else {
                        $error = "Failed to upload file.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                }
            }
            
            // Only proceed if no file upload error
            if (!$error) {
                // Insert into database - MATCHING YOUR TABLE STRUCTURE
                $stmt = $pdo->prepare("
                    INSERT INTO found_items 
                    (user_id, item_name, category_id, description, photo, place_found, date_found, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Found')
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $item_name,
                    $category_id ?: NULL,
                    $description,
                    $photo,
                    $place_found,
                    $date_found
                ]);
                
                $success = "Found item reported successfully!";
                
                // Clear form
                $_POST = [];
            }
            
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$current_page = 'add_found_item.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Report Found Item - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PATH FIX: Go up 2 levels to /user_panel/css/styles.css -->
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

<!-- PATH FIX: Go up 2 levels to /user_panel/includes/sidebar.php -->
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
            <i class="fas fa-check-circle"></i>
            Report Found Item
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <!-- PATH FIX: Go up one level to found_items.php -->
            <a href="../found_items.php" class="btn btn-primary" style="margin-left: 15px; background: #10b981;">
                <i class="fas fa-list"></i> View Found Items
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
                    <label class="form-label" for="place_found">
                        <i class="fas fa-map-marker-alt"></i> Place Found *
                    </label>
                    <input type="text" 
                           id="place_found" 
                           name="place_found" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['place_found'] ?? ''); ?>"
                           required 
                           placeholder="e.g., Library 2nd floor, Cafeteria, Parking lot">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_found">
                        <i class="far fa-calendar"></i> Date Found
                    </label>
                    <input type="date" 
                           id="date_found" 
                           name="date_found" 
                           class="form-control" 
                           value="<?php echo $_POST['date_found'] ?? date('Y-m-d'); ?>"
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
                           accept="image/*"
                           onchange="previewImage(this)">
                    <div id="imagePreview"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Report Found Item
                    </button>
                    <!-- PATH FIX: Go up one level to found_items.php -->
                    <a href="../found_items.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PATH FIX: Go up 2 levels to /user_panel/includes/footer.php -->
<?php require_once '../../includes/footer.php'; ?>

<script>
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-image';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function validateForm() {
    const itemName = document.getElementById('item_name').value.trim();
    const placeFound = document.getElementById('place_found').value.trim();
    
    if (!itemName) {
        alert('Please enter the item name');
        return false;
    }
    
    if (!placeFound) {
        alert('Please enter where the item was found');
        return false;
    }
    
    return true;
}

// Set today's date as default and max
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date_found');
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