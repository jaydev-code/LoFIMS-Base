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
$userEmail = $isLoggedIn ? $_SESSION['email'] : '';

// Process support ticket submission
$ticketMessage = '';
$ticketError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $ticketError = 'Security validation failed. Please try again.';
    } else {
        // Sanitize inputs
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
        $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $contact_method = filter_input(INPUT_POST, 'contact_method', FILTER_SANITIZE_STRING);
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
        if (empty($subject)) $errors[] = 'Subject is required';
        if (empty($category)) $errors[] = 'Category is required';
        if (empty($description) || strlen($description) < 10) $errors[] = 'Description must be at least 10 characters';
        
        if (empty($errors)) {
            try {
                // Generate ticket number
                $ticketNumber = 'TICKET-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                
                // Save to database
                $stmt = $pdo->prepare("INSERT INTO support_tickets 
                    (ticket_number, user_id, name, email, subject, category, priority, description, contact_method, ip_address, status)
                    VALUES (:ticket_number, :user_id, :name, :email, :subject, :category, :priority, :description, :contact_method, :ip_address, 'Open')");
                
                $stmt->execute([
                    ':ticket_number' => $ticketNumber,
                    ':user_id' => $isLoggedIn ? $_SESSION['user_id'] : NULL,
                    ':name' => $name,
                    ':email' => $email,
                    ':subject' => $subject,
                    ':category' => $category,
                    ':priority' => $priority,
                    ':description' => $description,
                    ':contact_method' => $contact_method,
                    ':ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $ticketId = $pdo->lastInsertId();
                
                // Send confirmation email (simulated)
                // In production, use PHPMailer or similar
                $to = $email;
                $subject_email = "LoFIMS Support Ticket Created: $ticketNumber";
                $message = "Dear $name,\n\n";
                $message .= "Thank you for contacting LoFIMS Support.\n";
                $message .= "Your ticket has been created successfully.\n\n";
                $message .= "Ticket Details:\n";
                $message .= "----------------\n";
                $message .= "Ticket Number: $ticketNumber\n";
                $message .= "Subject: $subject\n";
                $message .= "Category: $category\n";
                $message .= "Priority: $priority\n";
                $message .= "Description: $description\n\n";
                $message .= "We will respond to your inquiry within 24-48 hours.\n\n";
                $message .= "Best regards,\n";
                $message .= "LoFIMS Support Team\n";
                $message .= "TUP Lopez\n";
                
                // Uncomment to actually send email
                // mail($to, $subject_email, $message, "From: lofims@tuplopez.edu.ph");
                
                $ticketMessage = "<div class='success-message'>
                    <i class='fas fa-check-circle'></i>
                    <div>
                        <h4>Ticket Created Successfully!</h4>
                        <p>Your support ticket <strong>$ticketNumber</strong> has been submitted.</p>
                        <p>We've sent a confirmation to <strong>$email</strong> and will respond within 24-48 hours.</p>
                        <p><small>You can track your ticket using this number.</small></p>
                    </div>
                </div>";
                
            } catch (PDOException $e) {
                $ticketError = "Database error: " . $e->getMessage();
            }
        } else {
            $ticketError = implode('<br>', $errors);
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch FAQs from database
try {
    $faqs = $pdo->query("SELECT * FROM faqs WHERE status = 'active' ORDER BY category, display_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $faqs = [];
}

// Fetch support categories
$categories = [
    'account' => 'Account Issues',
    'lost_item' => 'Lost Item Report',
    'found_item' => 'Found Item Report',
    'claim' => 'Claim Process',
    'technical' => 'Technical Problems',
    'bug' => 'Bug Report',
    'feature' => 'Feature Request',
    'security' => 'Security Concern',
    'other' => 'Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Center - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8fafc; color:#333; line-height:1.6; }

/* HEADER */
header {
    width:100%;
    padding:20px 60px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border-bottom: 1px solid #e2e8f0;
}
.logo-placeholder {
    display:flex;
    align-items:center;
    gap:12px;
    font-weight:bold;
    color:#0a3d62;
    font-size:18px;
}
.logo-placeholder img {
    height:50px;
    width:auto;
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
.login-btn {
    padding:10px 25px;
    background:linear-gradient(45deg,#1e90ff,#4facfe);
    color:white;
    font-size:14px;
    font-weight:600;
    border-radius:10px;
    border:none;
    cursor:pointer;
    transition:transform 0.3s, box-shadow 0.3s;
}
.login-btn:hover {
    transform:translateY(-2px);
    box-shadow:0 5px 15px rgba(30,144,255,0.3);
}

/* HERO SECTION */
.hero-section {
    background: linear-gradient(135deg, #1e90ff 0%, #4facfe 100%);
    color: white;
    padding: 100px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L1000,0 L1000,100 L0,100" fill="white"/></svg>');
    background-size: 100% 100px;
    background-position: bottom;
    background-repeat: no-repeat;
}
.hero-content {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.hero-title {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}
.hero-subtitle {
    font-size: 20px;
    margin-bottom: 30px;
    opacity: 0.9;
}
.support-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 40px;
    flex-wrap: wrap;
}
.stat-item {
    text-align: center;
}
.stat-number {
    font-size: 36px;
    font-weight: 800;
    color: white;
    margin-bottom: 5px;
}
.stat-label {
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* MAIN CONTENT */
.main-content {
    max-width: 1200px;
    margin: -50px auto 0;
    padding: 0 20px;
    position: relative;
    z-index: 2;
}

/* QUICK ACTIONS */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 60px;
}
.action-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid #e2e8f0;
}
.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}
.action-icon {
    font-size: 40px;
    color: #1e90ff;
    margin-bottom: 20px;
    width: 80px;
    height: 80px;
    background: #e3f2fd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.action-card h3 {
    color: #0a3d62;
    margin-bottom: 15px;
    font-size: 20px;
}
.action-card p {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}
.action-btn {
    display: inline-block;
    padding: 10px 25px;
    background: #1e90ff;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s;
}
.action-btn:hover {
    background: #0d7bd4;
}

/* SUPPORT FORM */
.support-form-section {
    background: white;
    border-radius: 15px;
    padding: 50px;
    margin-bottom: 60px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}
.section-title {
    color: #0a3d62;
    font-size: 32px;
    margin-bottom: 10px;
    font-weight: 700;
}
.section-subtitle {
    color: #666;
    margin-bottom: 40px;
    font-size: 16px;
}

/* MESSAGES */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    border-left: 4px solid #28a745;
}
.success-message i {
    font-size: 24px;
    margin-top: 2px;
}
.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    border-left: 4px solid #dc3545;
}

/* FORM STYLES */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 25px;
}
.form-group label {
    display: block;
    color: #0a3d62;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}
.form-group label.required::after {
    content: ' *';
    color: #dc3545;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
    background: white;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.1);
}
.form-group textarea {
    min-height: 150px;
    resize: vertical;
}
.priority-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.priority-badge {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid #e2e8f0;
    background: white;
}
.priority-badge:hover {
    transform: translateY(-2px);
}
.priority-badge.low { color: #28a745; border-color: #28a745; }
.priority-badge.medium { color: #ffc107; border-color: #ffc107; }
.priority-badge.high { color: #fd7e14; border-color: #fd7e14; }
.priority-badge.urgent { color: #dc3545; border-color: #dc3545; }
.priority-badge input[type="radio"]:checked + .badge-label {
    background: currentColor;
    color: white;
}
.priority-badge input[type="radio"] {
    display: none;
}
.badge-label {
    display: block;
    padding: 4px 12px;
    border-radius: 15px;
    transition: all 0.3s;
}
.submit-btn {
    padding: 15px 40px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
}
.submit-btn i {
    font-size: 20px;
}

/* FAQ SECTION */
.faq-section {
    background: white;
    border-radius: 15px;
    padding: 50px;
    margin-bottom: 60px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}
.faq-grid {
    display: grid;
    gap: 15px;
    margin-top: 30px;
}
.faq-item {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.faq-question {
    padding: 20px;
    background: #f8fafc;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: #0a3d62;
    transition: background 0.3s;
}
.faq-question:hover {
    background: #e3f2fd;
}
.faq-question i {
    transition: transform 0.3s;
}
.faq-answer {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s, padding 0.3s;
}
.faq-item.active .faq-answer {
    padding: 20px;
    max-height: 500px;
}
.faq-item.active .faq-question i {
    transform: rotate(180deg);
}

/* CONTACT INFO */
.contact-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}
.contact-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    text-align: center;
    border: 1px solid #e2e8f0;
}
.contact-icon {
    font-size: 40px;
    color: #1e90ff;
    margin-bottom: 20px;
}
.contact-card h3 {
    color: #0a3d62;
    margin-bottom: 15px;
}
.contact-details {
    list-style: none;
}
.contact-details li {
    margin-bottom: 10px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
}
.contact-details i {
    color: #1e90ff;
    width: 20px;
}

/* RESPONSE TIME */
.response-time {
    background: linear-gradient(135deg, #1e90ff 0%, #4facfe 100%);
    color: white;
    padding: 40px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 60px;
}
.response-time h3 {
    font-size: 28px;
    margin-bottom: 20px;
}
.timeline {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}
.timeline-item {
    text-align: center;
}
.timeline-time {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 10px;
}
.timeline-label {
    font-size: 14px;
    opacity: 0.9;
}

/* FOOTER */
footer {
    background: #0a3d62;
    color: white;
    padding: 60px 20px 20px;
    margin-top: 80px;
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
    font-size: 18px;
    margin-bottom: 20px;
    color: white;
}
.footer-links {
    list-style: none;
}
.footer-links li {
    margin-bottom: 12px;
}
.footer-links a {
    color: #cce7ff;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s;
}
.footer-links a:hover {
    color: white;
}
.copyright {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    color: #cce7ff;
    font-size: 14px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }
    
    nav ul {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }
    
    .hero-title {
        font-size: 36px;
    }
    
    .hero-subtitle {
        font-size: 18px;
    }
    
    .support-form-section,
    .faq-section {
        padding: 30px 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .main-content {
        margin-top: 0;
        padding: 20px;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo-placeholder" onclick="window.location.href='index.php'">
        <i class="fas fa-life-ring" style="font-size: 40px; color: #1e90ff;"></i>
        <div style="display: flex; flex-direction: column;">
            <span style="font-weight: 800; color: #0a3d62; font-size: 24px;">LoFIMS</span>
            <span style="font-size: 13px; color: #1e90ff;">Support Center</span>
        </div>
    </div>
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost Items</a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> Found Items</a></li>
            <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
            
            <?php if ($isLoggedIn): ?>
                <li style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-circle" style="color: #0a3d62;"></i>
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($userName); ?></span>
                </li>
                <li><a href="logout.php" class="login-btn">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="login-btn">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- HERO SECTION -->
<div class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">LoFIMS Support Center</h1>
        <p class="hero-subtitle">Get help with lost items, found items, claims, and technical issues</p>
        
        <div class="support-stats">
            <div class="stat-item">
                <div class="stat-number"><i class="fas fa-clock"></i> 24/7</div>
                <div class="stat-label">Support Available</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">2-4 hrs</div>
                <div class="stat-label">Response Time</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">98%</div>
                <div class="stat-label">Satisfaction Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    
    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <h3>Submit a Ticket</h3>
            <p>Create a support ticket for personalized assistance with your issue</p>
            <a href="#support-form" class="action-btn">Create Ticket</a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <h3>Browse FAQs</h3>
            <p>Find quick answers to common questions about LoFIMS</p>
            <a href="#faq-section" class="action-btn">View FAQs</a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h3>Contact Support</h3>
            <p>Get in touch with our support team via email, phone, or chat</p>
            <a href="#contact-info" class="action-btn">Contact Us</a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-book"></i>
            </div>
            <h3>Knowledge Base</h3>
            <p>Access guides, tutorials, and documentation for LoFIMS</p>
            <a href="guide.php" class="action-btn">View Guides</a>
        </div>
    </div>

    <!-- SUPPORT FORM -->
    <div class="support-form-section" id="support-form">
        <h2 class="section-title">Submit Support Request</h2>
        <p class="section-subtitle">Fill out the form below and we'll get back to you as soon as possible</p>
        
        <?php if ($ticketMessage): ?>
            <?php echo $ticketMessage; ?>
        <?php endif; ?>
        
        <?php if ($ticketError): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $ticketError; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="required">Your Name</label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo $isLoggedIn ? htmlspecialchars($userName) : ''; ?>" 
                           required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo $isLoggedIn ? htmlspecialchars($userEmail) : ''; ?>" 
                           required placeholder="Enter your email address">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="subject" class="required">Subject</label>
                    <input type="text" id="subject" name="subject" required 
                           placeholder="Brief description of your issue">
                </div>
                
                <div class="form-group">
                    <label for="category" class="required">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required">Priority Level</label>
                <div class="priority-badges">
                    <?php 
                    $priorities = [
                        'low' => 'Low (General Question)',
                        'medium' => 'Medium (Minor Issue)',
                        'high' => 'High (Urgent Issue)',
                        'urgent' => 'Urgent (Critical Problem)'
                    ];
                    foreach ($priorities as $value => $label): ?>
                    <label class="priority-badge <?php echo $value; ?>">
                        <input type="radio" name="priority" value="<?php echo $value; ?>" 
                               <?php echo $value === 'medium' ? 'checked' : ''; ?>>
                        <span class="badge-label"><?php echo $label; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description" class="required">Description</label>
                <textarea id="description" name="description" required 
                          placeholder="Please provide detailed information about your issue..."></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">
                    Include relevant details: Item name, dates, ticket numbers, error messages, etc.
                </small>
            </div>
            
            <div class="form-group">
                <label for="contact_method">Preferred Contact Method</label>
                <select id="contact_method" name="contact_method">
                    <option value="email">Email</option>
                    <option value="phone">Phone Call</option>
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" name="submit_ticket" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Support Request
                </button>
                <small style="color: #666; margin-left: 15px;">
                    <i class="fas fa-info-circle"></i> We'll respond within 24-48 hours
                </small>
            </div>
        </form>
    </div>

    <!-- FAQ SECTION -->
    <div class="faq-section" id="faq-section">
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-subtitle">Quick answers to common questions about LoFIMS</p>
        
        <div class="faq-grid">
            <?php if (!empty($faqs)): ?>
                <?php foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <div class="faq-question">
                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default FAQs if database is empty -->
                <div class="faq-item active">
                    <div class="faq-question">
                        <span>How do I report a lost item?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>To report a lost item, go to the "Lost Items" page and click "Report Lost Item". Fill in the required details including item name, description, location lost, and date. You can also upload a photo if available.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How long does it take to process a claim?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Claims are typically processed within 1-3 business days. The exact time depends on the complexity of the claim and the availability of verification information. You'll receive email notifications at each stage.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What should I do if I find someone's lost item?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>If you find a lost item, report it immediately on the "Found Items" page. Provide as much detail as possible and upload photos if available. Keep the item safe until the owner claims it or you receive further instructions.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do I reset my password?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Click "Forgot Password" on the login page. Enter your registered email address and you'll receive a password reset link. If you don't receive the email within 15 minutes, check your spam folder or contact support.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What information do I need to claim an item?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>To claim an item, you'll need to provide proof of ownership such as: a detailed description, purchase receipt, serial number, or photos showing identifying marks. You may also need to present valid ID when collecting the item.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CONTACT INFO -->
    <div class="contact-info" id="contact-info">
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <h3>Email Support</h3>
            <ul class="contact-details">
                <li><i class="fas fa-inbox"></i> support@lofims.tuplopez.edu.ph</li>
                <li><i class="fas fa-clock"></i> Response: Within 24 hours</li>
                <li><i class="fas fa-paperclip"></i> Include ticket # if available</li>
            </ul>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h3>Phone Support</h3>
            <ul class="contact-details">
                <li><i class="fas fa-phone"></i> (042) 555-LOST (5678)</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</li>
                <li><i class="fas fa-mobile-alt"></i> Sat: 9:00 AM - 12:00 PM</li>
            </ul>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3>In-Person Support</h3>
            <ul class="contact-details">
                <li><i class="fas fa-university"></i> TUP Lopez Main Campus</li>
                <li><i class="fas fa-building"></i> Administration Building, Room 101</li>
                <li><i class="fas fa-clock"></i> 8:00 AM - 5:00 PM (Weekdays)</li>
            </ul>
        </div>
    </div>

    <!-- RESPONSE TIME -->
    <div class="response-time">
        <h3>Our Response Time Commitment</h3>
        <p>We're committed to responding to your inquiries as quickly as possible</p>
        
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-time">2-4 hrs</div>
                <div class="timeline-label">Urgent Issues</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-time">24 hrs</div>
                <div class="timeline-label">High Priority</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-time">48 hrs</div>
                <div class="timeline-label">Standard Issues</div>
            </div>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="lost_items.php">Lost Items</a></li>
                <li><a href="found_items.php">Found Items</a></li>
                <li><a href="claim_item.php">Claims</a></li>
                <li><a href="faq.php">FAQ</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Support</h3>
            <ul class="footer-links">
                <li><a href="support.php">Support Center</a></li>
                <li><a href="feedback.php">Submit Feedback</a></li>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="report_issue.php">Report Issue</a></li>
                <li><a href="status.php">System Status</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Resources</h3>
            <ul class="footer-links">
                <li><a href="guide.php">User Guide</a></li>
                <li><a href="tutorials.php">Tutorials</a></li>
                <li><a href="policy.php">Privacy Policy</a></li>
                <li><a href="terms.php">Terms of Service</a></li>
                <li><a href="about.php">About LoFIMS</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Emergency</h3>
            <ul class="footer-links">
                <li><a href="tel:911"><i class="fas fa-phone"></i> Emergency: 911</a></li>
                <li><a href="tel:0425555678"><i class="fas fa-phone"></i> Campus Security</a></li>
                <li><a href="lost_card.php"><i class="fas fa-id-card"></i> Lost ID Card</a></li>
                <li><a href="lost_device.php"><i class="fas fa-laptop"></i> Lost Device</a></li>
                <li><a href="urgent.php"><i class="fas fa-exclamation-triangle"></i> Urgent Report</a></li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.<br>
        <small>Support Center Version 1.0 • Last updated: <?php echo date('F d, Y'); ?></small><br>
        <small>For immediate assistance, call: (042) 555-LOST (5678) or email: support@lofims.tuplopez.edu.ph</small>
    </div>
</footer>

<script>
// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const item = question.parentElement;
        item.classList.toggle('active');
    });
});

// Priority badge selection
document.querySelectorAll('.priority-badge input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.priority-badge').forEach(badge => {
            badge.style.background = 'white';
        });
        
        if (this.checked) {
            const badge = this.closest('.priority-badge');
            badge.style.background = getComputedStyle(badge).color;
            badge.style.color = 'white';
        }
    });
});

// Auto-resize textarea
const textarea = document.getElementById('description');
if (textarea) {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
}

// Form validation
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.style.borderColor = '#dc3545';
                field.nextElementSibling?.classList.add('error');
            } else {
                field.style.borderColor = '#e2e8f0';
                field.nextElementSibling?.classList.remove('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
}

// Auto-fill form based on URL parameters
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('category')) {
    const categorySelect = document.getElementById('category');
    if (categorySelect) {
        categorySelect.value = urlParams.get('category');
    }
}
if (urlParams.has('subject')) {
    const subjectInput = document.getElementById('subject');
    if (subjectInput) {
        subjectInput.value = decodeURIComponent(urlParams.get('subject'));
    }
}
</script>

</body>
</html>