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
    $urgentItems = $pdo->prepare("
        SELECT li.*, ic.category_name
        FROM lost_items li
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.user_id = ?
        AND li.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND li.status = 'Lost'
        ORDER BY li.created_at DESC LIMIT 5
    ");
    $urgentItems->execute([$_SESSION['user_id']]);
    $urgentItems = $urgentItems->fetchAll(PDO::FETCH_ASSOC);

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
        AND f.user_id != ?
        ORDER BY match_score DESC LIMIT 3
    ");
    $potentialMatches->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
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

<style>
    /* ANIMATIONS FOR USER GUIDE */
    @keyframes slideInDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutUp {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(-20px);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
        40% {transform: translateY(-10px);}
        60% {transform: translateY(-5px);}
    }
    
    @keyframes shimmer {
        0% { background-position: -200px 0; }
        100% { background-position: calc(200px + 100%) 0; }
    }
    
    /* User Guide Styles with Animations */
    .user-guide-container {
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .simple-guide-section {
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
        animation: slideInDown 0.5s ease-out;
    }
    
    .simple-guide-section.hiding {
        animation: slideOutUp 0.3s ease-out forwards;
    }
    
    .simple-guide-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #ef4444, #10b981, #3b82f6, #8b5cf6);
        background-size: 200% 100%;
        animation: shimmer 3s infinite linear;
    }
    
    .guide-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e2e8f0;
        animation: fadeIn 0.6s ease-out 0.2s both;
    }
    
    .guide-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .guide-title i {
        color: #3b82f6;
        font-size: 22px;
        animation: bounce 2s infinite;
    }
    
    .guide-title h3 {
        margin: 0;
        color: #1e293b;
        font-size: 18px;
        font-weight: 600;
    }
    
    #hideGuideBtn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        color: #64748b;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    #hideGuideBtn:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    }
    
    .guide-steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
        animation: fadeIn 0.8s ease-out 0.4s both;
    }
    
    .guide-step-card {
        background: white;
        padding: 18px;
        border-radius: 10px;
        border-left: 4px solid;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .guide-step-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    
    .guide-step-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
        transform: translateX(-100%);
        transition: transform 0.6s;
    }
    
    .guide-step-card:hover::after {
        transform: translateX(100%);
    }
    
    .step-number {
        width: 28px;
        height: 28px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 13px;
        margin-bottom: 12px;
        animation: pulse 2s infinite;
    }
    
    .step-content {
        position: relative;
        z-index: 1;
    }
    
    .step-title {
        color: #1e293b;
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .step-description {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 12px;
    }
    
    .step-action {
        background: linear-gradient(135deg, currentColor, currentColor);
        color: white;
        padding: 6px 12px;
        border-radius: 5px;
        text-decoration: none;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .step-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        filter: brightness(110%);
    }
    
    /* Color variations for each step */
    .step-1 { border-left-color: #ef4444; }
    .step-1 .step-action { background: linear-gradient(135deg, #ef4444, #dc2626); }
    
    .step-2 { border-left-color: #10b981; }
    .step-2 .step-action { background: linear-gradient(135deg, #10b981, #059669); }
    
    .step-3 { border-left-color: #3b82f6; }
    .step-3 .step-action { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    
    .step-4 { border-left-color: #8b5cf6; }
    .step-4 .step-action { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    
    /* Show Guide Button Animation */
    #showGuideBtn {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        border: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    #showGuideBtn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(59, 130, 246, 0.4);
    }
    
    #showGuideBtn:active {
        transform: translateY(-1px);
    }
    
    #showGuideBtn::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }
    
    #showGuideBtn:hover::after {
        left: 100%;
    }
    
    /* Floating animation for new users */
    .new-user-pulse {
        animation: pulse 1.5s infinite;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .guide-steps-grid {
            grid-template-columns: 1fr;
        }
        
        .simple-guide-section {
            padding: 15px;
        }
        
        .guide-step-card {
            padding: 15px;
        }
    }
</style>

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
            <!-- SHOW GUIDE BUTTON with pulse animation for new users -->
            <button id="showGuideBtn" class="new-user-pulse">
                <i class="fas fa-graduation-cap"></i> Show User Guide
            </button>
        </div>

        <!-- USER GUIDE SECTION with animations -->
        <div class="user-guide-container">
            <div class="simple-guide-section" id="userGuideSection" style="display: none;">
                <div class="guide-header">
                    <div class="guide-title">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Quick Start Guide</h3>
                    </div>
                    <button id="hideGuideBtn">
                        <i class="fas fa-times"></i> Hide Guide
                    </button>
                </div>
                
                <div class="guide-steps-grid">
                    <!-- Step 1 -->
                    <div class="guide-step-card step-1">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Lost an Item?</span>
                            </div>
                            <p class="step-description">Report it immediately to increase recovery chances.</p>
                            <a href="lost_items.php" class="step-action">
                                <i class="fas fa-plus"></i> Report Lost
                            </a>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="guide-step-card step-2">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">
                                <i class="fas fa-check-circle"></i>
                                <span>Found an Item?</span>
                            </div>
                            <p class="step-description">Help others by reporting found items.</p>
                            <a href="found_items.php" class="step-action">
                                <i class="fas fa-plus"></i> Report Found
                            </a>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="guide-step-card step-3">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">
                                <i class="fas fa-search"></i>
                                <span>Search Items</span>
                            </div>
                            <p class="step-description">Browse found items to find your belongings.</p>
                            <a href="found_items.php" class="step-action">
                                <i class="fas fa-search"></i> Browse Items
                            </a>
                        </div>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="guide-step-card step-4">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">
                                <i class="fas fa-handshake"></i>
                                <span>File a Claim</span>
                            </div>
                            <p class="step-description">Found a match? File a claim to get your item back.</p>
                            <a href="claims.php" class="step-action">
                                <i class="fas fa-handshake"></i> File Claim
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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
                    <h3><i class="fas fa-exclamation-triangle"></i> My Urgent Items</h3>
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

<!-- ENHANCED USER GUIDE JAVASCRIPT WITH ANIMATIONS -->
<script>
// ===== ENHANCED USER GUIDE FUNCTIONALITY WITH ANIMATIONS =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading with animations...');
    
    // Get guide elements
    var guide = document.getElementById('userGuideSection');
    var showBtn = document.getElementById('showGuideBtn');
    var hideBtn = document.getElementById('hideGuideBtn');
    
    // If guide elements don't exist, skip
    if (!guide || !showBtn || !hideBtn) {
        console.log('Guide elements not found');
        return;
    }
    
    console.log('All guide elements found');
    
    // Check if guide was hidden before
    var isHidden = localStorage.getItem('guideHidden') === 'true';
    console.log('Guide was hidden before?', isHidden);
    
    // Set initial state - ALWAYS HIDDEN on page load
    guide.style.display = 'none';
    
    // Remove pulse animation from show button after 10 seconds
    setTimeout(function() {
        showBtn.classList.remove('new-user-pulse');
    }, 10000);
    
    // Add click event to SHOW button with animation
    showBtn.addEventListener('click', function() {
        console.log('Show button clicked');
        
        // Add click animation to button
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
        
        // Remove pulse animation
        this.classList.remove('new-user-pulse');
        
        // Show guide with animation
        guide.style.display = 'block';
        guide.classList.remove('hiding');
        
        // Save state
        localStorage.setItem('guideHidden', 'false');
        
        console.log('Guide SHOWN with animation');
        
        // Scroll to guide smoothly
        setTimeout(() => {
            guide.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }, 100);
    });
    
    // Add click event to HIDE button with animation
    hideBtn.addEventListener('click', function() {
        console.log('Hide button clicked');
        
        // Add click animation to button
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
        
        // Hide guide with animation
        guide.classList.add('hiding');
        
        // Wait for animation to complete before hiding
        setTimeout(() => {
            guide.style.display = 'none';
            guide.classList.remove('hiding');
        }, 300); // Match animation duration
        
        // Save state
        localStorage.setItem('guideHidden', 'true');
        
        console.log('Guide HIDDEN with animation');
    });
    
    // Add hover effect to guide cards
    var guideCards = document.querySelectorAll('.guide-step-card');
    guideCards.forEach(function(card, index) {
        // Add staggered animation delay
        card.style.animationDelay = (index * 0.1) + 's';
        
        // Add hover sound effect simulation
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    console.log('Enhanced guide functionality with animations ready');
});

// ===== CHART.JS FOR DASHBOARD =====
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
                cutout: '65%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>