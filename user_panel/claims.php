<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch user's claims
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               li.item_name as lost_item_name,
               fi.item_name as found_item_name,
               fi.status as found_status
        FROM claims c
        LEFT JOIN lost_items li ON c.lost_id = li.lost_id
        LEFT JOIN found_items fi ON c.found_id = fi.found_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $total_claims = count($claims);
    $pending_claims = 0;
    $approved_claims = 0;
    
    foreach($claims as $claim) {
        if ($claim['status'] == 'Pending') $pending_claims++;
        if ($claim['status'] == 'Approved') $approved_claims++;
    }
    
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
    <title>My Claims - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<?php require_once 'includes/sidebar.php'; ?>

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
            <input type="text" id="globalSearch" placeholder="Search claims...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-handshake"></i>
            My Claims
            <a href="claims/add.php" class="btn btn-primary" style="float: right; background: #8b5cf6;">
                <i class="fas fa-plus"></i> File New Claim
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='claims.php'">
                <div class="stat-number"><?php echo $total_claims; ?></div>
                <div class="stat-label">Total Claims</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_claims; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $approved_claims; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card" onclick="window.location.href='claims/add.php'">
                <div class="stat-number"><i class="fas fa-plus"></i></div>
                <div class="stat-label">New Claim</div>
            </div>
        </div>

        <!-- Claims Table -->
        <?php if($claims): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Claim #</th>
                        <th>Lost Item</th>
                        <th>Found Item</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($claims as $claim): ?>
                    <tr>
                        <td><strong>#<?php echo $claim['claim_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($claim['lost_item_name']); ?></td>
                        <td><?php echo htmlspecialchars($claim['found_item_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($claim['date_claimed'])); ?><br>
                            <small style="color: #94a3b8;"><?php echo date('h:i A', strtotime($claim['created_at'])); ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($claim['status']); ?>">
                                <?php echo $claim['status']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="claims/view.php?id=<?php echo $claim['claim_id']; ?>" 
                               class="btn btn-primary btn-sm">
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
            <h3>No Claims Filed</h3>
            <p>You haven't filed any claims yet. Found an item that might be yours?</p>
            <a href="claims/add.php" class="btn btn-primary" style="background: #8b5cf6;">
                <i class="fas fa-plus"></i> File Your First Claim
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>