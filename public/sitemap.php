<?php
// START SESSION AT THE VERY BEGINNING
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use your existing config file
require_once '../config/config.php';

// Check login status
$isLoggedIn = isset($_SESSION['user_id']);

// Safely get user name if logged in
if ($isLoggedIn) {
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        $firstName = $_SESSION['first_name'];
        $lastName = $_SESSION['last_name'];
        $userName = trim($firstName . ' ' . $lastName);
    } else {
        try {
            $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $userName = trim($firstName . ' ' . $lastName);
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $user['email'] ?? '';
            } else {
                $userName = 'User';
                $isLoggedIn = false;
                session_destroy();
            }
        } catch (PDOException $e) {
            $userName = 'User';
        }
    }
    
    if (empty($userName)) {
        $userName = $_SESSION['email'] ?? $_SESSION['username'] ?? 'User';
    }
} else {
    $userName = '';
}

// Logo path
$logoPath = '../assets/images/lofims-logo.png';
$logoExists = file_exists($logoPath);

// Get total pages count for statistics
try {
    $totalPages = 45; // Approximate total pages in your system
    $completedPages = 38; // Pages already implemented
    $completionRate = round(($completedPages / $totalPages) * 100);
    
    $inProgressPages = 7;
    $plannedPages = 12;
    
    // Sitemap data structure
    $sitemapSections = [
        [
            'title' => 'Public Pages',
            'description' => 'Pages accessible to all visitors',
            'pages' => [
                ['name' => 'Homepage', 'url' => 'index.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Lost Items', 'url' => 'lost_items.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Found Items', 'url' => 'found_items.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Claims Portal', 'url' => 'claim_item.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Advanced Search', 'url' => 'search_results.php', 'status' => 'in-progress', 'priority' => 'Medium'],
                ['name' => 'About Us', 'url' => 'about.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'FAQ', 'url' => 'faq.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Contact', 'url' => 'contact.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Privacy Policy', 'url' => 'privacy.php', 'status' => 'planned', 'priority' => 'Low'],
                ['name' => 'Terms of Service', 'url' => 'terms.php', 'status' => 'planned', 'priority' => 'Low'],
            ]
        ],
        [
            'title' => 'User Dashboard',
            'description' => 'Pages for registered users',
            'pages' => [
                ['name' => 'User Login', 'url' => 'login.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'User Registration', 'url' => 'register.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'User Profile', 'url' => '../user_panel/profile/view.php', 'status' => 'in-progress', 'priority' => 'High'],
                ['name' => 'My Lost Items', 'url' => '../user_panel/items/lost/list.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'My Found Items', 'url' => '../user_panel/items/found/list.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'My Claims', 'url' => '../user_panel/claims/list.php', 'status' => 'in-progress', 'priority' => 'Medium'],
                ['name' => 'Notifications', 'url' => '../user_panel/notifications.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Settings', 'url' => '../user_panel/profile/edit.php', 'status' => 'planned', 'priority' => 'Low'],
            ]
        ],
        [
            'title' => 'Admin Panel',
            'description' => 'Administrative pages',
            'pages' => [
                ['name' => 'Admin Dashboard', 'url' => '../admin_panel/dashboard.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'User Management', 'url' => '../admin_panel/manage_user.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Item Management', 'url' => '../admin_panel/manage_items.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Claims Management', 'url' => '../admin_panel/claims.php', 'status' => 'in-progress', 'priority' => 'High'],
                ['name' => 'Announcements', 'url' => '../admin_panel/announcements.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Reports & Analytics', 'url' => '../admin_panel/reports.php', 'status' => 'planned', 'priority' => 'Low'],
                ['name' => 'System Settings', 'url' => '../admin_panel/categories.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Email/SMS Configuration', 'url' => '../config/email_config.php', 'status' => 'planned', 'priority' => 'Low'],
            ]
        ],
        [
            'title' => 'System Pages',
            'description' => 'Technical and configuration pages',
            'pages' => [
                ['name' => 'Sitemap (Current)', 'url' => 'sitemap.php', 'status' => 'in-progress', 'priority' => 'Medium'],
                ['name' => 'How It Works', 'url' => 'how_it_works.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Feedback System', 'url' => 'feedback.php', 'status' => 'planned', 'priority' => 'Low'],
                ['name' => 'Success Stories', 'url' => 'success_stories.php', 'status' => 'planned', 'priority' => 'Low'],
                ['name' => 'Support Center', 'url' => 'support.php', 'status' => 'planned', 'priority' => 'Low'],
                ['name' => 'Issue Reporting', 'url' => 'report_issue.php', 'status' => 'completed', 'priority' => 'Medium'],
                ['name' => 'Email Verification', 'url' => 'verify-email.php', 'status' => 'completed', 'priority' => 'High'],
                ['name' => 'Password Reset', 'url' => 'forgot_password_process.php', 'status' => 'completed', 'priority' => 'High'],
            ]
        ]
    ];
    
} catch (Exception $e) {
    $completionRate = 65;
    $totalPages = 45;
    $completedPages = 30;
    $inProgressPages = 8;
    $plannedPages = 7;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Site Map - LoFIMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" href="<?php echo $logoPath; ?>" type="image/png">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f5f9ff; color:#333; }

/* HEADER - Same as index.php */
header {
    width:100%;
    padding:12px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}
.logo-container {
    display:flex;
    align-items:center;
    gap:12px;
    font-weight:bold;
    color:#0a3d62;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    flex-shrink: 0;
}
.logo-container:hover {
    transform: translateX(3px);
}
.logo-container img {
    height:55px;
    width:55px;
    border-radius: 16px;
    transition: transform 0.3s, box-shadow 0.3s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    object-fit: cover;
    border: 3px solid white;
    padding: 4px;
    background: white;
}
.logo-container:hover img { 
    transform: rotate(4deg) scale(1.05); 
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    border-radius: 18px;
}
.logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}
.logo-text .logo-title {
    font-weight: 800;
    color: #0a3d62;
    font-size: 20px;
}
.logo-text .logo-subtitle {
    font-size: 11px;
    color: #1e90ff;
    font-weight: 600;
}

/* NAVIGATION */
nav ul {
    list-style:none;
    display:flex;
    gap:20px;
    align-items:center;
}
nav ul li a {
    text-decoration:none;
    font-size:14px;
    color:#0a3d62;
    font-weight:500;
    position: relative;
    padding: 6px 0;
    transition: color 0.3s;
}
nav ul li a:hover { color:#1e90ff; }
nav ul li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: #1e90ff;
    transition: width 0.3s;
}
nav ul li a:hover::after { width: 100%; }

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    font-size: 13px;
    color: #0a3d62;
}
.user-info i {
    color: #1e90ff;
}

.nav-btn {
    padding: 8px 18px;
    background: linear-gradient(45deg,#1e90ff,#4facfe);
    color: white;
    font-size: 13px;
    font-weight: bold;
    border-radius: 10px;
    border: 2px solid rgba(30,144,255,0.8);
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    text-decoration: none;
    display: inline-block;
}
.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    color: white;
}

/* UNDER DEVELOPMENT BANNER */
.dev-banner {
    background: linear-gradient(90deg, #ff6b6b, #ff8e53);
    color: white;
    text-align: center;
    padding: 20px;
    margin: 20px auto;
    max-width: 1200px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(255,107,107,0.3);
    animation: pulse 2s infinite;
    border: 3px solid white;
    position: relative;
    overflow: hidden;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); box-shadow: 0 8px 25px rgba(255,107,107,0.3); }
    50% { transform: scale(1.01); box-shadow: 0 10px 30px rgba(255,107,107,0.4); }
}
.dev-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shine 3s infinite;
}
@keyframes shine {
    0% { left: -100%; }
    100% { left: 100%; }
}
.dev-banner h2 {
    font-size: 28px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}
.dev-banner p {
    font-size: 16px;
    opacity: 0.9;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* MAIN CONTENT */
.main-container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
}
.page-header h1 {
    font-size: 42px;
    color: #0a3d62;
    margin-bottom: 15px;
    position: relative;
    display: inline-block;
}
.page-header h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    border-radius: 2px;
}
.page-header p {
    color: #666;
    font-size: 18px;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* DEVELOPMENT STATS */
.dev-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 50px;
}
.stat-card-dev {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
    border-top: 5px solid #1e90ff;
}
.stat-card-dev:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
}
.stat-number-dev {
    font-size: 48px;
    font-weight: 900;
    color: #0a3d62;
    margin-bottom: 10px;
}
.stat-label-dev {
    font-size: 18px;
    color: #666;
    font-weight: 500;
}
.stat-card-dev i {
    font-size: 40px;
    color: #1e90ff;
    margin-bottom: 15px;
}

/* PROGRESS BAR */
.progress-container {
    background: #f0f4ff;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 40px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.progress-header h3 {
    color: #0a3d62;
    font-size: 22px;
}
.progress-percentage {
    font-size: 24px;
    font-weight: 700;
    color: #10b981;
    background: rgba(16,185,129,0.1);
    padding: 8px 20px;
    border-radius: 30px;
}
.progress-bar {
    height: 20px;
    background: #e0e7ff;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    border-radius: 10px;
    width: <?php echo $completionRate; ?>%;
    transition: width 1.5s ease-in-out;
    position: relative;
    overflow: hidden;
}
.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}
.progress-labels {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #666;
}
.progress-label {
    display: flex;
    align-items: center;
    gap: 8px;
}
.progress-label i {
    font-size: 12px;
}

/* SITEMAP SECTIONS */
.sitemap-container {
    display: flex;
    flex-direction: column;
    gap: 40px;
    margin-bottom: 60px;
}
.section-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
}
.section-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
}
.section-header {
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    color: white;
    padding: 25px 30px;
    border-bottom: 3px solid rgba(255,255,255,0.2);
}
.section-header h3 {
    font-size: 24px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.section-header p {
    opacity: 0.9;
    font-size: 15px;
}

/* PAGES LIST */
.pages-list {
    padding: 0;
}
.page-item {
    display: flex;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.3s;
}
.page-item:last-child {
    border-bottom: none;
}
.page-item:hover {
    background: #f8fbff;
}
.page-info {
    flex: 1;
}
.page-name {
    font-weight: 600;
    color: #0a3d62;
    font-size: 17px;
    margin-bottom: 5px;
}
.page-url {
    color: #666;
    font-size: 14px;
    font-family: monospace;
    background: #f5f5f5;
    padding: 4px 10px;
    border-radius: 5px;
    display: inline-block;
}

/* STATUS BADGES */
.status-badge {
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-right: 15px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.status-completed {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.3);
}
.status-in-progress {
    background: rgba(249,115,22,0.1);
    color: #f97316;
    border: 1px solid rgba(249,115,22,0.3);
}
.status-planned {
    background: rgba(59,130,246,0.1);
    color: #3b82f6;
    border: 1px solid rgba(59,130,246,0.3);
}

.priority-badge {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}
.priority-high {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}
.priority-medium {
    background: rgba(249,115,22,0.1);
    color: #f97316;
}
.priority-low {
    background: rgba(59,130,246,0.1);
    color: #3b82f6;
}

/* LEGEND */
.legend {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    margin-bottom: 40px;
}
.legend h4 {
    color: #0a3d62;
    margin-bottom: 20px;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}
.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 50%;
}
.legend-text {
    font-size: 15px;
    color: #666;
}

/* FOOTER */
footer {
    width:100%;
    padding:50px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
    border-top:2px solid #b0c4de;
    border-radius:18px 18px 0 0;
    margin-top:60px;
    color:#0a3d62;
}
.footer-content {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 35px;
    margin-bottom: 35px;
}
.footer-section h3 {
    font-size: 18px;
    margin-bottom: 18px;
    color: #0a3d62;
    border-bottom: 2px solid #1e90ff;
    padding-bottom: 8px;
}
.footer-links {
    list-style: none;
}
.footer-links li {
    margin-bottom: 10px;
}
.footer-links a {
    color: #0a3d62;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.footer-links a:hover {
    color: #1e90ff;
    transform: translateX(4px);
}
.copyright {
    text-align: center;
    margin-top: 35px;
    padding-top: 18px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 13px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    header {
        padding: 12px 20px;
    }
    .dev-banner h2 {
        font-size: 22px;
    }
    .dev-banner p {
        font-size: 14px;
    }
    .page-header h1 {
        font-size: 32px;
    }
    .section-header {
        padding: 20px;
    }
    .page-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .legend-items {
        flex-direction: column;
        gap: 15px;
    }
    .dev-stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <a href="index.php" class="logo-container">
        <?php if ($logoExists): ?>
            <img src="<?php echo $logoPath; ?>" alt="LoFIMS Logo">
        <?php else: ?>
            <div class="logo-fallback" style="width:55px;height:55px;background:#1e90ff;border-radius:16px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">LF</div>
        <?php endif; ?>
        <div class="logo-text">
            <span class="logo-title">LoFIMS</span>
            <span class="logo-subtitle">Lost & Found Management System</span>
        </div>
    </a>
    
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
            <li><a href="sitemap.php" style="color:#1e90ff;"><i class="fas fa-sitemap"></i> Sitemap</a></li>
            
            <?php if ($isLoggedIn): ?>
                <li class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </li>
                <li><a href="logout.php" class="nav-btn">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-btn">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- UNDER DEVELOPMENT BANNER -->
<div class="dev-banner">
    <h2><i class="fas fa-tools"></i> Under Active Development</h2>
    <p>This sitemap shows the current structure of LoFIMS. Some pages are still being developed and will be available soon. Check back regularly for updates!</p>
</div>

<!-- MAIN CONTENT -->
<div class="main-container">
    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>LoFIMS Sitemap</h1>
        <p>A comprehensive overview of all pages in the Lost and Found Management System. This map helps you navigate the system structure and understand what's available.</p>
    </div>

    <!-- DEVELOPMENT STATISTICS -->
    <div class="dev-stats">
        <div class="stat-card-dev">
            <i class="fas fa-file-code"></i>
            <div class="stat-number-dev"><?php echo $totalPages; ?></div>
            <div class="stat-label-dev">Total Pages Planned</div>
        </div>
        <div class="stat-card-dev">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number-dev"><?php echo $completedPages; ?></div>
            <div class="stat-label-dev">Pages Completed</div>
        </div>
        <div class="stat-card-dev">
            <i class="fas fa-code-branch"></i>
            <div class="stat-number-dev"><?php echo $inProgressPages; ?></div>
            <div class="stat-label-dev">In Development</div>
        </div>
        <div class="stat-card-dev">
            <i class="fas fa-tasks"></i>
            <div class="stat-number-dev"><?php echo $plannedPages; ?></div>
            <div class="stat-label-dev">Planned Features</div>
        </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="progress-container">
        <div class="progress-header">
            <h3><i class="fas fa-chart-line"></i> Overall Development Progress</h3>
            <div class="progress-percentage"><?php echo $completionRate; ?>%</div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $completionRate; ?>%"></div>
        </div>
        <div class="progress-labels">
            <div class="progress-label"><i class="fas fa-circle" style="color:#10b981;"></i> Completed (<?php echo $completedPages; ?>)</div>
            <div class="progress-label"><i class="fas fa-circle" style="color:#f97316;"></i> In Progress (<?php echo $inProgressPages; ?>)</div>
            <div class="progress-label"><i class="fas fa-circle" style="color:#3b82f6;"></i> Planned (<?php echo $plannedPages; ?>)</div>
        </div>
    </div>

    <!-- LEGEND -->
    <div class="legend">
        <h4><i class="fas fa-key"></i> Status Legend</h4>
        <div class="legend-items">
            <div class="legend-item">
                <div class="legend-color" style="background:#10b981;"></div>
                <span class="legend-text"><strong>Completed</strong> - Page is fully functional and ready for use</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background:#f97316;"></div>
                <span class="legend-text"><strong>In Progress</strong> - Page is being developed/improved</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background:#3b82f6;"></div>
                <span class="legend-text"><strong>Planned</strong> - Page is planned for future development</span>
            </div>
        </div>
    </div>

    <!-- SITEMAP SECTIONS -->
    <div class="sitemap-container">
        <?php foreach ($sitemapSections as $section): ?>
        <div class="section-card">
            <div class="section-header">
                <h3><i class="fas fa-folder"></i> <?php echo $section['title']; ?></h3>
                <p><?php echo $section['description']; ?></p>
            </div>
            <div class="pages-list">
                <?php foreach ($section['pages'] as $page): 
                    $statusClass = 'status-' . str_replace(' ', '-', $page['status']);
                    $priorityClass = 'priority-' . strtolower($page['priority']);
                ?>
                <div class="page-item">
                    <div class="page-info">
                        <div class="page-name"><?php echo $page['name']; ?></div>
                        <div class="page-url"><?php echo $page['url']; ?></div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php if ($page['status'] == 'completed'): ?>
                                <i class="fas fa-check"></i> Completed
                            <?php elseif ($page['status'] == 'in-progress'): ?>
                                <i class="fas fa-code"></i> In Progress
                            <?php else: ?>
                                <i class="fas fa-clock"></i> Planned
                            <?php endif; ?>
                        </span>
                        <span class="priority-badge <?php echo $priorityClass; ?>">
                            <?php echo $page['priority']; ?> Priority
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- NOTES -->
    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 40px;">
        <h4 style="color: #856404; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle"></i> Development Notes
        </h4>
        <p style="color: #856404; line-height: 1.6;">
            <strong>Current Focus:</strong> Completing the claims management system and user dashboard enhancements.<br>
            <strong>Next Release:</strong> Version 2.1 will include enhanced search functionality and mobile optimizations.<br>
            <strong>Timeline:</strong> All planned features are scheduled for completion within the next 3 months.
        </p>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Navigation</h3>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost Items</a></li>
                <li><a href="found_items.php"><i class="fas fa-box"></i> Found Items</a></li>
                <li><a href="sitemap.php"><i class="fas fa-sitemap"></i> Sitemap</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Development</h3>
            <ul class="footer-links">
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Give Feedback</a></li>
                <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> System Architecture</a></li>
                <li><a href="changelog.php"><i class="fas fa-history"></i> Changelog</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Status</h3>
            <ul class="footer-links">
                <li><i class="fas fa-server"></i> System: <span style="color:#10b981;">Operational</span></li>
                <li><i class="fas fa-code"></i> Version: 2.0.1</li>
                <li><i class="fas fa-calendar"></i> Last Updated: <?php echo date('M d, Y'); ?></li>
                <li><i class="fas fa-tasks"></i> Progress: <?php echo $completionRate; ?>% Complete</li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. This sitemap is updated regularly as development progresses.<br>
        <small>Page generated on <?php echo date('F j, Y \a\t g:i A'); ?></small>
    </div>
</footer>

<script>
// Animated progress bar on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bar
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        const targetWidth = progressFill.style.width;
        progressFill.style.width = '0%';
        
        setTimeout(() => {
            progressFill.style.transition = 'width 1.5s ease-in-out';
            progressFill.style.width = targetWidth;
        }, 500);
    }
    
    // Add click events to page items
    const pageItems = document.querySelectorAll('.page-item');
    pageItems.forEach(item => {
        item.addEventListener('click', function() {
            const url = this.querySelector('.page-url').textContent;
            const statusBadge = this.querySelector('.status-badge');
            const statusText = statusBadge.textContent.trim();
            
            // Don't navigate for planned pages
            if (statusText.includes('Planned') || statusText.includes('In Progress')) {
                alert('This page is still under development. Please check back soon!');
            } else if (statusText.includes('Completed')) {
                // For demo purposes - in real system, this would navigate
                console.log('Navigating to:', url);
                // window.location.href = url;
            }
        });
    });
    
    // Auto-update time every minute
    function updateTime() {
        const timeElements = document.querySelectorAll('.auto-time');
        const now = new Date();
        timeElements.forEach(el => {
            el.textContent = now.toLocaleTimeString();
        });
    }
    setInterval(updateTime, 60000);
});
</script>
</body>
</html>