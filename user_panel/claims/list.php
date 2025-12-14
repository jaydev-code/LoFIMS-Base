<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's claims
$claimsStmt = $pdo->prepare("
    SELECT c.*, 
           l.item_name as lost_item_name,
           l.photo as lost_photo,
           f.item_name as found_item_name,
           f.photo as found_photo,
           CONCAT(a.first_name, ' ', a.last_name) as approver_name
    FROM claims c
    LEFT JOIN lost_items l ON c.lost_id = l.lost_id
    LEFT JOIN found_items f ON c.found_id = f.found_id
    LEFT JOIN users a ON c.approved_by = a.user_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$claimsStmt->execute([$_SESSION['user_id']]);
$claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);

// Check for messages
$success = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f4f6fa;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            color: #1e2a38;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            background: #f8f9fa;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            font-size: 12px;
            font-weight: bold;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #6c757d;
            font-size: 16px;
        }
        
        .claims-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            color: #1e2a38;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #f8f9fa;
        }
        
        th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            background: #f8f9fa;
            color: #495057;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: #e9ecef;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .item-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .item-photo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-handshake"></i> My Claims</h1>
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Claim
                </a>
                <div class="user-info">
                    <div class="notification-bell" onclick="window.location.href='../notifications.php'">
                        <i class="fas fa-bell"></i>
                        <?php if($unreadCount > 0): ?>
                        <span class="notification-badge"><?= min($unreadCount, 9) ?><?= $unreadCount > 9 ? '+' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="font-weight: 600; color: #1e2a38;">
                        <i class="fas fa-user-circle"></i>
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-cards">
            <?php
            // Calculate stats
            $totalClaims = count($claims);
            $pendingClaims = array_filter($claims, fn($claim) => $claim['status'] === 'Pending');
            $approvedClaims = array_filter($claims, fn($claim) => $claim['status'] === 'Approved');
            $rejectedClaims = array_filter($claims, fn($claim) => $claim['status'] === 'Rejected');
            ?>
            
            <div class="stat-card">
                <h3><?= $totalClaims ?></h3>
                <p>Total Claims</p>
            </div>
            
            <div class="stat-card">
                <h3><?= count($pendingClaims) ?></h3>
                <p>Pending Review</p>
            </div>
            
            <div class="stat-card">
                <h3><?= count($approvedClaims) ?></h3>
                <p>Approved</p>
            </div>
            
            <div class="stat-card">
                <h3><?= count($rejectedClaims) ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        
        <!-- Claims Table -->
        <div class="claims-table">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Claim History</h3>
                <div style="font-size: 14px; color: #6c757d;">
                    Showing <?= $totalClaims ?> claim<?= $totalClaims != 1 ? 's' : '' ?>
                </div>
            </div>
            
            <?php if(empty($claims)): ?>
            <div class="empty-state">
                <i class="fas fa-handshake"></i>
                <h3>No Claims Yet</h3>
                <p>You haven't submitted any claims yet. Start by submitting your first claim!</p>
                <a href="add.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Submit Your First Claim
                </a>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($claims as $claim): 
                        $claimId = htmlspecialchars($claim['claim_id']);
                        $itemName = $claim['lost_item_name'] ?: $claim['found_item_name'];
                        $itemType = $claim['lost_item_name'] ? 'Lost Item' : 'Found Item';
                        $itemPhoto = $claim['lost_photo'] ?: $claim['found_photo'];
                        $status = htmlspecialchars($claim['status']);
                        $date = date('M d, Y', strtotime($claim['created_at']));
                        $approver = htmlspecialchars($claim['approver_name'] ?? 'Not processed yet');
                    ?>
                    <tr>
                        <td>#<?= $claimId ?></td>
                        <td>
                            <div class="item-info">
                                <?php if($itemPhoto): ?>
                                <img src="../../uploads/<?= $claim['lost_item_name'] ? 'lost_items' : 'found_items' ?>/<?= htmlspecialchars($itemPhoto) ?>" 
                                     alt="<?= htmlspecialchars($itemName) ?>" 
                                     class="item-photo">
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($itemName) ?></div>
                                    <div style="font-size: 12px; color: #6c757d;">ID: <?= $claimId ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= $itemType ?></td>
                        <td><?= $date ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($status) ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td><?= $approver ?></td>
                        <td>
                            <button class="action-btn" onclick="viewClaimDetails(<?= $claimId ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function viewClaimDetails(claimId) {
        window.location.href = 'view.php?id=' + claimId;
    }
    </script>
</body>
</html>