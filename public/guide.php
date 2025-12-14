<?php
// START SESSION AT THE VERY BEGINNING
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use your existing config file
require_once '../config/config.php';

// Check login status
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : '';

// Logo path
$logoPath = '../assets/images/lofims-logo.png';
$logoExists = file_exists($logoPath);

// Get statistics for footer
try {
    $lostCount = $pdo->query("SELECT COUNT(*) as count FROM lost_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $foundCount = $pdo->query("SELECT COUNT(*) as count FROM found_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $categoriesCount = $pdo->query("SELECT COUNT(*) as count FROM item_categories")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $usersCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $dbError = false;
} catch (PDOException $e) {
    $lostCount = $foundCount = $categoriesCount = $usersCount = 0;
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Guide - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

/* HEADER - WITH LOGO */
header {
    width:100%;
    padding:15px 40px;
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
    gap:15px;
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
/* LOGO WITH BORDER RADIUS */
.logo-container img {
    height:60px;
    width:60px;
    border-radius: 18px;
    transition: transform 0.3s, box-shadow 0.3s, border-radius 0.3s;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    object-fit: cover;
    border: 3px solid white;
    padding: 5px;
    background: white;
}
.logo-container:hover img { 
    transform: rotate(5deg) scale(1.08); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    border-radius: 20px;
}
.logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
    min-width: 0;
}
.logo-text .logo-title {
    font-weight: 800;
    color: #0a3d62;
    font-size: 24px;
    letter-spacing: 0.3px;
    white-space: nowrap;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}
.logo-text .logo-subtitle {
    font-size: 12px;
    color: #1e90ff;
    font-weight: 600;
    letter-spacing: 0.2px;
    white-space: nowrap;
}
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
    white-space: nowrap;
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
.login-btn {
    padding:10px 25px;
    background:linear-gradient(45deg,#1e90ff,#4facfe,#1e90ff);
    background-size:300% 300%;
    color:white;
    font-size:14px;
    font-weight:bold;
    border-radius:12px;
    border:2px solid rgba(30,144,255,0.8);
    cursor:pointer;
    backdrop-filter:blur(8px);
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    animation:gradientShift 3s ease infinite;
    transition:transform 0.3s, box-shadow 0.3s;
}
.login-btn:hover {
    transform:translateY(-4px);
    box-shadow:0 12px 25px rgba(0,0,0,0.25);
}
@keyframes gradientShift {
    0% { background-position:0% 50%; }
    50% { background-position:100% 50%; }
    100% { background-position:0% 50%; }
}

/* USER INFO IN NAV */
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    font-size: 13px;
    color: #0a3d62;
    white-space: nowrap;
}
.user-info i {
    color: #1e90ff;
}

/* LOGO FALLBACK STYLE */
.logo-fallback {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #1e90ff, #4facfe);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    border: 3px solid white;
    transition: transform 0.3s, box-shadow 0.3s;
}
.logo-container:hover .logo-fallback {
    transform: rotate(4deg) scale(1.05);
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    border-radius: 20px;
}

/* MAIN CONTENT */
.content-wrapper {
    animation:bgGradient 15s ease infinite;
    background:linear-gradient(-45deg,#e0f0ff,#f9faff,#d0e8ff,#cce0ff);
    background-size:400% 400%;
    padding:100px 20px 80px;
    text-align: center;
}
@keyframes bgGradient { 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }

.page-header {
    max-width: 800px;
    margin: 0 auto;
}

.page-title {
    font-size: 48px;
    font-weight: 800;
    color: #0a3d62;
    margin-bottom: 20px;
    background: linear-gradient(45deg, #0a3d62, #1e90ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.page-subtitle {
    font-size: 20px;
    color: #555;
    margin-bottom: 40px;
}

/* QUICK NAV */
.quick-nav {
    max-width: 1000px;
    margin: 0 auto 40px;
    padding: 0 20px;
}

.quick-nav h3 {
    color: #0a3d62;
    font-size: 22px;
    margin-bottom: 20px;
    text-align: center;
}

.nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.nav-card {
    background: rgba(52,152,219,0.8);
    padding: 15px;
    border-radius: 12px;
    color: white;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.nav-card:hover {
    background: rgba(41,128,185,0.9);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    text-decoration: none;
}

.nav-card i {
    font-size: 24px;
}

.nav-card span {
    font-size: 14px;
    font-weight: 500;
}

/* GUIDE CONTAINER */
.guide-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* TABLE OF CONTENTS */
.toc-container {
    background: rgba(255,255,255,0.95);
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-left: 5px solid #1e90ff;
}

.toc-container h2 {
    color: #0a3d62;
    font-size: 24px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.toc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.toc-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    border-radius: 12px;
    transition: all 0.3s;
    text-decoration: none;
    color: #555;
    background: #f8f9fa;
    border: 2px solid transparent;
}

.toc-item:hover {
    background: #e3f2fd;
    color: #0a3d62;
    transform: translateX(5px);
    border-color: #1e90ff;
    text-decoration: none;
}

.toc-number {
    width: 35px;
    height: 35px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    flex-shrink: 0;
    font-size: 14px;
}

.toc-content h3 {
    color: #0a3d62;
    font-size: 16px;
    margin-bottom: 5px;
}

.toc-content p {
    color: #666;
    font-size: 13px;
    line-height: 1.5;
}

/* GUIDE SECTIONS */
.guide-section {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transition: transform 0.3s;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.guide-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.12);
    border-color: #e3f2fd;
}

.guide-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.section-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.section-icon i {
    font-size: 28px;
    color: white;
}

.section-title {
    color: #0a3d62;
    font-size: 28px;
    margin: 0;
    flex: 1;
}

.section-subtitle {
    color: #1e90ff;
    font-size: 16px;
    margin: 5px 0 0;
    font-weight: 500;
}

.guide-section h3 {
    color: #0a3d62;
    font-size: 20px;
    margin: 30px 0 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e3f2fd;
}

.guide-section p {
    color: #555;
    line-height: 1.8;
    margin-bottom: 15px;
    font-size: 15px;
}

.guide-section ul, .guide-section ol {
    margin-left: 25px;
    margin-bottom: 20px;
}

.guide-section li {
    color: #555;
    line-height: 1.7;
    margin-bottom: 10px;
    padding-left: 5px;
    position: relative;
}

.guide-section li::before {
    content: '•';
    color: #1e90ff;
    font-weight: bold;
    position: absolute;
    left: -15px;
}

/* SPECIAL BOXES */
.step-box, .tip-box, .warning-box, .important-box {
    padding: 25px;
    border-radius: 15px;
    margin: 20px 0;
    border-left: 5px solid;
    position: relative;
    overflow: hidden;
}

.step-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-left-color: #1e90ff;
}

.tip-box {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border-left-color: #ffc107;
}

.warning-box {
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    border-left-color: #f44336;
}

.important-box {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    border-left-color: #4caf50;
}

.box-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.box-header i {
    font-size: 24px;
}

.box-header h4 {
    margin: 0;
    font-size: 18px;
    color: inherit;
}

.step-box .box-header i { color: #1e90ff; }
.step-box .box-header h4 { color: #0a3d62; }

.tip-box .box-header i { color: #ff9800; }
.tip-box .box-header h4 { color: #7d6608; }

.warning-box .box-header i { color: #f44336; }
.warning-box .box-header h4 { color: #721c24; }

.important-box .box-header i { color: #4caf50; }
.important-box .box-header h4 { color: #2e7d32; }

/* VISUAL GUIDES */
.visual-guide {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.visual-item {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
    border: 2px solid #e9ecef;
    transition: all 0.3s;
}

.visual-item:hover {
    transform: translateY(-5px);
    border-color: #1e90ff;
    box-shadow: 0 10px 20px rgba(30,144,255,0.1);
}

.visual-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.visual-icon i {
    font-size: 30px;
    color: white;
}

.visual-item h5 {
    color: #0a3d62;
    margin-bottom: 10px;
    font-size: 16px;
}

.visual-item p {
    color: #666;
    font-size: 13px;
    line-height: 1.5;
}

/* QUICK ACTIONS */
.quick-actions {
    display: flex;
    gap: 15px;
    margin: 40px 0;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    padding: 12px 25px;
    background: #1e90ff;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.action-btn:hover {
    background: #0d7bd4;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}

.action-btn.secondary {
    background: transparent;
    color: #0a3d62;
    border: 2px solid #0a3d62;
}

.action-btn.secondary:hover {
    background: rgba(10,61,98,0.1);
}

/* DOWNLOAD SECTION */
.download-section {
    text-align: center;
    padding: 40px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 20px;
    margin: 40px 0;
    position: relative;
    overflow: hidden;
}

.download-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}

.download-section i {
    font-size: 60px;
    color: #1e90ff;
    margin-bottom: 20px;
}

.download-section h3 {
    color: #0a3d62;
    font-size: 24px;
    margin-bottom: 15px;
}

.download-section p {
    color: #555;
    font-size: 16px;
    margin-bottom: 25px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.download-btn {
    background: white;
    color: #1e90ff;
    border: 2px solid #1e90ff;
    padding: 15px 30px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.download-btn:hover {
    background: #1e90ff;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(30,144,255,0.2);
}

/* HOMEPAGE FOOTER STYLES */
footer {
    width:100%;
    padding:60px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
    backdrop-filter:blur(8px);
    border-top:2px solid #b0c4de;
    border-radius:20px 20px 0 0;
    margin-top:80px;
    color:#0a3d62;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    margin-bottom: 40px;
}

.footer-section h3 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #0a3d62;
    border-bottom: 2px solid #1e90ff;
    padding-bottom: 10px;
}

.footer-links {
    list-style: none;
}

.footer-links li {
    margin-bottom: 12px;
}

.footer-links a {
    color: #0a3d62;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-links a:hover {
    color: #1e90ff;
    transform: translateX(5px);
}

.footer-links i {
    width: 20px;
    text-align: center;
}

.copyright {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 14px;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    header {
        padding: 12px 30px;
    }
    
    nav ul {
        gap: 15px;
    }
    
    nav ul li a {
        font-size: 13px;
    }
    
    .logo-text .logo-title {
        font-size: 20px;
    }
    
    .logo-text .logo-subtitle {
        font-size: 11px;
    }
}

@media (max-width: 900px) {
    .logo-text .logo-subtitle {
        display: none;
    }
    
    nav ul li a span {
        display: none;
    }
    
    nav ul li a {
        font-size: 16px;
        padding: 8px;
    }
    
    .user-info span {
        display: none;
    }
}

@media (max-width: 768px) {
    header {
        padding: 10px 20px;
        flex-direction: column;
        gap: 15px;
    }
    
    nav ul {
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-title {
        font-size: 36px;
    }
    
    .page-subtitle {
        font-size: 18px;
    }
    
    .toc-grid {
        grid-template-columns: 1fr;
    }
    
    .guide-section {
        padding: 25px;
    }
    
    .section-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .section-title {
        font-size: 24px;
        text-align: center;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .quick-actions {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
    }
    
    .visual-guide {
        grid-template-columns: 1fr;
    }
    
    .nav-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
    }
}

</style>
</head>
<body>

<!-- HEADER - WITH UPDATED LOGO -->
<header>
    <a href="index.php" class="logo-container">
        <?php if ($logoExists): ?>
            <img src="<?php echo $logoPath; ?>" alt="LoFIMS Logo" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
            <div id="logo-fallback" class="logo-fallback" style="display: none;">LF</div>
        <?php else: ?>
            <div class="logo-fallback">LF</div>
        <?php endif; ?>
        <div class="logo-text">
            <span class="logo-title">LoFIMS</span>
            <span class="logo-subtitle">Lost & Found Management System</span>
        </div>
    </a>
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost</a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> Found</a></li>
            <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> Claims</a></li>
            <li><a href="guide.php"><i class="fas fa-book"></i> Guide</a></li>
            
            <?php if ($isLoggedIn): ?>
                <li class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($userName); ?></span>
                </li>
                <li><a href="logout.php" class="login-btn">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="login-btn">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">Complete User Guide</h1>
        <p class="page-subtitle">Everything you need to know to use LoFIMS effectively</p>
    </div>
</div>

<!-- QUICK NAV -->
<div class="quick-nav">
    <h3>Quick Navigation</h3>
    <div class="nav-grid">
        <a href="#getting-started" class="nav-card">
            <i class="fas fa-play-circle"></i>
            <span>Getting Started</span>
        </a>
        <a href="#reporting-lost" class="nav-card">
            <i class="fas fa-exclamation-circle"></i>
            <span>Report Lost Items</span>
        </a>
        <a href="#reporting-found" class="nav-card">
            <i class="fas fa-search"></i>
            <span>Report Found Items</span>
        </a>
        <a href="#searching-items" class="nav-card">
            <i class="fas fa-binoculars"></i>
            <span>Search for Items</span>
        </a>
        <a href="#claiming-items" class="nav-card">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Claim Items</span>
        </a>
        <a href="#troubleshooting" class="nav-card">
            <i class="fas fa-tools"></i>
            <span>Troubleshooting</span>
        </a>
    </div>
</div>

<!-- GUIDE CONTENT -->
<div class="guide-container">
    <!-- TABLE OF CONTENTS -->
    <div class="toc-container">
        <h2><i class="fas fa-list-ol"></i> Table of Contents</h2>
        <div class="toc-grid">
            <a href="#getting-started" class="toc-item">
                <div class="toc-number">1</div>
                <div class="toc-content">
                    <h3>Getting Started</h3>
                    <p>Account registration, login, and basic setup</p>
                </div>
            </a>
            <a href="#reporting-lost" class="toc-item">
                <div class="toc-number">2</div>
                <div class="toc-content">
                    <h3>Reporting Lost Items</h3>
                    <p>Step-by-step guide to report lost items</p>
                </div>
            </a>
            <a href="#reporting-found" class="toc-item">
                <div class="toc-number">3</div>
                <div class="toc-content">
                    <h3>Reporting Found Items</h3>
                    <p>How to help others by reporting found items</p>
                </div>
            </a>
            <a href="#searching-items" class="toc-item">
                <div class="toc-number">4</div>
                <div class="toc-content">
                    <h3>Searching for Items</h3>
                    <p>Tips and techniques for effective searching</p>
                </div>
            </a>
            <a href="#claiming-items" class="toc-item">
                <div class="toc-number">5</div>
                <div class="toc-content">
                    <h3>Claiming Items</h3>
                    <p>How to claim your lost items securely</p>
                </div>
            </a>
            <a href="#troubleshooting" class="toc-item">
                <div class="toc-number">6</div>
                <div class="toc-content">
                    <h3>Troubleshooting</h3>
                    <p>Solutions to common problems and issues</p>
                </div>
            </a>
        </div>
    </div>

    <!-- GETTING STARTED -->
    <div class="guide-section" id="getting-started">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-play-circle"></i>
            </div>
            <div>
                <h2 class="section-title">Getting Started with LoFIMS</h2>
                <p class="section-subtitle">Everything you need to begin using our system</p>
            </div>
        </div>
        
        <h3>Account Registration Process</h3>
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-user-plus"></i>
                <h4>Step-by-Step Registration</h4>
            </div>
            <p><strong>Step 1:</strong> Click the "Register" button on the homepage or login page</p>
            <p><strong>Step 2:</strong> Fill in your personal information including:</p>
            <ul>
                <li><strong>First and Last Name:</strong> Your complete legal name as per TUP records</li>
                <li><strong>TUP Email Address:</strong> Must be your official @tuplopez.edu.ph email</li>
                <li><strong>Student/Employee ID Number:</strong> Your official TUP identification number</li>
                <li><strong>Contact Information:</strong> Valid phone number for recovery notifications</li>
                <li><strong>Course/Department:</strong> Your current program or department</li>
                <li><strong>Year Level:</strong> If applicable</li>
                <li><strong>Secure Password:</strong> Minimum 8 characters with letters and numbers</li>
            </ul>
            <p><strong>Step 3:</strong> Verify your email address by clicking the confirmation link sent to your email</p>
            <p><strong>Step 4:</strong> Wait for account approval (typically within 24 hours)</p>
            <p><strong>Step 5:</strong> Log in to your activated account</p>
        </div>
        
        <div class="tip-box">
            <div class="box-header">
                <i class="fas fa-lightbulb"></i>
                <h4>Registration Tips</h4>
            </div>
            <p><strong>Use Official Credentials:</strong> Always use your official TUP email address and ID number for registration to ensure account approval and verification.</p>
            <p><strong>Password Security:</strong> Create a strong, unique password and never share it with anyone. Consider using a password manager.</p>
            <p><strong>Complete Profile:</strong> Fill out all profile fields completely for better account management and verification.</p>
        </div>
        
        <div class="important-box">
            <div class="box-header">
                <i class="fas fa-exclamation-circle"></i>
                <h4>Important Notes</h4>
            </div>
            <p><strong>One Account Per Person:</strong> Each student/staff member is allowed only one active account. Multiple accounts will be deactivated.</p>
            <p><strong>Account Responsibility:</strong> You are responsible for all activities under your account. Report any unauthorized access immediately.</p>
            <p><strong>Profile Accuracy:</strong> Ensure your profile information is accurate and up-to-date for smooth item recovery processes.</p>
        </div>
    </div>

    <!-- REPORTING LOST ITEMS -->
    <div class="guide-section" id="reporting-lost">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <h2 class="section-title">Reporting Lost Items</h2>
                <p class="section-subtitle">How to effectively report items you've lost on campus</p>
            </div>
        </div>
        
        <h3>Complete Lost Item Reporting Process</h3>
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-clipboard-list"></i>
                <h4>Detailed Reporting Steps</h4>
            </div>
            <p><strong>Step 1:</strong> Log in to your verified LoFIMS account</p>
            <p><strong>Step 2:</strong> Navigate to the "Lost Items" section from the main menu</p>
            <p><strong>Step 3:</strong> Click the "Report Lost Item" button (usually a prominent green button)</p>
            <p><strong>Step 4:</strong> Carefully fill out the item reporting form with:</p>
            <ul>
                <li><strong>Item Name:</strong> Descriptive name (e.g., "Black Umbrella with Wooden Handle")</li>
                <li><strong>Category:</strong> Select appropriate category from dropdown</li>
                <li><strong>Description:</strong> Detailed description including color, brand, model, size, and unique features</li>
                <li><strong>Location Lost:</strong> Specific area/building where item was lost</li>
                <li><strong>Date & Time Lost:</strong> As specific as possible</li>
                <li><strong>Estimated Value:</strong> Approximate monetary value (optional)</li>
                <li><strong>Photos:</strong> Upload clear photos from multiple angles (highly recommended)</li>
                <li><strong>Additional Notes:</strong> Any other identifying information</li>
            </ul>
            <p><strong>Step 5:</strong> Review all information for accuracy</p>
            <p><strong>Step 6:</strong> Submit the report and note your report number</p>
        </div>
        
        <div class="visual-guide">
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h5>Photo Guidelines</h5>
                <p>Upload clear, well-lit photos showing distinctive features, serial numbers, or unique marks</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h5>Location Details</h5>
                <p>Be specific: "2nd Floor Library near Window 3" is better than "In the library"</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h5>Timing Matters</h5>
                <p>Report items as soon as possible - the sooner you report, the better the recovery chances</p>
            </div>
        </div>
        
        <div class="warning-box">
            <div class="box-header">
                <i class="fas fa-ban"></i>
                <h4>What NOT to Report</h4>
            </div>
            <p><strong>Campus Boundaries:</strong> Only report items lost within TUP Lopez campus premises</p>
            <p><strong>Personal Safety Items:</strong> For lost IDs, wallets with money/cards, or phones, also report to campus security immediately</p>
            <p><strong>Prohibited Items:</strong> Do not report illegal or prohibited items - contact campus security instead</p>
        </div>
    </div>

    <!-- REPORTING FOUND ITEMS -->
    <div class="guide-section" id="reporting-found">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-search"></i>
            </div>
            <div>
                <h2 class="section-title">Reporting Found Items</h2>
                <p class="section-subtitle">Helping others by reporting items you've found</p>
            </div>
        </div>
        
        <h3>How to Be a Responsible Finder</h3>
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-hands-helping"></i>
                <h4>Found Item Reporting Process</h4>
            </div>
            <p><strong>Step 1:</strong> Ensure the item is safe to handle and document</p>
            <p><strong>Step 2:</strong> Log in to your LoFIMS account</p>
            <p><strong>Step 3:</strong> Go to "Found Items" section and click "Report Found Item"</p>
            <p><strong>Step 4:</strong> Complete the found item form with:</p>
            <ul>
                <li><strong>Item Description:</strong> Detailed description of what you found</li>
                <li><strong>Exact Location Found:</strong> Specific spot where found</li>
                <li><strong>Date & Time Found:</strong> When discovery occurred</li>
                <li><strong>Current Location:</strong> Where item is currently stored</li>
                <li><strong>Condition:</strong> State of the item (good, damaged, etc.)</li>
                <li><strong>Multiple Photos:</strong> Clear images from different angles</li>
                <li><strong>Safety Notes:</strong> Any handling precautions needed</li>
            </ul>
            <p><strong>Step 5:</strong> Decide on item custody options:</p>
            <ul>
                <li><strong>Hold Item:</strong> Keep it safely until owner claims (for non-valuable items)</li>
                <li><strong>Turn In:</strong> Take to administration office (for valuable/sensitive items)</li>
            </ul>
            <p><strong>Step 6:</strong> Submit report and wait for owner contact</p>
        </div>
        
        <div class="tip-box">
            <div class="box-header">
                <i class="fas fa-star"></i>
                <h4>Best Practices for Finders</h4>
            </div>
            <p><strong>Immediate Reporting:</strong> Report found items within 24 hours for best results</p>
            <p><strong>Safe Handling:</strong> Handle items carefully and avoid tampering with contents</p>
            <p><strong>Privacy Respect:</strong> Do not share found item details on social media - use only the official system</p>
            <p><strong>Valuable Items:</strong> For wallets, phones, IDs, or cash - turn in immediately to administration</p>
        </div>
        
        <h3>Item Custody Options</h3>
        <div class="visual-guide">
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #4caf50, #8bc34a);">
                    <i class="fas fa-home"></i>
                </div>
                <h5>Self Custody</h5>
                <p>For low-value items: Keep item safely until owner arranges pickup</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                    <i class="fas fa-building"></i>
                </div>
                <h5>Office Custody</h5>
                <p>For valuable items: Turn in to Administration Office for secure storage</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #9c27b0, #ba68c8);">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h5>Security Custody</h5>
                <p>For sensitive items: Hand over to Campus Security for proper handling</p>
            </div>
        </div>
    </div>

    <!-- SEARCHING FOR ITEMS -->
    <div class="guide-section" id="searching-items">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-binoculars"></i>
            </div>
            <div>
                <h2 class="section-title">Searching for Items</h2>
                <p class="section-subtitle">Effective techniques to find lost items</p>
            </div>
        </div>
        
        <h3>Search Strategies and Techniques</h3>
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-search-plus"></i>
                <h4>Effective Search Process</h4>
            </div>
            <p><strong>Step 1:</strong> Use the main search bar on any page for quick searches</p>
            <p><strong>Step 2:</strong> For advanced searches, go to "Search Items" page</p>
            <p><strong>Step 3:</strong> Try different search approaches:</p>
            <ul>
                <li><strong>Keyword Search:</strong> Use descriptive terms (color + item type)</li>
                <li><strong>Category Filter:</strong> Narrow by specific item categories</li>
                <li><strong>Date Range:</strong> Filter by when item was lost/found</li>
                <li><strong>Location Filter:</strong> Search by specific campus locations</li>
                <li><strong>Status Filter:</strong> Show only available/unclaimed items</li>
            </ul>
            <p><strong>Step 4:</strong> Browse search results and click items for details</p>
            <p><strong>Step 5:</strong> Use "Save Search" feature for regular monitoring</p>
            <p><strong>Step 6:</strong> Set up email alerts for new matching items</p>
        </div>
        
        <h3>Advanced Search Tips</h3>
        <div class="visual-guide">
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>Exact Phrases</h5>
                <p>Use quotes: "blue water bottle" finds exact phrase matches</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-random"></i>
                </div>
                <h5>Multiple Options</h5>
                <p>Use OR: calculator OR scientific expands search results</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon">
                    <i class="fas fa-minus"></i>
                </div>
                <h5>Exclusion Search</h5>
                <p>Use -: umbrella -black excludes black umbrellas</p>
            </div>
        </div>
        
        <div class="tip-box">
            <div class="box-header">
                <i class="fas fa-bell"></i>
                <h4>Proactive Search Strategies</h4>
            </div>
            <p><strong>Regular Checks:</strong> Search daily for the first week after losing an item</p>
            <p><strong>Varied Terms:</strong> Try different keywords and descriptions</p>
            <p><strong>Broad Then Narrow:</strong> Start with broad search, then apply filters</p>
            <p><strong>Check Both Categories:</strong> Always check both Lost and Found items</p>
            <p><strong>Use Alerts:</strong> Enable email notifications for matching new items</p>
        </div>
    </div>

    <!-- CLAIMING ITEMS -->
    <div class="guide-section" id="claiming-items">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-hand-holding-heart"></i>
            </div>
            <div>
                <h2 class="section-title">Claiming Your Items</h2>
                <p class="section-subtitle">Secure process to reclaim your lost belongings</p>
            </div>
        </div>
        
        <h3>Complete Claim Process</h3>
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-check-circle"></i>
                <h4>Step-by-Step Claim Procedure</h4>
            </div>
            <p><strong>Step 1:</strong> Locate your item in search results</p>
            <p><strong>Step 2:</strong> Click "Claim This Item" button on item details page</p>
            <p><strong>Step 3:</strong> Provide comprehensive proof of ownership:</p>
            <ul>
                <li><strong>Detailed Description:</strong> Describe unique features not visible in photos</li>
                <li><strong>Matching Photos:</strong> Upload your own photos showing same item</li>
                <li><strong>Purchase Proof:</strong> Receipts, invoices, or warranty cards</li>
                <li><strong>Serial Numbers:</strong> Any identifying numbers or codes</li>
                <li><strong>Distinctive Marks:</strong> Scratches, stains, or modifications</li>
                <li><strong>Witness Information:</strong> People who can verify ownership</li>
            </ul>
            <p><strong>Step 4:</strong> Submit claim and receive claim reference number</p>
            <p><strong>Step 5:</strong> Wait for administrator verification (24-48 hours)</p>
            <p><strong>Step 6:</strong> If approved, arrange secure pickup/delivery</p>
            <p><strong>Step 7:</strong> Complete verification at pickup with ID check</p>
            <p><strong>Step 8:</strong> Mark claim as completed in system</p>
        </div>
        
        <div class="warning-box">
            <div class="box-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Claim Verification Standards</h4>
            </div>
            <p><strong>False Claims:</strong> Attempting to claim items not belonging to you will result in immediate account suspension and possible disciplinary action</p>
            <p><strong>Proof Requirements:</strong> Be prepared to provide multiple forms of evidence</p>
            <p><strong>ID Verification:</strong> Bring valid TUP ID for all pickup/delivery transactions</p>
            <p><strong>Time Limits:</strong> Claims must be completed within 7 days of approval</p>
        </div>
        
        <h3>Secure Pickup Options</h3>
        <div class="visual-guide">
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #2196f3, #03a9f4);">
                    <i class="fas fa-handshake"></i>
                </div>
                <h5>Direct Meetup</h5>
                <p>Meet finder in public campus location during daylight hours</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #4caf50, #00bcd4);">
                    <i class="fas fa-building"></i>
                </div>
                <h5>Office Pickup</h5>
                <p>Collect from Administration Office during business hours</p>
            </div>
            <div class="visual-item">
                <div class="visual-icon" style="background: linear-gradient(45deg, #ff9800, #ff5722);">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h5>Security Escorted</h5>
                <p>For valuable items, arrange security-escorted exchange</p>
            </div>
        </div>
    </div>

    <!-- TROUBLESHOOTING -->
    <div class="guide-section" id="troubleshooting">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-tools"></i>
            </div>
            <div>
                <h2 class="section-title">Troubleshooting Guide</h2>
                <p class="section-subtitle">Solutions to common problems and issues</p>
            </div>
        </div>
        
        <h3>Common Issues and Solutions</h3>
        
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-sign-in-alt"></i>
                <h4>Login Problems</h4>
            </div>
            <p><strong>Can't log in?</strong> Try these solutions:</p>
            <ul>
                <li><strong>Check Credentials:</strong> Verify email and password (case-sensitive)</li>
                <li><strong>Forgot Password:</strong> Use "Forgot Password" link for reset</li>
                <li><strong>CAPS LOCK:</strong> Ensure CAPS LOCK is not accidentally on</li>
                <li><strong>Browser Issues:</strong> Clear cache and cookies, try different browser</li>
                <li><strong>Account Status:</strong> Check if account is approved and not suspended</li>
                <li><strong>Network Problems:</strong> Check internet connection and try again</li>
            </ul>
        </div>
        
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-upload"></i>
                <h4>Photo Upload Issues</h4>
            </div>
            <p><strong>Photos won't upload?</strong> Follow these guidelines:</p>
            <ul>
                <li><strong>File Size:</strong> Maximum 5MB per photo</li>
                <li><strong>Format Support:</strong> JPG, PNG, GIF formats only</li>
                <li><strong>Resolution:</strong> Optimize large images before uploading</li>
                <li><strong>Browser Compatibility:</strong> Try Chrome, Firefox, or Edge</li>
                <li><strong>Firewall/Antivirus:</strong> Temporary disable if blocking uploads</li>
                <li><strong>Alternative Method:</strong> Try mobile app if web upload fails</li>
            </ul>
        </div>
        
        <div class="step-box">
            <div class="box-header">
                <i class="fas fa-search-minus"></i>
                <h4>Search Problems</h4>
            </div>
            <p><strong>Can't find items?</strong> Improve search results:</p>
            <ul>
                <li><strong>Broaden Terms:</strong> Use fewer keywords initially</li>
                <li><strong>Check All Categories:</strong> Don't limit to specific categories</li>
                <li><strong>Date Range:</strong> Search within appropriate time frames</li>
                <li><strong>Spelling Variations:</strong> Try different spellings or synonyms</li>
                <li><strong>System Delay:</strong> New items may take time to appear in search</li>
                <li><strong>Contact Support:</strong> If persistent issues, report to technical team</li>
            </ul>
        </div>
        
        <h3>When to Contact Support</h3>
        <div class="important-box">
            <div class="box-header">
                <i class="fas fa-headset"></i>
                <h4>Contact Technical Support For:</h4>
            </div>
            <ul>
                <li><strong>System Errors:</strong> Error messages, crashes, or bugs</li>
                <li><strong>Account Issues:</strong> Unable to register, verify, or access account</li>
                <li><strong>Feature Problems:</strong> Specific features not working correctly</li>
                <li><strong>Security Concerns:</strong> Suspected unauthorized access or breaches</li>
                <li><strong>Performance Issues:</strong> Slow loading or unresponsive pages</li>
                <li><strong>Mobile App Problems:</strong> App crashes or synchronization issues</li>
            </ul>
            <p><strong>Support Contact:</strong> Email: tech@lofims.edu.ph | Phone: (042) 555-8888</p>
        </div>
    </div>

    <!-- DOWNLOAD SECTION -->
    <div class="download-section">
        <i class="fas fa-file-pdf"></i>
        <h3>Download Complete Guide</h3>
        <p>Get the complete LoFIMS User Guide as a PDF document for offline reference, printing, or sharing with others.</p>
        <a href="#" class="download-btn" onclick="downloadGuide()">
            <i class="fas fa-download"></i> Download PDF Guide (v2.0)
        </a>
        <p style="margin-top: 15px; font-size: 14px; color: #666;">
            <i class="fas fa-info-circle"></i> File size: 2.1 MB | Last updated: <?php echo date('F d, Y'); ?>
        </p>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="faq.php" class="action-btn">
            <i class="fas fa-question-circle"></i> Frequently Asked Questions
        </a>
        <a href="contact.php" class="action-btn secondary">
            <i class="fas fa-headset"></i> Contact Support
        </a>
        <a href="index.php" class="action-btn secondary">
            <i class="fas fa-home"></i> Return to Home
        </a>
    </div>
</div>

<!-- HOMEPAGE FOOTER -->
<footer>
    <div class="footer-content">
        <!-- SYSTEM LINKS -->
        <div class="footer-section">
            <h3>System</h3>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost Items</a></li>
                <li><a href="found_items.php"><i class="fas fa-box"></i> Found Items</a></li>
                <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> Claims</a></li>
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            </ul>
        </div>
        
        <!-- INFORMATION -->
        <div class="footer-section">
            <h3>Information</h3>
            <ul class="footer-links">
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a></li>
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a></li>
                <li><a href="success_stories.php"><i class="fas fa-trophy"></i> Success Stories</a></li>
            </ul>
        </div>
        
        <!-- SUPPORT -->
        <div class="footer-section">
            <h3>Support</h3>
            <ul class="footer-links">
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><i class="fas fa-phone"></i> (042) 555-1234</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
            </ul>
        </div>
        
        <!-- LEARNING -->
        <div class="footer-section">
            <h3>Learning</h3>
            <ul class="footer-links">
                <li><a href="guide.php"><i class="fas fa-graduation-cap"></i> User Guide</a></li>
                <li><a href="tutorials.php"><i class="fas fa-play-circle"></i> Video Tutorials</a></li>
                <li><a href="faq.php"><i class="fas fa-question"></i> FAQ</a></li>
                <li><a href="tips.php"><i class="fas fa-lightbulb"></i> Tips & Tricks</a></li>
                <li><i class="fas fa-database"></i> Status: 
                    <?php echo $dbError ? '<span style="color: #e74c3c;">Offline</span>' : '<span style="color: #2ecc71;">Online</span>'; ?>
                </li>
                <li><i class="fas fa-users"></i> <?php echo $usersCount; ?> Registered Users</li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
        <br>
        <small>System Version 2.0 • Last updated: <?php echo date('F d, Y'); ?></small>
        <br>
        <small style="color: #1e90ff; margin-top: 5px; display: inline-block;">
            <i class="fas fa-book-open"></i> Need help? Refer to this User Guide or contact our support team
        </small>
    </div>
</footer>

<script>
// Smooth scroll for navigation links
document.querySelectorAll('.nav-card, .toc-item, .quick-nav a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (this.getAttribute('href').startsWith('#')) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
                
                // Highlight current section
                document.querySelectorAll('.guide-section').forEach(section => {
                    section.style.border = '2px solid transparent';
                });
                targetElement.style.border = '2px solid #1e90ff';
                
                setTimeout(() => {
                    targetElement.style.border = '2px solid transparent';
                }, 2000);
            }
        }
    });
});

// Highlight current section on scroll
const sections = document.querySelectorAll('.guide-section');
const navLinks = document.querySelectorAll('.nav-card, .toc-item');

window.addEventListener('scroll', () => {
    let current = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        
        if (scrollY >= (sectionTop - 150)) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('active');
            if (link.classList.contains('nav-card')) {
                link.style.transform = 'translateY(-5px)';
                link.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
            }
        } else {
            if (link.classList.contains('nav-card')) {
                link.style.transform = 'translateY(0)';
                link.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
            }
        }
    });
});

// Download guide functionality
function downloadGuide() {
    alert('In a real system, this would download the LoFIMS User Guide PDF.\n\nFor now, you can:\n1. Print this page (Ctrl+P)\n2. Take screenshots of important sections\n3. Bookmark this page for future reference\n\nPDF download feature coming soon!');
    
    // In a real system, this would trigger download:
    // window.open('downloads/lofims_user_guide_v2.0.pdf', '_blank');
}

// Print guide functionality
function printGuide() {
    window.print();
}

// Expand/collapse sections for mobile
if (window.innerWidth <= 768) {
    document.querySelectorAll('.guide-section h3').forEach(header => {
        header.style.cursor = 'pointer';
        const content = header.nextElementSibling;
        if (content && content.classList.contains('step-box')) {
            content.style.display = 'block';
            header.addEventListener('click', function() {
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    this.querySelector('i')?.classList.remove('fa-chevron-down');
                    this.querySelector('i')?.classList.add('fa-chevron-up');
                } else {
                    content.style.display = 'none';
                    this.querySelector('i')?.classList.remove('fa-chevron-up');
                    this.querySelector('i')?.classList.add('fa-chevron-down');
                }
            });
        }
    });
}

// Smooth scroll for header links
document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const targetElement = document.querySelector(href);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        }
    });
});

// Initialize section highlighting on page load
window.addEventListener('load', () => {
    setTimeout(() => {
        const hash = window.location.hash;
        if (hash) {
            const targetElement = document.querySelector(hash);
            if (targetElement) {
                targetElement.style.border = '2px solid #1e90ff';
                setTimeout(() => {
                    targetElement.style.border = '2px solid transparent';
                }, 3000);
            }
        }
    }, 500);
});

// Handle logo loading errors
document.addEventListener('DOMContentLoaded', function() {
    const logoImage = document.querySelector('img[alt="LoFIMS Logo"]');
    if (logoImage) {
        logoImage.addEventListener('error', function() {
            this.style.display = 'none';
            const fallback = this.nextElementSibling;
            if (fallback && fallback.classList.contains('logo-fallback')) {
                fallback.style.display = 'flex';
            }
        });
    }
});
</script>

</body>
</html>