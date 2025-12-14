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
<title>Terms of Service - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

/* LOGO STYLES */
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

/* HEADER */
header {
    width:100%;
    padding:20px 60px;
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
nav ul {
    list-style:none;
    display:flex;
    gap:25px;
    align-items:center;
}
nav ul li a {
    text-decoration:none;
    font-size:16px;
    color:#0a3d62;
    font-weight:500;
    position: relative;
    padding: 5px 0;
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

/* USER INFO IN NAV */
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    font-size: 14px;
    color: #0a3d62;
    white-space: nowrap;
    font-weight: 500;
}
.user-info i {
    color: #1e90ff;
}

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

/* IMPORTANT NOTICE */
.important-notice {
    max-width: 900px;
    margin: 0 auto 40px;
    padding: 25px;
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 2px solid #ffc107;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2);
}

.important-notice i {
    font-size: 32px;
    color: #e67e22;
    margin-bottom: 15px;
}

.important-notice h3 {
    color: #d35400;
    margin-bottom: 10px;
    font-size: 22px;
}

.important-notice p {
    color: #7d6608;
    font-weight: 500;
    line-height: 1.6;
}

/* TERMS CONTENT */
.terms-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* TABLE OF CONTENTS */
.toc-container {
    background: rgba(255,255,255,0.95);
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-left: 5px solid #1e90ff;
}

.toc-container h3 {
    color: #0a3d62;
    font-size: 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.toc-list {
    column-count: 2;
    column-gap: 40px;
}

.toc-list ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.toc-list li {
    margin-bottom: 12px;
    break-inside: avoid;
}

.toc-list a {
    color: #555;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 15px;
    border-radius: 8px;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.toc-list a:hover {
    background: #e3f2fd;
    color: #1e90ff;
    border-left-color: #1e90ff;
    transform: translateX(5px);
}

.toc-list a.active {
    background: #e3f2fd;
    color: #1e90ff;
    border-left-color: #1e90ff;
    font-weight: 600;
}

.toc-list a i {
    width: 20px;
    text-align: center;
    color: #1e90ff;
}

/* TERMS SECTIONS */
.terms-section {
    background: rgba(255,255,255,0.95);
    padding: 35px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.terms-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.12);
    border-color: #e3f2fd;
}

.terms-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: #1e90ff;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.section-number {
    width: 40px;
    height: 40px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
}

.terms-section h2 {
    color: #0a3d62;
    font-size: 24px;
    margin: 0;
    flex: 1;
}

.terms-section p {
    color: #555;
    line-height: 1.8;
    margin-bottom: 15px;
    font-size: 15px;
}

.terms-section ul {
    margin-left: 25px;
    margin-bottom: 20px;
}

.terms-section li {
    color: #555;
    line-height: 1.7;
    margin-bottom: 10px;
    padding-left: 5px;
    position: relative;
}

.terms-section li::before {
    content: '•';
    color: #1e90ff;
    font-weight: bold;
    position: absolute;
    left: -15px;
}

/* HIGHLIGHTED SECTIONS */
.terms-section.warning {
    background: linear-gradient(135deg, #fff3e0, #ffecb3);
    border-color: #ffb74d;
}

.terms-section.warning::before {
    background: #ff9800;
}

.terms-section.important {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-color: #64b5f6;
}

.terms-section.important::before {
    background: #2196f3;
}

/* QUICK ACTION BUTTONS */
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

/* LAST UPDATED INFO */
.last-updated {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    margin: 40px 0;
    color: #666;
    font-size: 14px;
    border: 2px dashed #ddd;
}

.last-updated strong {
    color: #0a3d62;
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
        padding: 15px 30px;
    }
    
    nav ul {
        gap: 20px;
    }
    
    .logo-text .logo-subtitle {
        display: none;
    }
}

@media (max-width: 768px) {
    header {
        padding: 15px 20px;
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
    
    .toc-list {
        column-count: 1;
    }
    
    .terms-section {
        padding: 25px;
    }
    
    .section-header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .terms-section h2 {
        font-size: 20px;
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
    
    .user-info span {
        display: none;
    }
    
    nav ul li a span {
        display: none;
    }
    
    nav ul li a {
        font-size: 16px;
        padding: 8px;
    }
}

@media (max-width: 480px) {
    .terms-section {
        padding: 20px;
    }
    
    .section-number {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
    
    .terms-section h2 {
        font-size: 18px;
    }
    
    .logo-text .logo-title {
        font-size: 20px;
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
    }
    
    .logo-fallback {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
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
            <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms</a></li>
            
            <?php if ($isLoggedIn): ?>
                <li class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($userName); ?></span>
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
        <h1 class="page-title">Terms of Service</h1>
        <p class="page-subtitle">Please read these terms carefully before using LoFIMS</p>
    </div>
</div>

<!-- IMPORTANT NOTICE -->
<div class="important-notice">
    <i class="fas fa-exclamation-triangle"></i>
    <h3>Important Legal Notice</h3>
    <p>By accessing and using LoFIMS, you agree to be bound by these Terms of Service. These terms affect your legal rights and responsibilities. If you do not agree with any part of these terms, please discontinue use of the system immediately.</p>
</div>

<!-- TERMS CONTENT -->
<div class="terms-container">
    <!-- TABLE OF CONTENTS -->
    <div class="toc-container">
        <h3><i class="fas fa-list"></i> Table of Contents</h3>
        <div class="toc-list">
            <ul>
                <li><a href="#section1"><i class="fas fa-check-circle"></i> Acceptance of Terms</a></li>
                <li><a href="#section2"><i class="fas fa-user-check"></i> User Eligibility</a></li>
                <li><a href="#section3"><i class="fas fa-tasks"></i> User Responsibilities</a></li>
                <li><a href="#section4"><i class="fas fa-shield-alt"></i> Item Handling & Liability</a></li>
                <li><a href="#section5"><i class="fas fa-lock"></i> Privacy & Data Protection</a></li>
                <li><a href="#section6"><i class="fas fa-ban"></i> System Usage Rules</a></li>
                <li><a href="#section7"><i class="fas fa-times-circle"></i> Termination of Access</a></li>
                <li><a href="#section8"><i class="fas fa-sync-alt"></i> Changes to Terms</a></li>
                <li><a href="#section9"><i class="fas fa-envelope"></i> Contact Information</a></li>
                <li><a href="#section10"><i class="fas fa-balance-scale"></i> Governing Law</a></li>
            </ul>
        </div>
    </div>

    <!-- SECTION 1 -->
    <div class="terms-section" id="section1">
        <div class="section-header">
            <div class="section-number">1</div>
            <h2>Acceptance of Terms</h2>
        </div>
        <p>By accessing and using the Lost and Found Information Management System (LoFIMS), you accept and agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our system.</p>
        <p>Your continued use of LoFIMS following any changes to these terms constitutes your acceptance of those changes.</p>
    </div>

    <!-- SECTION 2 -->
    <div class="terms-section" id="section2">
        <div class="section-header">
            <div class="section-number">2</div>
            <h2>User Eligibility</h2>
        </div>
        <p>LoFIMS is available only to:</p>
        <ul>
            <li>Currently enrolled students of Technological University of the Philippines - Lopez Campus with valid student IDs</li>
            <li>Current faculty and staff members of TUP Lopez with valid employment credentials</li>
            <li>Authorized administrative personnel involved in campus lost and found management</li>
        </ul>
        <p>You must provide accurate information during registration and maintain the confidentiality of your account credentials. You are responsible for all activities that occur under your account.</p>
    </div>

    <!-- SECTION 3 -->
    <div class="terms-section" id="section3">
        <div class="section-header">
            <div class="section-number">3</div>
            <h2>User Responsibilities</h2>
        </div>
        <p>As a user of LoFIMS, you agree to:</p>
        <ul>
            <li>Provide accurate and truthful information when reporting lost or found items</li>
            <li>Submit claims only for items that rightfully belong to you with verifiable proof</li>
            <li>Respect the privacy and rights of other users at all times</li>
            <li>Not use the system for fraudulent, malicious, or commercial purposes</li>
            <li>Report any suspicious activity or system vulnerabilities to administrators</li>
            <li>Maintain the security of your account credentials and immediately report unauthorized access</li>
        </ul>
    </div>

    <!-- SECTION 4 -->
    <div class="terms-section warning" id="section4">
        <div class="section-header">
            <div class="section-number">4</div>
            <h2>Item Handling and Liability</h2>
        </div>
        <p><strong>Important:</strong> LoFIMS serves as an intermediary platform only. We are not responsible for:</p>
        <ul>
            <li>The condition, authenticity, or quality of lost or found items</li>
            <li>Theft, damage, loss, or deterioration of items while in possession of finders or during exchanges</li>
            <li>Disputes between users regarding item ownership, value, or condition</li>
            <li>Fraudulent claims, misrepresentation, or false item descriptions</li>
            <li>Personal injuries or property damage during item exchanges</li>
        </ul>
        <p>Users are strongly encouraged to:</p>
        <ul>
            <li>Meet in safe, public, well-lit locations on campus for item exchanges</li>
            <li>Bring a friend or campus security for high-value item exchanges</li>
            <li>Verify item condition and ownership before accepting items</li>
            <li>Report any suspicious behavior during exchanges to campus security</li>
        </ul>
    </div>

    <!-- SECTION 5 -->
    <div class="terms-section important" id="section5">
        <div class="section-header">
            <div class="section-number">5</div>
            <h2>Privacy and Data Protection</h2>
        </div>
        <p>We collect and process personal data in accordance with our <a href="privacy.php" style="color: #1e90ff; text-decoration: none; font-weight: 500;">Privacy Policy</a>. By using LoFIMS, you consent to:</p>
        <ul>
            <li>Collection of necessary personal information (name, contact details, ID) for system operation</li>
            <li>Limited sharing of contact information with other users for legitimate item recovery purposes only</li>
            <li>Data retention for system records, statistical analysis, and legal compliance (typically 2 years)</li>
            <li>Use of cookies and tracking technologies for improved user experience and system security</li>
            <li>Processing of uploaded photos and item descriptions for matching and verification purposes</li>
        </ul>
        <p>You have the right to request deletion of your data in accordance with applicable privacy laws and institutional policies.</p>
    </div>

    <!-- SECTION 6 -->
    <div class="terms-section warning" id="section6">
        <div class="section-header">
            <div class="section-number">6</div>
            <h2>System Usage Rules</h2>
        </div>
        <p><strong>Prohibited activities include:</strong></p>
        <ul>
            <li>Creating multiple accounts or impersonating other individuals</li>
            <li>Submitting false reports, fraudulent claims, or misleading information</li>
            <li>Harassing, threatening, or intimidating other users</li>
            <li>Attempting to hack, compromise, or disrupt system security or functionality</li>
            <li>Using automated scripts, bots, or unauthorized third-party tools</li>
            <li>Commercial use, advertising, or solicitation through the system</li>
            <li>Uploading malicious files, viruses, or inappropriate content</li>
            <li>Circumventing system security measures or access controls</li>
        </ul>
        <p>Violations may result in immediate account suspension and potential disciplinary action by the university.</p>
    </div>

    <!-- SECTION 7 -->
    <div class="terms-section" id="section7">
        <div class="section-header">
            <div class="section-number">7</div>
            <h2>Termination of Access</h2>
        </div>
        <p>We reserve the right to suspend or terminate your access to LoFIMS at our sole discretion if you:</p>
        <ul>
            <li>Violate these Terms of Service or institutional policies</li>
            <li>Engage in fraudulent, illegal, or unethical activity</li>
            <li>Provide false or misleading information</li>
            <li>Misuse the system or compromise its security</li>
            <li>Fail to maintain eligibility requirements</li>
        </ul>
        <p>Terminated users may appeal their suspension through official university channels. Appeals must be submitted in writing within 14 days of suspension notification.</p>
    </div>

    <!-- SECTION 8 -->
    <div class="terms-section" id="section8">
        <div class="section-header">
            <div class="section-number">8</div>
            <h2>Changes to Terms</h2>
        </div>
        <p>We may update these Terms of Service from time to time to reflect:</p>
        <ul>
            <li>Changes in system functionality or features</li>
            <li>Legal or regulatory requirements</li>
            <li>Institutional policy updates</li>
            <li>Security improvements</li>
        </ul>
        <p>Continued use of LoFIMS after changes constitutes acceptance of the new terms. Users will be notified of significant changes through system announcements, email notifications, or campus communications. It is your responsibility to review these terms periodically.</p>
    </div>

    <!-- SECTION 9 -->
    <div class="terms-section" id="section9">
        <div class="section-header">
            <div class="section-number">9</div>
            <h2>Contact Information</h2>
        </div>
        <p>For questions, concerns, or legal notices regarding these Terms of Service, please contact:</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;">
            <p style="margin-bottom: 10px;"><strong>LoFIMS Administration Office</strong></p>
            <p style="margin-bottom: 5px;"><i class="fas fa-university"></i> Technological University of the Philippines - Lopez Campus</p>
            <p style="margin-bottom: 5px;"><i class="fas fa-map-marker-alt"></i> Lopez, Quezon 4316</p>
            <p style="margin-bottom: 5px;"><i class="fas fa-envelope"></i> admin@lofims.edu.ph</p>
            <p style="margin-bottom: 5px;"><i class="fas fa-phone"></i> 0981 161 8489</p>
            <p><i class="fas fa-clock"></i> Office Hours: Monday-Friday, 8:00 AM - 5:00 PM</p>
        </div>
    </div>

    <!-- SECTION 10 -->
    <div class="terms-section" id="section10">
        <div class="section-header">
            <div class="section-number">10</div>
            <h2>Governing Law and Jurisdiction</h2>
        </div>
        <p>These Terms of Service shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes arising from or relating to these terms or your use of LoFIMS shall be subject to the exclusive jurisdiction of the courts located within Lopez, Quezon.</p>
        <p>These terms constitute the entire agreement between you and LoFIMS regarding the system and supersede all prior agreements and understandings.</p>
    </div>

    <!-- LAST UPDATED -->
    <div class="last-updated">
        <p><strong>Last updated:</strong> <?php echo date('F d, Y'); ?> at <?php echo date('h:i A'); ?></p>
        <p>Version: 2.0 | Effective Date: March 1, 2025</p>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="privacy.php" class="action-btn">
            <i class="fas fa-shield-alt"></i> Privacy Policy
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
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a></li>
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
        
        <!-- LEGAL -->
        <div class="footer-section">
            <h3>Legal</h3>
            <ul class="footer-links">
                <li><a href="terms.php"><i class="fas fa-gavel"></i> Terms of Service</a></li>
                <li><a href="privacy.php"><i class="fas fa-lock"></i> Privacy Policy</a></li>
                <li><a href="cookie_policy.php"><i class="fas fa-cookie-bite"></i> Cookie Policy</a></li>
                <li><a href="disclaimer.php"><i class="fas fa-exclamation-triangle"></i> Disclaimer</a></li>
                <li><i class="fas fa-database"></i> Status: 
                    <?php echo $dbError ? '<span style="color: #e74c3c;">Offline</span>' : '<span style="color: #2ecc71;">Online</span>'; ?>
                </li>
                <li><i class="fas fa-users"></i> <?php echo $usersCount; ?> Users</li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
        <br>
        <small>System Version 2.0 • Last updated: <?php echo date('F d, Y'); ?></small>
        <br>
        <small style="color: #1e90ff; margin-top: 5px; display: inline-block;">
            <i class="fas fa-balance-scale"></i> By using this system, you agree to our Terms of Service and Privacy Policy
        </small>
    </div>
</footer>

<script>
// Smooth scroll for table of contents links
document.querySelectorAll('.toc-list a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});

// Highlight current section on scroll
const sections = document.querySelectorAll('.terms-section');
const navLinks = document.querySelectorAll('.toc-list a');

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
        }
    });
});

// Print Terms functionality
function printTerms() {
    window.print();
}

// Handle logo image errors
document.addEventListener('DOMContentLoaded', function() {
    const logoImg = document.querySelector('.logo-container img');
    if (logoImg) {
        logoImg.addEventListener('error', function() {
            this.style.display = 'none';
            const fallback = document.getElementById('logo-fallback');
            if (fallback) {
                fallback.style.display = 'flex';
            }
        });
    }
});

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
</script>

</body>
</html>