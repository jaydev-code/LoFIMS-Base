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
<title>How It Works - LoFIMS TUP Lopez</title>
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

/* PROCESS SECTION - ENHANCED */
.process-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    position: relative;
}

.process-step {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 25px;
    margin-bottom: 50px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 40px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.process-step:hover {
    transform: translateY(-8px);
    box-shadow: 0 18px 40px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.step-number {
    width: 90px;
    height: 90px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    font-weight: bold;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}

.step-number i {
    font-size: 36px;
    position: absolute;
}

.step-number .number {
    font-size: 24px;
    font-weight: bold;
}

.step-content {
    flex: 1;
    text-align: left;
}

.step-title {
    font-size: 28px;
    color: #0a3d62;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.step-description {
    font-size: 16px;
    color: #555;
    line-height: 1.8;
    margin-bottom: 20px;
}

/* FLOW ARROWS */
.flow-arrow {
    text-align: center;
    margin: -30px 0;
    z-index: 2;
    position: relative;
}

.flow-arrow i {
    font-size: 32px;
    color: #1e90ff;
    background: white;
    padding: 10px;
    border-radius: 50%;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* STEP ACTIONS */
.step-action {
    margin-top: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 20px;
    background: #1e90ff;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    background: #0d7bd4;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
}

.action-btn.secondary {
    background: transparent;
    color: #0a3d62;
    border: 2px solid #0a3d62;
}

.action-btn.secondary:hover {
    background: rgba(10,61,98,0.1);
}

/* FAQ SECTION */
.faq-section {
    max-width: 1200px;
    margin: 80px auto 0;
    padding: 40px 20px;
    background: rgba(255,255,255,0.95);
    border-radius: 25px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}

.faq-title {
    font-size: 32px;
    color: #0a3d62;
    text-align: center;
    margin-bottom: 40px;
}

.faq-item {
    background: white;
    border-radius: 15px;
    padding: 25px 30px;
    margin-bottom: 20px;
    border-left: 4px solid #1e90ff;
    transition: all 0.3s;
}

.faq-item:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.faq-item h3 {
    font-size: 18px;
    color: #0a3d62;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.faq-item p {
    color: #555;
    line-height: 1.6;
}

/* CTA SECTION */
.cta-section {
    text-align: center;
    margin: 60px auto;
    padding: 40px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 25px;
    color: white;
    max-width: 800px;
}

.cta-section h2 {
    font-size: 32px;
    margin-bottom: 20px;
}

.cta-section p {
    font-size: 18px;
    margin-bottom: 30px;
    opacity: 0.9;
}

.cta-button {
    padding: 15px 40px;
    background: white;
    color: #1e90ff;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.cta-button:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* FOOTER - Enhanced */
footer {
    width:100%;
    padding:60px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
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

.copyright {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 14px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    header {
        padding: 15px 20px;
    }
    
    nav ul {
        gap: 15px;
    }
    
    .page-title {
        font-size: 36px;
    }
    
    .page-subtitle {
        font-size: 18px;
    }
    
    .process-step {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .step-number {
        width: 70px;
        height: 70px;
    }
    
    .step-content {
        text-align: center;
    }
    
    .step-title {
        justify-content: center;
    }
    
    .flow-arrow {
        margin: -20px 0;
    }
    
    .flow-arrow i {
        font-size: 24px;
        padding: 8px;
    }
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
    
    .process-step {
        flex-direction: column;
        text-align: center;
        gap: 20px;
        padding: 30px 20px;
    }
    
    .step-number {
        width: 70px;
        height: 70px;
    }
    
    .step-content {
        text-align: center;
    }
    
    .step-title {
        justify-content: center;
        font-size: 24px;
    }
    
    .step-description {
        font-size: 15px;
    }
    
    .flow-arrow {
        margin: -20px 0;
    }
    
    .flow-arrow i {
        font-size: 24px;
        padding: 8px;
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
            <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
            
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
        <h1 class="page-title">How LoFIMS Works</h1>
        <p class="page-subtitle">A step-by-step guide to using our Lost and Found Information Management System</p>
    </div>
</div>

<!-- PROCESS STEPS -->
<div class="process-container">
    <!-- STEP 1 -->
    <div class="process-step">
        <div class="step-number">
            <i class="fas fa-exclamation-circle"></i>
            <span class="number">1</span>
        </div>
        <div class="step-content">
            <h2 class="step-title"><i class="fas fa-map-marker-alt"></i> Report Lost Item</h2>
            <p class="step-description">
                If you've lost an item on campus, log in to your account and navigate to the "Lost Items" section. 
                Fill out the form with details about your item including name, category, description, location where it was lost, 
                date, and upload a photo if available. The more details you provide, the easier it will be to match with found items.
            </p>
            <div class="step-action">
                <a href="lost_items.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i> Report Lost Item
                </a>
                <a href="guide.php#reporting" class="action-btn secondary">
                    <i class="fas fa-book"></i> Read Guide
                </a>
            </div>
        </div>
    </div>

    <!-- FLOW ARROW -->
    <div class="flow-arrow">
        <i class="fas fa-arrow-down"></i>
    </div>

    <!-- STEP 2 -->
    <div class="process-step">
        <div class="step-number">
            <i class="fas fa-search"></i>
            <span class="number">2</span>
        </div>
        <div class="step-content">
            <h2 class="step-title"><i class="fas fa-search"></i> Search for Items</h2>
            <p class="step-description">
                Use the search functionality to look for your lost item. You can filter by category, date range, location, 
                or use keywords in the description. The system will show you all matching items. You can also browse through 
                recently reported lost and found items in the respective sections.
            </p>
            <div class="step-action">
                <a href="lost_items.php" class="action-btn">
                    <i class="fas fa-search"></i> Search Lost Items
                </a>
                <a href="found_items.php" class="action-btn secondary">
                    <i class="fas fa-box"></i> Browse Found Items
                </a>
            </div>
        </div>
    </div>

    <!-- FLOW ARROW -->
    <div class="flow-arrow">
        <i class="fas fa-arrow-down"></i>
    </div>

    <!-- STEP 3 -->
    <div class="process-step">
        <div class="step-number">
            <i class="fas fa-hand-holding-heart"></i>
            <span class="number">3</span>
        </div>
        <div class="step-content">
            <h2 class="step-title"><i class="fas fa-hand-holding"></i> Claim Your Item</h2>
            <p class="step-description">
                If you find your lost item in the system, click the "Claim" button. You'll need to provide proof of ownership 
                (description of unique features, receipt if available) and submit a claim request. The system administrator 
                will review your claim, verify your ownership, and contact you for item retrieval.
            </p>
            <div class="step-action">
                <a href="claim_item.php" class="action-btn">
                    <i class="fas fa-hand-holding"></i> Start a Claim
                </a>
                <a href="guide.php#claiming" class="action-btn secondary">
                    <i class="fas fa-question-circle"></i> Claim Process
                </a>
            </div>
        </div>
    </div>

    <!-- FLOW ARROW -->
    <div class="flow-arrow">
        <i class="fas fa-arrow-down"></i>
    </div>

    <!-- STEP 4 -->
    <div class="process-step">
        <div class="step-number">
            <i class="fas fa-tasks"></i>
            <span class="number">4</span>
        </div>
        <div class="step-content">
            <h2 class="step-title"><i class="fas fa-chart-line"></i> Track Your Claims</h2>
            <p class="step-description">
                Once you've submitted a claim, you can track its status in your dashboard. The system shows real-time updates:
                Pending → Under Review → Approved/Rejected → Item Retrieved. You'll receive notifications when there are 
                updates to your claim, and can communicate with administrators through the system.
            </p>
            <div class="step-action">
                <?php if ($isLoggedIn): ?>
                <a href="user_panel/dashboard.php" class="action-btn">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <?php else: ?>
                <a href="login.php" class="action-btn">
                    <i class="fas fa-sign-in-alt"></i> Login to Track Claims
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FLOW ARROW -->
    <div class="flow-arrow">
        <i class="fas fa-arrow-down"></i>
    </div>

    <!-- STEP 5 -->
    <div class="process-step">
        <div class="step-number">
            <i class="fas fa-box"></i>
            <span class="number">5</span>
        </div>
        <div class="step-content">
            <h2 class="step-title"><i class="fas fa-hands-helping"></i> Report Found Item</h2>
            <p class="step-description">
                If you find an item on campus, help its owner by reporting it. Go to the "Found Items" section and submit 
                a report with details about where and when you found it, along with photos. You can choose to keep the item 
                safely or turn it in to the designated lost and found office.
            </p>
            <div class="step-action">
                <a href="found_items.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i> Report Found Item
                </a>
                <button class="action-btn secondary" onclick="alert('Thank you for helping! Found items should be reported within 24 hours for best results.')">
                    <i class="fas fa-info-circle"></i> Quick Tips
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CTA SECTION -->
<div class="cta-section">
    <h2>Ready to Get Started?</h2>
    <p>Join hundreds of students and staff who have successfully recovered their lost items through LoFIMS</p>
    <?php if ($isLoggedIn): ?>
    <button class="cta-button" onclick="window.location.href='user_panel/dashboard.php'">
        <i class="fas fa-tachometer-alt"></i> Go to Your Dashboard
    </button>
    <?php else: ?>
    <button class="cta-button" onclick="window.location.href='register.php'">
        <i class="fas fa-user-plus"></i> Create Your Account
    </button>
    <?php endif; ?>
</div>

<!-- FAQ SECTION -->
<div class="faq-section">
    <h2 class="faq-title">Frequently Asked Questions</h2>
    
    <div class="faq-item">
        <h3><i class="fas fa-question-circle"></i> How long are items kept in the system?</h3>
        <p>Lost items remain active for 60 days. Found items are kept for 90 days before being processed according to campus policy.</p>
    </div>
    
    <div class="faq-item">
        <h3><i class="fas fa-question-circle"></i> Is there a fee for using LoFIMS?</h3>
        <p>No, LoFIMS is completely free for all TUP Lopez students, faculty, and staff as a campus service.</p>
    </div>
    
    <div class="faq-item">
        <h3><i class="fas fa-question-circle"></i> What happens if my claim is rejected?</h3>
        <p>If your claim is rejected due to insufficient proof, you can appeal with additional evidence or contact the administrator for clarification.</p>
    </div>
    
    <div class="faq-item">
        <h3><i class="fas fa-question-circle"></i> Can I report items anonymously?</h3>
        <p>You need to be logged in to report items, but your personal information is only visible to administrators during the claiming process.</p>
    </div>
    
    <div class="faq-item">
        <h3><i class="fas fa-question-circle"></i> How long does the claim process take?</h3>
        <p>Most claims are processed within 2-3 business days. Complex cases may take up to a week.</p>
    </div>
</div>

<!-- ENHANCED FOOTER -->
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
                <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            </ul>
        </div>
        
        <!-- INFORMATION -->
        <div class="footer-section">
            <h3>Information</h3>
            <ul class="footer-links">
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a></li>
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a></li>
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
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
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
        <br>
        <small>System Version 2.0 • Last updated: <?php echo date('F d, Y'); ?></small>
        <br>
        <small style="color: #1e90ff; margin-top: 5px; display: inline-block;">
            <i class="fas fa-graduation-cap"></i> Designed for TUP Lopez Community
        </small>
    </div>
</footer>

<script>
// Add hover animations
document.querySelectorAll('.process-step').forEach(step => {
    step.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px)';
    });
    
    step.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
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

// FAQ toggle functionality
document.querySelectorAll('.faq-item h3').forEach(question => {
    question.addEventListener('click', function() {
        const answer = this.nextElementSibling;
        const icon = this.querySelector('i');
        
        if (answer.style.maxHeight) {
            answer.style.maxHeight = null;
            icon.className = 'fas fa-question-circle';
        } else {
            answer.style.maxHeight = answer.scrollHeight + 'px';
            icon.className = 'fas fa-chevron-down';
        }
    });
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