<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

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
    
    // Get claim details - SEPARATE QUERIES for security
    $stmt = $pdo->prepare("
        SELECT c.*, 
               li.item_name as lost_item_name,
               li.description as lost_description,
               li.photo as lost_photo,
               li.date_reported as lost_date,
               li.location_lost,
               li.status as lost_status,
               li.place_lost
        FROM claims c
        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
        WHERE c.claim_id = ? AND c.user_id = ?
    ");
    $stmt->execute([$claim_id, $_SESSION['user_id']]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        header("Location: ../claims.php");
        exit();
    }
    
    // Get found item details ONLY for display (not for editing reference)
    // But we'll only show minimal info
    $foundDetails = null;
    if ($claim['found_id']) {
        $stmt = $pdo->prepare("
            SELECT 
                item_name,
                category_id,
                date_found,
                place_found,
                status
            FROM found_items 
            WHERE found_id = ?
        ");
        $stmt->execute([$claim['found_id']]);
        $foundDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Track that user viewed this claim (for security logging)
    $viewStmt = $pdo->prepare("
        UPDATE claims 
        SET view_count = view_count + 1, 
            last_viewed_at = NOW()
        WHERE claim_id = ?
    ");
    $viewStmt->execute([$claim_id]);
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$current_page = 'claims.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>View Claim #<?php echo $claim_id; ?> - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .claim-details {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-top: 20px;
        }
        
        .detail-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-section:last-child {
            border-bottom: none;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .detail-label {
            width: 200px;
            font-weight: 600;
            color: #475569;
            flex-shrink: 0;
        }
        
        .detail-value {
            flex: 1;
            color: #1e293b;
            word-break: break-word;
        }
        
        .item-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #374151; }
        .status-found { background: #dbeafe; color: #1e40af; }
        .status-claimed { background: #fef3c7; color: #92400e; }
        .status-returned { background: #d1fae5; color: #065f46; }
        .status-lost { background: #fee2e2; color: #991b1b; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .photo-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .photo-wrapper {
            text-align: center;
        }
        
        .photo-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .security-notice {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hidden-details {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #64748b;
            margin: 10px 0;
        }
        
        .hidden-details i {
            font-size: 32px;
            margin-bottom: 10px;
            color: #94a3b8;
        }
        
        .info-note {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 10px 0;
            color: #065f46;
            font-size: 14px;
        }
        
        .info-note i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .item-photo {
                max-width: 150px;
                max-height: 150px;
            }
            
            .action-buttons {
                flex-direction: column;
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
            <i class="fas fa-handshake"></i>
            Claim #<?php echo $claim_id; ?>
            <a href="../claims.php" class="btn btn-secondary" style="float: right;">
                <i class="fas fa-arrow-left"></i> Back to Claims
            </a>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <div>
                <strong>Security Protection:</strong> Found item details are hidden to prevent fraud. 
                Only the person who found the item can see full details.
            </div>
        </div>

        <div class="claim-details">
            <!-- Claim Status -->
            <div class="detail-section">
                <h3 style="color: #1e293b; margin-bottom: 20px;">Claim Status</h3>
                <div class="detail-row">
                    <div class="detail-label">Claim ID:</div>
                    <div class="detail-value">
                        <strong>#<?php echo $claim_id; ?></strong>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($claim['status']); ?>">
                            <?php echo htmlspecialchars($claim['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Date Filed:</div>
                    <div class="detail-value">
                        <?php echo date('F d, Y h:i A', strtotime($claim['created_at'])); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Last Viewed:</div>
                    <div class="detail-value">
                        <?php 
                        if ($claim['last_viewed_at']) {
                            echo date('F d, Y h:i A', strtotime($claim['last_viewed_at']));
                        } else {
                            echo 'Never viewed';
                        }
                        ?>
                        <?php if ($claim['view_count'] > 0): ?>
                        <small style="color: #64748b; margin-left: 10px;">
                            (Viewed <?php echo $claim['view_count']; ?> time<?php echo $claim['view_count'] > 1 ? 's' : ''; ?>)
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Claimant Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($claim['claimant_name']); ?></div>
                </div>
            </div>

            <!-- Lost Item Details (User CAN see their own lost item) -->
            <?php if($claim['lost_item_name']): ?>
            <div class="detail-section">
                <h3 style="color: #1e293b; margin-bottom: 20px;">Your Lost Item</h3>
                <div class="detail-row">
                    <div class="detail-label">Item Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($claim['lost_item_name']); ?></div>
                </div>
                <?php if($claim['lost_description']): ?>
                <div class="detail-row">
                    <div class="detail-label">Description:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($claim['lost_description'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <div class="detail-label">Date Lost:</div>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($claim['lost_date'])); ?></div>
                </div>
                <?php if($claim['location_lost'] || $claim['place_lost']): ?>
                <div class="detail-row">
                    <div class="detail-label">Location Lost:</div>
                    <div class="detail-value">
                        <?php 
                        $location = $claim['location_lost'] ?? $claim['place_lost'];
                        echo htmlspecialchars($location);
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <div class="detail-label">Lost Item Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($claim['lost_status']); ?>">
                            <?php echo htmlspecialchars($claim['lost_status']); ?>
                        </span>
                    </div>
                </div>
                <?php if($claim['lost_photo']): ?>
                <div class="detail-row">
                    <div class="detail-label">Photo:</div>
                    <div class="detail-value">
                        <div class="photo-container">
                            <div class="photo-wrapper">
                                <img src="../../uploads/lost_items/<?php echo htmlspecialchars($claim['lost_photo']); ?>" 
                                     alt="Your lost item photo" class="item-photo"
                                     onerror="this.src='../assets/images/no-image.jpg'">
                                <div class="photo-label">Your Lost Item</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Found Item (HIDDEN DETAILS) -->
            <?php if($foundDetails): ?>
            <div class="detail-section">
                <h3 style="color: #1e293b; margin-bottom: 20px;">Found Item</h3>
                
                <!-- Hidden Details Box -->
                <div class="hidden-details">
                    <i class="fas fa-lock"></i>
                    <h4>Details Protected</h4>
                    <p>The person who found this item is reviewing your claim.</p>
                    <p>They can see both your proof and the item details.</p>
                    <small style="color: #94a3b8; display: block; margin-top: 10px;">
                        <i class="fas fa-info-circle"></i>
                        This prevents fraudulent claims by hiding specific item details.
                    </small>
                </div>
                
                <!-- Minimal visible info -->
                <div class="detail-row">
                    <div class="detail-label">Category:</div>
                    <div class="detail-value">
                        <?php 
                        if ($foundDetails['category_id']) {
                            $catStmt = $pdo->prepare("SELECT category_name FROM item_categories WHERE category_id = ?");
                            $catStmt->execute([$foundDetails['category_id']]);
                            $category = $catStmt->fetchColumn();
                            echo htmlspecialchars($category ?: 'Unknown');
                        } else {
                            echo 'Unknown';
                        }
                        ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Date Found:</div>
                    <div class="detail-value">
                        <?php echo date('F d, Y', strtotime($foundDetails['date_found'])); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">General Location:</div>
                    <div class="detail-value">
                        <?php 
                        // Show only general area, not exact location
                        $place = $foundDetails['place_found'] ?? '';
                        if ($place) {
                            // Extract just the building/area (not room number)
                            $generalArea = preg_replace('/Room \d+|#\d+/i', '', $place);
                            $generalArea = trim($generalArea);
                            echo htmlspecialchars($generalArea ?: 'On Campus');
                        } else {
                            echo 'On Campus';
                        }
                        ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Item Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($foundDetails['status']); ?>">
                            <?php echo htmlspecialchars($foundDetails['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- MY Proof Section (User CAN see and edit this) -->
            <div class="detail-section">
                <h3 style="color: #1e293b; margin-bottom: 20px;">Your Proof Submission</h3>
                
                <!-- Information Note -->
                <div class="info-note">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Tip:</strong> The finder will compare your proof with the actual found item.
                    Make sure your proof clearly shows why this item belongs to you.
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Your Proof Photo:</div>
                    <div class="detail-value">
                        <div class="photo-container">
                            <div class="photo-wrapper">
                                <?php if($claim['proof_photo']): ?>
                                <img src="../../uploads/claims/<?php echo htmlspecialchars($claim['proof_photo']); ?>" 
                                     alt="Your proof photo" class="item-photo"
                                     onerror="this.src='../assets/images/no-image.jpg'">
                                <div class="photo-label">Your Proof</div>
                                <?php else: ?>
                                <div class="hidden-details" style="padding: 15px;">
                                    <i class="fas fa-camera"></i>
                                    <p>No proof photo submitted</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($claim['notes']): ?>
                <div class="detail-row">
                    <div class="detail-label">Your Notes:</div>
                    <div class="detail-value">
                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <?php echo nl2br(htmlspecialchars($claim['notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if($claim['status'] == 'Pending'): ?>
                <!-- User CAN edit their OWN proof/info -->
                <a href="edit.php?id=<?php echo $claim_id; ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Edit My Proof/Info
                </a>
                
                <button class="btn btn-danger" onclick="cancelClaim(<?php echo $claim_id; ?>)">
                    <i class="fas fa-times"></i> Cancel Claim
                </button>
                <?php endif; ?>
                
                <a href="../claims.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> All Claims
                </a>
                
                <?php if($claim['lost_item_name']): ?>
                <a href="../items/lost/view.php?id=<?php echo $claim['lost_id']; ?>" class="btn btn-info">
                    <i class="fas fa-search"></i> View Lost Item
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Cancel claim function
function cancelClaim(claimId) {
    if (confirm('Are you sure you want to cancel this claim? This action cannot be undone.')) {
        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
        btn.disabled = true;
        
        fetch('cancel.php?id=' + claimId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Claim cancelled successfully!');
                    window.location.href = '../claims.php';
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }
}

// Image error handler fallback
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[onerror]');
    images.forEach(img => {
        img.onerror = function() {
            this.src = '../assets/images/no-image.jpg';
        };
    });
});
</script>
</body>
</html>