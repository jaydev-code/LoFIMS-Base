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
    // If session has user info, use it
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        $firstName = $_SESSION['first_name'];
        $lastName = $_SESSION['last_name'];
        $userName = trim($firstName . ' ' . $lastName);
    } else {
        // If session doesn't have name, fetch from database
        try {
            $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $userName = trim($firstName . ' ' . $lastName);
                
                // Update session with user info
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $user['email'] ?? '';
            } else {
                // User not found in database
                $userName = 'User';
                $isLoggedIn = false; // Force logout
                session_destroy();
            }
        } catch (PDOException $e) {
            // Database error
            $userName = 'User';
        }
    }
    
    // If name is still empty, show email or username
    if (empty($userName)) {
        $userName = $_SESSION['email'] ?? $_SESSION['username'] ?? 'User';
    }
} else {
    $userName = '';
}

// Logo paths - Updated to use your actual logo
$logoPath = '../assets/images/lofims-logo.png';
$footerLogoPath = '../assets/images/lofims-logo.png'; // Same logo for footer

// Check if logo exists, fallback to text if not
$logoExists = file_exists($logoPath);

// Database connection already established by config.php as $pdo
try {
    // Fetch statistics using $pdo from config.php
    // Lost items count (excluding claimed/resolved)
    $lostCount = $pdo->query("SELECT COUNT(*) as count FROM lost_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Found items count (excluding claimed/resolved)
    $foundCount = $pdo->query("SELECT COUNT(*) as count FROM found_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Pending claims
    $claimsCount = $pdo->query("SELECT COUNT(*) as count FROM claims WHERE status = 'Pending'")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Approved/solved claims
    $solvedCount = $pdo->query("SELECT COUNT(*) as count FROM claims WHERE status = 'Approved'")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Recent announcements (last 30 days)
    $announcements = $pdo->query("
        SELECT title, content, DATE_FORMAT(created_at, '%Y-%m-%d') as date 
        FROM announcements 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC 
        LIMIT 2
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent lost items
    $recentLost = $pdo->query("
        SELECT item_name, DATE_FORMAT(date_reported, '%b %d') as date 
        FROM lost_items 
        WHERE status NOT IN ('Claimed', 'Resolved')
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Categories count
    $categoriesCount = $pdo->query("SELECT COUNT(*) as count FROM item_categories")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total users
    $usersCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Success rate
    $totalClaims = $pdo->query("SELECT COUNT(*) as count FROM claims")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $successRate = $totalClaims > 0 ? round(($solvedCount / $totalClaims) * 100) : 0;
    
    $dbError = false;
    
} catch (PDOException $e) {
    // Fallback values if database query fails
    $lostCount = $foundCount = $claimsCount = $solvedCount = $categoriesCount = $usersCount = 0;
    $successRate = 0;
    $announcements = [];
    $recentLost = [];
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LoFIMS - Homepage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Add favicon from your logo -->
<link rel="icon" href="<?php echo $logoPath; ?>" type="image/png">

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

/* HEADER - IMPROVED LAYOUT */
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
    flex-shrink: 0; /* Prevent logo from shrinking */
}
.logo-container:hover {
    transform: translateX(3px);
}
/* UPDATED LOGO STYLES WITH BORDER RADIUS */
.logo-container img {
    height:55px;
    width:55px;
    border-radius: 16px;
    transition: transform 0.3s, box-shadow 0.3s, border-radius 0.3s;
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
    min-width: 0; /* Allow text to shrink properly */
}
.logo-text .logo-title {
    font-weight: 800;
    color: #0a3d62;
    font-size: 20px;
    letter-spacing: 0.3px;
    white-space: nowrap; /* Prevent line break */
}
.logo-text .logo-subtitle {
    font-size: 11px;
    color: #1e90ff;
    font-weight: 600;
    letter-spacing: 0.2px;
    white-space: nowrap; /* Prevent line break */
}

/* IMPROVED NAVIGATION */
nav {
    display: flex;
    align-items: center;
    flex: 1;
    justify-content: flex-end;
    min-width: 0; /* Allow nav to shrink */
}
nav ul {
    list-style:none;
    display:flex;
    gap:20px;
    align-items:center;
    flex-wrap: nowrap; /* Prevent wrapping */
    justify-content: flex-end;
}
nav ul li {
    flex-shrink: 0; /* Prevent nav items from shrinking */
}
nav ul li a {
    text-decoration:none;
    font-size:14px;
    color:#0a3d62;
    font-weight:500;
    position: relative;
    padding: 6px 0;
    transition: color 0.3s;
    white-space: nowrap; /* Prevent text wrapping */
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

/* USER INFO IN NAV */
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    font-size: 13px;
    color: #0a3d62;
    white-space: nowrap;
    flex-shrink: 0;
}
.user-info i {
    color: #1e90ff;
}

/* COMPACT LOGIN/LOGOUT BUTTON */
.nav-btn {
    padding: 8px 18px;
    background: linear-gradient(45deg,#1e90ff,#4facfe);
    color: white;
    font-size: 13px;
    font-weight: bold;
    border-radius: 10px;
    border: 2px solid rgba(30,144,255,0.8);
    cursor: pointer;
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s, background 0.3s;
    white-space: nowrap;
    flex-shrink: 0;
    text-decoration: none;
    display: inline-block;
}
.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    background: linear-gradient(45deg,#0d7bd4,#3a9cfc);
    color: white;
}

/* HAMBURGER MENU FOR MOBILE */
.hamburger {
    display: none;
    flex-direction: column;
    gap: 4px;
    cursor: pointer;
    padding: 8px;
    background: rgba(30,144,255,0.1);
    border-radius: 8px;
    border: none;
}
.hamburger span {
    width: 24px;
    height: 2px;
    background: #0a3d62;
    border-radius: 2px;
    transition: 0.3s;
}
.hamburger.active span:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}
.hamburger.active span:nth-child(2) {
    opacity: 0;
}
.hamburger.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
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
    padding:70px 20px;
    position: relative;
    overflow: hidden;
}
@keyframes bgGradient { 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }
.content {
    display:flex;
    justify-content:center;
    align-items:flex-start;
    gap:50px;
    flex-wrap:wrap;
    min-height:450px;
}
.content-text {
    flex:1 1 500px;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    padding:60px 40px;
    border-radius:22px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    animation:floatUpDown 6s ease-in-out infinite;
    position: relative;
    overflow: hidden;
}
.content-text::before {
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
.big-title { 
    font-size:68px; 
    font-weight:900; 
    color:#0a3d62; 
    line-height:1.1; 
    margin-bottom:25px; 
    background: linear-gradient(45deg, #0a3d62, #1e90ff); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1); 
}
.slogan { 
    font-size:20px; 
    color:#555; 
    margin-bottom:35px; 
    line-height: 1.6;
}
.cta-buttons { 
    display: flex; 
    gap: 15px; 
    margin-top: 30px; 
    flex-wrap: wrap;
}
.cta-btn {
    padding: 12px 22px;
    border-radius: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 170px;
    justify-content: center;
}
.cta-primary { 
    background: #1e90ff; 
    color: white; 
    box-shadow: 0 5px 15px rgba(30,144,255,0.3); 
}
.cta-primary:hover { 
    background: #0d7bd4; 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(30,144,255,0.4); 
}
.cta-secondary { 
    background: transparent; 
    color: #0a3d62; 
    border: 2px solid #0a3d62; 
}
.cta-secondary:hover { 
    background: rgba(10,61,98,0.1); 
    transform: translateY(-3px); 
}

.divider { 
    width:5px; 
    height:350px; 
    background:#0a3d62; 
    border-radius:4px; 
    animation:floatUpDown 6s ease-in-out infinite alternate; 
    position: relative; 
    overflow: hidden; 
}
.divider::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, transparent, #1e90ff, transparent);
    animation: shine 2s ease-in-out infinite;
}
@keyframes shine { 0% { transform: translateY(-100%); } 100% { transform: translateY(100%); } }

.right-panel {
    flex:0 0 300px;
    text-align:center;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:20px;
    animation:floatUpDown 6s ease-in-out infinite alternate-reverse;
}
.right-title { 
    font-size:36px; 
    font-weight:800; 
    color:#0a3d62; 
    margin-bottom:20px; 
    text-shadow: 1px 1px 3px rgba(0,0,0,0.1); 
}
.box-container { 
    display:flex; 
    flex-direction:column; 
    gap:25px; 
    align-items:center; 
}
.info-box {
    width:300px;
    padding:30px;
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    border-radius:22px;
    font-size:22px;
    font-weight:bold;
    color:white;
    cursor:pointer;
    border:2px solid rgba(41,128,185,0.8);
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}
.info-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}
.info-box:hover::before { left: 100%; }
.info-box:hover { 
    transform:translateY(-5px) translateX(4px); 
    background:rgba(41,128,185,0.9); 
    box-shadow:0 15px 25px rgba(0,0,0,0.2); 
}

@keyframes floatUpDown { 0%{transform:translateY(0px);} 50%{transform:translateY(-12px);} 100%{transform:translateY(0px);} }

/* HOW IT WORKS */
.how-it-works-wrapper {
    padding:70px 20px;
    text-align:center;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:50px auto;
    border-radius:22px;
    max-width:1100px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    position: relative;
    overflow: hidden;
}
.how-it-works-wrapper::before {
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
    font-size:42px;
    font-weight:800;
    color:#0a3d62;
    margin-bottom:40px;
    position: relative;
    display: inline-block;
}
.section-title::after {
    content: '';
    position: absolute;
    bottom: -12px;
    left: 50%;
    transform: translateX(-50%);
    width: 70px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}
.steps-container {
    display:flex;
    justify-content:center;
    gap:35px;
    flex-wrap:wrap;
}
.step-card {
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    padding:35px 25px;
    border-radius:22px;
    width:260px;
    color:white;
    font-weight:bold;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:18px;
    animation:floatStep 6s ease-in-out infinite alternate;
    position: relative;
    overflow: hidden;
}
.step-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #1e90ff;
}
.step-card i { 
    color:#fff; 
    font-size: 42px; 
    transition: transform 0.3s; 
}
.step-card:hover i { 
    transform: scale(1.1); 
}
.step-card h3 { 
    font-size:22px; 
    margin-bottom:8px; 
    text-align: center;
}
.step-card p { 
    font-size:15px; 
    color:#f0f8ff; 
    text-align: center; 
    line-height: 1.5; 
}
.step-card:hover { 
    transform:translateY(-10px); 
    background:rgba(41,128,185,0.85); 
    box-shadow: 0 18px 30px rgba(0,0,0,0.2); 
}

/* ANNOUNCEMENTS */
.announcements-wrapper {
    padding:50px 20px;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:50px auto;
    border-radius:22px;
    max-width:1100px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    text-align:center;
    position: relative;
    overflow: hidden;
}
.announcements-wrapper::before {
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
.announcement {
    background:rgba(52,152,219,0.8);
    padding:22px 25px;
    margin:18px 0;
    border-radius:14px;
    color:white;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    text-align: left;
    position: relative;
    overflow: hidden;
}
.announcement::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: #1e90ff;
}
.announcement:hover {
    transform:translateY(-5px);
    background:rgba(41,128,185,0.85);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}
.announcement-title { 
    font-size:18px; 
    font-weight:bold; 
    margin-bottom:6px; 
}
.announcement-date { 
    font-size:13px; 
    color:#f0f8ff; 
    margin-bottom:8px; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.announcement-text { 
    font-size:15px; 
    color:#f0f8ff; 
    line-height: 1.5; 
}
.view-all { 
    display: inline-block; 
    margin-top: 18px; 
    color: #1e90ff; 
    font-weight: bold; 
    text-decoration: none; 
    transition: all 0.3s; 
    padding: 7px 14px; 
    border-radius: 8px; 
    border: 2px solid transparent; 
}
.view-all:hover { 
    background: rgba(30,144,255,0.1); 
    transform: translateX(4px); 
    border-color: #1e90ff; 
}

/* STATISTICS */
.stats-wrapper {
    padding:70px 20px;
    text-align:center;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:50px auto;
    border-radius:22px;
    max-width:1100px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    position: relative;
    overflow: hidden;
}
.stats-wrapper::before {
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
.stats-container { 
    display:flex; 
    justify-content:center; 
    gap:35px; 
    flex-wrap:wrap; 
    margin-top:40px; 
}
.stat-card {
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    padding:35px 25px;
    border-radius:22px;
    width:260px;
    color:white;
    font-weight:bold;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:18px;
    font-size:16px;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    animation:floatStep 6s ease-in-out infinite alternate;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}
.stat-card::before { 
    content: ''; 
    position: absolute; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 5px; 
    background: #1e90ff; 
}
.stat-card:hover { 
    transform:translateY(-8px); 
    background:rgba(41,128,185,0.85); 
    box-shadow: 0 16px 28px rgba(0,0,0,0.2); 
}
.stat-number { 
    font-size:38px; 
    font-weight:900; 
    color:#fff; 
    text-shadow: 1px 1px 3px rgba(0,0,0,0.2); 
}
.stat-label { 
    font-size:18px; 
    color:#f0f8ff; 
}

/* FOOTER STYLES - UPDATED */
footer {
    width:100%;
    padding:50px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
    backdrop-filter:blur(8px);
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

.footer-links i {
    width: 18px;
    text-align: center;
}

.copyright {
    text-align: center;
    margin-top: 35px;
    padding-top: 18px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 13px;
}

footer a { 
    color:#0a3d62; 
    text-decoration:none; 
    font-weight:bold; 
    transition:all 0.3s; 
}
footer a:hover { 
    color:#1e90ff; 
}

/* UPDATED FOOTER LOGO STYLES */
.footer-logo {
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.footer-logo img {
    height: 55px;
    width: 55px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    object-fit: cover;
    border: 3px solid white;
    padding: 4px;
    background: white;
    transition: transform 0.3s, box-shadow 0.3s;
}
.footer-logo img:hover {
    transform: rotate(4deg) scale(1.05);
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
}
.footer-logo-text {
    display: flex;
    flex-direction: column;
}
.footer-logo-text .logo-title {
    font-weight: 800;
    color: #0a3d62;
    font-size: 20px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}
.footer-logo-text .logo-subtitle {
    font-size: 12px;
    color: #555;
    font-weight: 600;
}

/* RESPONSIVE DESIGN - IMPROVED */
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
        font-size: 18px;
    }
    
    .logo-text .logo-subtitle {
        font-size: 10px;
    }
}

@media (max-width: 900px) {
    .logo-text .logo-subtitle {
        display: none; /* Hide subtitle on medium screens */
    }
    
    nav ul li a span {
        display: none; /* Hide text, keep icons */
    }
    
    nav ul li a {
        font-size: 16px;
        padding: 8px;
    }
    
    .user-info span {
        display: none; /* Hide user name, keep icon */
    }
}

@media (max-width: 768px) {
    header {
        padding: 10px 20px;
        flex-wrap: wrap;
    }
    
    .logo-container {
        order: 1;
    }
    
    .hamburger {
        display: flex;
        order: 2;
        margin-left: auto;
        margin-right: 15px;
    }
    
    nav {
        order: 3;
        width: 100%;
        display: none;
        margin-top: 15px;
    }
    
    nav.active {
        display: block;
    }
    
    nav ul {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
        background: rgba(255,255,255,0.95);
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    nav ul li {
        width: 100%;
    }
    
    nav ul li a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        border-radius: 8px;
        background: rgba(240, 244, 255, 0.5);
        font-size: 15px;
    }
    
    nav ul li a span {
        display: inline; /* Show text in mobile menu */
    }
    
    .user-info {
        justify-content: center;
        padding: 12px;
    }
    
    .user-info span {
        display: inline; /* Show user name in mobile menu */
    }
    
    .nav-btn {
        width: 100%;
        text-align: center;
        margin-top: 5px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .big-title {
        font-size: 42px;
    }
    
    .content {
        flex-direction: column;
        align-items: center;
        gap: 30px;
    }
    
    .divider {
        height: 5px;
        width: 80%;
        margin: 30px 0;
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
    }
    
    .footer-logo img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
    }
    
    .cta-btn {
        min-width: 100%;
    }
    
    .info-box {
        width: 100%;
        max-width: 300px;
    }
    
    .right-panel {
        flex: 1 1 100%;
    }
    
    .section-title {
        font-size: 36px;
    }
    
    .content-text {
        padding: 40px 25px;
    }
}

@media (max-width: 480px) {
    .logo-text .logo-title {
        font-size: 16px;
    }
    
    .big-title {
        font-size: 36px;
    }
    
    .slogan {
        font-size: 17px;
    }
    
    .cta-btn {
        font-size: 14px;
        padding: 10px 18px;
    }
    
    .step-card, .stat-card, .info-box {
        width: 100%;
    }
}

/* LOADING STATES */
.loading {
    opacity: 0.7;
    position: relative;
}
.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 18px;
    height: 18px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* LOGO FALLBACK STYLE */
.logo-fallback {
    width: 55px;
    height: 55px;
    background: linear-gradient(135deg, #1e90ff, #4facfe);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    border: 3px solid white;
    transition: transform 0.3s, box-shadow 0.3s;
}
.logo-container:hover .logo-fallback {
    transform: rotate(4deg) scale(1.05);
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    border-radius: 18px;
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

<!-- HEADER - WITH IMPROVED NAVIGATION -->
<header>
    <a href="index.php" class="logo-container">
        <?php if ($logoExists): ?>
            <img src="<?php echo $logoPath; ?>" alt="LoFIMS Logo" 
                 onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
            <div id="logo-fallback" class="logo-fallback" style="display: none;">LF</div>
        <?php else: ?>
            <div class="logo-fallback">LF</div>
        <?php endif; ?>
        <div class="logo-text">
            <span class="logo-title">LoFIMS</span>
            <span class="logo-subtitle">Lost & Found Management System</span>
        </div>
    </a>
    
    <!-- HAMBURGER MENU FOR MOBILE -->
    <button class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <nav id="navMenu">
        <ul>
            <!-- CLEAN HEADER NAVIGATION -->
            <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> <span>Lost</span></a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> <span>Found</span></a></li>
            <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> <span>Claims</span></a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i> <span>About</span></a></li>
            
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

<!-- MAIN CONTENT -->
<div class="content-wrapper fade-in">
    <div class="content">
        <div class="content-text fade-in">
            <div class="big-title">Find. Report. Return.</div>
            
            <div class="slogan">The official Lost and Found Management System for TUP Lopez Quezon that helps manage lost and found items efficiently.</div>
            
            <!-- Recent Lost Items -->
            <?php if (!empty($recentLost)): ?>
            <div style="margin-top: 20px; padding: 15px; background: rgba(52,152,219,0.1); border-radius: 10px;">
                <h4 style="color: #0a3d62; margin-bottom: 10px;"><i class="fas fa-clock"></i> Recently Reported Lost Items</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach($recentLost as $item): ?>
                    <span style="background: rgba(30,144,255,0.1); color: #0a3d62; padding: 5px 10px; border-radius: 15px; font-size: 14px; border: 1px solid rgba(30,144,255,0.3);">
                        <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo htmlspecialchars($item['date']); ?>)
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="cta-buttons">
                <button class="cta-btn cta-primary" onclick="window.location.href='lost_items.php'">
                    <i class="fas fa-search"></i> Search Lost Items
                </button>
                <button class="cta-btn cta-secondary" onclick="window.location.href='found_items.php'">
                    <i class="fas fa-plus-circle"></i> Report Found Item
                </button>
                <button class="cta-btn cta-secondary" onclick="window.location.href='claim_item.php'">
                    <i class="fas fa-hand-holding"></i> View Claims
                </button>
            </div>
        </div>
        <div class="divider"></div>
        <div class="right-panel fade-in">
            <div class="right-title">TUP-LoFIMS</div>
            <div class="box-container">
                <div class="info-box fade-in" onclick="window.location.href='claim_item.php'">
                    <i class="fas fa-hand-holding"></i> Claims <span style="font-size: 15px; background: white; color: #3498db; padding: 2px 8px; border-radius: 10px; margin-left: 8px;"><?php echo $claimsCount; ?></span>
                </div>
                <div class="info-box fade-in" onclick="window.location.href='lost_items.php'">
                    <i class="fas fa-search"></i> Lost Items <span style="font-size: 15px; background: white; color: #3498db; padding: 2px 8px; border-radius: 10px; margin-left: 8px;"><?php echo $lostCount; ?></span>
                </div>
                <div class="info-box fade-in" onclick="window.location.href='found_items.php'">
                    <i class="fas fa-box"></i> Found Items <span style="font-size: 15px; background: white; color: #3498db; padding: 2px 8px; border-radius: 10px; margin-left: 8px;"><?php echo $foundCount; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HOW IT WORKS -->
<div class="how-it-works-wrapper fade-in">
    <div class="section-title">How It Works</div>
    <div class="steps-container">
        <div class="step-card fade-in">
            <i class="fas fa-search"></i>
            <h3>Search or Report</h3>
            <p>Search for lost items using keywords, or report found items to help others find their belongings.</p>
        </div>
        <div class="step-card fade-in">
            <i class="fas fa-clipboard-check"></i>
            <h3>Verify & Match</h3>
            <p>Our system verifies reports and matches lost items with found items based on descriptions.</p>
        </div>
        <div class="step-card fade-in">
            <i class="fas fa-hand-holding"></i>
            <h3>Claim Securely</h3>
            <p>Claim your items through a secure process with proper verification and documentation.</p>
        </div>
    </div>
</div>

<!-- ANNOUNCEMENTS -->
<div class="announcements-wrapper fade-in">
    <div class="section-title">Announcements</div>
    
    <?php if (!empty($announcements)): ?>
        <?php foreach($announcements as $announcement): ?>
        <div class="announcement fade-in">
            <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
            <div class="announcement-date">
                <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($announcement['date']); ?>
            </div>
            <div class="announcement-text"><?php echo htmlspecialchars($announcement['content']); ?></div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="announcement fade-in">
            <div class="announcement-title">Welcome to LoFIMS</div>
            <div class="announcement-date"><i class="fas fa-calendar-alt"></i> <?php echo date('Y-m-d'); ?></div>
            <div class="announcement-text">No announcements yet. Check back later for updates!</div>
        </div>
    <?php endif; ?>
    
    <?php if (file_exists('announcements.php')): ?>
    <a href="announcements.php" class="view-all">View All Announcements</a>
    <?php else: ?>
    <a href="#" class="view-all">View All Announcements</a>
    <?php endif; ?>
</div>

<!-- STATISTICS -->
<div class="stats-wrapper fade-in">
    <div class="section-title">System Statistics</div>
    <div class="stats-container">
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $lostCount; ?>">0</div>
            <div class="stat-label">Active Lost Items</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $foundCount; ?>">0</div>
            <div class="stat-label">Active Found Items</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $claimsCount; ?>">0</div>
            <div class="stat-label">Pending Claims</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $solvedCount; ?>">0</div>
            <div class="stat-label">Solved Cases</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $successRate; ?>">0</div>
            <div class="stat-label">Success Rate (%)</div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-number" data-target="<?php echo $usersCount; ?>">0</div>
            <div class="stat-label">Registered Users</div>
        </div>
    </div>
</div>

<!-- FOOTER -->
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
                <li><a href="search_results.php"><i class="fas fa-search-plus"></i> Advanced Search</a></li>
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
        
        <!-- CONTACT INFO WITH LOGO -->
        <div class="footer-section">
            <div class="footer-logo">
                <?php if (file_exists($footerLogoPath)): ?>
                    <img src="<?php echo $footerLogoPath; ?>" alt="LoFIMS Logo" onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="footer-logo-text">
                    <div class="logo-title">LoFIMS</div>
                    <div class="logo-subtitle">TUP Lopez Lost & Found</div>
                </div>
            </div>
            <ul class="footer-links">
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
                <li><i class="fas fa-envelope"></i> lofims@tuplopez.edu.ph</li>
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
// Animated counter
const counters = document.querySelectorAll('.stat-number');
const duration = 2000;

const animate = counter => {
    const target = parseInt(counter.getAttribute('data-target'));
    const increment = target / (duration / 16);
    let current = 0;
    
    const update = () => {
        current += increment;
        if (current < target) {
            counter.textContent = Math.ceil(current);
            requestAnimationFrame(update);
        } else {
            counter.textContent = target;
        }
    };
    update();
};

const observerCounter = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animate(entry.target);
            observerCounter.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

counters.forEach(counter => observerCounter.observe(counter));

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

// Hamburger menu toggle
const hamburger = document.getElementById('hamburger');
const navMenu = document.getElementById('navMenu');

if (hamburger && navMenu) {
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
    });
}

// Close mobile menu when clicking outside
document.addEventListener('click', (event) => {
    if (window.innerWidth <= 768) {
        const isClickInsideMenu = navMenu.contains(event.target);
        const isClickOnHamburger = hamburger.contains(event.target);
        
        if (!isClickInsideMenu && !isClickOnHamburger && navMenu.classList.contains('active')) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    }
});

// Handle logo loading errors
document.addEventListener('DOMContentLoaded', function() {
    const logoImages = document.querySelectorAll('img[alt="LoFIMS Logo"]');
    logoImages.forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            // Show fallback if exists
            const fallback = this.nextElementSibling;
            if (fallback && fallback.classList.contains('logo-fallback')) {
                fallback.style.display = 'flex';
            }
        });
    });
});

// Auto-refresh stats every 30 seconds
setInterval(() => {
    // You could implement AJAX here to refresh statistics without page reload
    console.log('Stats refresh triggered - Add AJAX call here');
}, 30000);

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        // Close mobile menu on resize to desktop
        if (window.innerWidth > 768 && navMenu.classList.contains('active')) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    }, 250);
});

</script>
</body>
</html>