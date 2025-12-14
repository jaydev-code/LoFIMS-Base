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
<title>Privacy Policy - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

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

/* HAMBURGER MENU */
.hamburger {
    display: none;
    flex-direction: column;
    gap: 4px;
    cursor: pointer;
    padding: 8px;
    background: rgba(30,144,255,0.1);
    border-radius: 8px;
    border: none;
    margin-left: auto;
    margin-right: 15px;
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

/* NAVIGATION */
nav {
    display: flex;
    align-items: center;
    flex: 1;
    justify-content: flex-end;
}

nav ul {
    list-style:none;
    display:flex;
    gap:20px;
    align-items:center;
    flex-wrap: nowrap;
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

/* USER INFO */
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

/* LOGIN/LOGOUT BUTTON */
.login-btn {
    padding:10px 25px;
    background:linear-gradient(45deg,#1e90ff,#4facfe);
    color:white;
    font-size:14px;
    font-weight:bold;
    border-radius:12px;
    border:2px solid rgba(30,144,255,0.8);
    cursor:pointer;
    backdrop-filter:blur(8px);
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    transition:transform 0.3s, box-shadow 0.3s, background 0.3s;
    text-decoration: none;
    display: inline-block;
    white-space: nowrap;
}

.login-btn:hover {
    transform:translateY(-4px);
    box-shadow:0 12px 25px rgba(0,0,0,0.25);
    background:linear-gradient(45deg,#0d7bd4,#3a9cfc);
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

/* PRIVACY CONTENT */
.privacy-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

.privacy-section {
    background: rgba(255,255,255,0.9);
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid transparent;
}

.privacy-section:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.privacy-section h2 {
    color: #0a3d62;
    font-size: 24px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #1e90ff;
}

.privacy-section h3 {
    color: #1e90ff;
    font-size: 18px;
    margin: 20px 0 10px;
}

.privacy-section p {
    color: #555;
    line-height: 1.6;
    margin-bottom: 15px;
}

.privacy-section ul {
    margin-left: 20px;
    margin-bottom: 15px;
}

.privacy-section li {
    color: #555;
    line-height: 1.6;
    margin-bottom: 8px;
}

.privacy-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.privacy-table th {
    background: #1e90ff;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: bold;
}

.privacy-table td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.privacy-table tr:last-child td {
    border-bottom: none;
}

.privacy-table tr:hover {
    background: #e3f2fd;
}

.note-box {
    background: #e3f2fd;
    padding: 15px;
    border-left: 4px solid #1e90ff;
    margin: 15px 0;
    border-radius: 5px;
}

.warning-box {
    background: #ffebee;
    padding: 15px;
    border-left: 4px solid #f44336;
    margin: 15px 0;
    border-radius: 5px;
}

/* CONTACT CTA */
.contact-cta {
    text-align: center;
    padding: 40px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 20px;
    margin-top: 60px;
    position: relative;
    overflow: hidden;
}

.contact-cta::before {
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

.contact-cta i {
    font-size: 60px;
    color: #1e90ff;
    margin-bottom: 20px;
}

.contact-cta h3 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 15px;
}

.contact-cta p {
    color: #555;
    font-size: 16px;
    margin-bottom: 25px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.contact-btn {
    background: white;
    color: #1e90ff;
    border: 2px solid #1e90ff;
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.contact-btn:hover {
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

footer a { color:#0a3d62; text-decoration:none; font-weight:bold; transition:all 0.3s; }
footer a:hover { color:#1e90ff; }

/* RESPONSIVE DESIGN */
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
    
    .user-info span {
        display: none;
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
        display: inline !important;
    }
    
    .user-info {
        justify-content: center;
        padding: 12px;
    }
    
    .user-info span {
        display: inline !important;
    }
    
    .login-btn {
        width: 100%;
        text-align: center;
        margin-top: 5px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .page-title {
        font-size: 36px;
    }
    
    .page-subtitle {
        font-size: 18px;
    }
    
    .privacy-section {
        padding: 20px;
    }
    
    .privacy-section h2 {
        font-size: 20px;
    }
    
    .privacy-table {
        font-size: 14px;
    }
    
    .privacy-table th,
    .privacy-table td {
        padding: 8px;
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
    }
}

@media (max-width: 480px) {
    .privacy-section {
        padding: 15px;
    }
    
    .privacy-section h2 {
        font-size: 18px;
    }
    
    .privacy-section p, 
    .privacy-section li {
        font-size: 14px;
    }
    
    .contact-cta {
        padding: 25px;
    }
    
    .contact-cta h3 {
        font-size: 22px;
    }
}

</style>
</head>
<body>

<!-- HEADER WITH HAMBURGER MENU -->
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
    
    <!-- HAMBURGER MENU FOR MOBILE -->
    <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <nav id="navMenu">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> <span>Lost</span></a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> <span>Found</span></a></li>
            <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> <span>Claims</span></a></li>
            <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> <span>Privacy</span></a></li>
            
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
        <h1 class="page-title">Privacy Policy</h1>
        <p class="page-subtitle">How we collect, use, and protect your personal information</p>
    </div>
</div>

<!-- PRIVACY POLICY CONTENT -->
<div class="privacy-container">
    <!-- INTRODUCTION -->
    <div class="privacy-section">
        <h2>1. Introduction</h2>
        <p>Welcome to LoFIMS (Lost and Found Information Management System). This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our system. We are committed to protecting your privacy and ensuring the security of your personal data.</p>
        
        <p>By using LoFIMS, you consent to the data practices described in this policy. If you do not agree with the terms of this privacy policy, please do not access or use our system.</p>
    </div>

    <!-- INFORMATION COLLECTION -->
    <div class="privacy-section">
        <h2>2. Information We Collect</h2>
        
        <h3>2.1 Personal Information</h3>
        <p>When you register for and use LoFIMS, we may collect the following personal information:</p>
        <table class="privacy-table">
            <thead>
                <tr>
                    <th>Information Type</th>
                    <th>Purpose of Collection</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Full Name</td>
                    <td>User identification and item claim verification</td>
                </tr>
                <tr>
                    <td>TUP Email Address</td>
                    <td>Account creation, communication, and verification</td>
                </tr>
                <tr>
                    <td>Student/Employee ID</td>
                    <td>Identity verification and system access control</td>
                </tr>
                <tr>
                    <td>Contact Information</td>
                    <td>Communication regarding lost/found items</td>
                </tr>
                <tr>
                    <td>Course/Department</td>
                    <td>User categorization and statistical analysis</td>
                </tr>
            </tbody>
        </table>
        
        <h3>2.2 Item Information</h3>
        <p>When you report lost or found items, we collect:</p>
        <ul>
            <li>Item descriptions and details</li>
            <li>Photos of items (optional but recommended)</li>
            <li>Location and date information</li>
            <li>Claim information and verification details</li>
        </ul>
        
        <h3>2.3 Usage Data</h3>
        <p>We automatically collect certain information when you use LoFIMS:</p>
        <ul>
            <li>IP addresses and browser information</li>
            <li>Pages visited and time spent on pages</li>
            <li>Search queries and filters used</li>
            <li>System interaction logs</li>
        </ul>
    </div>

    <!-- USE OF INFORMATION -->
    <div class="privacy-section">
        <h2>3. How We Use Your Information</h2>
        
        <p>We use the collected information for the following purposes:</p>
        
        <table class="privacy-table">
            <thead>
                <tr>
                    <th>Purpose</th>
                    <th>Legal Basis</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>To provide and maintain LoFIMS services</td>
                    <td>Contractual necessity</td>
                </tr>
                <tr>
                    <td>To process lost/found item reports and claims</td>
                    <td>Legitimate interest</td>
                </tr>
                <tr>
                    <td>To verify user identity and prevent fraud</td>
                    <td>Legal obligation and legitimate interest</td>
                </tr>
                <tr>
                    <td>To communicate about item status and system updates</td>
                    <td>Legitimate interest</td>
                </tr>
                <tr>
                    <td>To improve system functionality and user experience</td>
                    <td>Legitimate interest</td>
                </tr>
                <tr>
                    <td>To generate statistical reports for administration</td>
                    <td>Legitimate interest</td>
                </tr>
            </tbody>
        </table>
        
        <div class="note-box">
            <p><strong>Note:</strong> We do not sell, rent, or trade your personal information to third parties for marketing purposes.</p>
        </div>
    </div>

    <!-- DATA SHARING -->
    <div class="privacy-section">
        <h2>4. Information Sharing and Disclosure</h2>
        
        <p>We may share your information in the following circumstances:</p>
        
        <h3>4.1 With Other Users</h3>
        <ul>
            <li>When you report a lost item, your contact information may be shared with users who find matching items</li>
            <li>When you find an item, your contact information may be shared with the legitimate owner</li>
            <li>This sharing is necessary for item recovery and is limited to essential contact details only</li>
        </ul>
        
        <h3>4.2 With TUP Administration</h3>
        <ul>
            <li>Authorized TUP Lopez administrators have access to system data</li>
            <li>Administrators use this access for system management, verification, and reporting purposes</li>
            <li>Access is granted on a need-to-know basis with strict confidentiality agreements</li>
        </ul>
        
        <h3>4.3 Legal Requirements</h3>
        <p>We may disclose your information if required to do so by law or in response to:</p>
        <ul>
            <li>Court orders or legal processes</li>
            <li>Government requests</li>
            <li>Investigation of potential violations of our Terms of Service</li>
            <li>Protection of rights, property, or safety of users or the public</li>
        </ul>
        
        <div class="warning-box">
            <p><strong>Important:</strong> Your personal information will never be shared with external third parties for commercial purposes without your explicit consent.</p>
        </div>
    </div>

    <!-- DATA SECURITY -->
    <div class="privacy-section">
        <h2>5. Data Security</h2>
        
        <p>We implement appropriate technical and organizational measures to protect your personal information:</p>
        
        <h3>5.1 Security Measures</h3>
        <ul>
            <li>Encryption of sensitive data in transit and at rest</li>
            <li>Secure password hashing using industry-standard algorithms</li>
            <li>Regular security audits and vulnerability assessments</li>
            <li>Access controls and authentication mechanisms</li>
            <li>Regular system updates and patches</li>
            <li>Secure database management practices</li>
        </ul>
        
        <h3>5.2 User Responsibilities</h3>
        <p>While we strive to protect your information, you also have responsibilities:</p>
        <ul>
            <li>Keep your login credentials confidential</li>
            <li>Use strong, unique passwords</li>
            <li>Log out after each session, especially on shared computers</li>
            <li>Notify us immediately of any unauthorized account access</li>
        </ul>
        
        <p>Despite our security measures, no system can guarantee 100% security. We cannot guarantee the absolute security of information transmitted to or stored in our system.</p>
    </div>

    <!-- DATA RETENTION -->
    <div class="privacy-section">
        <h2>6. Data Retention</h2>
        
        <p>We retain personal information for as long as necessary to fulfill the purposes outlined in this policy:</p>
        
        <table class="privacy-table">
            <thead>
                <tr>
                    <th>Data Type</th>
                    <th>Retention Period</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>User account information</td>
                    <td>While account is active + 1 year after deactivation</td>
                </tr>
                <tr>
                    <td>Lost/found item reports</td>
                    <td>2 years from last activity</td>
                </tr>
                <tr>
                    <td>Claim history</td>
                    <td>3 years for record-keeping purposes</td>
                </tr>
                <tr>
                    <td>System logs</td>
                    <td>6 months for security monitoring</td>
                </tr>
                <tr>
                    <td>Statistical data</td>
                    <td>Indefinitely in anonymized form</td>
                </tr>
            </tbody>
        </table>
        
        <p>After the retention period, data is either deleted or anonymized for statistical analysis.</p>
    </div>

    <!-- USER RIGHTS -->
    <div class="privacy-section">
        <h2>7. Your Rights</h2>
        
        <p>You have the following rights regarding your personal information:</p>
        
        <h3>7.1 Access and Correction</h3>
        <ul>
            <li>Right to access the personal information we hold about you</li>
            <li>Right to request correction of inaccurate or incomplete information</li>
            <li>Right to update your profile information through your account settings</li>
        </ul>
        
        <h3>7.2 Data Management</h3>
        <ul>
            <li>Right to request deletion of your personal information (subject to legal requirements)</li>
            <li>Right to restrict processing of your information in certain circumstances</li>
            <li>Right to data portability (receive your data in a structured format)</li>
        </ul>
        
        <h3>7.3 Communication Preferences</h3>
        <ul>
            <li>Right to opt-out of non-essential communications</li>
            <li>Right to withdraw consent for data processing (where applicable)</li>
            <li>Right to object to processing based on legitimate interests</li>
        </ul>
        
        <p>To exercise these rights, please contact us using the information in Section 9.</p>
    </div>

    <!-- COOKIES -->
    <div class="privacy-section">
        <h2>8. Cookies and Tracking Technologies</h2>
        
        <p>LoFIMS uses cookies and similar tracking technologies to:</p>
        <ul>
            <li>Maintain user sessions</li>
            <li>Remember user preferences</li>
            <li>Analyze system usage patterns</li>
            <li>Improve system performance</li>
        </ul>
        
        <p>You can control cookies through your browser settings. However, disabling certain cookies may affect system functionality.</p>
        
        <div class="note-box">
            <p><strong>Note:</strong> LoFIMS does not use cookies for third-party advertising or tracking across different websites.</p>
        </div>
    </div>

    <!-- CONTACT -->
    <div class="privacy-section">
        <h2>9. Contact Information</h2>
        
        <p>If you have questions, concerns, or requests regarding this Privacy Policy or your personal information, please contact:</p>
        
        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>LoFIMS Data Protection Officer</strong><br>
            Technological University of the Philippines - Lopez Campus<br>
            Lopez, Quezon<br>
            Email: privacy@lofims.edu.ph<br>
            Phone: (042) 555-7890</p>
        </div>
        
        <p>We will respond to your inquiry within 7 business days.</p>
    </div>

    <!-- UPDATES -->
    <div class="privacy-section">
        <h2>10. Changes to This Privacy Policy</h2>
        
        <p>We may update this Privacy Policy from time to time. The updated version will be indicated by an updated "Last Updated" date at the bottom of this page. Significant changes will be notified through:</p>
        <ul>
            <li>System announcements</li>
            <li>Email notifications to registered users</li>
            <li>Prominent notices on the LoFIMS website</li>
        </ul>
        
        <p>We encourage you to review this Privacy Policy periodically to stay informed about how we protect your information.</p>
    </div>

    <!-- CONTACT CTA -->
    <div class="contact-cta">
        <i class="fas fa-shield-alt"></i>
        <h3>Privacy Concerns?</h3>
        <p>If you have specific privacy concerns or need assistance with your personal data, our dedicated privacy team is here to help.</p>
        <a href="contact.php?type=privacy" class="contact-btn">
            <i class="fas fa-envelope"></i> Contact Privacy Team
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
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy</a></li>
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
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
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
        
        <!-- CONTACT INFO -->
        <div class="footer-section">
            <h3>Quick Help</h3>
            <ul class="footer-links">
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
                <li><a href="contact.php"><i class="fas fa-headset"></i> Live Support</a></li>
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
            <i class="fas fa-chart-bar"></i> Statistics: 
            <?php echo $categoriesCount; ?> Categories • 
            <?php echo $lostCount + $foundCount; ?> Active Items • 
            <?php echo $usersCount; ?> Registered Users
        </small>
    </div>
</footer>

<script>
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

// Smooth scroll for navigation links
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

// Add subtle hover animation to privacy sections
document.querySelectorAll('.privacy-section').forEach(section => {
    section.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
    });
    
    section.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.boxShadow = '0 8px 20px rgba(0,0,0,0.15)';
    });
});
</script>

</body>
</html>