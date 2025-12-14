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

// Get some statistics for the footer
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
<title>About LoFIMS - TUP Lopez Lost & Found System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

/* LOGOUT SUCCESS MESSAGE */
.logout-success-message {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    animation: slideInRight 0.3s ease-out;
}
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}

/* HEADER - UPDATED WITH LOGO */
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
    border-radius: 18px; /* Rounded corners */
    transition: transform 0.3s, box-shadow 0.3s, border-radius 0.3s;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    object-fit: cover; /* Changed to cover for better filling */
    border: 3px solid white;
    padding: 5px;
    background: white;
}
.logo-container:hover img { 
    transform: rotate(5deg) scale(1.08); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    border-radius: 20px; /* Slightly more rounded on hover */
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

/* FADE-IN ANIMATION */
.fade-in { opacity: 0; transform: translateY(20px); transition: opacity 1s ease-out, transform 1s ease-out; }
.fade-in.visible { opacity: 1; transform: translateY(0); }

/* MAIN CONTENT */
.content-wrapper {
    animation:bgGradient 15s ease infinite;
    background:linear-gradient(-45deg,#e0f0ff,#f9faff,#d0e8ff,#cce0ff);
    background-size:400% 400%;
    transition:all 0.5s;
    padding:100px 20px 80px;
    position: relative;
    overflow: hidden;
    text-align: center;
}
@keyframes bgGradient { 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }

.page-header {
    max-width: 800px;
    margin: 0 auto;
}

.page-title {
    font-size: 60px;
    font-weight: 900;
    color: #0a3d62;
    line-height: 1.1;
    margin-bottom: 20px;
    background: linear-gradient(45deg, #0a3d62, #1e90ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.page-subtitle {
    font-size: 22px;
    color: #555;
    margin-bottom: 40px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

/* QUICK NAV SECTION - ADDED THIS */
.quick-nav-section {
    max-width: 1200px;
    margin: 40px auto 60px;
    padding: 0 20px;
}

.quick-nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.quick-nav-card {
    background: rgba(52,152,219,0.8);
    backdrop-filter: blur(8px);
    padding: 25px;
    border-radius: 20px;
    color: white;
    text-align: center;
    text-decoration: none;
    display: block;
    transition: transform 0.3s, background 0.3s, box-shadow 0.3s;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
}

.quick-nav-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #1e90ff;
}

.quick-nav-card:hover {
    transform: translateY(-8px);
    background: rgba(41,128,185,0.85);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    text-decoration: none;
}

.quick-nav-card i {
    font-size: 36px;
    margin-bottom: 15px;
    color: white;
}

.quick-nav-card h3 {
    font-size: 18px;
    margin-bottom: 10px;
    color: white;
}

.quick-nav-card p {
    font-size: 14px;
    color: #f0f8ff;
    opacity: 0.9;
}

/* ABOUT CONTENT */
.about-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.about-section {
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    padding: 60px 50px;
    border-radius:25px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    margin-bottom: 60px;
    position: relative;
    overflow: hidden;
}

.about-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}

.section-title {
    font-size: 36px;
    font-weight: 800;
    color: #0a3d62;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}

/* MISSION VISION SECTION */
.mission-vision-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 40px;
    margin-top: 40px;
}

.mission-card, .vision-card {
    background: rgba(52,152,219,0.8);
    backdrop-filter: blur(8px);
    padding: 40px 30px;
    border-radius: 25px;
    color: white;
    text-align: center;
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s, background 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}

.mission-card::before, .vision-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #1e90ff;
}

.mission-card:hover, .vision-card:hover {
    transform: translateY(-10px);
    background: rgba(41,128,185,0.85);
    box-shadow: 0 18px 30px rgba(0,0,0,0.2);
}

.mission-card i, .vision-card i {
    font-size: 48px;
    margin-bottom: 20px;
    color: white;
}

.mission-card h3, .vision-card h3 {
    font-size: 28px;
    margin-bottom: 15px;
    color: white;
}

.mission-card p, .vision-card p {
    font-size: 16px;
    line-height: 1.6;
    color: #f0f8ff;
}

/* FEATURES SECTION */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.feature-item {
    background: rgba(52,152,219,0.8);
    backdrop-filter: blur(8px);
    padding: 30px;
    border-radius: 25px;
    color: white;
    text-align: center;
    transition: transform 0.3s, background 0.3s, box-shadow 0.3s;
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
}

.feature-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #1e90ff;
}

.feature-item:hover {
    transform: translateY(-8px);
    background: rgba(41,128,185,0.85);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.feature-icon i {
    font-size: 32px;
    color: white;
}

.feature-item h4 {
    font-size: 20px;
    margin-bottom: 15px;
    color: white;
}

.feature-item p {
    font-size: 15px;
    line-height: 1.5;
    color: #f0f8ff;
}

/* PROJECT PROGRESS SECTION */
.project-progress-section {
    margin-top: 60px;
}

.progress-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.progress-card {
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid rgba(52,152,219,0.3);
    text-align: center;
}

.progress-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.progress-image {
    width: 100%;
    height: 200px;
    background: #f0f4ff;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.progress-image i {
    font-size: 60px;
    color: #1e90ff;
    opacity: 0.7;
}

.progress-card h4 {
    font-size: 18px;
    color: #0a3d62;
    margin-bottom: 10px;
}

.progress-card p {
    color: #555;
    font-size: 14px;
    line-height: 1.5;
}

/* TEAM SECTION WITH PICTURES */
.team-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.team-member {
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    padding: 30px;
    border-radius: 25px;
    text-align: center;
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid rgba(52,152,219,0.3);
}

.team-member:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
    font-weight: bold;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border: 3px solid white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.team-member h4 {
    font-size: 20px;
    color: #0a3d62;
    margin-bottom: 5px;
}

.team-member .role {
    color: #1e90ff;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 14px;
    background: rgba(30,144,255,0.1);
    padding: 5px 15px;
    border-radius: 20px;
    display: inline-block;
}

.team-member p {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
    margin-top: 10px;
}

/* CONTACT INFO - UPDATED EMAIL */
.contact-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.contact-item {
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    padding: 30px;
    border-radius: 25px;
    text-align: center;
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid rgba(52,152,219,0.3);
}

.contact-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.contact-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.contact-icon i {
    font-size: 24px;
    color: white;
}

.contact-item h4 {
    font-size: 18px;
    color: #0a3d62;
    margin-bottom: 10px;
}

.contact-item p {
    color: #555;
    font-size: 15px;
    line-height: 1.5;
}

/* CTA BUTTON */
.cta-button {
    text-align: center;
    margin: 50px 0;
}

.cta-btn {
    padding: 15px 40px;
    background: #1e90ff;
    color: white;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}

.cta-btn:hover {
    background: #0d7bd4;
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(30,144,255,0.4);
}

/* FOOTER STYLES */
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

footer a { color:#0a3d62; text-decoration:none; font-weight:bold; transition:all 0.3s; }
footer a:hover { color:#1e90ff; }

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
        font-size: 40px;
    }
    
    .page-subtitle {
        font-size: 18px;
    }
    
    .about-section {
        padding: 40px 20px;
    }
    
    .section-title {
        font-size: 28px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .quick-nav-grid {
        grid-template-columns: 1fr;
    }
    
    .team-container {
        grid-template-columns: 1fr;
    }
    
    .progress-grid {
        grid-template-columns: 1fr;
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
    }
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

</style>
</head>
<body>

<!-- LOGOUT SUCCESS MESSAGE -->
<?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
<div id="logoutMessage" class="logout-success-message">
    <i class="fas fa-check-circle"></i> 
    <?php 
    if (isset($_GET['message'])) {
        echo htmlspecialchars(urldecode($_GET['message']));
    } else {
        echo 'You have been successfully logged out!';
    }
    ?>
</div>

<script>
    // Auto-hide logout message after 3 seconds
    setTimeout(function() {
        const message = document.getElementById('logoutMessage');
        if (message) {
            message.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(function() {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 300);
        }
    }, 3000);
</script>
<?php endif; ?>

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
            <!-- CLEAN HEADER NAVIGATION (5 ITEMS MAX) -->
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost</a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> Found</a></li>
            <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> Claims</a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            
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
<div class="content-wrapper fade-in">
    <div class="page-header">
        <h1 class="page-title">About LoFIMS</h1>
        <p class="page-subtitle">Learn about our mission, vision, and how we're revolutionizing lost and found management at TUP Lopez</p>
    </div>
</div>

<!-- QUICK NAVIGATION SECTION -->
<div class="quick-nav-section fade-in">
    <h2 class="section-title">Quick Navigation</h2>
    <p style="text-align: center; color: #555; margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;">
        Access all important pages quickly from here:
    </p>
    
    <div class="quick-nav-grid">
        <a href="index.php" class="quick-nav-card">
            <i class="fas fa-home"></i>
            <h3>Home</h3>
            <p>Return to the main dashboard</p>
        </a>
        
        <a href="lost_items.php" class="quick-nav-card">
            <i class="fas fa-search"></i>
            <h3>Lost Items</h3>
            <p>Search or report lost items</p>
        </a>
        
        <a href="found_items.php" class="quick-nav-card">
            <i class="fas fa-box"></i>
            <h3>Found Items</h3>
            <p>Report or browse found items</p>
        </a>
        
        <a href="claim_item.php" class="quick-nav-card">
            <i class="fas fa-hand-holding"></i>
            <h3>Claims</h3>
            <p>Claim your lost items</p>
        </a>
        
        <a href="announcements.php" class="quick-nav-card">
            <i class="fas fa-bullhorn"></i>
            <h3>Announcements</h3>
            <p>View latest updates</p>
        </a>
        
        <?php if (!$isLoggedIn): ?>
        <a href="login.php" class="quick-nav-card">
            <i class="fas fa-sign-in-alt"></i>
            <h3>Login</h3>
            <p>Access your account</p>
        </a>
        <?php endif; ?>
        
        <a href="about.php" class="quick-nav-card">
            <i class="fas fa-info-circle"></i>
            <h3>About Us</h3>
            <p>Learn more about LoFIMS</p>
        </a>
        
        <?php if ($isLoggedIn): ?>
        <a href="user_panel/dashboard.php" class="quick-nav-card">
            <i class="fas fa-tachometer-alt"></i>
            <h3>Dashboard</h3>
            <p>Go to user dashboard</p>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ABOUT CONTENT -->
<div class="about-content">
    <!-- INTRODUCTION -->
    <section class="about-section fade-in">
        <h2 class="section-title">What is LoFIMS?</h2>
        <div style="max-width: 800px; margin: 0 auto;">
            <p style="font-size: 18px; line-height: 1.8; color: #555; margin-bottom: 20px; text-align: center;">
                <strong>LoFIMS (Lost and Found Information Management System)</strong> is the official digital platform for managing lost and found items at Technological University of the Philippines - Lopez, Quezon Campus. Our system streamlines the process of reporting, searching, and claiming lost items, making it easier for students, faculty, and staff to recover their belongings.
            </p>
            <p style="font-size: 18px; line-height: 1.8; color: #555; text-align: center;">
                This innovative platform is designed to replace traditional lost-and-found methods with a modern, efficient, and user-friendly digital solution that enhances campus life and promotes community cooperation.
            </p>
        </div>
    </section>

    <!-- MISSION & VISION -->
    <section class="about-section fade-in">
        <h2 class="section-title">Our Mission & Vision</h2>
        <div class="mission-vision-container">
            <div class="mission-card">
                <i class="fas fa-bullseye"></i>
                <h3>Our Mission</h3>
                <p>To provide a reliable, efficient, and user-friendly platform that simplifies the process of reporting and recovering lost items within the TUP Lopez community, fostering a sense of security and trust among students, faculty, and staff.</p>
            </div>
            <div class="vision-card">
                <i class="fas fa-eye"></i>
                <h3>Our Vision</h3>
                <p>To become the leading digital solution for lost and found management in educational institutions, promoting accountability, community cooperation, and efficient resource recovery through innovative technology.</p>
            </div>
        </div>
    </section>

    <!-- KEY FEATURES -->
    <section class="about-section fade-in">
        <h2 class="section-title">Key Features</h2>
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h4>Item Search</h4>
                <p>Search lost items by category, date, location, or keywords to find your belongings.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h4>Photo Upload</h4>
                <p>Upload photos of found items for better identification and verification during claims.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Secure Claims</h4>
                <p>Protected claiming process with proper verification to ensure items go to rightful owners.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h4>Real-time Notifications</h4>
                <p>Get instant updates when items matching your lost reports are found in the system.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h4>Statistics & Reports</h4>
                <p>Comprehensive analytics on lost and found items, recovery rates, and system performance.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h4>Mobile-Friendly</h4>
                <p>Access the system from any device - desktop, tablet, or smartphone - with responsive design.</p>
            </div>
        </div>
    </section>

    <!-- PROJECT PROGRESS -->
    <section class="about-section fade-in">
        <h2 class="section-title">Project Progress</h2>
        <p style="text-align: center; color: #555; margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;">
            Follow our journey as we develop and enhance the LoFIMS system. Here are some key milestones and progress updates:
        </p>
        
        <div class="progress-grid">
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <h4>System Development</h4>
                <p>Complete backend and frontend development with PHP, MySQL, and modern web technologies.</p>
            </div>
            
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-database"></i>
                </div>
                <h4>Database Design</h4>
                <p>Optimized database structure with 8 tables for efficient data management and retrieval.</p>
            </div>
            
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-paint-brush"></i>
                </div>
                <h4>UI/UX Design</h4>
                <p>Intuitive user interface design with responsive layouts and smooth user experience.</p>
            </div>
            
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Security Implementation</h4>
                <p>Robust security features including user authentication, data encryption, and secure sessions.</p>
            </div>
            
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-file-upload"></i>
                </div>
                <h4>File Upload System</h4>
                <p>Secure file upload functionality for item photos and claim verification documents.</p>
            </div>
            
            <div class="progress-card">
                <div class="progress-image">
                    <i class="fas fa-tasks"></i>
                </div>
                <h4>Testing & Quality Assurance</h4>
                <p>Comprehensive testing phase to ensure system stability and bug-free performance.</p>
            </div>
        </div>
    </section>

    <!-- DEVELOPMENT TEAM WITH PICTURES -->
<!-- UPDATED DEVELOPMENT TEAM SECTION - FULLY RESPONSIVE -->
<section id="development-team" class="about-section fade-in">
    <div class="section-header">
        <h2 class="section-title">Development Team</h2>
        <div class="section-subtitle">
            <p>Meet the dedicated team behind LoFIMS, working together to create an efficient lost and found management system for TUP Lopez.</p>
            <div class="team-stats">
                <span class="stat-item"><i class="fas fa-users"></i> 5 Team Members</span>
                <span class="stat-item"><i class="fas fa-code-branch"></i> 4 Specializations</span>
                <span class="stat-item"><i class="fas fa-calendar-alt"></i> Active Since 2023</span>
            </div>
        </div>
    </div>
    
    <!-- TEAM GRID WITH FILTERING -->
    <div class="team-controls">
        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filterTeam('all')" aria-label="Show all team members">All Team</button>
            <button class="filter-btn" onclick="filterTeam('leadership')" aria-label="Filter leadership team">Leadership</button>
            <button class="filter-btn" onclick="filterTeam('development')" aria-label="Filter development team">Development</button>
            <button class="filter-btn" onclick="filterTeam('design')" aria-label="Filter design team">Design</button>
            <button class="filter-btn" onclick="filterTeam('support')" aria-label="Filter support team">Support</button>
        </div>
        <div class="view-toggle">
            <button class="view-btn active" onclick="changeView('grid')" title="Grid View" aria-label="Switch to grid view">
                <i class="fas fa-th-large"></i>
            </button>
            <button class="view-btn" onclick="changeView('list')" title="List View" aria-label="Switch to list view">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>
    
    <!-- TEAM MEMBERS GRID -->
    <div class="team-grid" id="teamContainer">
        <!-- ELjay M. Felismino - Project Lead -->
        <div class="team-card" data-category="leadership development" onclick="showProfile('eljay')" tabindex="0" role="button" aria-label="View ELjay M. Felismino profile">
            <div class="card-badge">Team Lead</div>
            <div class="card-avatar">
                <div class="avatar-wrapper" 
                     style="background-image: url('../uploads/team_pictures/eljay.jpg');"
                     role="img" 
                     aria-label="ELjay M. Felismino photo">
                    <div class="online-indicator" aria-label="Currently active"></div>
                </div>
            </div>
            <div class="card-content">
                <h3 class="member-name">ELjay M. Felismino</h3>
                <div class="member-role">Project Lead & Lead Developer</div>
                <div class="member-tags">
                    <span class="tag">Full-Stack</span>
                    <span class="tag">PHP</span>
                    <span class="tag">Architecture</span>
                </div>
                <p class="member-brief">Oversees entire project development and system architecture with 3+ years experience.</p>
                <div class="card-footer">
                    <div class="social-mini">
                        <a href="https://github.com/jaydev-code" target="_blank" aria-label="ELjay's GitHub" onclick="event.stopPropagation();">
                            <i class="fab fa-github"></i>
                        </a>
                        <a href="https://www.facebook.com/eljay.felismino.1/" target="_blank" aria-label="ELjay's Facebook" onclick="event.stopPropagation();">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                    <button class="view-profile-btn">View Profile <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Lorna Castro - Documentation Specialist -->
        <div class="team-card" data-category="support" onclick="showProfile('lorna')" tabindex="0" role="button" aria-label="View Lorna Castro profile">
            <div class="card-badge">QA</div>
            <div class="card-avatar">
                <div class="avatar-wrapper" 
                     style="background-image: url('../uploads/team_pictures/lorna.jpg');"
                     role="img" 
                     aria-label="Lorna Castro photo">
                </div>
            </div>
            <div class="card-content">
                <h3 class="member-name">Lorna Castro</h3>
                <div class="member-role">Documentation Specialist & QA Tester</div>
                <div class="member-tags">
                    <span class="tag">Documentation</span>
                    <span class="tag">Testing</span>
                    <span class="tag">Quality</span>
                </div>
                <p class="member-brief">Manages project documentation and ensures system reliability through thorough testing.</p>
                <div class="card-footer">
                    <div class="social-mini">
                        <a href="https://www.facebook.com/larrah.castro" target="_blank" aria-label="Lorna's Facebook" onclick="event.stopPropagation();">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                    <button class="view-profile-btn">View Profile <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Maverick Capisonda - Research Analyst -->
        <div class="team-card" data-category="development" onclick="showProfile('maverick')" tabindex="0" role="button" aria-label="View Maverick Capisonda profile">
            <div class="card-avatar">
                <div class="avatar-wrapper" 
                     style="background-image: url('../uploads/team_pictures/maverick.jpg');"
                     role="img" 
                     aria-label="Maverick Capisonda photo">
                </div>
            </div>
            <div class="card-content">
                <h3 class="member-name">Maverick Capisonda</h3>
                <div class="member-role">Research Analyst & Content Manager</div>
                <div class="member-tags">
                    <span class="tag">Research</span>
                    <span class="tag">Analysis</span>
                    <span class="tag">Strategy</span>
                </div>
                <p class="member-brief">Conducts system requirements research and analyzes user needs for feature development.</p>
                <div class="card-footer">
                    <div class="social-mini">
                        <a href="https://www.facebook.com/dasnomavy" target="_blank" aria-label="Maverick's Facebook" onclick="event.stopPropagation();">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                    <button class="view-profile-btn">View Profile <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Michaella Grace Tarala - UI/UX Designer -->
        <div class="team-card" data-category="design" onclick="showProfile('michaella')" tabindex="0" role="button" aria-label="View Michaella Grace Tarala profile">
            <div class="card-badge">Design</div>
            <div class="card-avatar">
                <div class="avatar-wrapper" 
                     style="background-image: url('../uploads/team_pictures/michaella.jpg');"
                     role="img" 
                     aria-label="Michaella Grace Tarala photo">
                </div>
            </div>
            <div class="card-content">
                <h3 class="member-name">Michaella Grace Tarala</h3>
                <div class="member-role">UI/UX Designer & Graphic Artist</div>
                <div class="member-tags">
                    <span class="tag">UI/UX</span>
                    <span class="tag">Graphic Design</span>
                    <span class="tag">Wireframing</span>
                </div>
                <p class="member-brief">Creates intuitive user interfaces and visual assets for optimal user experience.</p>
                <div class="card-footer">
                    <button class="view-profile-btn">View Profile <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Maurice Campillos - Deployment Coordinator -->
        <div class="team-card" data-category="support" onclick="showProfile('maurice')" tabindex="0" role="button" aria-label="View Maurice Campillos profile">
            <div class="card-avatar">
                <div class="avatar-wrapper" 
                     style="background-image: url('../uploads/team_pictures/maurice.jpg');"
                     role="img" 
                     aria-label="Maurice Campillos photo">
                </div>
            </div>
            <div class="card-content">
                <h3 class="member-name">Maurice Campillos</h3>
                <div class="member-role">Deployment Coordinator & Technical Support</div>
                <div class="member-tags">
                    <span class="tag">Deployment</span>
                    <span class="tag">Support</span>
                    <span class="tag">Training</span>
                </div>
                <p class="member-brief">Coordinates system deployment and provides ongoing technical support to users.</p>
                <div class="card-footer">
                    <div class="social-mini">
                        <a href="https://www.facebook.com/mau.rice.7165" target="_blank" aria-label="Maurice's Facebook" onclick="event.stopPropagation();">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                    <button class="view-profile-btn">View Profile <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TEAM OVERVIEW -->
    <div class="team-overview">
        <div class="overview-card">
            <i class="fas fa-project-diagram"></i>
            <h4>Agile Methodology</h4>
            <p>Using Scrum framework with 2-week sprints for efficient development</p>
        </div>
        <div class="overview-card">
            <i class="fas fa-code"></i>
            <h4>Tech Stack</h4>
            <p>PHP, MySQL, JavaScript, Bootstrap, Git</p>
        </div>
        <div class="overview-card">
            <i class="fas fa-handshake"></i>
            <h4>Collaboration</h4>
            <p>Daily standups, code reviews, and pair programming sessions</p>
        </div>
    </div>
</section>

<!-- ENHANCED PROFILE MODAL -->
<div id="profileModal" class="profile-modal">
    <div class="modal-overlay" onclick="closeProfile()" role="button" aria-label="Close modal"></div>
    <div class="modal-container">
        <div class="modal-sidebar" id="modalSidebar" role="navigation" aria-label="Profile navigation">
            <!-- Navigation will be loaded here -->
        </div>
        <div class="modal-main" id="modalMain" role="main" aria-label="Profile content">
            <!-- Main content will be loaded here -->
        </div>
    </div>
</div>

<style>
/* ===== RESPONSIVE VARIABLES ===== */
:root {
    /* Primary Colors */
    --primary-blue: #1a73e8;
    --primary-dark: #0d47a1;
    --primary-light: #e8f0fe;
    --secondary-teal: #00bcd4;
    --accent-orange: #ff9800;
    
    /* Gray Scale */
    --gray-50: #f8f9fa;
    --gray-100: #f1f3f4;
    --gray-200: #e8eaed;
    --gray-300: #dadce0;
    --gray-800: #3c4043;
    
    /* Shadows */
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-xl: 0 20px 60px rgba(0,0,0,0.15);
    
    /* Border Radius */
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
    --radius-xl: 28px;
    
    /* Transitions */
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Spacing */
    --space-xs: 4px;
    --space-sm: 8px;
    --space-md: 16px;
    --space-lg: 24px;
    --space-xl: 32px;
    --space-2xl: 48px;
}

/* ===== BASE RESPONSIVE STYLES ===== */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    font-size: 16px;
    -webkit-text-size-adjust: 100%;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    line-height: 1.5;
}

/* ===== SECTION HEADER ===== */
.section-header {
    text-align: center;
    margin-bottom: clamp(30px, 5vw, 50px);
    padding: 0 var(--space-md);
}

.section-title {
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    margin-bottom: var(--space-lg);
    color: var(--gray-800);
}

.section-subtitle {
    max-width: min(800px, 90vw);
    margin: 0 auto;
    color: var(--gray-800);
    line-height: 1.6;
    font-size: clamp(0.95rem, 2vw, 1.05rem);
}

.team-stats {
    display: flex;
    justify-content: center;
    gap: clamp(15px, 3vw, 30px);
    margin-top: clamp(15px, 4vw, 25px);
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: clamp(8px, 2vw, 10px) clamp(15px, 3vw, 20px);
    background: var(--primary-light);
    border-radius: var(--radius-lg);
    color: var(--primary-dark);
    font-weight: 500;
    font-size: clamp(0.8rem, 2vw, 0.95rem);
    white-space: nowrap;
}

.stat-item i {
    font-size: clamp(1rem, 2vw, 1.1rem);
}

/* ===== TEAM CONTROLS ===== */
.team-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: clamp(25px, 4vw, 40px);
    padding: clamp(15px, 3vw, 20px);
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    flex-wrap: wrap;
    gap: clamp(15px, 3vw, 20px);
    margin: 0 auto clamp(25px, 4vw, 40px);
    max-width: min(1200px, 95vw);
}

.filter-tabs {
    display: flex;
    gap: clamp(6px, 1.5vw, 10px);
    flex-wrap: wrap;
    justify-content: center;
}

.filter-btn {
    padding: clamp(8px, 2vw, 10px) clamp(15px, 3vw, 24px);
    border: 2px solid var(--gray-200);
    background: white;
    border-radius: var(--radius-lg);
    color: var(--gray-800);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    font-size: clamp(0.8rem, 2vw, 0.95rem);
    min-width: max-content;
}

.filter-btn:hover,
.filter-btn:focus {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
    transform: translateY(-2px);
    outline: none;
}

.filter-btn.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: white;
    box-shadow: var(--shadow-md);
}

.view-toggle {
    display: flex;
    gap: clamp(6px, 1.5vw, 8px);
}

.view-btn {
    width: clamp(40px, 10vw, 44px);
    height: clamp(40px, 10vw, 44px);
    border-radius: var(--radius-md);
    border: 2px solid var(--gray-200);
    background: white;
    color: var(--gray-800);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover,
.view-btn:focus {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
    outline: none;
}

.view-btn.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: white;
}

/* ===== TEAM GRID ===== */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
    gap: clamp(20px, 4vw, 30px);
    margin-bottom: clamp(40px, 6vw, 60px);
    padding: 0 clamp(15px, 3vw, 0);
    max-width: min(1200px, 100%);
    margin-left: auto;
    margin-right: auto;
}

.team-grid.list-view {
    grid-template-columns: 1fr;
    max-width: min(800px, 95%);
}

.team-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    border: 1px solid var(--gray-200);
    min-height: 450px;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
}

.team-card:hover,
.team-card:focus {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-blue);
    outline: none;
}

.card-badge {
    position: absolute;
    top: clamp(15px, 3vw, 20px);
    right: clamp(15px, 3vw, 20px);
    background: var(--primary-blue);
    color: white;
    padding: clamp(4px, 1vw, 6px) clamp(12px, 2vw, 16px);
    border-radius: var(--radius-lg);
    font-size: clamp(0.7rem, 1.5vw, 0.8rem);
    font-weight: 600;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-avatar {
    height: clamp(150px, 30vw, 180px);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.avatar-wrapper {
    width: clamp(120px, 25vw, 140px);
    height: clamp(120px, 25vw, 140px);
    border-radius: 50%;
    background-size: cover;
    background-position: center;
    background-color: white;
    border: clamp(3px, 0.8vw, 4px) solid white;
    box-shadow: var(--shadow-lg);
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: clamp(8px, 2vw, 12px);
    right: clamp(8px, 2vw, 12px);
    width: clamp(12px, 3vw, 16px);
    height: clamp(12px, 3vw, 16px);
    background: #4caf50;
    border: clamp(2px, 0.6vw, 3px) solid white;
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
}

.card-content {
    padding: clamp(20px, 4vw, 30px);
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.member-name {
    margin: 0 0 clamp(6px, 1.5vw, 8px) 0;
    color: var(--gray-800);
    font-size: clamp(1.2rem, 3vw, 1.5rem);
    line-height: 1.3;
}

.member-role {
    color: var(--primary-blue);
    font-weight: 600;
    margin-bottom: clamp(15px, 3vw, 20px);
    font-size: clamp(0.9rem, 2vw, 1rem);
}

.member-tags {
    display: flex;
    justify-content: center;
    gap: clamp(6px, 1.5vw, 8px);
    margin-bottom: clamp(15px, 3vw, 20px);
    flex-wrap: wrap;
}

.tag {
    padding: clamp(4px, 1vw, 6px) clamp(10px, 2vw, 14px);
    background: var(--gray-100);
    color: var(--gray-800);
    border-radius: var(--radius-lg);
    font-size: clamp(0.75rem, 1.8vw, 0.85rem);
    font-weight: 500;
    transition: var(--transition);
    white-space: nowrap;
}

.team-card:hover .tag,
.team-card:focus .tag {
    background: var(--primary-light);
    color: var(--primary-dark);
}

.member-brief {
    color: var(--gray-800);
    line-height: 1.6;
    margin-bottom: clamp(15px, 3vw, 25px);
    font-size: clamp(0.85rem, 2vw, 0.95rem);
    opacity: 0.9;
    flex: 1;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    gap: clamp(10px, 2vw, 15px);
    flex-wrap: wrap;
}

.social-mini {
    display: flex;
    gap: clamp(8px, 2vw, 12px);
}

.social-mini a {
    width: clamp(32px, 8vw, 36px);
    height: clamp(32px, 8vw, 36px);
    border-radius: 50%;
    background: var(--gray-100);
    color: var(--gray-800);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    text-decoration: none;
    font-size: clamp(0.9rem, 2vw, 1rem);
}

.social-mini a:hover,
.social-mini a:focus {
    background: var(--primary-blue);
    color: white;
    transform: translateY(-3px);
    outline: none;
}

.view-profile-btn {
    padding: clamp(10px, 2vw, 12px) clamp(15px, 3vw, 24px);
    background: var(--primary-blue);
    color: white;
    border: none;
    border-radius: var(--radius-lg);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: clamp(6px, 1.5vw, 8px);
    font-size: clamp(0.85rem, 2vw, 0.95rem);
    white-space: nowrap;
    min-width: max-content;
}

.view-profile-btn:hover,
.view-profile-btn:focus {
    background: var(--primary-dark);
    transform: translateX(5px);
    outline: none;
}

/* ===== TEAM OVERVIEW ===== */
.team-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
    gap: clamp(20px, 4vw, 30px);
    margin-top: clamp(40px, 6vw, 60px);
    padding-top: clamp(30px, 5vw, 50px);
    border-top: 1px solid var(--gray-200);
    padding: 0 clamp(15px, 3vw, 0);
    max-width: min(1200px, 100%);
    margin-left: auto;
    margin-right: auto;
}

.overview-card {
    text-align: center;
    padding: clamp(20px, 4vw, 30px);
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    height: 100%;
}

.overview-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-blue);
}

.overview-card i {
    font-size: clamp(2rem, 5vw, 2.5rem);
    color: var(--primary-blue);
    margin-bottom: clamp(15px, 3vw, 20px);
    background: var(--primary-light);
    width: clamp(60px, 15vw, 70px);
    height: clamp(60px, 15vw, 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 auto clamp(20px, 4vw, 25px);
}

.overview-card h4 {
    margin: 0 0 clamp(10px, 2vw, 15px) 0;
    color: var(--gray-800);
    font-size: clamp(1.1rem, 2.5vw, 1.3rem);
}

.overview-card p {
    color: var(--gray-800);
    opacity: 0.8;
    line-height: 1.6;
    margin: 0;
    font-size: clamp(0.9rem, 2vw, 1rem);
}

/* ===== ENHANCED PROFILE MODAL ===== */
.profile-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: min(1200px, 95vw);
    height: min(85vh, 95vh);
    background: white;
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-xl);
    display: flex;
    animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -48%) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
}

.modal-sidebar {
    width: min(320px, 35vw);
    background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
    color: white;
    padding: clamp(25px, 4vw, 40px) clamp(20px, 3vw, 30px);
    position: relative;
    overflow-y: auto;
    flex-shrink: 0;
}

.modal-main {
    flex: 1;
    padding: clamp(25px, 4vw, 40px);
    overflow-y: auto;
    background: var(--gray-50);
}

/* Sidebar Responsive Styles */
.sidebar-header {
    text-align: center;
    margin-bottom: clamp(25px, 4vw, 40px);
}

.sidebar-avatar {
    width: clamp(100px, 25vw, 120px);
    height: clamp(100px, 25vw, 120px);
    border-radius: var(--radius-lg);
    background-size: cover;
    background-position: center;
    border: 3px solid white;
    margin: 0 auto clamp(15px, 3vw, 20px);
    box-shadow: var(--shadow-lg);
}

.sidebar-header h3 {
    font-size: clamp(1.2rem, 3vw, 1.5rem);
    margin-bottom: clamp(6px, 1.5vw, 8px);
}

.sidebar-header p {
    font-size: clamp(0.9rem, 2vw, 1rem);
    opacity: 0.9;
}

.profile-nav {
    display: flex;
    flex-direction: column;
    gap: clamp(10px, 2vw, 15px);
}

.nav-item {
    padding: clamp(12px, 2.5vw, 15px) clamp(15px, 3vw, 20px);
    border-radius: var(--radius-md);
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: clamp(8px, 2vw, 12px);
    font-weight: 500;
    cursor: pointer;
    font-size: clamp(0.9rem, 2vw, 1rem);
}

.nav-item:hover,
.nav-item:focus {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(5px);
    outline: none;
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left: 4px solid var(--accent-orange);
}

.close-modal-btn {
    position: absolute;
    top: clamp(15px, 3vw, 25px);
    right: clamp(15px, 3vw, 25px);
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    width: clamp(36px, 9vw, 44px);
    height: clamp(36px, 9vw, 44px);
    border-radius: 50%;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: clamp(1rem, 2.5vw, 1.2rem);
    z-index: 10;
}

.close-modal-btn:hover,
.close-modal-btn:focus {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
    outline: none;
}

/* Main Content Responsive Styles */
.profile-header {
    background: white;
    border-radius: var(--radius-lg);
    padding: clamp(25px, 4vw, 40px);
    margin-bottom: clamp(20px, 4vw, 30px);
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: clamp(25px, 4vw, 40px);
    flex-wrap: wrap;
}

.profile-avatar-large {
    width: clamp(120px, 30vw, 160px);
    height: clamp(120px, 30vw, 160px);
    border-radius: var(--radius-lg);
    background-size: cover;
    background-position: center;
    border: clamp(3px, 0.8vw, 4px) solid var(--primary-blue);
    box-shadow: var(--shadow-lg);
    flex-shrink: 0;
}

.profile-info-header {
    flex: 1;
    min-width: min(300px, 100%);
}

.profile-name-large {
    font-size: clamp(1.5rem, 4vw, 2.2rem);
    color: var(--gray-800);
    margin: 0 0 clamp(8px, 2vw, 10px) 0;
    line-height: 1.2;
}

.profile-role-large {
    color: var(--primary-blue);
    font-size: clamp(1rem, 2.5vw, 1.2rem);
    font-weight: 600;
    margin-bottom: clamp(15px, 3vw, 20px);
}

.profile-bio {
    color: var(--gray-800);
    line-height: 1.7;
    font-size: clamp(0.95rem, 2vw, 1.05rem);
    opacity: 0.9;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: clamp(20px, 4vw, 30px);
    margin-bottom: clamp(30px, 5vw, 40px);
}

.info-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: clamp(20px, 4vw, 30px);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.info-card h3 {
    color: var(--gray-800);
    margin-bottom: clamp(15px, 3vw, 20px);
    font-size: clamp(1.1rem, 2.5vw, 1.3rem);
    display: flex;
    align-items: center;
    gap: clamp(8px, 2vw, 12px);
}

.info-card h3 i {
    color: var(--primary-blue);
}

.skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: clamp(8px, 2vw, 10px);
}

.skill-tag {
    padding: clamp(6px, 1.5vw, 8px) clamp(12px, 2.5vw, 18px);
    background: var(--primary-light);
    color: var(--primary-dark);
    border-radius: var(--radius-lg);
    font-weight: 500;
    font-size: clamp(0.8rem, 2vw, 0.9rem);
    transition: var(--transition);
    white-space: nowrap;
}

.skill-tag:hover {
    background: var(--primary-blue);
    color: white;
    transform: scale(1.05);
}

/* Timeline */
.timeline {
    margin-top: clamp(15px, 3vw, 20px);
}

.timeline-item {
    padding-left: clamp(20px, 4vw, 25px);
    border-left: 3px solid var(--primary-blue);
    margin-bottom: clamp(20px, 3vw, 25px);
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 0;
    width: clamp(10px, 2.5vw, 14px);
    height: clamp(10px, 2.5vw, 14px);
    border-radius: 50%;
    background: var(--primary-blue);
}

.timeline-date {
    color: var(--primary-blue);
    font-weight: 600;
    margin-bottom: clamp(4px, 1vw, 5px);
    font-size: clamp(0.8rem, 2vw, 0.9rem);
}

.timeline-content {
    color: var(--gray-800);
    line-height: 1.6;
    font-size: clamp(0.9rem, 2vw, 1rem);
}

/* Contact Cards */
.contact-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
    gap: clamp(15px, 3vw, 20px);
    margin-top: clamp(15px, 3vw, 20px);
}

.contact-card {
    background: white;
    border-radius: var(--radius-md);
    padding: clamp(20px, 3vw, 25px);
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.contact-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.contact-card i {
    font-size: clamp(1.5rem, 4vw, 2rem);
    color: var(--primary-blue);
    margin-bottom: clamp(10px, 2vw, 15px);
}

.contact-card h4 {
    margin: 0 0 clamp(8px, 2vw, 10px) 0;
    color: var(--gray-800);
    font-size: clamp(0.95rem, 2vw, 1.1rem);
}

.contact-card a,
.contact-card p {
    color: var(--gray-800);
    text-decoration: none;
    transition: var(--transition);
    display: block;
    margin-top: clamp(6px, 1.5vw, 10px);
    font-size: clamp(0.85rem, 2vw, 0.95rem);
    word-break: break-word;
}

.contact-card a:hover {
    color: var(--primary-blue);
}

/* Social Links */
.social-links {
    display: flex;
    gap: clamp(10px, 2.5vw, 15px);
    margin-top: clamp(20px, 4vw, 30px);
    justify-content: center;
    padding-top: clamp(20px, 4vw, 25px);
    border-top: 1px solid var(--gray-200);
    flex-wrap: wrap;
}

.social-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: clamp(36px, 9vw, 45px);
    height: clamp(36px, 9vw, 45px);
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(30,144,255,0.3);
    font-size: clamp(0.9rem, 2vw, 1.1rem);
}

.social-links a:hover,
.social-links a:focus {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 8px 25px rgba(30,144,255,0.4);
    outline: none;
}

/* ===== RESPONSIVE BREAKPOINTS ===== */

/* Large Desktop (1200px and up) */
@media (min-width: 1200px) {
    .team-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .team-grid.list-view {
        grid-template-columns: 1fr;
    }
}

/* Tablet Landscape (992px to 1199px) */
@media (max-width: 1199px) and (min-width: 768px) {
    .team-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-container {
        width: 95%;
        height: 90vh;
    }
    
    .modal-sidebar {
        width: min(280px, 30%);
    }
}

/* Tablet Portrait (768px to 991px) */
@media (max-width: 991px) {
    .team-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-container {
        width: 95%;
        height: 90vh;
        flex-direction: column;
    }
    
    .modal-sidebar {
        width: 100%;
        height: auto;
        max-height: 30vh;
        padding: 20px;
    }
    
    .modal-main {
        height: 60vh;
    }
    
    .profile-nav {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 10px;
    }
    
    .nav-item {
        flex-shrink: 0;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar-large {
        margin: 0 auto;
    }
}

/* Mobile Landscape (576px to 767px) */
@media (max-width: 767px) and (min-width: 576px) {
    .team-grid {
        grid-template-columns: 1fr;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .team-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .view-toggle {
        align-self: center;
    }
    
    .team-overview {
        grid-template-columns: 1fr;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
}

/* Mobile Portrait (575px and below) */
@media (max-width: 575px) {
    .team-grid {
        grid-template-columns: 1fr;
        padding: 0 10px;
    }
    
    .team-controls {
        padding: 15px;
        margin: 0 10px 30px;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .filter-btn {
        padding: 8px 16px;
        font-size: 0.85rem;
    }
    
    .team-card {
        min-height: 420px;
    }
    
    .card-avatar {
        height: 140px;
    }
    
    .avatar-wrapper {
        width: 100px;
        height: 100px;
    }
    
    .card-content {
        padding: 20px 15px;
    }
    
    .team-overview {
        grid-template-columns: 1fr;
        padding: 0 10px;
    }
    
    .overview-card {
        padding: 20px 15px;
    }
    
    /* Modal adjustments for small screens */
    .modal-container {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        top: 0;
        left: 0;
        transform: none;
        animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-main {
        padding: 20px 15px;
    }
    
    .profile-header {
        padding: 20px 15px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .info-card {
        padding: 20px 15px;
    }
    
    .contact-cards {
        grid-template-columns: 1fr;
    }
}

/* Extra Small Mobile (375px and below) */
@media (max-width: 375px) {
    .team-stats {
        flex-direction: column;
        align-items: center;
    }
    
    .stat-item {
        width: 100%;
        max-width: 200px;
        justify-content: center;
    }
    
    .card-footer {
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    
    .social-mini {
        order: 2;
    }
    
    .view-profile-btn {
        order: 1;
        width: 100%;
        justify-content: center;
    }
    
    .member-tags {
        justify-content: center;
    }
    
    .tag {
        padding: 4px 10px;
        font-size: 0.75rem;
    }
}

/* ===== ACCESSIBILITY & TOUCH OPTIMIZATIONS ===== */
@media (hover: none) and (pointer: coarse) {
    /* Optimize for touch devices */
    .team-card:hover {
        transform: none;
    }
    
    .filter-btn,
    .view-btn {
        min-height: 44px; /* Minimum touch target size */
    }
    
    .nav-item {
        min-height: 44px;
    }
    
    .view-profile-btn {
        min-height: 44px;
    }
    
    .close-modal-btn {
        min-width: 44px;
        min-height: 44px;
    }
    
    /* Reduce hover effects on touch devices */
    .team-card:active {
        transform: scale(0.98);
    }
    
    .filter-btn:active,
    .view-btn:active {
        transform: scale(0.95);
    }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .team-card {
        border: 2px solid #000;
    }
    
    .filter-btn.active {
        border: 2px solid #000;
    }
    
    .tag {
        border: 1px solid #000;
    }
}

/* Reduced Motion Preference */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
    
    .modal-container {
        animation: none;
    }
}

/* Print Styles */
@media print {
    .team-controls,
    .view-profile-btn,
    .close-modal-btn,
    .social-mini {
        display: none !important;
    }
    
    .team-card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
    
    .profile-modal {
        position: static !important;
        display: block !important;
    }
    
    .modal-container {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
}
</style>

<script>
// Team data with UPDATED INFORMATION
const teamProfiles = {
    eljay: {
        name: "ELjay M. Felismino",
        role: "Project Lead & Lead Developer",
        education: {
            degree: "Computer Engineering Technology - 3rd Year",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Passionate full-stack developer with expertise in PHP, MySQL, JavaScript, and modern web technologies. Leads the entire LoFIMS project from conception to deployment with a focus on scalable architecture and clean code practices.",
        contact: {
            email: "jayDev-code@gmail.com",
            phone: "+63 981 161 8489",
            facebook: "https://www.facebook.com/eljay.felismino.1/",
            github: "https://github.com/jaydev-code"
        },
        skills: ["PHP Development", "MySQL Database Design", "JavaScript", "System Architecture", "Project Management", "Git"],
        timeline: [
            { date: "2023-Present", event: "LoFIMS Project Lead" },
            { date: "Jan 2023", event: "Started LoFIMS Project" },
            { date: "Mar 2023", event: "Completed System Architecture" },
            { date: "Jun 2023", event: "Implemented Core Features" }
        ]
    },
    lorna: {
        name: "Lorna Castro",
        role: "Documentation Specialist & QA Tester",
        education: {
            degree: "Information Technology",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Detail-oriented documentation specialist with a keen eye for quality assurance and user experience testing. Ensures all project documentation is comprehensive and user-friendly.",
        contact: {
            facebook: "https://www.facebook.com/larrah.castro",
            phone: "09xxxxxxxxx"
        },
        skills: ["Technical Writing", "Quality Assurance", "User Testing", "Documentation", "Bug Tracking", "Test Case Development"],
        timeline: [
            { date: "2023-Present", event: "Documentation Specialist" },
            { date: "Feb 2023", event: "Joined Development Team" },
            { date: "Apr 2023", event: "Established Documentation Standards" },
            { date: "Jul 2023", event: "Completed User Manual" }
        ]
    },
    maverick: {
        name: "Maverick Capisonda",
        role: "Research Analyst & Content Manager",
        education: {
            degree: "Computer Science",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Analytical researcher focused on user needs analysis and content strategy development. Bridges the gap between user requirements and technical implementation.",
        contact: {
            facebook: "https://www.facebook.com/dasnomavy",
            phone: "09xxxxxxxxx"
        },
        skills: ["User Research", "Requirements Analysis", "Content Strategy", "Data Analysis", "Market Research", "Report Writing"],
        timeline: [
            { date: "2023-Present", event: "Research Analyst" },
            { date: "Mar 2023", event: "Conducted Initial User Research" },
            { date: "May 2023", event: "Completed Requirements Analysis" },
            { date: "Aug 2023", event: "Developed User Personas" }
        ]
    },
    michaella: {
        name: "Michaella Grace Tarala",
        role: "UI/UX Designer & Graphic Artist",
        education: {
            degree: "Multimedia Arts",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Creative designer with expertise in user interface design and visual communication strategies. Focuses on creating intuitive and aesthetically pleasing user experiences.",
        contact: {
            phone: "09xxxxxxxxx"
        },
        skills: ["UI/UX Design", "Graphic Design", "Responsive Design", "Wireframing", "Design Systems"],
        timeline: [
            { date: "2023-Present", event: "UI/UX Designer" },
            { date: "Jan 2023", event: "Created Initial Wireframes" },
            { date: "Apr 2023", event: "Designed UI Components" },
            { date: "Jul 2023", event: "Completed Visual Design System" }
        ]
    },
    maurice: {
        name: "Maurice Campillos",
        role: "Deployment Coordinator & Technical Support",
        education: {
            degree: "Information Systems",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Technical expert focused on system deployment and user support infrastructure. Ensures smooth implementation and provides ongoing technical assistance.",
        contact: {
            facebook: "https://www.facebook.com/mau.rice.7165",
            phone: "09xxxxxxxxx"
        },
        skills: ["System Deployment", "Technical Support", "User Training", "System Maintenance", "Troubleshooting"],
        timeline: [
            { date: "2023-Present", event: "Deployment Coordinator" },
            { date: "Feb 2023", event: "Developed Deployment Plan" },
            { date: "May 2023", event: "Set Up Support Systems" },
            { date: "Aug 2023", event: "Conducted User Training" }
        ]
    }
};

// Initialize variables
let currentView = 'grid';
let currentFilter = 'all';
let activeModal = null;

// Filter team members
function filterTeam(category) {
    currentFilter = category;
    
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(category) || 
            (category === 'all' && btn.textContent.includes('All'))) {
            btn.classList.add('active');
        }
    });
    
    // Filter team cards
    const cards = document.querySelectorAll('.team-card');
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category.includes(category)) {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        } else {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.display = 'none';
            }, 300);
        }
    });
}

// Change view layout
function changeView(view) {
    currentView = view;
    const container = document.getElementById('teamContainer');
    const viewBtns = document.querySelectorAll('.view-btn');
    
    // Update active view button
    viewBtns.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Change layout
    if (view === 'list') {
        container.classList.add('list-view');
    } else {
        container.classList.remove('list-view');
    }
}

// Show profile modal
function showProfile(memberId) {
    const profile = teamProfiles[memberId];
    const modal = document.getElementById('profileModal');
    const sidebar = document.getElementById('modalSidebar');
    const main = document.getElementById('modalMain');
    
    activeModal = memberId;
    document.body.style.overflow = 'hidden';
    
    // Build sidebar navigation
    sidebar.innerHTML = `
        <button class="close-modal-btn" onclick="closeProfile()" aria-label="Close profile">
            <i class="fas fa-times"></i>
        </button>
        <div class="sidebar-header">
            <div class="sidebar-avatar" 
                 style="background-image: url('../uploads/team_pictures/${memberId}.jpg');"
                 onerror="handleImageError(this, '${profile.name}')"
                 aria-label="${profile.name} photo">
            </div>
            <h3>${profile.name}</h3>
            <p>${profile.role}</p>
        </div>
        <div class="profile-nav">
            <a class="nav-item active" onclick="switchProfileTab('overview', this)" role="button">
                <i class="fas fa-user"></i> Overview
            </a>
            <a class="nav-item" onclick="switchProfileTab('skills', this)" role="button">
                <i class="fas fa-tools"></i> Skills
            </a>
            <a class="nav-item" onclick="switchProfileTab('timeline', this)" role="button">
                <i class="fas fa-history"></i> Timeline
            </a>
            <a class="nav-item" onclick="switchProfileTab('contact', this)" role="button">
                <i class="fas fa-address-card"></i> Contact
            </a>
        </div>
    `;
    
    // Initial content (Overview)
    main.innerHTML = generateProfileContent(profile, 'overview');
    modal.style.display = 'block';
    
    // Focus trap for accessibility
    document.addEventListener('keydown', handleModalKeys);
}

// Generate profile content
function generateProfileContent(profile, tab) {
    switch(tab) {
        case 'overview':
            return `
                <div class="profile-header">
                    <div class="profile-avatar-large"
                         style="background-image: url('../uploads/team_pictures/${activeModal}.jpg');"
                         onerror="handleImageError(this, '${profile.name}')"
                         aria-label="${profile.name} photo">
                    </div>
                    <div class="profile-info-header">
                        <h1 class="profile-name-large">${profile.name}</h1>
                        <div class="profile-role-large">${profile.role}</div>
                        <p class="profile-bio">${profile.bio}</p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                        <p><strong>${profile.education.degree}</strong></p>
                        <p>${profile.education.school}</p>
                        <p>${profile.education.year}</p>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-tools"></i> Core Skills</h3>
                        <div class="skills-list">
                            ${profile.skills.map(skill => `<span class="skill-tag">${skill}</span>`).join('')}
                        </div>
                    </div>
                </div>
            `;
            
        case 'skills':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-tools"></i> Technical Skills</h3>
                    <div class="skills-list">
                        ${profile.skills.map(skill => `<span class="skill-tag">${skill}</span>`).join('')}
                    </div>
                </div>
            `;
            
        case 'timeline':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Project Timeline</h3>
                    <div class="timeline">
                        ${profile.timeline.map(item => `
                            <div class="timeline-item">
                                <div class="timeline-date">${item.date}</div>
                                <div class="timeline-content">${item.event}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
        case 'contact':
            let contactHTML = '<div class="contact-cards">';
            
            if (profile.contact.phone) {
                contactHTML += `
                    <div class="contact-card">
                        <i class="fas fa-phone"></i>
                        <h4>Phone</h4>
                        <a href="tel:${profile.contact.phone}">${profile.contact.phone}</a>
                    </div>
                `;
            }
            
            if (profile.contact.email) {
                contactHTML += `
                    <div class="contact-card">
                        <i class="fas fa-envelope"></i>
                        <h4>Email</h4>
                        <a href="mailto:${profile.contact.email}">${profile.contact.email}</a>
                    </div>
                `;
            }
            
            if (profile.contact.facebook) {
                contactHTML += `
                    <div class="contact-card">
                        <i class="fab fa-facebook-f"></i>
                        <h4>Facebook</h4>
                        <a href="${profile.contact.facebook}" target="_blank">${profile.contact.facebook.replace('https://www.facebook.com/', '@')}</a>
                    </div>
                `;
            }
            
            if (profile.contact.github) {
                contactHTML += `
                    <div class="contact-card">
                        <i class="fab fa-github"></i>
                        <h4>GitHub</h4>
                        <a href="${profile.contact.github}" target="_blank">${profile.contact.github.replace('https://github.com/', '@')}</a>
                    </div>
                `;
            }
            
            contactHTML += '</div>';
            
            // Add social links if available
            if (profile.contact.facebook || profile.contact.github) {
                contactHTML += `
                    <div class="social-links">
                        ${profile.contact.facebook ? `<a href="${profile.contact.facebook}" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>` : ''}
                        ${profile.contact.github ? `<a href="${profile.contact.github}" target="_blank" aria-label="GitHub"><i class="fab fa-github"></i></a>` : ''}
                    </div>
                `;
            }
            
            return `
                <div class="info-card">
                    <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                    ${contactHTML}
                </div>
            `;
    }
}

// Switch profile tabs
function switchProfileTab(tab, element) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    element.classList.add('active');
    
    const profile = teamProfiles[activeModal];
    const main = document.getElementById('modalMain');
    main.innerHTML = generateProfileContent(profile, tab);
}

// Handle image errors
function handleImageError(element, name) {
    element.style.backgroundImage = 'none';
    element.style.backgroundColor = '#1a73e8';
    element.style.display = 'flex';
    element.style.alignItems = 'center';
    element.style.justifyContent = 'center';
    element.style.color = 'white';
    element.style.fontSize = 'clamp(2rem, 6vw, 3rem)';
    element.style.fontWeight = 'bold';
    element.innerHTML = name.charAt(0);
}

// Handle modal keyboard navigation
function handleModalKeys(event) {
    if (!activeModal) return;
    
    const modal = document.getElementById('profileModal');
    if (modal.style.display !== 'block') return;
    
    switch(event.key) {
        case 'Escape':
            closeProfile();
            break;
        case 'ArrowLeft':
            navigateProfile(-1);
            break;
        case 'ArrowRight':
            navigateProfile(1);
            break;
    }
}

// Navigate between profiles
function navigateProfile(direction) {
    const members = Object.keys(teamProfiles);
    const currentIndex = members.indexOf(activeModal);
    const nextIndex = (currentIndex + direction + members.length) % members.length;
    
    closeProfile();
    setTimeout(() => {
        showProfile(members[nextIndex]);
    }, 300);
}

// Close profile modal
function closeProfile() {
    const modal = document.getElementById('profileModal');
    
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
        modal.style.opacity = '1';
        activeModal = null;
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', handleModalKeys);
    }, 300);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add click event to team cards for better accessibility
    const teamCards = document.querySelectorAll('.team-card');
    teamCards.forEach(card => {
        card.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // Add touch optimizations
    let touchStartY = 0;
    let touchStartX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartY = e.changedTouches[0].screenY;
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        const touchEndY = e.changedTouches[0].screenY;
        const touchEndX = e.changedTouches[0].screenX;
        
        // Detect swipe up/down in modal
        if (activeModal && Math.abs(touchEndY - touchStartY) > 50 && Math.abs(touchEndX - touchStartX) < 30) {
            const modalContent = document.querySelector('.modal-main');
            if (modalContent.scrollTop === 0 && touchEndY > touchStartY) {
                // At top and swiping down - close modal
                closeProfile();
            }
        }
    }, { passive: true });
});

// Handle window resize
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        // Re-adjust modal position on resize
        if (activeModal) {
            const modal = document.getElementById('profileModal');
            if (modal.style.display === 'block') {
                modal.querySelector('.modal-container').style.transform = 'translate(-50%, -50%)';
            }
        }
    }, 250);
});
</script>

<!-- ENHANCED PROFILE MODAL -->
<div id="profileModal" class="profile-modal">
    <div class="modal-overlay" onclick="closeProfile()"></div>
    <div class="modal-container">
        <div class="modal-sidebar" id="modalSidebar">
            <!-- Navigation will be loaded here -->
        </div>
        <div class="modal-main" id="modalMain">
            <!-- Main content will be loaded here -->
        </div>
    </div>
</div>

<style>
/* MODERN PROFESSIONAL STYLES */
:root {
    --primary-blue: #1a73e8;
    --primary-dark: #0d47a1;
    --primary-light: #e8f0fe;
    --secondary-teal: #00bcd4;
    --accent-orange: #ff9800;
    --gray-50: #f8f9fa;
    --gray-100: #f1f3f4;
    --gray-200: #e8eaed;
    --gray-300: #dadce0;
    --gray-800: #3c4043;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-xl: 0 20px 60px rgba(0,0,0,0.15);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
    --radius-xl: 28px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* SECTION HEADER */
.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-subtitle {
    max-width: 800px;
    margin: 0 auto;
    color: var(--gray-800);
    line-height: 1.6;
}

.team-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary-light);
    border-radius: var(--radius-lg);
    color: var(--primary-dark);
    font-weight: 500;
    font-size: 0.95rem;
}

.stat-item i {
    font-size: 1.1rem;
}

/* TEAM CONTROLS */
.team-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding: 20px;
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    flex-wrap: wrap;
    gap: 20px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 24px;
    border: 2px solid var(--gray-200);
    background: white;
    border-radius: var(--radius-lg);
    color: var(--gray-800);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.95rem;
}

.filter-btn:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: white;
    box-shadow: var(--shadow-md);
}

.view-toggle {
    display: flex;
    gap: 8px;
}

.view-btn {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    border: 2px solid var(--gray-200);
    background: white;
    color: var(--gray-800);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.view-btn.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: white;
}

/* TEAM GRID */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.team-grid.list-view {
    grid-template-columns: 1fr;
}

.team-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    border: 1px solid var(--gray-200);
}

.team-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-blue);
}

.card-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--primary-blue);
    color: white;
    padding: 6px 16px;
    border-radius: var(--radius-lg);
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-avatar {
    height: 180px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-wrapper {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background-size: cover;
    background-position: center;
    background-color: white;
    border: 4px solid white;
    box-shadow: var(--shadow-lg);
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: 12px;
    right: 12px;
    width: 16px;
    height: 16px;
    background: #4caf50;
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
}

.card-content {
    padding: 30px;
    text-align: center;
}

.member-name {
    margin: 0 0 8px 0;
    color: var(--gray-800);
    font-size: 1.5rem;
}

.member-role {
    color: var(--primary-blue);
    font-weight: 600;
    margin-bottom: 20px;
    font-size: 1rem;
}

.member-tags {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tag {
    padding: 6px 14px;
    background: var(--gray-100);
    color: var(--gray-800);
    border-radius: var(--radius-lg);
    font-size: 0.85rem;
    font-weight: 500;
    transition: var(--transition);
}

.team-card:hover .tag {
    background: var(--primary-light);
    color: var(--primary-dark);
}

.member-brief {
    color: var(--gray-800);
    line-height: 1.6;
    margin-bottom: 25px;
    font-size: 0.95rem;
    opacity: 0.9;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
}

.social-mini {
    display: flex;
    gap: 12px;
}

.social-mini a {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--gray-100);
    color: var(--gray-800);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    text-decoration: none;
}

.social-mini a:hover {
    background: var(--primary-blue);
    color: white;
    transform: translateY(-3px);
}

.view-profile-btn {
    padding: 12px 24px;
    background: var(--primary-blue);
    color: white;
    border: none;
    border-radius: var(--radius-lg);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}

.view-profile-btn:hover {
    background: var(--primary-dark);
    transform: translateX(5px);
}

/* TEAM OVERVIEW */
.team-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 60px;
    padding-top: 50px;
    border-top: 1px solid var(--gray-200);
}

.overview-card {
    text-align: center;
    padding: 30px;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.overview-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-blue);
}

.overview-card i {
    font-size: 2.5rem;
    color: var(--primary-blue);
    margin-bottom: 20px;
    background: var(--primary-light);
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 auto 25px;
}

.overview-card h4 {
    margin: 0 0 15px 0;
    color: var(--gray-800);
}

.overview-card p {
    color: var(--gray-800);
    opacity: 0.8;
    line-height: 1.6;
    margin: 0;
}

/* ENHANCED PROFILE MODAL */
.profile-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 1200px;
    height: 85vh;
    background: white;
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-xl);
    display: flex;
    animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -48%) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
}

.modal-sidebar {
    width: 320px;
    background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
    color: white;
    padding: 40px 30px;
    position: relative;
    overflow-y: auto;
}

.modal-main {
    flex: 1;
    padding: 40px;
    overflow-y: auto;
    background: var(--gray-50);
}

/* Sidebar Styles */
.profile-nav {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.nav-item {
    padding: 15px 20px;
    border-radius: var(--radius-md);
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    cursor: pointer;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left: 4px solid var(--accent-orange);
}

.close-modal-btn {
    position: absolute;
    top: 25px;
    right: 25px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    z-index: 10;
}

.close-modal-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

/* Main Content Styles */
.profile-header {
    background: white;
    border-radius: var(--radius-lg);
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 40px;
}

.profile-avatar-large {
    width: 160px;
    height: 160px;
    border-radius: var(--radius-lg);
    background-size: cover;
    background-position: center;
    border: 4px solid var(--primary-blue);
    box-shadow: var(--shadow-lg);
    flex-shrink: 0;
}

.profile-info-header {
    flex: 1;
}

.profile-name-large {
    font-size: 2.2rem;
    color: var(--gray-800);
    margin: 0 0 10px 0;
}

.profile-role-large {
    color: var(--primary-blue);
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.profile-bio {
    color: var(--gray-800);
    line-height: 1.7;
    font-size: 1.05rem;
    opacity: 0.9;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    margin-bottom: 40px;
}

.info-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 30px;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.info-card h3 {
    color: var(--gray-800);
    margin-bottom: 20px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-card h3 i {
    color: var(--primary-blue);
}

.skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.skill-tag {
    padding: 8px 18px;
    background: var(--primary-light);
    color: var(--primary-dark);
    border-radius: var(--radius-lg);
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
}

.skill-tag:hover {
    background: var(--primary-blue);
    color: white;
    transform: scale(1.05);
}

/* Timeline */
.timeline {
    margin-top: 20px;
}

.timeline-item {
    padding-left: 25px;
    border-left: 3px solid var(--primary-blue);
    margin-bottom: 25px;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--primary-blue);
}

.timeline-date {
    color: var(--primary-blue);
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.timeline-content {
    color: var(--gray-800);
    line-height: 1.6;
}

/* Contact Cards */
.contact-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.contact-card {
    background: white;
    border-radius: var(--radius-md);
    padding: 25px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.contact-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.contact-card i {
    font-size: 2rem;
    color: var(--primary-blue);
    margin-bottom: 15px;
}

.contact-card a {
    color: var(--gray-800);
    text-decoration: none;
    transition: var(--transition);
    display: block;
    margin-top: 10px;
}

.contact-card a:hover {
    color: var(--primary-blue);
}

/* RESPONSIVE DESIGN */
@media (max-width: 1200px) {
    .modal-container {
        width: 95%;
        height: 90vh;
    }
}

@media (max-width: 992px) {
    .modal-container {
        flex-direction: column;
        width: 95%;
        max-height: 90vh;
        overflow: hidden;
    }
    
    .modal-sidebar {
        width: 100%;
        height: auto;
        padding: 30px 20px;
    }
    
    .profile-nav {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .nav-item {
        padding: 12px 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .team-grid {
        grid-template-columns: 1fr;
    }
    
    .team-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
        gap: 30px;
    }
    
    .profile-avatar-large {
        width: 140px;
        height: 140px;
    }
    
    .profile-name-large {
        font-size: 1.8rem;
    }
    
    .team-overview {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .modal-container {
        width: 100%;
        height: 100vh;
        border-radius: 0;
    }
    
    .modal-main {
        padding: 20px;
    }
    
    .profile-header {
        padding: 30px 20px;
    }
    
    .card-content {
        padding: 20px;
    }
    
    .contact-cards {
        grid-template-columns: 1fr;
    }
}

/* SMOOTH SCROLLING */
.modal-main::-webkit-scrollbar,
.modal-sidebar::-webkit-scrollbar {
    width: 8px;
}

.modal-main::-webkit-scrollbar-track,
.modal-sidebar::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 4px;
}

.modal-main::-webkit-scrollbar-thumb,
.modal-sidebar::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}

.modal-main::-webkit-scrollbar-thumb:hover,
.modal-sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--primary-blue);
}

/* LOADING ANIMATION */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* MICRO-INTERACTIONS */
.ripple {
    position: relative;
    overflow: hidden;
}

.ripple::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.ripple:active::after {
    width: 300px;
    height: 300px;
}
</style>

<script>
// Enhanced JavaScript for Professional Floating Pages

// Team data with more details
const teamProfiles = {
    eljay: {
        name: "ELjay M. Felismino",
        role: "Project Lead & Lead Developer",
        education: {
            degree: "Computer Engineering Technology - 3rd Year",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Passionate full-stack developer with expertise in PHP, MySQL, JavaScript, and modern web technologies. Leads the entire LoFIMS project from conception to deployment with a focus on scalable architecture and clean code practices.",
        contact: {
            email: "jayDev-code@gmail.com",
            phone: "+63 912 345 6789",
            facebook: "https://facebook.com/eljay.felismino",
            github: "https://github.com/eljaydev",
            linkedin: "https://linkedin.com/in/eljayfelismino"
        },
        skills: ["PHP Development", "MySQL Database Design", "JavaScript/Node.js", "System Architecture", "Project Management", "API Integration", "Git", "Docker", "REST APIs"],
        description: "Oversees the entire project development, implements core system architecture, and manages database design and backend functionality. Responsible for system integration and overall project coordination with 3+ years of experience in web development and system design.",
        contributions: [
            "Designed and implemented the core system architecture",
            "Developed the complete backend API structure",
            "Managed database design and optimization",
            "Led team coordination and code reviews",
            "Implemented security protocols and authentication systems"
        ],
        timeline: [
            { date: "Jan 2023", event: "Started LoFIMS Project" },
            { date: "Mar 2023", event: "Completed System Architecture" },
            { date: "Jun 2023", event: "Implemented Core Features" },
            { date: "Sep 2023", event: "Led First Beta Testing" }
        ]
    },
    lorna: {
        name: "Lorna Castro",
        role: "Documentation Specialist & QA Tester",
        education: {
            degree: "Information Technology",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Detail-oriented documentation specialist with a keen eye for quality assurance and user experience testing. Ensures all project documentation is comprehensive and user-friendly.",
        contact: {
            email: "lorna.castro@example.com",
            phone: "+63 923 456 7890",
            facebook: "https://facebook.com/lorna.castro"
        },
        skills: ["Technical Writing", "Quality Assurance", "User Testing", "Documentation", "Bug Tracking", "Process Documentation", "Test Case Development", "User Manuals"],
        contributions: [
            "Created comprehensive project documentation",
            "Developed user manuals and guides",
            "Performed systematic QA testing",
            "Managed bug tracking and reporting",
            "Conducted user acceptance testing"
        ],
        timeline: [
            { date: "Feb 2023", event: "Joined Development Team" },
            { date: "Apr 2023", event: "Established Documentation Standards" },
            { date: "Jul 2023", event: "Completed User Manual" },
            { date: "Oct 2023", event: "Led QA Testing Phase" }
        ]
    },
    maverick: {
        name: "Maverick Capisonda",
        role: "Research Analyst & Content Manager",
        education: {
            degree: "Computer Science",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Analytical researcher focused on user needs analysis and content strategy development. Bridges the gap between user requirements and technical implementation.",
        contact: {
            email: "maverick.capisonda@example.com",
            phone: "+63 934 567 8901",
            linkedin: "https://linkedin.com/in/maverickcapisonda"
        },
        skills: ["User Research", "Requirements Analysis", "Content Strategy", "Data Analysis", "Market Research", "Report Writing", "User Interviews", "Competitive Analysis"],
        contributions: [
            "Conducted comprehensive user research",
            "Analyzed system requirements",
            "Developed content strategy",
            "Managed user feedback collection",
            "Created research reports and analysis"
        ],
        timeline: [
            { date: "Mar 2023", event: "Conducted Initial User Research" },
            { date: "May 2023", event: "Completed Requirements Analysis" },
            { date: "Aug 2023", event: "Developed User Personas" },
            { date: "Nov 2023", event: "Presented Research Findings" }
        ]
    },
    michaella: {
        name: "Michaella Grace Tarala",
        role: "UI/UX Designer & Graphic Artist",
        education: {
            degree: "Multimedia Arts",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Creative designer with expertise in user interface design and visual communication strategies. Focuses on creating intuitive and aesthetically pleasing user experiences.",
        contact: {
            email: "michaella.tarala@example.com",
            phone: "+63 945 678 9012",
            behance: "https://behance.net/michaellatarala",
            dribbble: "https://dribbble.com/michaella"
        },
        skills: ["UI/UX Design", "Graphic Design", "Adobe Creative Suite", "Responsive Design", "Prototyping", "User Testing", "Wireframing", "Design Systems"],
        contributions: [
            "Designed complete UI/UX for LoFIMS",
            "Created all visual assets and graphics",
            "Developed responsive design system",
            "Conducted user interface testing",
            "Created interactive prototypes"
        ],
        timeline: [
            { date: "Jan 2023", event: "Created Initial Wireframes" },
            { date: "Apr 2023", event: "Designed UI Components" },
            { date: "Jul 2023", event: "Completed Visual Design System" },
            { date: "Oct 2023", event: "Conducted Usability Testing" }
        ]
    },
    maurice: {
        name: "Maurice Campillos",
        role: "Deployment Coordinator & Technical Support",
        education: {
            degree: "Information Systems",
            school: "Technological University of the Philippines - Lopez, Quezon",
            year: "2021-2025"
        },
        bio: "Technical expert focused on system deployment and user support infrastructure. Ensures smooth implementation and provides ongoing technical assistance.",
        contact: {
            email: "maurice.campillos@example.com",
            phone: "+63 956 789 0123"
        },
        skills: ["System Deployment", "Technical Support", "User Training", "System Maintenance", "Troubleshooting", "Server Management", "Backup Systems", "Performance Monitoring"],
        contributions: [
            "Coordinated system deployment",
            "Developed technical support protocols",
            "Created user training materials",
            "Managed system maintenance",
            "Provided ongoing technical support"
        ],
        timeline: [
            { date: "Feb 2023", event: "Developed Deployment Plan" },
            { date: "May 2023", event: "Set Up Support Systems" },
            { date: "Aug 2023", event: "Conducted User Training" },
            { date: "Nov 2023", event: "Managed Production Deployment" }
        ]
    }
};

// State management
let currentView = 'grid';
let currentFilter = 'all';
let activeModal = null;

// Filter team members
function filterTeam(category) {
    currentFilter = category;
    
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(category) || 
            (category === 'all' && btn.textContent.includes('All'))) {
            btn.classList.add('active');
        }
    });
    
    // Filter team cards
    const cards = document.querySelectorAll('.team-card');
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category.includes(category)) {
            card.style.display = 'block';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        } else {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.display = 'none';
            }, 300);
        }
    });
}

// Change view layout
function changeView(view) {
    currentView = view;
    const container = document.getElementById('teamContainer');
    const viewBtns = document.querySelectorAll('.view-btn');
    
    // Update active view button
    viewBtns.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Change layout
    if (view === 'list') {
        container.classList.add('list-view');
        container.style.gridTemplateColumns = '1fr';
    } else {
        container.classList.remove('list-view');
        container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
    }
}

// Enhanced profile modal
function showProfile(memberId) {
    const profile = teamProfiles[memberId];
    const modal = document.getElementById('profileModal');
    const sidebar = document.getElementById('modalSidebar');
    const main = document.getElementById('modalMain');
    
    // Store active modal
    activeModal = memberId;
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
    
    // Build sidebar navigation
    sidebar.innerHTML = `
        <button class="close-modal-btn" onclick="closeProfile()">
            <i class="fas fa-times"></i>
        </button>
        <div class="sidebar-header">
            <div class="sidebar-avatar" 
                 style="background-image: url('../uploads/team_pictures/${memberId}.jpg');"
                 onerror="handleImageError(this, '${profile.name}')">
            </div>
            <h3>${profile.name}</h3>
            <p>${profile.role}</p>
        </div>
        <div class="profile-nav">
            <a class="nav-item active" onclick="switchProfileTab('overview', this)">
                <i class="fas fa-user"></i> Overview
            </a>
            <a class="nav-item" onclick="switchProfileTab('skills', this)">
                <i class="fas fa-tools"></i> Skills
            </a>
            <a class="nav-item" onclick="switchProfileTab('contributions', this)">
                <i class="fas fa-tasks"></i> Contributions
            </a>
            <a class="nav-item" onclick="switchProfileTab('timeline', this)">
                <i class="fas fa-history"></i> Timeline
            </a>
            <a class="nav-item" onclick="switchProfileTab('contact', this)">
                <i class="fas fa-address-card"></i> Contact
            </a>
            <a class="nav-item" onclick="switchProfileTab('education', this)">
                <i class="fas fa-graduation-cap"></i> Education
            </a>
        </div>
        <div class="sidebar-footer">
            <button class="download-cv-btn">
                <i class="fas fa-download"></i> Download CV
            </button>
        </div>
    `;
    
    // Initial content (Overview)
    main.innerHTML = generateProfileContent(profile, 'overview');
    
    // Show modal with animation
    modal.style.display = 'block';
    
    // Add keyboard navigation
    document.addEventListener('keydown', handleModalKeys);
}

// Generate profile content based on tab
function generateProfileContent(profile, tab) {
    switch(tab) {
        case 'overview':
            return `
                <div class="profile-header">
                    <div class="profile-avatar-large"
                         style="background-image: url('../uploads/team_pictures/${activeModal}.jpg');"
                         onerror="handleImageError(this, '${profile.name}')">
                    </div>
                    <div class="profile-info-header">
                        <h1 class="profile-name-large">${profile.name}</h1>
                        <div class="profile-role-large">${profile.role}</div>
                        <p class="profile-bio">${profile.bio}</p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-bullseye"></i> Key Contributions</h3>
                        <ul style="color: var(--gray-800); line-height: 1.8; padding-left: 20px;">
                            ${profile.contributions.map(item => `<li>${item}</li>`).join('')}
                        </ul>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-chart-line"></i> Project Impact</h3>
                        <p style="color: var(--gray-800); line-height: 1.7;">
                            ${profile.description}
                        </p>
                        <div class="impact-stats" style="display: flex; gap: 20px; margin-top: 20px;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: var(--primary-blue);">${profile.contributions.length}</div>
                                <div style="color: var(--gray-800); font-size: 0.9rem;">Key Contributions</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: var(--primary-blue);">${profile.skills.length}</div>
                                <div style="color: var(--gray-800); font-size: 0.9rem;">Core Skills</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
        case 'skills':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-tools"></i> Technical Skills</h3>
                    <div class="skills-list">
                        ${profile.skills.map(skill => `<span class="skill-tag">${skill}</span>`).join('')}
                    </div>
                </div>
                
                <div class="info-card" style="margin-top: 30px;">
                    <h3><i class="fas fa-star"></i> Expertise Areas</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                        ${generateExpertiseAreas(profile)}
                    </div>
                </div>
            `;
            
        case 'contributions':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-tasks"></i> Project Contributions</h3>
                    <div style="margin-top: 20px;">
                        ${profile.contributions.map((item, index) => `
                            <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid var(--gray-200);">
                                <div style="background: var(--primary-blue); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                                    ${index + 1}
                                </div>
                                <div>
                                    <h4 style="margin: 0 0 10px 0; color: var(--gray-800);">${item}</h4>
                                    <p style="color: var(--gray-800); opacity: 0.8; margin: 0; line-height: 1.6;">
                                        Made significant impact on project development and user experience.
                                    </p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
        case 'timeline':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Project Timeline</h3>
                    <div class="timeline" style="margin-top: 20px;">
                        ${profile.timeline.map(item => `
                            <div class="timeline-item">
                                <div class="timeline-date">${item.date}</div>
                                <div class="timeline-content">${item.event}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
        case 'contact':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                    <div class="contact-cards" style="margin-top: 20px;">
                        ${generateContactCards(profile.contact)}
                    </div>
                </div>
                
                <div class="info-card" style="margin-top: 30px;">
                    <h3><i class="fas fa-share-alt"></i> Connect</h3>
                    <div class="social-links" style="display: flex; gap: 15px; margin-top: 20px;">
                        ${generateSocialLinks(profile.contact)}
                    </div>
                </div>
            `;
            
        case 'education':
            return `
                <div class="info-card">
                    <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                    <div style="margin-top: 20px;">
                        <div style="background: var(--primary-light); padding: 25px; border-radius: var(--radius-md);">
                            <h4 style="margin: 0 0 10px 0; color: var(--primary-dark);">${profile.education.degree}</h4>
                            <p style="margin: 0 0 8px 0; color: var(--gray-800); font-weight: 500;">${profile.education.school}</p>
                            <p style="margin: 0; color: var(--gray-800); opacity: 0.8;">${profile.education.year}</p>
                        </div>
                    </div>
                </div>
            `;
    }
}

// Generate expertise areas
function generateExpertiseAreas(profile) {
    const areas = [];
    if (profile.skills.some(s => s.includes('PHP') || s.includes('Backend'))) {
        areas.push(`
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: var(--radius-md);">
                <i class="fas fa-server" style="font-size: 2rem; margin-bottom: 15px;"></i>
                <h4 style="margin: 0 0 10px 0;">Backend Development</h4>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Server-side logic and database management</p>
            </div>
        `);
    }
    if (profile.skills.some(s => s.includes('Design') || s.includes('UI/UX'))) {
        areas.push(`
            <div style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 20px; border-radius: var(--radius-md);">
                <i class="fas fa-paint-brush" style="font-size: 2rem; margin-bottom: 15px;"></i>
                <h4 style="margin: 0 0 10px 0;">UI/UX Design</h4>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">User interface and experience design</p>
            </div>
        `);
    }
    return areas.join('');
}

// Generate contact cards
function generateContactCards(contact) {
    const cards = [];
    if (contact.email) {
        cards.push(`
            <div class="contact-card">
                <i class="fas fa-envelope"></i>
                <h4>Email</h4>
                <a href="mailto:${contact.email}">${contact.email}</a>
            </div>
        `);
    }
    if (contact.phone) {
        cards.push(`
            <div class="contact-card">
                <i class="fas fa-phone"></i>
                <h4>Phone</h4>
                <a href="tel:${contact.phone}">${contact.phone}</a>
            </div>
        `);
    }
    return cards.join('');
}

// Generate social links
function generateSocialLinks(contact) {
    const links = [];
    const socials = {
        facebook: { icon: 'fab fa-facebook-f', color: '#1877f2' },
        github: { icon: 'fab fa-github', color: '#333' },
        linkedin: { icon: 'fab fa-linkedin-in', color: '#0077b5' },
        behance: { icon: 'fab fa-behance', color: '#1769ff' },
        dribbble: { icon: 'fab fa-dribbble', color: '#ea4c89' }
    };
    
    Object.entries(socials).forEach(([platform, info]) => {
        if (contact[platform]) {
            links.push(`
                <a href="${contact[platform]}" target="_blank" 
                   style="background: ${info.color}; color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: transform 0.3s;"
                   onmouseover="this.style.transform='scale(1.1)'"
                   onmouseout="this.style.transform='scale(1)'">
                    <i class="${info.icon}"></i>
                </a>
            `);
        }
    });
    return links.join('');
}

// Switch profile tabs
function switchProfileTab(tab, element) {
    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    element.classList.add('active');
    
    // Update content
    const profile = teamProfiles[activeModal];
    const main = document.getElementById('modalMain');
    main.innerHTML = generateProfileContent(profile, tab);
}

// Handle image errors
function handleImageError(element, name) {
    element.style.backgroundImage = 'none';
    element.style.backgroundColor = '#1a73e8';
    element.style.display = 'flex';
    element.style.alignItems = 'center';
    element.style.justifyContent = 'center';
    element.style.color = 'white';
    element.style.fontSize = '3rem';
    element.style.fontWeight = 'bold';
    element.innerHTML = name.charAt(0);
}

// Handle modal keyboard navigation
function handleModalKeys(event) {
    if (!activeModal) return;
    
    const modal = document.getElementById('profileModal');
    if (modal.style.display !== 'block') return;
    
    switch(event.key) {
        case 'Escape':
            closeProfile();
            break;
        case 'ArrowLeft':
            navigateProfile(-1);
            break;
        case 'ArrowRight':
            navigateProfile(1);
            break;
    }
}

// Navigate between profiles
function navigateProfile(direction) {
    const members = Object.keys(teamProfiles);
    const currentIndex = members.indexOf(activeModal);
    const nextIndex = (currentIndex + direction + members.length) % members.length;
    
    closeProfile();
    setTimeout(() => {
        showProfile(members[nextIndex]);
    }, 300);
}

// Close profile modal
function closeProfile() {
    const modal = document.getElementById('profileModal');
    
    // Add closing animation
    modal.style.opacity = '0';
    modal.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        modal.style.opacity = '1';
        modal.style.transform = 'scale(1)';
        activeModal = null;
        
        // Restore body scrolling
        document.body.style.overflow = 'auto';
        
        // Remove keyboard listener
        document.removeEventListener('keydown', handleModalKeys);
    }, 300);
}

// Initialize team section
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple effect to cards
    const cards = document.querySelectorAll('.team-card, .filter-btn, .view-btn');
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.7);
                transform: scale(0);
                animation: ripple 0.6s linear;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                pointer-events: none;
                z-index: 1;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<!-- PROFILE MODAL POPUP -->
<div id="profileModal" class="profile-modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeProfile()">&times;</span>
        <div id="profileContent">
            <!-- Content will be loaded here by JavaScript -->
        </div>
    </div>
</div>

<style>
/* TEAM LAYOUT STYLES */
.team-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 40px;
    max-width: 900px;
    margin: 0 auto;
}

.team-row {
    display: flex;
    justify-content: center;
    gap: 60px;
    width: 100%;
}

/* MAIN MEMBER (YOU) */
.team-member-main {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 30px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 400px;
}

.team-member-main:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar-main {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    margin: 0 auto 20px;
    background-size: cover;
    background-position: center;
    background-color: #f0f4ff;
    border: 4px solid #1e90ff;
    box-shadow: 0 8px 25px rgba(30,144,255,0.3);
}

/* REMOVE TEXT INSIDE AVATARS */
.member-avatar-main::before,
.member-avatar::before {
    display: none !important;
}

/* REGULAR MEMBERS */
.team-member {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 25px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 300px;
}

.team-member:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background-size: cover;
    background-position: center;
    background-color: #f0f4ff;
    border: 3px solid #1e90ff;
    box-shadow: 0 6px 20px rgba(30,144,255,0.2);
}

.click-hint {
    font-size: 12px;
    color: #1e90ff;
    margin-top: 10px;
    font-style: italic;
    opacity: 0.8;
}

/* PROFILE MODAL STYLES - NEW LAYOUT */
.profile-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    animation: fadeIn 0.3s;
    overflow-y: auto;
    padding: 20px 0;
    backdrop-filter: blur(10px);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 800px;
    position: relative;
    animation: slideIn 0.3s;
    box-shadow: 0 25px 60px rgba(0,0,0,0.4);
    max-height: 85vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

@keyframes slideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.close-modal {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 32px;
    font-weight: bold;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 1001;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.5);
    border-radius: 50%;
    border: 2px solid white;
}

.close-modal:hover {
    color: #fff;
    background: #1e90ff;
    transform: scale(1.1);
    border-color: #1e90ff;
}

/* NEW MODAL LAYOUT - SPLIT DESIGN */
.profile-header {
    background: linear-gradient(135deg, #1e90ff, #4facfe);
    padding: 40px;
    border-radius: 20px 20px 0 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/images/pattern.png') repeat;
    opacity: 0.1;
}

.profile-header-content {
    display: flex;
    align-items: center;
    gap: 40px;
    position: relative;
    z-index: 1;
}

/* EXPANDED PROFILE PICTURE */
.profile-avatar-expanded {
    width: 200px;
    height: 200px;
    border-radius: 20px;
    background-size: cover;
    background-position: center;
    background-color: white;
    border: 4px solid white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    flex-shrink: 0;
    transition: transform 0.3s;
}

.profile-avatar-expanded:hover {
    transform: scale(1.02);
}

.profile-header-text {
    flex: 1;
}

.profile-name {
    color: white;
    font-size: 36px;
    margin-bottom: 5px;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
}

.profile-role {
    color: rgba(255,255,255,0.95);
    font-weight: 600;
    font-size: 18px;
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 25px;
    display: inline-block;
    margin-bottom: 20px;
    backdrop-filter: blur(5px);
}

.profile-header-bio {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.6;
    margin-top: 15px;
}

.profile-info {
    padding: 40px;
    text-align: left;
    flex: 1;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.info-section {
    margin-bottom: 25px;
}

.info-section h4 {
    color: #0a3d62;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    padding-bottom: 8px;
    border-bottom: 2px solid #f0f4ff;
}

.info-section h4 i {
    color: #1e90ff;
    width: 24px;
    font-size: 20px;
}

.info-section p {
    color: #555;
    line-height: 1.7;
    margin-left: 36px;
    font-size: 15px;
}

.contact-details {
    background: #f8faff;
    padding: 25px;
    border-radius: 15px;
    margin-top: 20px;
    border-left: 4px solid #1e90ff;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    padding: 12px 15px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.contact-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.contact-item i {
    color: #1e90ff;
    font-size: 20px;
    width: 30px;
}

.contact-item a {
    color: #0a3d62;
    text-decoration: none;
    transition: color 0.3s;
}

.contact-item a:hover {
    color: #1e90ff;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    justify-content: center;
    padding-top: 25px;
    border-top: 1px solid #eee;
}

.social-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(30,144,255,0.3);
}

.social-links a:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 8px 25px rgba(30,144,255,0.4);
}

/* Custom scrollbar styling */
.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
    margin: 20px 0;
}

.modal-content::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #1e90ff, #4facfe);
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, #0d7bd4, #1e90ff);
}

/* For Firefox */
.modal-content {
    scrollbar-width: thin;
    scrollbar-color: #1e90ff #f1f1f1;
}

/* Keep body from scrolling when modal is open */
body.modal-open {
    overflow: hidden;
}

/* Back to team button */
.back-to-team {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.back-to-team button {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.back-to-team button:hover {
    background: #0d7bd4;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .team-row {
        flex-direction: column;
        align-items: center;
        gap: 30px;
    }
    
    .team-member, .team-member-main {
        max-width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 30px auto;
        max-height: 90vh;
    }
    
    .member-avatar-main {
        width: 150px;
        height: 150px;
    }
    
    .member-avatar {
        width: 120px;
        height: 120px;
    }
    
    .profile-header-content {
        flex-direction: column;
        text-align: center;
        gap: 25px;
    }
    
    .profile-avatar-expanded {
        width: 180px;
        height: 180px;
    }
    
    .profile-name {
        font-size: 28px;
    }
    
    .profile-role {
        font-size: 16px;
    }
    
    .profile-info {
        padding: 25px 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .contact-item {
        padding: 10px;
    }
    
    .social-links a {
        width: 40px;
        height: 40px;
    }
}

@media (max-width: 480px) {
    .team-member, .team-member-main {
        padding: 20px;
    }
    
    .modal-content {
        margin: 20px auto;
    }
    
    .profile-header {
        padding: 30px 20px;
    }
    
    .profile-avatar-expanded {
        width: 150px;
        height: 150px;
    }
    
    .profile-name {
        font-size: 24px;
    }
    
    .info-section p {
        margin-left: 20px;
        font-size: 14px;
    }
    
    .close-modal {
        right: 15px;
        top: 10px;
        width: 35px;
        height: 35px;
        font-size: 28px;
    }
}

/* Add pulse animation */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.team-member:hover,
.team-member-main:hover {
    animation: pulse 0.5s;
}

/* MODAL BACKGROUND OVERLAY ANIMATION */
.profile-modal::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(30,144,255,0.1), rgba(79,172,254,0.1));
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.5; }
}
</style>

<script>
// Store scroll position when opening modal
let scrollPosition = 0;
let developmentTeamSection = null;

// Team member data
const teamProfiles = {
    eljay: {
        name: "ELjay M. Felismino",
        role: "Project Lead & Lead Developer",
        education: "Computer Engineering Technology - 3rd Year",
        school: "Technological University of the Philippines - Lopez, Quezon",
        bio: "Passionate full-stack developer with expertise in PHP, MySQL, JavaScript, and modern web technologies. Leads the entire LoFIMS project from conception to deployment.",
        contact: {
            facebook: "https://facebook.com/eljay.felismino",
            email: "jayDev-code@gmail.com",
            phone: "+63 912 345 6789",
            github: "https://github.com/eljaydev"
        },
        skills: ["PHP Development", "MySQL Database Design", "JavaScript/Node.js", "System Architecture", "Project Management", "API Integration"],
        description: "Oversees the entire project development, implements core system architecture, and manages database design and backend functionality. Responsible for system integration and overall project coordination. Has 3+ years of experience in web development and system design."
    },
    lorna: {
        name: "Lorna Castro",
        role: "Documentation Specialist & QA Tester",
        education: "Information Technology",
        school: "Technological University of the Philippines - Lopez, Quezon",
        bio: "Detail-oriented documentation specialist with a keen eye for quality assurance and user experience testing.",
        contact: {
            email: "lorna.castro@example.com",
            phone: "+63 923 456 7890"
        },
        skills: ["Technical Writing", "Quality Assurance", "User Testing", "Documentation", "Bug Tracking", "Process Documentation"],
        description: "Manages project documentation, creates user manuals, and performs quality assurance testing to ensure system reliability and user-friendly experience. Specializes in creating comprehensive documentation and conducting thorough testing protocols to identify and resolve system issues."
    },
    maverick: {
        name: "Maverick Capisonda",
        role: "Research Analyst & Content Manager",
        education: "Computer Science",
        school: "Technological University of the Philippines - Lopez, Quezon",
        bio: "Analytical researcher focused on user needs analysis and content strategy development.",
        contact: {
            email: "maverick.capisonda@example.com",
            phone: "+63 934 567 8901"
        },
        skills: ["User Research", "Requirements Analysis", "Content Strategy", "Data Analysis", "Market Research", "Report Writing"],
        description: "Conducts system requirements research, analyzes user needs, and manages content creation for system features and user documentation. Responsible for gathering user feedback and translating it into actionable development requirements."
    },
    michaella: {
        name: "Michaella Grace Tarala",
        role: "UI/UX Designer & Graphic Artist",
        education: "Multimedia Arts",
        school: "Technological University of the Philippines - Lopez, Quezon",
        bio: "Creative designer with expertise in user interface design and visual communication strategies.",
        contact: {
            email: "michaella.tarala@example.com",
            phone: "+63 945 678 9012"
        },
        skills: ["UI/UX Design", "Graphic Design", "Adobe Creative Suite", "Responsive Design", "Prototyping", "User Testing"],
        description: "Designs user interfaces, creates visual assets, and ensures optimal user experience across all system platforms and devices. Specializes in responsive design, color theory, and creating intuitive navigation flows that enhance user engagement."
    },
    maurice: {
        name: "Maurice Campillos",
        role: "Deployment Coordinator & Technical Support",
        education: "Information Systems",
        school: "Technological University of the Philippines - Lopez, Quezon",
        bio: "Technical expert focused on system deployment and user support infrastructure.",
        contact: {
            email: "maurice.campillos@example.com",
            phone: "+63 956 789 0123"
        },
        skills: ["System Deployment", "Technical Support", "User Training", "System Maintenance", "Troubleshooting", "Server Management"],
        description: "Coordinates system deployment, manages technical support protocols, and assists with user training and system maintenance planning. Ensures smooth system implementation and provides ongoing technical support to users."
    }
};

// Show profile modal
function showProfile(memberId) {
    const profile = teamProfiles[memberId];
    const modal = document.getElementById('profileModal');
    const content = document.getElementById('profileContent');
    
    // Store current scroll position
    scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    // Find development team section
    developmentTeamSection = document.getElementById('development-team');
    
    // Build profile HTML with new layout
    let html = `
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar-expanded" 
                     style="background-image: url('../uploads/team_pictures/${memberId}.jpg');" 
                     onerror="handleAvatarError(this, '${profile.name}')">
                </div>
                <div class="profile-header-text">
                    <h2 class="profile-name">${profile.name}</h2>
                    <div class="profile-role">${profile.role}</div>
                    <p class="profile-header-bio">${profile.bio}</p>
                </div>
            </div>
        </div>
        
        <div class="profile-info">
            <div class="info-grid">
                <div class="info-section">
                    <h4><i class="fas fa-graduation-cap"></i> Education</h4>
                    <p><strong>${profile.education}</strong></p>
                    <p>${profile.school}</p>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-tools"></i> Skills</h4>
                    <p>${profile.skills.join(' • ')}</p>
                </div>
            </div>
            
            <div class="info-section">
                <h4><i class="fas fa-user"></i> About</h4>
                <p>${profile.description}</p>
            </div>
            
            <div class="contact-details">
                <h4 style="color: #0a3d62; margin-bottom: 20px; border: none;"><i class="fas fa-address-card"></i> Contact Information</h4>
    `;
    
    // Add contact info
    if (profile.contact.facebook) {
        html += `
            <div class="contact-item">
                <i class="fab fa-facebook-f"></i>
                <div>
                    <strong>Facebook:</strong><br>
                    <a href="${profile.contact.facebook}" target="_blank">${profile.contact.facebook.replace('https://facebook.com/', '@')}</a>
                </div>
            </div>`;
    }
    
    if (profile.contact.email) {
        html += `
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Email:</strong><br>
                    <a href="mailto:${profile.contact.email}">${profile.contact.email}</a>
                </div>
            </div>`;
    }
    
    if (profile.contact.phone) {
        html += `
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <div>
                    <strong>Phone:</strong><br>
                    ${profile.contact.phone}
                </div>
            </div>`;
    }
    
    if (profile.contact.github) {
        html += `
            <div class="contact-item">
                <i class="fab fa-github"></i>
                <div>
                    <strong>GitHub:</strong><br>
                    <a href="${profile.contact.github}" target="_blank">${profile.contact.github.replace('https://github.com/', '@')}</a>
                </div>
            </div>`;
    }
    
    // Social links
    html += `
            </div>
            
            <div class="social-links">
    `;
    
    if (profile.contact.facebook) {
        html += `<a href="${profile.contact.facebook}" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>`;
    }
    
    if (profile.contact.email) {
        html += `<a href="mailto:${profile.contact.email}" title="Email"><i class="fas fa-envelope"></i></a>`;
    }
    
    if (profile.contact.github) {
        html += `<a href="${profile.contact.github}" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>`;
    }
    
    html += `
                <a href="#" title="Share Profile" onclick="shareProfile('${memberId}'); return false;"><i class="fas fa-share-alt"></i></a>
            </div>
            
            <div class="back-to-team">
                <button onclick="closeProfile()">
                    <i class="fas fa-arrow-left"></i> Back to Team
                </button>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    
    // Add modal-open class to body
    document.body.classList.add('modal-open');
    
    // Focus the close button for accessibility
    setTimeout(() => {
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) closeBtn.focus();
    }, 100);
}

// Handle avatar errors
function handleAvatarError(element, memberName) {
    element.style.backgroundImage = 'none';
    element.style.backgroundColor = '#1e90ff';
    element.style.display = 'flex';
    element.style.alignItems = 'center';
    element.style.justifyContent = 'center';
    element.innerHTML = `<span style="font-size: 60px; color: white; font-weight: bold;">${memberName.charAt(0)}</span>`;
}

// Close profile modal and return to Development Team section
function closeProfile() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    
    // Return to Development Team section
    setTimeout(() => {
        if (developmentTeamSection) {
            // Calculate position relative to Development Team section
            const sectionTop = developmentTeamSection.getBoundingClientRect().top + window.pageYOffset;
            const offset = 80; // Account for fixed header
            
            // Smooth scroll to section
            window.scrollTo({
                top: sectionTop - offset,
                behavior: 'smooth'
            });
        } else {
            // Fallback: scroll to stored position
            window.scrollTo({
                top: scrollPosition,
                behavior: 'smooth'
            });
        }
    }, 100);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target == modal) {
        closeProfile();
    }
}

// Close with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfile();
    }
});

// Share profile function
function shareProfile(memberId) {
    const profile = teamProfiles[memberId];
    const shareText = `Check out ${profile.name} - ${profile.role} at LoFIMS TUP Lopez`;
    const shareUrl = window.location.href + '#development-team';
    
    if (navigator.share) {
        navigator.share({
            title: `${profile.name} - LoFIMS Team`,
            text: shareText,
            url: shareUrl
        });
    } else {
        // Fallback: Copy to clipboard
        const textArea = document.createElement('textarea');
        textArea.value = `${shareText}\n\n${shareUrl}`;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Show success message
        const shareBtn = event.target.closest('a');
        if (shareBtn) {
            const originalHTML = shareBtn.innerHTML;
            shareBtn.innerHTML = '<i class="fas fa-check"></i>';
            shareBtn.style.background = '#2ecc71';
            
            setTimeout(() => {
                shareBtn.innerHTML = originalHTML;
                shareBtn.style.background = '';
            }, 2000);
        }
    }
    return false;
}

// Add fallback for avatar images on page load
document.addEventListener('DOMContentLoaded', function() {
    const avatars = document.querySelectorAll('.member-avatar, .member-avatar-main');
    avatars.forEach(avatar => {
        const bgImage = avatar.style.backgroundImage;
        if (bgImage && bgImage.includes('url')) {
            const imgUrl = bgImage.replace(/url\(['"]?(.*?)['"]?\)/i, '$1');
            const img = new Image();
            img.src = imgUrl;
            img.onerror = function() {
                // Remove any existing content
                avatar.innerHTML = '';
                avatar.style.backgroundImage = 'none';
                avatar.style.backgroundColor = '#1e90ff';
                avatar.style.display = 'flex';
                avatar.style.alignItems = 'center';
                avatar.style.justifyContent = 'center';
                
                // Get name from parent
                const parent = avatar.closest('.team-member, .team-member-main');
                if (parent) {
                    const nameElement = parent.querySelector('h4');
                    if (nameElement) {
                        const name = nameElement.textContent.trim();
                        const fontSize = avatar.classList.contains('member-avatar-main') ? '50px' : '40px';
                        avatar.innerHTML = `<span style="font-size: ${fontSize}; color: white; font-weight: bold;">${name.charAt(0)}</span>`;
                    }
                }
            };
        }
    });
});
</script>

<!-- PROFILE MODAL POPUP -->
<div id="profileModal" class="profile-modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeProfile()">&times;</span>
        <div id="profileContent">
            <!-- Content will be loaded here by JavaScript -->
        </div>
    </div>
</div>

<style>
/* TEAM LAYOUT STYLES */
.team-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 40px;
    max-width: 900px;
    margin: 0 auto;
}

.team-row {
    display: flex;
    justify-content: center;
    gap: 60px;
    width: 100%;
}

/* MAIN MEMBER (YOU) */
.team-member-main {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 30px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 400px;
}

.team-member-main:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar-main {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    margin: 0 auto 20px;
    background-size: cover;
    background-position: center;
    background-color: #f0f4ff;
    border: 4px solid #1e90ff;
    box-shadow: 0 8px 25px rgba(30,144,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e90ff;
    font-size: 50px;
    font-weight: bold;
}

/* REGULAR MEMBERS */
.team-member {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 25px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 300px;
}

.team-member:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background-size: cover;
    background-position: center;
    background-color: #f0f4ff;
    border: 3px solid #1e90ff;
    box-shadow: 0 6px 20px rgba(30,144,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e90ff;
    font-size: 40px;
    font-weight: bold;
}

.click-hint {
    font-size: 12px;
    color: #1e90ff;
    margin-top: 10px;
    font-style: italic;
    opacity: 0.8;
}

/* PROFILE MODAL STYLES - FIXED SCROLLING */
.profile-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
    overflow-y: auto;
    padding: 20px 0;
    backdrop-filter: blur(5px);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 40px;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    position: relative;
    animation: slideIn 0.3s;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    max-height: 85vh;
    overflow-y: auto;
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.close-modal {
    position: absolute;
    right: 25px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.3s;
    z-index: 1001;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.9);
    border-radius: 50%;
}

.close-modal:hover {
    color: #333;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 20px;
    background-size: cover;
    background-position: center;
    background-color: #f0f4ff;
    border: 4px solid #1e90ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e90ff;
    font-size: 40px;
    font-weight: bold;
}

.profile-name {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 5px;
}

.profile-role {
    color: #1e90ff;
    font-weight: 600;
    font-size: 16px;
    background: rgba(30,144,255,0.1);
    padding: 8px 20px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 20px;
}

.profile-info {
    text-align: left;
    margin-top: 20px;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

.info-section {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.info-section:last-child {
    border-bottom: none;
}

.info-section h4 {
    color: #0a3d62;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-section h4 i {
    color: #1e90ff;
    width: 20px;
}

.info-section p {
    color: #555;
    line-height: 1.6;
    margin-left: 30px;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    justify-content: center;
}

.social-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #f0f4ff;
    border-radius: 50%;
    color: #1e90ff;
    text-decoration: none;
    transition: all 0.3s;
}

.social-links a:hover {
    background: #1e90ff;
    color: white;
    transform: translateY(-3px);
}

/* Custom scrollbar styling */
.modal-content::-webkit-scrollbar,
.profile-info::-webkit-scrollbar {
    width: 6px;
}

.modal-content::-webkit-scrollbar-track,
.profile-info::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb,
.profile-info::-webkit-scrollbar-thumb {
    background: #1e90ff;
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb:hover,
.profile-info::-webkit-scrollbar-thumb:hover {
    background: #0d7bd4;
}

/* For Firefox */
.modal-content,
.profile-info {
    scrollbar-width: thin;
    scrollbar-color: #1e90ff #f1f1f1;
}

/* Prevent body scroll when modal is open */
body.modal-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
    height: 100%;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .team-row {
        flex-direction: column;
        align-items: center;
        gap: 30px;
    }
    
    .team-member, .team-member-main {
        max-width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 30px auto;
        padding: 25px 20px;
        max-height: 90vh;
    }
    
    .member-avatar-main {
        width: 150px;
        height: 150px;
        font-size: 40px;
    }
    
    .member-avatar {
        width: 120px;
        height: 120px;
        font-size: 30px;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        font-size: 30px;
    }
    
    .profile-name {
        font-size: 22px;
    }
    
    .profile-info {
        max-height: 300px;
    }
    
    .social-links a {
        width: 35px;
        height: 35px;
    }
}

@media (max-width: 480px) {
    .team-member, .team-member-main {
        padding: 20px;
    }
    
    .modal-content {
        margin: 20px auto;
        padding: 20px 15px;
    }
    
    .profile-name {
        font-size: 20px;
    }
    
    .info-section p {
        margin-left: 15px;
        font-size: 14px;
    }
}

/* Add pulse animation */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.team-member:hover,
.team-member-main:hover {
    animation: pulse 0.5s;
}
</style>

<script>
// Team member data
const teamProfiles = {
    eljay: {
        name: "ELjay M. Felismino",
        role: "Project Lead & Lead Developer",
        education: "Computer Engineering Technology - 3rd Year",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            facebook: "https://facebook.com/eljay.felismino",
            email: "jayDev-code@gmail.com",
            phone: "+63 912 345 6789"
        },
        description: "Oversees the entire project development, implements core system architecture, and manages database design and backend functionality. Responsible for system integration and overall project coordination. Expertise in PHP, MySQL, JavaScript, and full-stack web development."
    },
    lorna: {
        name: "Lorna Castro",
        role: "Documentation Specialist & QA Tester",
        education: "Information Technology",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "lorna.castro@example.com",
            phone: "+63 923 456 7890"
        },
        description: "Manages project documentation, creates user manuals, and performs quality assurance testing to ensure system reliability and user-friendly experience. Specializes in creating comprehensive documentation and conducting thorough testing protocols to identify and resolve system issues."
    },
    maverick: {
        name: "Maverick Capisonda",
        role: "Research Analyst & Content Manager",
        education: "Computer Science",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "maverick.capisonda@example.com",
            phone: "+63 934 567 8901"
        },
        description: "Conducts system requirements research, analyzes user needs, and manages content creation for system features and user documentation. Responsible for gathering user feedback and translating it into actionable development requirements."
    },
    michaella: {
        name: "Michaella Grace Tarala",
        role: "UI/UX Designer & Graphic Artist",
        education: "Multimedia Arts",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "michaella.tarala@example.com",
            phone: "+63 945 678 9012"
        },
        description: "Designs user interfaces, creates visual assets, and ensures optimal user experience across all system platforms and devices. Specializes in responsive design, color theory, and creating intuitive navigation flows that enhance user engagement."
    },
    maurice: {
        name: "Maurice Campillos",
        role: "Deployment Coordinator & Technical Support",
        education: "Information Systems",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "maurice.campillos@example.com",
            phone: "+63 956 789 0123"
        },
        description: "Coordinates system deployment, manages technical support protocols, and assists with user training and system maintenance planning. Ensures smooth system implementation and provides ongoing technical support to users."
    }
};

// Show profile modal
function showProfile(memberId) {
    const profile = teamProfiles[memberId];
    const modal = document.getElementById('profileModal');
    const content = document.getElementById('profileContent');
    
    // Build profile HTML
    let html = `
        <div class="profile-header">
            <div class="profile-avatar" style="background-image: url('../uploads/team_pictures/${memberId}.jpg');" 
                 onerror="this.style.backgroundImage='none'; this.innerHTML='<span style=\"font-size: 40px; color: #1e90ff; font-weight: bold;\">${profile.name.charAt(0)}</span>'">
            </div>
            <h2 class="profile-name">${profile.name}</h2>
            <div class="profile-role">${profile.role}</div>
        </div>
        
        <div class="profile-info">
            <div class="info-section">
                <h4><i class="fas fa-graduation-cap"></i> Education</h4>
                <p><strong>${profile.education}</strong></p>
                <p>${profile.school}</p>
            </div>
            
            <div class="info-section">
                <h4><i class="fas fa-user"></i> About</h4>
                <p>${profile.description}</p>
            </div>
            
            <div class="info-section">
                <h4><i class="fas fa-address-card"></i> Contact Information</h4>
    `;
    
    // Add contact info
    if (profile.contact.facebook) {
        html += `<p><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook: <a href="${profile.contact.facebook}" target="_blank">${profile.contact.facebook.replace('https://facebook.com/', '')}</a></p>`;
    }
    
    if (profile.contact.email) {
        html += `<p><i class="fas fa-envelope" style="color: #ea4335;"></i> Email: <a href="mailto:${profile.contact.email}">${profile.contact.email}</a></p>`;
    }
    
    if (profile.contact.phone) {
        html += `<p><i class="fas fa-phone" style="color: #34a853;"></i> Phone: ${profile.contact.phone}</p>`;
    }
    
    // Social links
    html += `
            </div>
            
            <div class="social-links">
    `;
    
    if (profile.contact.facebook) {
        html += `<a href="${profile.contact.facebook}" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>`;
    }
    
    if (profile.contact.email) {
        html += `<a href="mailto:${profile.contact.email}" title="Email"><i class="fas fa-envelope"></i></a>`;
    }
    
    html += `
                <a href="#" title="Share Profile" onclick="shareProfile('${memberId}'); return false;"><i class="fas fa-share-alt"></i></a>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    
    // Add modal-open class to body
    document.body.classList.add('modal-open');
    
    // Focus the close button for accessibility
    setTimeout(() => {
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) closeBtn.focus();
    }, 100);
}

// Close profile modal
function closeProfile() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target == modal) {
        closeProfile();
    }
}

// Close with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfile();
    }
});

// Share profile function
function shareProfile(memberId) {
    const profile = teamProfiles[memberId];
    const shareText = `Check out ${profile.name} - ${profile.role} at LoFIMS TUP Lopez`;
    const shareUrl = window.location.href;
    
    if (navigator.share) {
        navigator.share({
            title: `${profile.name} - LoFIMS Team`,
            text: shareText,
            url: shareUrl
        });
    } else {
        // Fallback: Copy to clipboard
        const textArea = document.createElement('textarea');
        textArea.value = `${shareText}\n\n${shareUrl}`;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Show success message
        const originalText = event.target.innerHTML;
        event.target.innerHTML = '<i class="fas fa-check"></i>';
        event.target.style.color = '#2ecc71';
        
        setTimeout(() => {
            event.target.innerHTML = originalText;
            event.target.style.color = '';
        }, 2000);
    }
    return false;
}

// Add fallback for avatar images
document.addEventListener('DOMContentLoaded', function() {
    const avatars = document.querySelectorAll('.member-avatar, .member-avatar-main');
    avatars.forEach(avatar => {
        const bgImage = avatar.style.backgroundImage;
        if (bgImage && bgImage.includes('url')) {
            const imgUrl = bgImage.replace(/url\(['"]?(.*?)['"]?\)/i, '$1');
            const img = new Image();
            img.src = imgUrl;
            img.onerror = function() {
                // If image fails to load, show initials
                const parent = avatar.closest('.team-member, .team-member-main');
                if (parent) {
                    const nameElement = parent.querySelector('h4');
                    if (nameElement) {
                        const name = nameElement.textContent.trim();
                        const initials = name.split(' ').map(n => n.charAt(0)).join('');
                        avatar.innerHTML = `<span style="font-size: ${avatar.classList.contains('member-avatar-main') ? '50px' : '40px'}; color: #1e90ff; font-weight: bold;">${initials}</span>`;
                        avatar.style.backgroundImage = 'none';
                    }
                }
            };
        }
    });
});

// Add smooth transitions
document.addEventListener('DOMContentLoaded', function() {
    // Add CSS for transitions
    const style = document.createElement('style');
    style.textContent = `
        .team-member, .team-member-main {
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
        }
        
        .modal-content {
            transition: transform 0.3s ease-out;
        }
        
        .profile-modal {
            transition: opacity 0.3s;
        }
    `;
    document.head.appendChild(style);
});

// Handle image loading errors for modal avatars
function handleAvatarError(element, memberName) {
    element.style.backgroundImage = 'none';
    element.innerHTML = `<span style="font-size: 40px; color: #1e90ff; font-weight: bold;">${memberName.charAt(0)}</span>`;
}
</script>

<!-- PROFILE MODAL POPUP -->
<div id="profileModal" class="profile-modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeProfile()">&times;</span>
        <div id="profileContent">
            <!-- Content will be loaded here by JavaScript -->
        </div>
    </div>
</div>

<style>
/* TEAM LAYOUT STYLES */
.team-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 40px;
    max-width: 900px;
    margin: 0 auto;
}

.team-row {
    display: flex;
    justify-content: center;
    gap: 60px;
    width: 100%;
}

/* MAIN MEMBER (YOU) */
.team-member-main {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 30px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 400px;
}

.team-member-main:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar-main {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    margin: 0 auto 20px;
    background-size: cover;
    background-position: center;
    border: 4px solid #1e90ff;
    box-shadow: 0 8px 25px rgba(30,144,255,0.3);
}

/* REGULAR MEMBERS */
.team-member {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    padding: 25px;
    border-radius: 25px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    border: 2px solid rgba(52,152,219,0.3);
    width: 100%;
    max-width: 300px;
}

.team-member:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
    border-color: #1e90ff;
}

.member-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background-size: cover;
    background-position: center;
    border: 3px solid #1e90ff;
    box-shadow: 0 6px 20px rgba(30,144,255,0.2);
}

.click-hint {
    font-size: 12px;
    color: #1e90ff;
    margin-top: 10px;
    font-style: italic;
    opacity: 0.8;
}

/* PROFILE MODAL STYLES */
.profile-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 40px;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    position: relative;
    animation: slideIn 0.3s;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.close-modal {
    position: absolute;
    right: 25px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.3s;
}

.close-modal:hover {
    color: #333;
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 20px;
    background-size: cover;
    background-position: center;
    border: 4px solid #1e90ff;
}

.profile-name {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 5px;
}

.profile-role {
    color: #1e90ff;
    font-weight: 600;
    font-size: 16px;
    background: rgba(30,144,255,0.1);
    padding: 8px 20px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 20px;
}

.profile-info {
    text-align: left;
    margin-top: 20px;
}

.info-section {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.info-section:last-child {
    border-bottom: none;
}

.info-section h4 {
    color: #0a3d62;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-section h4 i {
    color: #1e90ff;
    width: 20px;
}

.info-section p {
    color: #555;
    line-height: 1.6;
    margin-left: 30px;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    justify-content: center;
}

.social-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #f0f4ff;
    border-radius: 50%;
    color: #1e90ff;
    text-decoration: none;
    transition: all 0.3s;
}

.social-links a:hover {
    background: #1e90ff;
    color: white;
    transform: translateY(-3px);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .team-row {
        flex-direction: column;
        align-items: center;
        gap: 30px;
    }
    
    .team-member, .team-member-main {
        max-width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
        padding: 30px 20px;
    }
    
    .member-avatar-main {
        width: 150px;
        height: 150px;
    }
    
    .member-avatar {
        width: 120px;
        height: 120px;
    }
}
</style>

<script>
// Team member data
const teamProfiles = {
    eljay: {
        name: "ELjay M. Felismino",
        role: "Project Lead & Lead Developer",
        education: "Computer Engineering Technology - 3rd Year",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            facebook: "https://facebook.com/eljay.felismino",
            email: "jayDev-code@gmail.com",
            phone: "+63 912 345 6789"
        },
        description: "Oversees the entire project development, implements core system architecture, and manages database design and backend functionality. Responsible for system integration and overall project coordination."
    },
    lorna: {
        name: "Lorna Castro",
        role: "Documentation Specialist & QA Tester",
        education: "Information Technology",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "lorna.castro@example.com",
            phone: "+63 923 456 7890"
        },
        description: "Manages project documentation, creates user manuals, and performs quality assurance testing to ensure system reliability and user-friendly experience."
    },
    maverick: {
        name: "Maverick Capisonda",
        role: "Research Analyst & Content Manager",
        education: "Computer Science",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "maverick.capisonda@example.com",
            phone: "+63 934 567 8901"
        },
        description: "Conducts system requirements research, analyzes user needs, and manages content creation for system features and user documentation."
    },
    michaella: {
        name: "Michaella Grace Tarala",
        role: "UI/UX Designer & Graphic Artist",
        education: "Multimedia Arts",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "michaella.tarala@example.com",
            phone: "+63 945 678 9012"
        },
        description: "Designs user interfaces, creates visual assets, and ensures optimal user experience across all system platforms and devices."
    },
    maurice: {
        name: "Maurice Campillos",
        role: "Deployment Coordinator & Technical Support",
        education: "Information Systems",
        school: "Technological University of the Philippines - Lopez, Quezon",
        contact: {
            email: "maurice.campillos@example.com",
            phone: "+63 956 789 0123"
        },
        description: "Coordinates system deployment, manages technical support protocols, and assists with user training and system maintenance planning."
    }
};

// Show profile modal
function showProfile(memberId) {
    const profile = teamProfiles[memberId];
    const modal = document.getElementById('profileModal');
    const content = document.getElementById('profileContent');
    
    // Build profile HTML
    let html = `
        <div class="profile-header">
            <div class="profile-avatar" style="background-image: url('../uploads/team_pictures/${memberId}.jpg')"></div>
            <h2 class="profile-name">${profile.name}</h2>
            <div class="profile-role">${profile.role}</div>
        </div>
        
        <div class="profile-info">
            <div class="info-section">
                <h4><i class="fas fa-graduation-cap"></i> Education</h4>
                <p><strong>${profile.education}</strong></p>
                <p>${profile.school}</p>
            </div>
            
            <div class="info-section">
                <h4><i class="fas fa-user"></i> About</h4>
                <p>${profile.description}</p>
            </div>
            
            <div class="info-section">
                <h4><i class="fas fa-address-card"></i> Contact Information</h4>
    `;
    
    // Add contact info
    if (profile.contact.facebook) {
        html += `<p><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook: <a href="${profile.contact.facebook}" target="_blank">${profile.contact.facebook.replace('https://facebook.com/', '')}</a></p>`;
    }
    
    if (profile.contact.email) {
        html += `<p><i class="fas fa-envelope" style="color: #ea4335;"></i> Email: <a href="mailto:${profile.contact.email}">${profile.contact.email}</a></p>`;
    }
    
    if (profile.contact.phone) {
        html += `<p><i class="fas fa-phone" style="color: #34a853;"></i> Phone: ${profile.contact.phone}</p>`;
    }
    
    // Social links
    html += `
            </div>
            
            <div class="social-links">
    `;
    
    if (profile.contact.facebook) {
        html += `<a href="${profile.contact.facebook}" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>`;
    }
    
    if (profile.contact.email) {
        html += `<a href="mailto:${profile.contact.email}" title="Email"><i class="fas fa-envelope"></i></a>`;
    }
    
    html += `
                <a href="#" title="Share Profile" onclick="shareProfile('${memberId}')"><i class="fas fa-share-alt"></i></a>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

// Close profile modal
function closeProfile() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target == modal) {
        closeProfile();
    }
}

// Close with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfile();
    }
});

// Share profile function
function shareProfile(memberId) {
    const profile = teamProfiles[memberId];
    const shareText = `Check out ${profile.name} - ${profile.role} at LoFIMS TUP Lopez`;
    const shareUrl = window.location.href;
    
    if (navigator.share) {
        navigator.share({
            title: `${profile.name} - LoFIMS Team`,
            text: shareText,
            url: shareUrl
        });
    } else {
        // Fallback: Copy to clipboard
        const textArea = document.createElement('textarea');
        textArea.value = `${shareText}\n\n${shareUrl}`;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        alert('Profile link copied to clipboard!');
    }
    return false;
}

// Add hover effects
document.addEventListener('DOMContentLoaded', function() {
    const teamMembers = document.querySelectorAll('.team-member, .team-member-main');
    
    teamMembers.forEach(member => {
        // Add pulse animation on hover
        member.addEventListener('mouseenter', function() {
            this.style.animation = 'pulse 0.5s';
        });
        
        member.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });
});

// Add pulse animation to CSS
const style = document.createElement('style');
style.textContent = `
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

/* Add smooth transitions for modal */
.modal-content {
    transition: transform 0.3s ease-out;
}

/* Add backdrop blur effect */
.profile-modal {
    backdrop-filter: blur(5px);
}

/* Make modal responsive */
@media (max-width: 600px) {
    .profile-avatar {
        width: 100px;
        height: 100px;
    }
    
    .profile-name {
        font-size: 22px;
    }
    
    .social-links a {
        width: 35px;
        height: 35px;
    }
}
`;
document.head.appendChild(style);
</script>

    <!-- CONTACT INFO - UPDATED EMAIL -->
    <section class="about-section fade-in">
        <h2 class="section-title">Contact Information</h2>
        <div class="contact-info">
            <div class="contact-item">
                <div class="contact-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h4>Location</h4>
                <p>Technological University of the Philippines<br>Lopez, Quezon Campus<br>Lopez, Quezon</p>
            </div>
            <div class="contact-item">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h4>Email</h4>
                <p>lofims.system@gmail.com</p>
            </div>
            <div class="contact-item">
                <div class="contact-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h4>Phone</h4>
                <p>(042) 555-1234 (Administration)<br>(042) 555-5678 (Support)</p>
            </div>
            <div class="contact-item">
                <div class="contact-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h4>Office Hours</h4>
                <p>Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 9:00 AM - 12:00 PM</p>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <div class="cta-button">
        <button class="cta-btn" onclick="window.location.href='index.php'">
            <i class="fas fa-home"></i> Back to Homepage
        </button>
    </div>
</div>

<!-- FOOTER WITH COMPREHENSIVE NAVIGATION - UPDATED EMAIL -->
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
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
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
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
            </ul>
        </div>
        
        <!-- SUPPORT -->
        <div class="footer-section">
            <h3>Support</h3>
            <ul class="footer-links">
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                <li><a href="support.php"><i class="fas fa-headset"></i> Support Center</a></li>
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><a href="success_stories.php"><i class="fas fa-trophy"></i> Success Stories</a></li>
                <li><a href="sitemap.php"><i class="fas fa-sitemap"></i> Sitemap</a></li>
            </ul>
        </div>
        
        <!-- CONTACT INFO - UPDATED EMAIL -->
        <div class="footer-section">
            <h3>Contact</h3>
            <ul class="footer-links">
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
                <li><i class="fas fa-envelope"></i> lofims.system@gmail.com</li>
                <li><i class="fas fa-phone"></i> (042) 555-1234</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-user-graduate"></i> For TUP Students & Staff</li>
                <li><i class="fas fa-database"></i> Database Status: 
                    <?php echo $dbError ? '<span style="color: #e74c3c;">Offline</span>' : '<span style="color: #2ecc71;">Online</span>'; ?>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
        <br>
        <small>System Version 2.0 • Last updated: <?php echo date('F d, Y'); ?></small>
        <br>
        <small style="color: #1e90ff; margin-top: 5px; display: inline-block;">
            <i class="fas fa-chart-bar"></i> Statistics: 
            <?php echo $categoriesCount; ?> Categories • 
            <?php echo $lostCount + $foundCount; ?> Total Items • 
            <?php echo $usersCount; ?> Registered Users
        </small>
    </div>
</footer>

<script>
// FADE-IN ON SCROLL
const faders = document.querySelectorAll('.fade-in');
const appearOptions = { 
    threshold: 0.2, 
    rootMargin: "0px 0px -50px 0px" 
};

const appearOnScroll = new IntersectionObserver(function(entries, appearOnScroll) {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('visible');
        appearOnScroll.unobserve(entry.target);
    });
}, appearOptions);

faders.forEach(fader => appearOnScroll.observe(fader));

// Smooth scroll for navigation links
document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        
        // Only process anchor links
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

// Handle logo loading errors
document.addEventListener('DOMContentLoaded', function() {
    const logoImages = document.querySelectorAll('img[alt="LoFIMS Logo"]');
    logoImages.forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            const fallback = this.nextElementSibling;
            if (fallback && fallback.classList.contains('logo-fallback')) {
                fallback.style.display = 'flex';
            }
        });
    });
});

// Handle team picture loading errors
document.addEventListener('DOMContentLoaded', function() {
    const teamAvatars = document.querySelectorAll('.member-avatar');
    teamAvatars.forEach(avatar => {
        const bgImage = avatar.style.backgroundImage;
        if (bgImage && bgImage.includes('url')) {
            const img = new Image();
            img.src = bgImage.replace(/url\(['"]?(.*?)['"]?\)/i, '$1');
            img.onerror = function() {
                // If image fails to load, show initials
                const initials = avatar.textContent.trim();
                if (initials) {
                    avatar.innerHTML = `<span style="font-size: 40px; color: white;">${initials}</span>`;
                }
            };
        }
    });
});

</script>
</body>
</html>