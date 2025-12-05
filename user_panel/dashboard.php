<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }

    // ----- DASHBOARD STATISTICS -----
    
    // Existing statistics (system-wide)
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items WHERE status='Lost'")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status='Found'")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();

    // User's personal statistics
    $myLostItems = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id=? AND status='Lost'");
    $myLostItems->execute([$_SESSION['user_id']]);
    $myLostItems = $myLostItems->fetchColumn();

    $myFoundItems = $pdo->prepare("SELECT COUNT(*) FROM found_items WHERE user_id=? AND status='Found'");
    $myFoundItems->execute([$_SESSION['user_id']]);
    $myFoundItems = $myFoundItems->fetchColumn();

    $myClaims = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE user_id=?");
    $myClaims->execute([$_SESSION['user_id']]);
    $myClaims = $myClaims->fetchColumn();

    // Recovery rate
    $recoveryRate = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM claims WHERE user_id=? AND status='completed') * 100.0 /
            NULLIF((SELECT COUNT(*) FROM claims WHERE user_id=?), 0) as recovery_rate
    ");
    $recoveryRate->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recoveryRate = $recoveryRate->fetchColumn();
    $recoveryRate = $recoveryRate ? round($recoveryRate, 1) : 0;

    // Recent activity (combined from lost, found, and claims)
    $recentActivity = $pdo->prepare("
        (SELECT 'lost' as type, item_name as title, description, created_at, 'bg-danger' as color
         FROM lost_items WHERE user_id=? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'found' as type, item_name as title, description, created_at, 'bg-success' as color
         FROM found_items WHERE user_id=? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'claim' as type, CONCAT('Claim #', claim_id) as title, status, date_claimed as created_at, 'bg-info' as color
         FROM claims WHERE user_id=? ORDER BY date_claimed DESC LIMIT 3)
        ORDER BY created_at DESC LIMIT 8
    ");
    $recentActivity->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $recentActivity = $recentActivity->fetchAll(PDO::FETCH_ASSOC);

    // Urgent items (lost in last 24 hours)
    $urgentItems = $pdo->query("
        SELECT li.*, ic.category_name
        FROM lost_items li
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND li.status = 'Lost'
        ORDER BY li.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Potential matches for user's lost items
    $potentialMatches = $pdo->prepare("
        SELECT l.item_name as lost_item, f.item_name as found_item,
               l.description as lost_desc, f.description as found_desc,
               l.created_at as lost_date, f.created_at as found_date,
               CASE
                   WHEN l.item_name = f.item_name THEN 85
                   WHEN l.description LIKE CONCAT('%', f.item_name, '%') OR f.description LIKE CONCAT('%', l.item_name, '%') THEN 70
                   ELSE ROUND(RAND() * 50 + 30)
               END as match_score
        FROM lost_items l, found_items f
        WHERE l.user_id = ?
        AND l.status = 'Lost'
        AND f.status = 'Found'
        AND l.lost_id != f.found_id
        ORDER BY match_score DESC LIMIT 3
    ");
    $potentialMatches->execute([$_SESSION['user_id']]);
    $potentialMatches = $potentialMatches->fetchAll(PDO::FETCH_ASSOC);

    // Announcements
    $announcements = $pdo->query("
        SELECT * FROM announcements 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Notifications (user's claims)
    $notifications = $pdo->prepare("
        SELECT claim_id, status, date_claimed 
        FROM claims 
        WHERE user_id=? 
        ORDER BY date_claimed DESC 
        LIMIT 5
    ");
    $notifications->execute([$_SESSION['user_id']]);
    $notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
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
            <input type="text" id="globalSearch" placeholder="Search items, claims, or announcements...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-home"></i>
            Dashboard
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-btn" onclick="window.location.href='lost_items.php'">
                <i class="fas fa-exclamation-circle"></i>
                <span>Report Lost Item</span>
            </div>
            <div class="action-btn" onclick="window.location.href='found_items.php'">
                <i class="fas fa-check-circle"></i>
                <span>Report Found Item</span>
            </div>
            <div class="action-btn" onclick="window.location.href='claims.php'">
                <i class="fas fa-handshake"></i>
                <span>File a Claim</span>
            </div>
        </div>

        <!-- My Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='lost_items.php'">
                <div class="stat-number"><?php echo $myLostItems; ?></div>
                <div class="stat-label">My Lost Items</div>
            </div>
            <div class="stat-card" onclick="window.location.href='found_items.php'">
                <div class="stat-number"><?php echo $myFoundItems; ?></div>
                <div class="stat-label">My Found Items</div>
            </div>
            <div class="stat-card" onclick="window.location.href='claims.php'">
                <div class="stat-number"><?php echo $myClaims; ?></div>
                <div class="stat-label">My Claims</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $recoveryRate; ?>%</div>
                <div class="stat-label">Recovery Rate</div>
            </div>
        </div>

        <!-- Dashboard Layout -->
        <div class="dashboard-layout">
            <div class="left-column">
                <!-- System Statistics -->
                <div class="dashboard-boxes">
                    <div class="box">
                        <h2><?php echo $totalLost; ?></h2>
                        <p>Total Lost Items</p>
                    </div>
                    <div class="box">
                        <h2><?php echo $totalFound; ?></h2>
                        <p>Total Found Items</p>
                    </div>
                    <div class="box">
                        <h2><?php echo $totalClaims; ?></h2>
                        <p>Total Claims</p>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="card">
                    <h3><i class="fas fa-chart-pie"></i> System Overview</h3>
                    <canvas id="itemsChart"></canvas>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                    <?php if($recentActivity): ?>
                        <?php foreach($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-badge <?php echo $activity['color']; ?>"></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($activity['title']); ?></strong><br>
                                    <small><?php echo htmlspecialchars(substr($activity['description'] ?? '', 0, 50)); ?>...</small><br>
                                    <span class="activity-date">
                                        <?php echo date("M d, Y H:i", strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-column">
                <!-- Urgent Items -->
                <div class="card urgent-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Urgent Items</h3>
                    <?php if($urgentItems): ?>
                        <?php foreach($urgentItems as $item): ?>
                            <div class="activity-item">
                                <div class="activity-badge bg-danger"></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($item['category_name'] ?? 'Recently Lost'); ?></small><br>
                                    <span class="activity-date">
                                        Lost <?php echo date("M d H:i", strtotime($item['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No urgent items reported recently.</p>
                    <?php endif; ?>
                </div>

                <!-- Potential Matches -->
                <div class="card">
                    <h3><i class="fas fa-handshake"></i> Potential Matches</h3>
                    <?php if($potentialMatches): ?>
                        <?php foreach($potentialMatches as $match): ?>
                            <div class="match-item">
                                <strong><?php echo htmlspecialchars($match['lost_item']); ?></strong>
                                <span class="match-score"><?php echo $match['match_score']; ?>%</span><br>
                                <small>Matches: <?php echo htmlspecialchars($match['found_item']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No potential matches found.</p>
                    <?php endif; ?>
                </div>

                <!-- Announcements -->
                <div class="card">
                    <h3><i class="fas fa-bullhorn"></i> Recent Announcements</h3>
                    <?php if($announcements): ?>
                        <?php foreach($announcements as $announce): ?>
                            <div class="activity-item">
                                <div class="activity-badge bg-info"></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($announce['title']); ?></strong><br>
                                    <span class="activity-date">
                                        <i class="far fa-calendar"></i> <?php echo date("M d, Y", strtotime($announce['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No announcements available.</p>
                    <?php endif; ?>
                </div>

                <!-- Notifications -->
                <div class="card">
                    <h3><i class="fas fa-bell"></i> Latest Notifications</h3>
                    <?php if($notifications): ?>
                        <?php foreach($notifications as $notif): ?>
                            <div class="activity-item">
                                <div class="activity-badge bg-warning"></div>
                                <div>
                                    <strong>Claim #<?php echo $notif['claim_id']; ?></strong>
                                    <span class="status-badge status-<?php echo strtolower($notif['status']); ?>">
                                        <?php echo $notif['status']; ?>
                                    </span><br>
                                    <span class="activity-date">
                                        <i class="far fa-calendar"></i> <?php echo date("M d, Y", strtotime($notif['date_claimed'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard specific styles - NO sidebar styles here! */
.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 25px;
}

.card h3 {
    margin-bottom: 20px;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card h3 i {
    color: #3b82f6;
}

.urgent-card h3 i {
    color: #ef4444;
}

.activity-date {
    font-size: 12px;
    color: #64748b;
}

.empty-message {
    color: #64748b;
    text-align: center;
    padding: 20px;
}

.match-item {
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid #10b981;
}

.match-score {
    background: #10b981;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    float: right;
}
</style>

<script>
// Chart.js for dashboard
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('itemsChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lost Items', 'Found Items', 'Claims'],
                datasets: [{
                    label: 'System Statistics',
                    data: [<?php echo $totalLost; ?>, <?php echo $totalFound; ?>, <?php echo $totalClaims; ?>],
                    backgroundColor: [
                        'rgba(255, 107, 107, 0.8)',
                        'rgba(30, 144, 255, 0.8)',
                        'rgba(76, 175, 80, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 107, 107, 1)',
                        'rgba(30, 144, 255, 1)',
                        'rgba(76, 175, 80, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            padding: 20, 
                            usePointStyle: true 
                        } 
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>