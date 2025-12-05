<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';
$lost_items = [];
$found_items = [];

// Get user's lost items (that are still lost)
try {
    $stmt = $pdo->prepare("
        SELECT li.*, ic.category_name 
        FROM lost_items li 
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
        WHERE li.user_id = ? AND li.status = 'Lost'
        ORDER BY li.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $lost_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error loading lost items: " . $e->getMessage();
}

// Get found items (that are not yet claimed)
try {
    $stmt = $pdo->prepare("
        SELECT fi.*, ic.category_name, u.first_name, u.last_name
        FROM found_items fi 
        LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
        LEFT JOIN users u ON fi.user_id = u.user_id
        WHERE fi.status = 'Found'
        ORDER BY fi.created_at DESC
    ");
    $stmt->execute();
    $found_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error loading found items: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lost_id = $_POST['lost_id'] ?? '';
    $found_id = $_POST['found_id'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $date_claimed = $_POST['date_claimed'] ?? date('Y-m-d');
    $claimant_name = trim($_POST['claimant_name'] ?? $user['first_name'] . ' ' . $user['last_name']);
    
    // Validate
    if (empty($lost_id) || empty($found_id)) {
        $error = "Please select both a lost item and a found item!";
    } elseif (empty($claimant_name)) {
        $error = "Please enter your name as claimant!";
    } else {
        try {
            // Handle file upload for proof photo
            $proof_photo = '';
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../uploads/claims/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['proof_photo']['name']);
                $targetFile = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                $fileExt = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                
                if (in_array($fileExt, $allowedTypes)) {
                    if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $targetFile)) {
                        $proof_photo = $fileName;
                    } else {
                        $error = "Failed to upload proof file.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG, GIF & PDF files are allowed.";
                }
            }
            
            // Only proceed if no file upload error
            if (!$error) {
                // Check if claim already exists for this combination
                $checkStmt = $pdo->prepare("
                    SELECT claim_id FROM claims 
                    WHERE lost_id = ? AND found_id = ? AND user_id = ?
                ");
                $checkStmt->execute([$lost_id, $found_id, $_SESSION['user_id']]);
                
                if ($checkStmt->fetch()) {
                    $error = "You have already filed a claim for these items!";
                } else {
                    // Insert claim into database
                    $stmt = $pdo->prepare("
                        INSERT INTO claims 
                        (lost_id, found_id, user_id, proof_photo, notes, status, claimant_name, date_claimed) 
                        VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)
                    ");
                    
                    $stmt->execute([
                        $lost_id,
                        $found_id,
                        $_SESSION['user_id'],
                        $proof_photo,
                        $notes,
                        $claimant_name,
                        $date_claimed
                    ]);
                    
                    $claim_id = $pdo->lastInsertId();
                    $success = "Claim #{$claim_id} filed successfully! It is now pending review.";
                    
                    // Clear form
                    $_POST = [];
                }
            }
            
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$current_page = 'add_claim.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>File a Claim - LoFIMS</title>
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
            color: #8b5cf6;
            background: #f5f3ff;
            padding: 10px;
            border-radius: 10px;
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section h3 {
            color: #475569;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            color: #8b5cf6;
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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
            background: #8b5cf6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
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
        
        .item-info-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #8b5cf6;
        }
        
        .item-info-box p {
            margin: 5px 0;
            color: #475569;
        }
        
        .item-info-box strong {
            color: #1e293b;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #cbd5e1;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

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
            File a Claim
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <a href="../claims.php" class="btn btn-primary" style="margin-left: 15px; background: #8b5cf6;">
                <i class="fas fa-list"></i> View My Claims
            </a>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateClaimForm()">
                
                <!-- Section 1: Select Lost Item -->
                <div class="form-section">
                    <h3><i class="fas fa-exclamation-circle"></i> 1. Select Your Lost Item</h3>
                    
                    <?php if(empty($lost_items)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-info-circle"></i> You don't have any lost items to claim. 
                        <a href="../lost_items.php" style="color: #065f46; font-weight: bold;">
                            Report a lost item first.
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label" for="lost_id">
                            <i class="fas fa-search"></i> Your Lost Item *
                        </label>
                        <select id="lost_id" name="lost_id" class="form-select" required onchange="showLostItemInfo(this.value)">
                            <option value="">-- Select your lost item --</option>
                            <?php foreach($lost_items as $item): ?>
                            <option value="<?php echo $item['lost_id']; ?>" 
                                <?php echo ($_POST['lost_id'] ?? '') == $item['lost_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (Lost: <?php echo date('M d, Y', strtotime($item['date_reported'])); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="lostItemInfo" class="item-info-box" style="display: none;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section 2: Select Found Item -->
                <div class="form-section">
                    <h3><i class="fas fa-check-circle"></i> 2. Select Found Item to Claim</h3>
                    
                    <?php if(empty($found_items)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-info-circle"></i> There are no found items available to claim at the moment.
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label" for="found_id">
                            <i class="fas fa-search"></i> Found Item to Claim *
                        </label>
                        <select id="found_id" name="found_id" class="form-select" required onchange="showFoundItemInfo(this.value)">
                            <option value="">-- Select found item to claim --</option>
                            <?php foreach($found_items as $item): ?>
                            <option value="<?php echo $item['found_id']; ?>" 
                                <?php echo ($_POST['found_id'] ?? '') == $item['found_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (Found: <?php echo date('M d, Y', strtotime($item['date_found'])); ?>)
                                <?php if($item['first_name']): ?>
                                - Reported by: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="foundItemInfo" class="item-info-box" style="display: none;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section 3: Claim Details -->
                <div class="form-section">
                    <h3><i class="fas fa-file-alt"></i> 3. Claim Details</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="claimant_name">
                            <i class="fas fa-user"></i> Your Name as Claimant *
                        </label>
                        <input type="text" 
                               id="claimant_name" 
                               name="claimant_name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['claimant_name'] ?? $user['first_name'] . ' ' . $user['last_name']); ?>"
                               required 
                               placeholder="Your full name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="date_claimed">
                            <i class="far fa-calendar"></i> Date of Claim
                        </label>
                        <input type="date" 
                               id="date_claimed" 
                               name="date_claimed" 
                               class="form-control" 
                               value="<?php echo $_POST['date_claimed'] ?? date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="notes">
                            <i class="fas fa-align-left"></i> Additional Notes (Optional)
                        </label>
                        <textarea id="notes" 
                                  name="notes" 
                                  class="form-control form-textarea" 
                                  placeholder="Provide any additional information or proof that this item belongs to you..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="proof_photo">
                            <i class="fas fa-camera"></i> Proof Photo/Document (Optional)
                        </label>
                        <input type="file" 
                               id="proof_photo" 
                               name="proof_photo" 
                               class="form-control" 
                               accept="image/*,.pdf"
                               onchange="previewProofPhoto(this)">
                        <small style="color: #64748b; display: block; margin-top: 5px;">
                            Upload photos of receipts, ownership documents, or other proof (JPG, PNG, PDF)
                        </small>
                        <div id="proofPhotoPreview"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?php echo (empty($lost_items) || empty($found_items)) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i> File Claim
                    </button>
                    <a href="../claims.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Items Comparison Preview -->
        <?php if(!empty($lost_items) && !empty($found_items)): ?>
        <div class="card" style="margin-top: 30px;">
            <h3><i class="fas fa-balance-scale"></i> Items Comparison</h3>
            <div class="items-grid" id="itemsComparison">
                <div>
                    <h4 style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Your Lost Item</h4>
                    <p style="color: #64748b;">Select a lost item above to see details</p>
                </div>
                <div>
                    <h4 style="color: #10b981;"><i class="fas fa-check-circle"></i> Found Item</h4>
                    <p style="color: #64748b;">Select a found item above to see details</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Data from PHP to JavaScript
const lostItems = <?php echo json_encode($lost_items); ?>;
const foundItems = <?php echo json_encode($found_items); ?>;

function showLostItemInfo(lostId) {
    const infoDiv = document.getElementById('lostItemInfo');
    if (!lostId) {
        infoDiv.style.display = 'none';
        return;
    }
    
    const item = lostItems.find(i => i.lost_id == lostId);
    if (item) {
        infoDiv.innerHTML = `
            <h4>${escapeHtml(item.item_name)}</h4>
            <p><strong>Category:</strong> ${escapeHtml(item.category_name || 'Not specified')}</p>
            <p><strong>Lost on:</strong> ${new Date(item.date_reported).toLocaleDateString()}</p>
            <p><strong>Place lost:</strong> ${escapeHtml(item.place_lost)}</p>
            <p><strong>Description:</strong> ${escapeHtml(item.description || 'No description')}</p>
        `;
        infoDiv.style.display = 'block';
    }
    
    updateComparison();
}

function showFoundItemInfo(foundId) {
    const infoDiv = document.getElementById('foundItemInfo');
    if (!foundId) {
        infoDiv.style.display = 'none';
        return;
    }
    
    const item = foundItems.find(i => i.found_id == foundId);
    if (item) {
        const reportedBy = item.first_name ? `Reported by: ${escapeHtml(item.first_name)} ${escapeHtml(item.last_name)}` : '';
        infoDiv.innerHTML = `
            <h4>${escapeHtml(item.item_name)}</h4>
            <p><strong>Category:</strong> ${escapeHtml(item.category_name || 'Not specified')}</p>
            <p><strong>Found on:</strong> ${new Date(item.date_found).toLocaleDateString()}</p>
            <p><strong>Place found:</strong> ${escapeHtml(item.place_found)}</p>
            <p><strong>Description:</strong> ${escapeHtml(item.description || 'No description')}</p>
            ${reportedBy ? `<p><strong>${reportedBy}</strong></p>` : ''}
        `;
        infoDiv.style.display = 'block';
    }
    
    updateComparison();
}

function updateComparison() {
    const lostId = document.getElementById('lost_id')?.value;
    const foundId = document.getElementById('found_id')?.value;
    const comparisonDiv = document.getElementById('itemsComparison');
    
    if (!comparisonDiv || !lostId || !foundId) return;
    
    const lostItem = lostItems.find(i => i.lost_id == lostId);
    const foundItem = foundItems.find(i => i.found_id == foundId);
    
    if (lostItem && foundItem) {
        comparisonDiv.innerHTML = `
            <div>
                <h4 style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Your Lost Item</h4>
                <div class="item-info-box">
                    <p><strong>${escapeHtml(lostItem.item_name)}</strong></p>
                    <p>${escapeHtml(lostItem.category_name || 'No category')}</p>
                    <p>Lost: ${new Date(lostItem.date_reported).toLocaleDateString()}</p>
                    <p>At: ${escapeHtml(lostItem.place_lost)}</p>
                </div>
            </div>
            <div>
                <h4 style="color: #10b981;"><i class="fas fa-check-circle"></i> Found Item</h4>
                <div class="item-info-box">
                    <p><strong>${escapeHtml(foundItem.item_name)}</strong></p>
                    <p>${escapeHtml(foundItem.category_name || 'No category')}</p>
                    <p>Found: ${new Date(foundItem.date_found).toLocaleDateString()}</p>
                    <p>At: ${escapeHtml(foundItem.place_found)}</p>
                    ${foundItem.first_name ? `<p>Reported by: ${escapeHtml(foundItem.first_name)} ${escapeHtml(foundItem.last_name)}</p>` : ''}
                </div>
            </div>
        `;
    }
}

function previewProofPhoto(input) {
    const preview = document.getElementById('proofPhotoPreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name.toLowerCase();
        
        if (fileName.endsWith('.pdf')) {
            // For PDF files, show a document icon
            preview.innerHTML = `
                <div style="margin-top: 10px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #cbd5e1;">
                    <i class="fas fa-file-pdf" style="color: #ef4444; font-size: 24px; margin-right: 10px;"></i>
                    <strong>${escapeHtml(file.name)}</strong><br>
                    <small>PDF Document - ${(file.size / 1024).toFixed(2)} KB</small>
                </div>
            `;
        } else if (fileName.match(/\.(jpg|jpeg|png|gif)$/)) {
            // For image files, show preview
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
}

function validateClaimForm() {
    const lostId = document.getElementById('lost_id').value;
    const foundId = document.getElementById('found_id').value;
    const claimantName = document.getElementById('claimant_name').value.trim();
    
    if (!lostId) {
        alert('Please select your lost item');
        return false;
    }
    
    if (!foundId) {
        alert('Please select the found item you want to claim');
        return false;
    }
    
    if (!claimantName) {
        alert('Please enter your name as claimant');
        return false;
    }
    
    return true;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Set today's date as default and max
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date_claimed');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
        if (!dateInput.value) {
            dateInput.value = today;
        }
    }
    
    // Show selected items info on page load
    const lostId = document.getElementById('lost_id')?.value;
    const foundId = document.getElementById('found_id')?.value;
    
    if (lostId) showLostItemInfo(lostId);
    if (foundId) showFoundItemInfo(foundId);
    if (lostId && foundId) updateComparison();
});
</script>
</body>
</html>