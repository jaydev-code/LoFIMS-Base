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

// Get the lost item details
try {
    $stmt = $pdo->prepare("
        SELECT li.*, ic.category_name, u.first_name, u.last_name, u.email,
               (SELECT COUNT(*) FROM claims WHERE lost_id = li.lost_id) as claim_count
        FROM lost_items li 
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
        LEFT JOIN users u ON li.user_id = u.user_id
        WHERE li.lost_id = ? AND li.user_id = ?
    ");
    $stmt->execute([$item_id, $_SESSION['user_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header("Location: ../../lost_items.php");
        exit();
    }
    
    // Get related claims
    $claims_stmt = $pdo->prepare("
        SELECT c.*, fi.item_name as found_item_name, fi.date_found
        FROM claims c
        LEFT JOIN found_items fi ON c.found_id = fi.found_id
        WHERE c.lost_id = ?
        ORDER BY c.created_at DESC
    ");
    $claims_stmt->execute([$item_id]);
    $claims = $claims_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Set page title
$page_title = "View Lost Item";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($item['item_name']); ?> - LoFIMS</title>
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
        
        .item-details-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .item-title h1 {
            font-size: 28px;
            color: #1e293b;
            margin: 0 0 10px 0;
        }
        
        .item-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-left: 15px;
        }
        
        .status-lost { background: #fee2e2; color: #991b1b; }
        .status-recovered { background: #d1fae5; color: #065f46; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
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
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-section h3 {
            color: #475569;
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-section h3 i {
            color: #3b82f6;
            width: 20px;
        }
        
        .detail-content {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
            min-height: 60px;
        }
        
        .detail-content p {
            margin: 0;
            color: #1e293b;
            line-height: 1.6;
        }
        
        .item-photo {
            text-align: center;
            margin-top: 20px;
        }
        
        .item-photo img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        
        .no-photo {
            background: #f1f5f9;
            padding: 40px;
            border-radius: 8px;
            color: #64748b;
        }
        
        .no-photo i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .section-title {
            font-size: 20px;
            color: #1e293b;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .claims-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .claims-table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            color: #475569;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .claims-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .claims-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            color: #cbd5e1;
        }
        
        @media (max-width: 768px) {
            .item-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: flex-start;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
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
            <i class="fas fa-eye"></i>
            View Lost Item
        </div>

        <div class="item-details-container">
            <div class="item-header">
                <div class="item-title">
                    <h1>
                        <?php echo htmlspecialchars($item['item_name']); ?>
                        <span class="item-status status-<?php echo strtolower($item['status']); ?>">
                            <?php echo $item['status']; ?>
                        </span>
                    </h1>
                    <p style="color: #64748b; margin: 0;">
                        <i class="far fa-calendar"></i> 
                        Reported on <?php echo date('F d, Y', strtotime($item['created_at'])); ?>
                    </p>
                </div>
                
                <div class="action-buttons">
                    <a href="../../lost_items.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="edit.php?id=<?php echo $item['lost_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if($item['status'] == 'Lost'): ?>
                    <button onclick="markAsRecovered(<?php echo $item['lost_id']; ?>)" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark as Found
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-grid">
                <div>
                    <!-- Item Information -->
                    <div class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> Item Information</h3>
                        <div class="detail-content">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category_name'] ?? 'Not specified'); ?></p>
                            <p><strong>Description:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($item['description'] ?? 'No description provided')); ?></p>
                        </div>
                    </div>
                    
                    <!-- Location Details -->
                    <div class="detail-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                        <div class="detail-content">
                            <p><strong>Place Lost:</strong> <?php echo htmlspecialchars($item['place_lost']); ?></p>
                            <p><strong>Specific Location:</strong> <?php echo htmlspecialchars($item['location_lost'] ?? 'Not specified'); ?></p>
                            <p><strong>Date Lost:</strong> <?php echo date('F d, Y', strtotime($item['date_reported'])); ?></p>
                        </div>
                    </div>
                    
                    <!-- Reporter Information -->
                    <div class="detail-section">
                        <h3><i class="fas fa-user"></i> Reporter Information</h3>
                        <div class="detail-content">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($item['email']); ?></p>
                            <p><strong>Reported:</strong> <?php echo date('F d, Y H:i', strtotime($item['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <!-- Item Photo -->
                    <div class="detail-section">
                        <h3><i class="fas fa-camera"></i> Item Photo</h3>
                        <div class="detail-content">
                            <div class="item-photo">
                                <?php if(!empty($item['photo'])): ?>
                                    <?php
                                    $photo_path = '../../../../uploads/lost_items/' . $item['photo'];
                                    if (file_exists($photo_path)): ?>
                                        <img src="../../../../uploads/lost_items/<?php echo htmlspecialchars($item['photo']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                    <?php else: ?>
                                        <div class="no-photo">
                                            <i class="fas fa-image"></i>
                                            <p>Photo not found on server</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-photo">
                                        <i class="fas fa-image"></i>
                                        <p>No photo uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="detail-section">
                        <h3><i class="fas fa-chart-bar"></i> Quick Stats</h3>
                        <div class="detail-content">
                            <p><strong>Status:</strong> 
                                <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                    <?php echo $item['status']; ?>
                                </span>
                            </p>
                            <p><strong>Claims Filed:</strong> <?php echo $item['claim_count']; ?></p>
                            <p><strong>Days Missing:</strong> 
                                <?php 
                                    $lost_date = new DateTime($item['date_reported']);
                                    $today = new DateTime();
                                    $interval = $today->diff($lost_date);
                                    echo $interval->format('%a days');
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Claims Section -->
        <div class="section-title">
            <i class="fas fa-handshake"></i> Claims Related to This Item
        </div>
        
        <?php if($claims): ?>
            <div style="overflow-x: auto;">
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Claim ID</th>
                            <th>Found Item</th>
                            <th>Date Filed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($claims as $claim): ?>
                        <tr>
                            <td>#<?php echo $claim['claim_id']; ?></td>
                            <td><?php echo htmlspecialchars($claim['found_item_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($claim['status']); ?>">
                                    <?php echo $claim['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="../../claims/view.php?id=<?php echo $claim['claim_id']; ?>" 
                                   class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-handshake"></i>
                <h3>No Claims Yet</h3>
                <p>No claims have been filed for this item yet.</p>
                <a href="../../claims/add.php?lost_id=<?php echo $item['lost_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> File a Claim
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
function markAsRecovered(lostId) {
    if (confirm('Are you sure you want to mark this item as recovered?')) {
        fetch('update_status.php?id=' + lostId + '&status=Recovered')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item marked as recovered successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
            });
    }
}
</script>
</body>
</html>