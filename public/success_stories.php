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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Success Stories - Coming Soon | LoFIMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
    font-family: 'Poppins', 'Arial', sans-serif; 
}
body { 
    background: #f0f4ff; 
    color: #333; 
    min-height: 100vh;
    overflow-x: hidden;
}

/* HEADER STYLES - CONSISTENT WITH ABOUT PAGE */
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

/* LOGO FALLBACK */
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

/* UNDER CONSTRUCTION HERO SECTION */
.construction-hero {
    min-height: 85vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(-45deg, #e0f0ff, #f9faff, #d0e8ff, #cce0ff);
    background-size: 400% 400%;
    animation: bgGradient 15s ease infinite;
    padding: 40px 20px;
    position: relative;
    overflow: hidden;
}
@keyframes bgGradient { 
    0% { background-position:0% 50%; } 
    50% { background-position:100% 50%; } 
    100% { background-position:0% 50%; } 
}

.construction-content {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

/* CONSTRUCTION ICON */
.construction-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    animation: pulse 2s infinite;
    box-shadow: 0 10px 30px rgba(30, 144, 255, 0.3);
}
.construction-icon i {
    font-size: 50px;
    color: white;
}
@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 10px 30px rgba(30, 144, 255, 0.3); }
    50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(30, 144, 255, 0.4); }
    100% { transform: scale(1); box-shadow: 0 10px 30px rgba(30, 144, 255, 0.3); }
}

/* TYPOGRAPHY */
.construction-title {
    font-size: 60px;
    font-weight: 900;
    color: #0a3d62;
    margin-bottom: 20px;
    background: linear-gradient(45deg, #0a3d62, #1e90ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.construction-subtitle {
    font-size: 22px;
    color: #555;
    margin-bottom: 30px;
    line-height: 1.6;
}

/* STATUS BADGE */
.status-badge {
    display: inline-block;
    background: linear-gradient(45deg, #ff9800, #ff5722);
    color: white;
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 18px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(255, 87, 34, 0.3);
    animation: badgePulse 3s infinite;
}
@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* INFO CARDS */
.info-section {
    max-width: 1200px;
    margin: 60px auto 40px;
    padding: 0 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.info-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    padding: 35px 30px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid rgba(30, 144, 255, 0.2);
}

.info-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.info-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-icon i {
    font-size: 32px;
    color: white;
}

.info-card h3 {
    font-size: 22px;
    color: #0a3d62;
    margin-bottom: 15px;
}

.info-card p {
    color: #555;
    font-size: 16px;
    line-height: 1.6;
}

/* COUNTDOWN TIMER */
.countdown-container {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(8px);
    padding: 30px;
    border-radius: 20px;
    margin: 40px auto;
    max-width: 600px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 2px dashed #1e90ff;
}

.countdown-title {
    font-size: 20px;
    color: #0a3d62;
    margin-bottom: 20px;
    text-align: center;
}

.countdown {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.countdown-item {
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    padding: 15px;
    border-radius: 15px;
    min-width: 80px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(30, 144, 255, 0.3);
}

.countdown-value {
    font-size: 32px;
    font-weight: 700;
    display: block;
    margin-bottom: 5px;
}

.countdown-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* PROGRESS BAR */
.progress-section {
    max-width: 600px;
    margin: 40px auto;
}

.progress-title {
    font-size: 18px;
    color: #0a3d62;
    margin-bottom: 15px;
    text-align: center;
}

.progress-bar {
    height: 12px;
    background: rgba(30, 144, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    border-radius: 10px;
    width: 65%; /* Change this percentage to update progress */
    position: relative;
    animation: progressAnimation 2s ease-in-out;
}

@keyframes progressAnimation {
    from { width: 0; }
    to { width: 65%; }
}

.progress-text {
    display: flex;
    justify-content: space-between;
    color: #555;
    font-size: 14px;
}

/* CALL TO ACTION */
.cta-section {
    text-align: center;
    margin: 50px 0 30px;
}

.cta-button {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 16px 40px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    font-size: 18px;
    font-weight: 600;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 10px 25px rgba(30, 144, 255, 0.3);
}

.cta-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(30, 144, 255, 0.4);
    background: linear-gradient(45deg, #0d7bd4, #3a96e9);
}

/* BACKGROUND ELEMENTS */
.bg-element {
    position: absolute;
    background: rgba(30, 144, 255, 0.05);
    border-radius: 50%;
    z-index: 1;
}

.bg-1 {
    width: 300px;
    height: 300px;
    top: 10%;
    left: 5%;
    animation: float 20s infinite linear;
}

.bg-2 {
    width: 200px;
    height: 200px;
    bottom: 20%;
    right: 10%;
    animation: float 15s infinite linear reverse;
}

.bg-3 {
    width: 150px;
    height: 150px;
    top: 50%;
    left: 15%;
    animation: float 25s infinite linear;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    25% { transform: translate(20px, 20px) rotate(90deg); }
    50% { transform: translate(0, 40px) rotate(180deg); }
    75% { transform: translate(-20px, 20px) rotate(270deg); }
}

/* FOOTER STYLES - SIMPLIFIED */
footer {
    width:100%;
    padding:40px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
    backdrop-filter:blur(8px);
    border-top:2px solid #b0c4de;
    border-radius:20px 20px 0 0;
    margin-top:40px;
    color:#0a3d62;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.footer-section h3 {
    font-size: 18px;
    margin-bottom: 15px;
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
    transform: translateX(5px);
}

.footer-links i {
    width: 18px;
    text-align: center;
}

.copyright {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 14px;
}

/* RESPONSIVE DESIGN */
@media (max-width: 1024px) {
    header {
        padding: 12px 30px;
    }
    
    nav ul {
        gap: 15px;
    }
    
    .construction-title {
        font-size: 50px;
    }
}

@media (max-width: 768px) {
    header {
        padding: 10px 20px;
        flex-direction: column;
        gap: 15px;
    }
    
    nav ul {
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .construction-title {
        font-size: 40px;
    }
    
    .construction-subtitle {
        font-size: 18px;
    }
    
    .construction-icon {
        width: 100px;
        height: 100px;
    }
    
    .construction-icon i {
        font-size: 40px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .countdown {
        flex-wrap: wrap;
    }
    
    .countdown-item {
        min-width: 70px;
        padding: 12px;
    }
    
    .countdown-value {
        font-size: 28px;
    }
    
    .logo-text .logo-subtitle {
        display: none;
    }
}

@media (max-width: 480px) {
    .construction-title {
        font-size: 32px;
    }
    
    .construction-subtitle {
        font-size: 16px;
    }
    
    .status-badge {
        font-size: 16px;
        padding: 8px 20px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- HEADER - CONSISTENT WITH OTHER PAGES -->
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

<!-- UNDER CONSTRUCTION HERO SECTION -->
<section class="construction-hero">
    <!-- BACKGROUND ANIMATED ELEMENTS -->
    <div class="bg-element bg-1"></div>
    <div class="bg-element bg-2"></div>
    <div class="bg-element bg-3"></div>
    
    <div class="construction-content">
        <div class="construction-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <div class="status-badge">
            <i class="fas fa-hammer"></i> Under Development
        </div>
        
        <h1 class="construction-title">Success Stories</h1>
        <p class="construction-subtitle">
            We're working hard to collect and share inspiring stories from our community.<br>
            This page will showcase how LoFIMS has helped reunite people with their lost belongings.
        </p>
        
        <!-- COUNTDOWN TIMER -->
        <div class="countdown-container">
            <h3 class="countdown-title">Estimated Launch Time</h3>
            <div class="countdown">
                <div class="countdown-item">
                    <span class="countdown-value" id="days">30</span>
                    <span class="countdown-label">Days</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-value" id="hours">12</span>
                    <span class="countdown-label">Hours</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-value" id="minutes">45</span>
                    <span class="countdown-label">Minutes</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-value" id="seconds">00</span>
                    <span class="countdown-label">Seconds</span>
                </div>
            </div>
        </div>
        
        <!-- PROGRESS BAR -->
        <div class="progress-section">
            <h3 class="progress-title">Development Progress</h3>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span>Planning</span>
                <span>65% Complete</span>
                <span>Launch</span>
            </div>
        </div>
        
        <!-- CTA BUTTON -->
        <div class="cta-section">
            <button class="cta-button" onclick="window.location.href='contact.php'">
                <i class="fas fa-envelope"></i>
                Share Your Success Story
            </button>
        </div>
    </div>
</section>

<!-- INFORMATION SECTION -->
<section class="info-section">
    <h2 style="text-align: center; font-size: 32px; color: #0a3d62; margin-bottom: 10px;">
        What to Expect
    </h2>
    <p style="text-align: center; color: #555; margin-bottom: 40px; max-width: 800px; margin-left: auto; margin-right: auto;">
        Once completed, this page will feature real stories from our users who successfully recovered their lost items through LoFIMS.
    </p>
    
    <div class="info-grid">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h3>Inspiring Stories</h3>
            <p>Read heartwarming stories of reunited owners with their valuable possessions, from smartphones to sentimental items.</p>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3>Recovery Statistics</h3>
            <p>View success rates, average recovery times, and other metrics showing how effective LoFIMS has been for our community.</p>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3>Community Impact</h3>
            <p>Learn how LoFIMS has strengthened our campus community and promoted honesty and cooperation among students and staff.</p>
        </div>
    </div>
</section>

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
            </ul>
        </div>
        
        <!-- CONTACT INFO -->
        <div class="footer-section">
            <h3>Contact</h3>
            <ul class="footer-links">
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
                <li><i class="fas fa-envelope"></i> lofims.system@gmail.com</li>
                <li><i class="fas fa-phone"></i> (042) 555-1234</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-user-graduate"></i> For TUP Students & Staff</li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
        <br>
        <small>Success Stories Page • Coming Soon</small>
    </div>
</footer>

<script>
// Countdown Timer
function updateCountdown() {
    // Set launch date (30 days from now)
    const launchDate = new Date();
    launchDate.setDate(launchDate.getDate() + 30);
    
    const now = new Date().getTime();
    const distance = launchDate - now;
    
    if (distance < 0) {
        // If countdown is over
        document.getElementById('days').innerText = '00';
        document.getElementById('hours').innerText = '00';
        document.getElementById('minutes').innerText = '00';
        document.getElementById('seconds').innerText = '00';
        return;
    }
    
    // Calculate days, hours, minutes, seconds
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    // Update display
    document.getElementById('days').innerText = days.toString().padStart(2, '0');
    document.getElementById('hours').innerText = hours.toString().padStart(2, '0');
    document.getElementById('minutes').innerText = minutes.toString().padStart(2, '0');
    document.getElementById('seconds').innerText = seconds.toString().padStart(2, '0');
}

// Initialize countdown and update every second
updateCountdown();
setInterval(updateCountdown, 1000);

// Progress bar animation
document.addEventListener('DOMContentLoaded', function() {
    const progressFill = document.querySelector('.progress-fill');
    let progress = 0;
    const targetProgress = 65; // 65% complete
    
    // Animate progress bar
    const progressInterval = setInterval(() => {
        if (progress >= targetProgress) {
            clearInterval(progressInterval);
            return;
        }
        progress++;
        progressFill.style.width = progress + '%';
    }, 30);
    
    // Smooth scroll for navigation
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
    
    // Handle logo loading errors
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

// Background animation for construction elements
const bgElements = document.querySelectorAll('.bg-element');
bgElements.forEach(el => {
    el.style.transform = `translate(${Math.random() * 20}px, ${Math.random() * 20}px) rotate(${Math.random() * 360}deg)`;
});
</script>
</body>
</html>