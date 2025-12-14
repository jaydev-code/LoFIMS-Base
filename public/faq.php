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
<title>FAQ - LoFIMS TUP Lopez</title>
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

/* FAQ SECTION */
.faq-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

.faq-item {
    background: rgba(255,255,255,0.9);
    margin-bottom: 20px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid transparent;
}

.faq-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.faq-question {
    padding: 25px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s;
}

.faq-question:hover {
    background: linear-gradient(45deg, #0d7bd4, #1e90ff);
}

.faq-question i {
    transition: transform 0.3s;
}

.faq-question.active i {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0 25px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    background: white;
}

.faq-answer.show {
    padding: 25px;
    max-height: 1000px;
}

.faq-answer p {
    font-size: 16px;
    color: #555;
    line-height: 1.6;
}

/* FAQ SEARCH BAR */
.faq-search {
    max-width: 600px;
    margin: 0 auto 40px;
    position: relative;
}

.faq-search input {
    width: 100%;
    padding: 18px 20px;
    border: 2px solid #ddd;
    border-radius: 15px;
    font-size: 16px;
    transition: all 0.3s;
    background: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.faq-search input:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.1);
}

.faq-search i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #1e90ff;
    font-size: 20px;
}

/* FAQ CATEGORIES */
.faq-categories {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 40px;
}

.category-btn {
    padding: 12px 25px;
    background: white;
    color: #0a3d62;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-btn:hover {
    border-color: #1e90ff;
    color: #1e90ff;
    transform: translateY(-2px);
}

.category-btn.active {
    background: #1e90ff;
    color: white;
    border-color: #1e90ff;
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
    
    .faq-question {
        font-size: 16px;
        padding: 20px;
    }
    
    .faq-search input {
        padding: 15px;
        font-size: 15px;
    }
    
    .faq-categories {
        gap: 10px;
    }
    
    .category-btn {
        padding: 10px 15px;
        font-size: 13px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
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
            <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
            
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
        <h1 class="page-title">Frequently Asked Questions</h1>
        <p class="page-subtitle">Find answers to common questions about LoFIMS</p>
    </div>
</div>

<!-- FAQ SECTION -->
<div class="faq-container">
    <!-- SEARCH BAR -->
    <div class="faq-search">
        <input type="text" id="faqSearch" placeholder="Search for questions...">
        <i class="fas fa-search"></i>
    </div>

    <!-- CATEGORIES -->
    <div class="faq-categories">
        <button class="category-btn active" data-category="all">
            <i class="fas fa-star"></i> All Questions
        </button>
        <button class="category-btn" data-category="general">
            <i class="fas fa-info-circle"></i> General
        </button>
        <button class="category-btn" data-category="reporting">
            <i class="fas fa-file-alt"></i> Reporting Items
        </button>
        <button class="category-btn" data-category="claiming">
            <i class="fas fa-hand-holding"></i> Claiming Items
        </button>
        <button class="category-btn" data-category="account">
            <i class="fas fa-user"></i> Account & Access
        </button>
        <button class="category-btn" data-category="technical">
            <i class="fas fa-cogs"></i> Technical
        </button>
    </div>

    <!-- FAQ ITEMS -->
    <!-- FAQ 1 - General -->
    <div class="faq-item" data-category="general">
        <div class="faq-question">
            <span>What is LoFIMS and who can use it?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p><strong>LoFIMS (Lost and Found Information Management System)</strong> is the official digital platform for managing lost and found items at Technological University of the Philippines - Lopez, Quezon Campus. It's available to all TUP Lopez students, faculty, and staff. You need to register with your valid TUP email address and student/employee ID.</p>
        </div>
    </div>

    <!-- FAQ 2 - General -->
    <div class="faq-item" data-category="general">
        <div class="faq-question">
            <span>Is there a fee for using LoFIMS?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>No, LoFIMS is completely free for all TUP Lopez community members as a campus service provided by the university administration to enhance campus security and community cooperation.</p>
        </div>
    </div>

    <!-- FAQ 3 - Reporting -->
    <div class="faq-item" data-category="reporting">
        <div class="faq-question">
            <span>How do I report a lost item?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>1. Log in to your LoFIMS account<br>
               2. Go to the "Lost Items" page<br>
               3. Click the "Report Lost Item" button<br>
               4. Fill out the form with detailed information about your item<br>
               5. Upload photos to help identify your item<br>
               6. Submit the report<br><br>
               The more details you provide, the better your chances of recovery!</p>
        </div>
    </div>

    <!-- FAQ 4 - Reporting -->
    <div class="faq-item" data-category="reporting">
        <div class="faq-question">
            <span>What happens when I find an item on campus?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>If you find an item on campus, you can:<br><br>
               1. <strong>Report it through LoFIMS:</strong> Go to "Found Items" page, click "Report Found Item", provide details about where and when you found it, upload photos if possible, and submit.<br>
               2. <strong>Turn it in physically:</strong> Bring the item to the designated lost and found office on campus (Admin Building, Ground Floor).<br>
               3. <strong>Keep it safe temporarily:</strong> If you prefer to keep the item until the owner is found, mark this in your report.<br><br>
               Reporting found items helps reunite owners with their belongings quickly.</p>
        </div>
    </div>

    <!-- FAQ 5 - Reporting -->
    <div class="faq-item" data-category="reporting">
        <div class="faq-question">
            <span>What types of items can I report?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>You can report any personal items including:<br>
               • Electronics (phones, laptops, tablets, chargers)<br>
               • Books and notebooks<br>
               • Clothing and accessories<br>
               • School supplies (calculators, pens, bags)<br>
               • IDs and documents<br>
               • Keys and small personal items<br>
               • Valuables (wallets, jewelry, watches)<br><br>
               <strong>Note:</strong> For extremely valuable items, contact campus security immediately in addition to reporting on LoFIMS.</p>
        </div>
    </div>

    <!-- FAQ 6 - Reporting -->
    <div class="faq-item" data-category="reporting">
        <div class="faq-question">
            <span>How long are items kept in the system?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p><strong>Lost items:</strong> Remain active in the system for 60 days. After this period, they are marked as "inactive" but can still be searched.<br><br>
               <strong>Found items:</strong> Are kept for 90 days before being processed according to campus policy. Items of significant value may be kept longer.<br><br>
               <strong>Note:</strong> Always report items as soon as possible for the best chance of recovery.</p>
        </div>
    </div>

    <!-- FAQ 7 - Claiming -->
    <div class="faq-item" data-category="claiming">
        <div class="faq-question">
            <span>How do I claim my lost item?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>1. Search for your item in the system using keywords, categories, or dates<br>
               2. Click "View Details" on any matching item<br>
               3. If it's your item, click the "Claim This Item" button<br>
               4. Provide proof of ownership (description of unique features, purchase receipt, photos, etc.)<br>
               5. Submit your claim request<br>
               6. Wait for administrator verification (usually 1-2 business days)<br>
               7. Once approved, you'll receive instructions for item retrieval<br><br>
               <strong>Important:</strong> Be prepared to provide additional verification when picking up your item.</p>
        </div>
    </div>

    <!-- FAQ 8 - Claiming -->
    <div class="faq-item" data-category="claiming">
        <div class="faq-question">
            <span>What proof of ownership do I need to provide?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>To successfully claim an item, you may need to provide:<br><br>
               <strong>For common items:</strong><br>
               • Detailed description of unique features or marks<br>
               • Photos showing you with the item<br>
               • Knowledge of specific contents<br>
               • Serial numbers (for electronics)<br><br>
               <strong>For valuable items:</strong><br>
               • Purchase receipt or invoice<br>
               • Serial numbers or unique identifiers<br>
               • Detailed description of unique characteristics<br>
               • Photos from different angles<br><br>
               <strong>For documents/IDs:</strong><br>
               • Government-issued ID matching the name<br>
               • Additional verification as requested</p>
        </div>
    </div>

    <!-- FAQ 9 - Account -->
    <div class="faq-item" data-category="account">
        <div class="faq-question">
            <span>Who has access to my personal information?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>Your privacy is important to us:<br><br>
               <strong>Publicly visible:</strong> Only item descriptions (no personal information) are visible to other users.<br><br>
               <strong>Administrator access:</strong> Only authorized system administrators can view your personal information for verification purposes.<br><br>
               <strong>Information sharing:</strong> Your contact details are only shared with other users when:<br>
               • You have matching lost/found items<br>
               • Both parties consent to contact<br>
               • It's necessary for item recovery<br><br>
               We comply with data protection regulations and our privacy policy.</p>
        </div>
    </div>

    <!-- FAQ 10 - Account -->
    <div class="faq-item" data-category="account">
        <div class="faq-question">
            <span>How will I know if my item is found?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>LoFIMS provides multiple notification methods:<br><br>
               <strong>Email notifications:</strong> You'll receive automatic emails when:<br>
               • Items matching your lost report are found<br>
               • Someone submits a claim on your found item<br>
               • Your claim status changes<br>
               • Important system updates<br><br>
               <strong>Dashboard updates:</strong> Check your user dashboard for:<br>
               • Status of your reported items<br>
               • Active claims<br>
               • Messages from administrators<br>
               • System notifications<br><br>
               <strong>Push notifications:</strong> Enable in-app notifications for real-time updates.</p>
        </div>
    </div>

    <!-- FAQ 11 - Technical -->
    <div class="faq-item" data-category="technical">
        <div class="faq-question">
            <span>What if I forget my password?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>If you forget your password:<br><br>
               1. Click "Forgot Password" on the login page<br>
               2. Enter your registered email address<br>
               3. Check your email for password reset link (check spam folder too)<br>
               4. Click the link and create a new strong password<br>
               5. Log in with your new password<br><br>
               <strong>Note:</strong> If you don't receive the reset email within 15 minutes, contact technical support at tech@lofims.edu.ph</p>
        </div>
    </div>

    <!-- FAQ 12 - Technical -->
    <div class="faq-item" data-category="technical">
        <div class="faq-question">
            <span>Can I use LoFIMS on my mobile phone?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>Yes! LoFIMS is fully responsive and works on:<br><br>
               <strong>Mobile phones:</strong> Optimized for iOS and Android devices<br>
               <strong>Tablets:</strong> Enhanced interface for tablet screens<br>
               <strong>Desktop computers:</strong> Full-featured interface<br>
               <strong>Recommended browsers:</strong><br>
               • Google Chrome (latest version)<br>
               • Mozilla Firefox<br>
               • Safari (for iOS/macOS)<br>
               • Microsoft Edge<br><br>
               <strong>Mobile features:</strong><br>
               • Touch-friendly interface<br>
               • Camera integration for photo uploads<br>
               • Mobile notifications<br>
               • GPS location tagging (optional)</p>
        </div>
    </div>

    <!-- FAQ 13 - Account -->
    <div class="faq-item" data-category="account">
        <div class="faq-question">
            <span>Can I report items anonymously?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>For accountability and verification purposes, you need to be logged in to report items. However:<br><br>
               <strong>For lost items:</strong> Your identity is only visible to administrators during the claiming process.<br><br>
               <strong>For found items:</strong> You can choose whether to:<br>
               • Display your name to the potential owner<br>
               • Remain anonymous and communicate through the system<br>
               • Designate an administrator as the contact point<br><br>
               <strong>Privacy settings:</strong> You can adjust your privacy preferences in your account settings to control how much information is shared.</p>
        </div>
    </div>

    <!-- FAQ 14 - Claiming -->
    <div class="faq-item" data-category="claiming">
        <div class="faq-question">
            <span>What happens if my claim is rejected?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p>If your claim is rejected, you can:<br><br>
               1. <strong>Appeal the decision:</strong> Provide additional evidence through the system<br>
               2. <strong>Contact the administrator:</strong> Request clarification on why it was rejected<br>
               3. <strong>Submit a new claim:</strong> With better supporting documentation<br>
               4. <strong>Visit the lost and found office:</strong> For in-person verification<br><br>
               <strong>Common rejection reasons:</strong><br>
               • Insufficient proof of ownership<br>
               • Multiple claims on same item<br>
               • Information mismatch<br>
               • Suspicious activity detected<br><br>
               Most issues can be resolved by providing additional verification.</p>
        </div>
    </div>

    <!-- FAQ 15 - General -->
    <div class="faq-item" data-category="general">
        <div class="faq-question">
            <span>How long does the claim process take?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            <p><strong>Standard processing times:</strong><br><br>
               • <strong>Simple claims:</strong> 1-2 business days<br>
               • <strong>Complex claims:</strong> 3-5 business days<br>
               • <strong>High-value items:</strong> Up to 7 business days<br>
               • <strong>Disputed claims:</strong> 5-10 business days<br><br>
               <strong>Factors affecting processing time:</strong><br>
               • Completeness of submitted information<br>
               • Availability of proof documentation<br>
               • Administrator workload<br>
               • Need for additional verification<br><br>
               You can track claim status in real-time through your dashboard.</p>
        </div>
    </div>

    <!-- CONTACT CTA -->
    <div class="contact-cta">
        <i class="fas fa-comments"></i>
        <h3>Still Have Questions?</h3>
        <p>Can't find the answer you're looking for? Our support team is here to help!</p>
        <a href="contact.php" class="contact-btn">
            <i class="fas fa-envelope"></i> Contact Support
        </a>
        <p style="margin-top: 15px; font-size: 14px; color: #666;">
            Or visit our <a href="how_it_works.php" style="color: #1e90ff; text-decoration: underline;">How It Works</a> page for detailed guides
        </p>
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
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            </ul>
        </div>
        
        <!-- INFORMATION -->
        <div class="footer-section">
            <h3>Information</h3>
            <ul class="footer-links">
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How It Works</a></li>
                <li><a href="guide.php"><i class="fas fa-book"></i> User Guide</a></li>
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
// FAQ Accordion Functionality
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const answer = question.nextElementSibling;
        const isActive = question.classList.contains('active');
        
        // Toggle current FAQ
        if (isActive) {
            question.classList.remove('active');
            answer.classList.remove('show');
        } else {
            question.classList.add('active');
            answer.classList.add('show');
        }
    });
});

// FAQ Search Functionality
document.getElementById('faqSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question span').textContent.toLowerCase();
        const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// FAQ Category Filtering
document.querySelectorAll('.category-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        const category = this.getAttribute('data-category');
        const faqItems = document.querySelectorAll('.faq-item');
        
        if (category === 'all') {
            faqItems.forEach(item => {
                item.style.display = 'block';
            });
        } else {
            faqItems.forEach(item => {
                if (item.getAttribute('data-category') === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    });
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

// Auto-open FAQ if URL contains hash
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const targetElement = document.querySelector(hash);
        if (targetElement && targetElement.classList.contains('faq-item')) {
            const question = targetElement.querySelector('.faq-question');
            if (question) {
                question.click();
                setTimeout(() => {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        }
    }
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

// Make FAQ items collapsible when clicking another
document.addEventListener('click', function(e) {
    if (!e.target.closest('.faq-question')) {
        // Clicked outside FAQ question, do nothing
        return;
    }
    
    // Optional: Auto-close other FAQs when opening a new one
    const clickedQuestion = e.target.closest('.faq-question');
    const isOpening = !clickedQuestion.classList.contains('active');
    
    if (isOpening) {
        document.querySelectorAll('.faq-question').forEach(question => {
            if (question !== clickedQuestion && question.classList.contains('active')) {
                question.classList.remove('active');
                question.nextElementSibling.classList.remove('show');
            }
        });
    }
});
</script>

</body>
</html>