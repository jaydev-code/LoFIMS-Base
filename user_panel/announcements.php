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

// Fetch announcements - SIMPLIFIED FOR YOUR DATABASE STRUCTURE
try {
    // Get all announcements (no target filtering since column doesn't exist)
    $stmt = $pdo->query("
        SELECT * FROM announcements 
        ORDER BY created_at DESC
    ");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total announcements
    $total_announcements = count($announcements);
    
    // Count today's announcements
    $today = date('Y-m-d');
    $today_count = 0;
    foreach($announcements as $announce) {
        if(date('Y-m-d', strtotime($announce['created_at'])) == $today) {
            $today_count++;
        }
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Announcements - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Additional styles for announcements page */
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
        
        .announcements-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            border-left: 5px solid #8b5cf6;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .announcement-card.recent {
            background: #f8f7ff;
            border-left-color: #ef4444;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .announcement-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .announcement-meta {
            display: flex;
            gap: 15px;
            color: #64748b;
            font-size: 14px;
        }
        
        .announcement-meta i {
            margin-right: 5px;
        }
        
        .announcement-content {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 15px;
            white-space: pre-line;
        }
        
        .announcement-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .announcement-badge.recent {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .announcement-badge.old {
            background: #d1fae5;
            color: #065f46;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            background: white;
            border-radius: 12px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .empty-state h3 {
            margin: 15px 0;
            color: #475569;
            font-size: 22px;
        }
        
        .empty-state p {
            margin-bottom: 25px;
            font-size: 16px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #8b5cf6;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .search-bar-custom {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 10px;
            padding: 10px 18px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            flex: 0 1 350px;
            border: 1px solid #cbd5e1;
        }
        
        .search-bar-custom input {
            border: none;
            outline: none;
            padding: 8px 12px;
            flex: 1;
            font-size: 15px;
            background: transparent;
            color: #475569;
        }
        
        .search-bar-custom i {
            color: #64748b;
            cursor: pointer;
            font-size: 16px;
        }
        
        .notification-badge {
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
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
    <a href="profile/view.php" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 10px; cursor: pointer;">
        <i class="fas fa-user-circle"></i> 
        Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
    </a>
</div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search announcements...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-bullhorn"></i>
            Announcements
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='announcements.php'">
                <div class="stat-number"><?php echo $total_announcements; ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $today_count; ?></div>
                <div class="stat-label">Today's Announcements</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $recent_count = 0;
                    foreach($announcements as $announce) {
                        if(strtotime($announce['created_at']) > strtotime('-7 days')) {
                            $recent_count++;
                        }
                    }
                    echo $recent_count;
                    ?>
                </div>
                <div class="stat-label">Last 7 Days</div>
            </div>
            <div class="stat-card" onclick="window.print()">
                <div class="stat-number"><i class="fas fa-print"></i></div>
                <div class="stat-label">Print Announcements</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-buttons">
                <button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print All
                </button>
            </div>
            
            <div class="search-bar-custom">
                <input type="text" id="searchAnnouncements" placeholder="Search announcements...">
                <i class="fas fa-search" id="searchAnnouncementsIcon"></i>
            </div>
        </div>

        <!-- Announcements List -->
        <?php if($announcements): ?>
        <div class="announcements-grid" id="announcementsList">
            <?php foreach($announcements as $announce): 
                // Check if announcement is recent (last 24 hours)
                $is_recent = (strtotime($announce['created_at']) > strtotime('-24 hours'));
                $card_class = $is_recent ? 'announcement-card recent' : 'announcement-card';
                $badge_class = $is_recent ? 'recent' : 'old';
                $badge_text = $is_recent ? 'NEW' : date('M d, Y', strtotime($announce['created_at']));
            ?>
            <div class="<?php echo $card_class; ?>" 
                 data-title="<?php echo htmlspecialchars(strtolower($announce['title'])); ?>" 
                 data-content="<?php echo htmlspecialchars(strtolower($announce['content'])); ?>">
                <div class="announcement-header">
                    <div>
                        <h3 class="announcement-title"><?php echo htmlspecialchars($announce['title']); ?></h3>
                        <div class="announcement-meta">
                            <span><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($announce['created_at'])); ?></span>
                            <span><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($announce['created_at'])); ?></span>
                        </div>
                    </div>
                    <span class="announcement-badge <?php echo $badge_class; ?>">
                        <?php echo $badge_text; ?>
                    </span>
                </div>
                
                <div class="announcement-content">
                    <?php echo nl2br(htmlspecialchars($announce['content'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <h3>No Announcements</h3>
            <p>There are no announcements at the moment. Check back later for updates.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include the common footer with JavaScript -->
<?php require_once 'includes/footer.php'; ?>

<script>
// Search functionality for announcements
const searchAnnouncementsInput = document.getElementById('searchAnnouncements');
const searchAnnouncementsIcon = document.getElementById('searchAnnouncementsIcon');

function searchAnnouncements() {
    const searchTerm = searchAnnouncementsInput.value.toLowerCase().trim();
    const announcementCards = document.querySelectorAll('.announcement-card');
    
    if (!searchTerm) {
        // Show all if search is empty
        announcementCards.forEach(card => {
            card.style.display = 'block';
        });
        return;
    }
    
    announcementCards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const content = card.getAttribute('data-content') || '';
        
        if (title.includes(searchTerm) || content.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

if (searchAnnouncementsInput) {
    searchAnnouncementsInput.addEventListener('input', searchAnnouncements);
    
    searchAnnouncementsInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchAnnouncements();
        }
    });
}

if (searchAnnouncementsIcon) {
    searchAnnouncementsIcon.addEventListener('click', searchAnnouncements);
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', function() {
    searchAnnouncements();
});
</script>
</body>
</html>