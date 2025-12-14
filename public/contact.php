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

// Process contact form submission
$contactMessage = '';
$formSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
    $subject = isset($_POST['subject']) ? htmlspecialchars(trim($_POST['subject'])) : '';
    $category = isset($_POST['category']) ? htmlspecialchars(trim($_POST['category'])) : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';
    
    // Enhanced validation
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($category) && !empty($message) && strlen($message) >= 20) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contactMessage = '<div class="success-message">
                <i class="fas fa-check-circle"></i> <strong>Thank you for contacting us!</strong> We\'ve received your message and will get back to you within 24-48 hours.
            </div>';
            $formSubmitted = true;
        } else {
            $contactMessage = '<div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> Please enter a valid email address.
            </div>';
        }
    } else {
        $contactMessage = '<div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> Please fill in all required fields and provide a detailed message (minimum 20 characters).
        </div>';
    }
}

// Logo path
$logoPath = '../assets/images/lofims-logo.png';
$logoExists = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>Contact Us - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

/* HEADER WITH HAMBURGER MENU */
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

/* LOGO CONTAINER - MATCHING ABOUT PAGE */
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

/* CONTACT CONTAINER */
.contact-container {
    max-width: 1300px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* MESSAGES */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 2px solid #c3e6cb;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideIn 0.5s;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 2px solid #f5c6cb;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideIn 0.5s;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* CONTACT INFO */
.contact-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.contact-card {
    background: rgba(255,255,255,0.95);
    padding: 35px 30px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    transition: all 0.3s;
    border: 2px solid rgba(52,152,219,0.2);
    position: relative;
    overflow: hidden;
}

.contact-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}

.contact-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.contact-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(30,144,255,0.3);
    transition: transform 0.3s;
}

.contact-card:hover .contact-icon {
    transform: scale(1.1);
}

.contact-icon i {
    font-size: 36px;
    color: white;
}

.contact-card h3 {
    font-size: 22px;
    color: #0a3d62;
    margin-bottom: 20px;
    font-weight: 700;
}

.contact-card p {
    color: #555;
    font-size: 16px;
    line-height: 1.7;
    margin-bottom: 10px;
}

.contact-card a {
    color: #1e90ff;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.contact-card a:hover {
    color: #0d7bd4;
    text-decoration: underline;
    transform: translateX(3px);
}

/* CONTACT FORM SECTION */
.contact-form-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    margin-top: 40px;
}

.contact-form {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.contact-form:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.contact-form h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.contact-form h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}

/* FORM ELEMENTS */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    color: #0a3d62;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label i {
    color: #1e90ff;
    width: 20px;
    text-align: center;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 15px 18px;
    border: 2px solid #ddd;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s;
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.1);
}

.form-group input:read-only {
    background: #f8f9fa;
    color: #6c757d;
    cursor: not-allowed;
}

.form-group textarea {
    height: 180px;
    resize: vertical;
    line-height: 1.6;
}

.char-count {
    text-align: right;
    font-size: 14px;
    color: #666;
    margin-top: 8px;
}

.char-count.warning {
    color: #ff9800;
}

.char-count.error {
    color: #f44336;
}

.submit-btn {
    width: 100%;
    padding: 18px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 10px;
}

.submit-btn:hover:not(:disabled) {
    background: linear-gradient(45deg, #0d7bd4, #1e90ff);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(30,144,255,0.3);
}

.submit-btn:disabled {
    background: #cccccc;
    cursor: not-allowed;
    transform: none;
}

/* MAP AND INFO SECTION */
.map-section {
    background: rgba(255,255,255,0.95);
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.map-section h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 25px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.map-section h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}

/* MAP PLACEHOLDER */
.map-placeholder {
    width: 100%;
    height: 280px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #0a3d62;
    border: 3px dashed #90caf9;
    margin-bottom: 30px;
    transition: all 0.3s;
    cursor: pointer;
}

.map-placeholder:hover {
    border-color: #1e90ff;
    transform: translateY(-3px);
}

.map-placeholder i {
    font-size: 60px;
    margin-bottom: 20px;
    color: #1e90ff;
}

.map-placeholder p {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
}

.map-placeholder small {
    font-size: 14px;
    color: #555;
}

/* OFFICE HOURS */
.office-hours {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
}

.office-hours h3 {
    color: #0a3d62;
    font-size: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.office-hours table {
    width: 100%;
    border-collapse: collapse;
}

.office-hours td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    color: #555;
    transition: background 0.3s;
}

.office-hours tr:hover td {
    background: rgba(30,144,255,0.05);
}

.office-hours td:first-child {
    font-weight: 600;
    color: #0a3d62;
    width: 40%;
}

/* FAQ PREVIEW */
.faq-preview {
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
}

.faq-preview h3 {
    color: #0a3d62;
    font-size: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.faq-preview ul {
    list-style: none;
    padding: 0;
}

.faq-preview li {
    margin-bottom: 15px;
}

.faq-preview a {
    color: #555;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    border-radius: 10px;
    transition: all 0.3s;
    background: white;
    border: 1px solid #e9ecef;
}

.faq-preview a:hover {
    background: #1e90ff;
    color: white;
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.2);
    border-color: #1e90ff;
}

.faq-preview a:hover i {
    color: white;
}

.faq-preview i {
    color: #1e90ff;
    width: 20px;
    text-align: center;
}

/* DEPARTMENT CONTACTS */
.departments-section {
    margin-top: 60px;
    padding: 40px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 25px;
    position: relative;
    overflow: hidden;
}

.departments-section::before {
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

.departments-section h2 {
    text-align: center;
    color: #0a3d62;
    font-size: 32px;
    margin-bottom: 40px;
    position: relative;
    padding-bottom: 15px;
}

.departments-section h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}

.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.department-card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    transition: all 0.3s;
    border: 2px solid transparent;
    text-align: center;
}

.department-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    border-color: #1e90ff;
}

.department-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.department-icon i {
    font-size: 30px;
    color: white;
}

.department-card h3 {
    color: #0a3d62;
    font-size: 20px;
    margin-bottom: 15px;
    font-weight: 700;
}

.department-card p {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.contact-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-top: 15px;
}

.contact-details p {
    margin: 8px 0;
    font-size: 14px;
    color: #555;
}

.contact-details strong {
    color: #0a3d62;
    display: inline-block;
    min-width: 70px;
}

/* EMERGENCY CONTACT */
.emergency-contact {
    text-align: center;
    margin-top: 40px;
    padding: 25px;
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-radius: 15px;
    border: 2px solid #ffc107;
}

.emergency-contact i {
    font-size: 40px;
    color: #d32f2f;
    margin-bottom: 15px;
}

.emergency-contact h3 {
    color: #c62828;
    font-size: 22px;
    margin-bottom: 10px;
}

.emergency-contact p {
    color: #795548;
    font-size: 16px;
    margin-bottom: 15px;
}

.emergency-contact a {
    color: white;
    background: #f44336;
    padding: 12px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.emergency-contact a:hover {
    background: #d32f2f;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(244, 67, 54, 0.3);
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
        font-size: 12px;
        display: inline !important;
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
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .contact-form-section {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    
    .contact-info-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-form {
        padding: 25px;
    }
    
    .map-section {
        padding: 25px;
    }
    
    .departments-section {
        padding: 25px;
    }
    
    .contact-card {
        padding: 25px 20px;
    }
    
    .contact-icon {
        width: 70px;
        height: 70px;
    }
    
    .departments-grid {
        grid-template-columns: 1fr;
    }
    
    .logo-container img {
        height: 50px;
        width: 50px;
        border-radius: 14px;
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
            <li><a href="contact.php"><i class="fas fa-envelope"></i> <span>Contact</span></a></li>
            
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
        <h1 class="page-title">Contact Us</h1>
        <p class="page-subtitle">Get in touch with our support team - we're here to help!</p>
    </div>
</div>

<!-- CONTACT CONTENT -->
<div class="contact-container">
    <!-- Contact Cards (UPDATED - Removed Live Chat) -->
    <div class="contact-info-grid">
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3>Our Location</h3>
            <p>Technological University of the Philippines<br>
            <strong>Lopez, Quezon Campus</strong><br>
            Lopez, Quezon 4316<br>
            Administration Building, 2nd Floor</p>
            <p style="font-size: 14px; color: #666; margin-top: 15px;">
                <i class="fas fa-directions"></i> Campus Map Available at Main Gate
            </p>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-phone"></i>
            </div>
            <h3>Phone Numbers</h3>
            <p><strong>Main Office:</strong> <a href="tel:+63425551234">(042) 555-1234</a><br>
            <strong>Support Line:</strong> <a href="tel:+63425555678">(042) 555-5678</a><br>
            <strong>Technical Support:</strong> <a href="tel:+63425558888">(042) 555-8888</a></p>
            <p style="font-size: 14px; color: #666; margin-top: 10px;">
                <i class="fas fa-clock"></i> Available during office hours
            </p>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <h3>Email Addresses</h3>
            <p><strong>General Inquiries:</strong> <a href="mailto:info@lofims.edu.ph">info@lofims.edu.ph</a><br>
            <strong>Technical Support:</strong> <a href="mailto:support@lofims.edu.ph">support@lofims.edu.ph</a><br>
            <strong>Item Recovery:</strong> <a href="mailto:recovery@lofims.edu.ph">recovery@lofims.edu.ph</a></p>
            <p style="font-size: 14px; color: #666; margin-top: 10px;">
                <i class="fas fa-bolt"></i> Response within 24-48 hours
            </p>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-comments"></i>
            </div>
            <h3>Contact Form</h3>
            <p>Send us a detailed message using our contact form. We'll get back to you as soon as possible with a comprehensive response.</p>
            <div style="margin-top: 20px;">
                <a href="#contact-form" style="background: #1e90ff; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0 auto; text-decoration: none; width: fit-content;">
                    <i class="fas fa-pen"></i> Use Contact Form
                </a>
            </div>
        </div>
    </div>

    <!-- Contact Form and Map -->
    <?php if (!$formSubmitted): ?>
    <div class="contact-form-section" id="contact-form">
        <!-- Contact Form -->
        <div class="contact-form">
            <h2>Send Us a Message</h2>
            
            <?php echo $contactMessage; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $isLoggedIn ? htmlspecialchars($userName) : ''; ?>"
                           <?php echo $isLoggedIn ? 'readonly' : ''; ?>
                           placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $isLoggedIn ? htmlspecialchars($_SESSION['email'] ?? '') : ''; ?>"
                           <?php echo $isLoggedIn ? 'readonly' : ''; ?>
                           placeholder="your.email@example.com">
                </div>
                
                <div class="form-group">
                    <label for="category"><i class="fas fa-tag"></i> Inquiry Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select a category</option>
                        <option value="technical">Technical Support</option>
                        <option value="item">Lost/Found Item Inquiry</option>
                        <option value="account">Account Issues</option>
                        <option value="claim">Claim Process</option>
                        <option value="feedback">Feedback/Suggestion</option>
                        <option value="bug">Report a Bug</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject"><i class="fas fa-pen"></i> Subject *</label>
                    <input type="text" id="subject" name="subject" required placeholder="Brief description of your inquiry">
                </div>
                
                <div class="form-group">
                    <label for="message"><i class="fas fa-comment"></i> Message *</label>
                    <textarea id="message" name="message" required 
                              placeholder="Please provide detailed information about your inquiry. Include item details, dates, or specific issues you're experiencing."
                              oninput="updateCharCount(this)"></textarea>
                    <div id="charCount" class="char-count">0 characters</div>
                </div>
                
                <button type="submit" name="submit_contact" class="submit-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
        
        <!-- Map and Info -->
        <div class="map-section">
            <h2>Visit Our Office</h2>
            
            <div class="map-placeholder" onclick="showMap()" aria-label="View interactive campus map">
                <i class="fas fa-map-marked-alt"></i>
                <p>TUP Lopez Campus</p>
                <small>Click to view interactive map</small>
            </div>
            
            <div class="office-hours">
                <h3><i class="fas fa-clock"></i> Office Hours</h3>
                <table>
                    <tr>
                        <td>Monday - Friday</td>
                        <td>8:00 AM - 5:00 PM</td>
                    </tr>
                    <tr>
                        <td>Saturday</td>
                        <td>9:00 AM - 12:00 PM</td>
                    </tr>
                    <tr>
                        <td>Sunday</td>
                        <td>Closed</td>
                    </tr>
                    <tr>
                        <td>Holidays</td>
                        <td>Closed (Campus Schedule)</td>
                    </tr>
                </table>
                <p style="margin-top: 15px; font-size: 14px; color: #666;">
                    <i class="fas fa-info-circle"></i> Extended hours during examination periods
                </p>
            </div>
            
            <div class="faq-preview">
                <h3><i class="fas fa-question-circle"></i> Quick Help</h3>
                <ul>
                    <li><a href="faq.php"><i class="fas fa-question"></i> Frequently Asked Questions</a></li>
                    <li><a href="how_it_works.php"><i class="fas fa-cogs"></i> How LoFIMS Works</a></li>
                    <li><a href="guide.php"><i class="fas fa-book"></i> User Guide & Tutorials</a></li>
                    <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Technical Issue</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DEPARTMENT CONTACTS -->
    <div class="departments-section">
        <h2>Department Contacts</h2>
        
        <div class="departments-grid">
            <div class="department-card">
                <div class="department-icon">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <h3>Technical Support</h3>
                <p>For system issues, bugs, technical problems, and website functionality</p>
                <div class="contact-details">
                    <p><strong>Email:</strong> tech@lofims.edu.ph</p>
                    <p><strong>Phone:</strong> Ext. 101</p>
                    <p><strong>Hours:</strong> Mon-Fri, 9AM-5PM</p>
                </div>
            </div>
            
            <div class="department-card">
                <div class="department-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Item Recovery</h3>
                <p>For lost/found item inquiries, claim processing, and recovery assistance</p>
                <div class="contact-details">
                    <p><strong>Email:</strong> recovery@lofims.edu.ph</p>
                    <p><strong>Phone:</strong> Ext. 102</p>
                    <p><strong>Hours:</strong> Mon-Sat, 8AM-5PM</p>
                </div>
            </div>
            
            <div class="department-card">
                <div class="department-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3>Account Support</h3>
                <p>For account issues, password reset, registration, and profile management</p>
                <div class="contact-details">
                    <p><strong>Email:</strong> accounts@lofims.edu.ph</p>
                    <p><strong>Phone:</strong> Ext. 103</p>
                    <p><strong>Hours:</strong> Mon-Fri, 8AM-4PM</p>
                </div>
            </div>
        </div>
    </div>

    <!-- EMERGENCY CONTACT -->
    <div class="emergency-contact">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Emergency Contact</h3>
        <p>For urgent matters outside office hours or security concerns regarding item exchanges</p>
        <a href="tel:+639123456789">
            <i class="fas fa-phone"></i> Call Campus Security: 0912-345-6789
        </a>
        <p style="font-size: 14px; color: #795548; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Use only for genuine emergencies
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
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
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
                <li><a href="contact.php"><i class="fas fa-headset"></i> Contact Support</a></li>
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Give Feedback</a></li>
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><i class="fas fa-phone"></i> (042) 555-5678</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
            </ul>
        </div>
        
        <!-- CONTACT INFO -->
        <div class="footer-section">
            <h3>Quick Contact</h3>
            <ul class="footer-links">
                <li><a href="mailto:support@lofims.edu.ph"><i class="fas fa-envelope"></i> Email Support</a></li>
                <li><a href="tel:+63425555678"><i class="fas fa-phone"></i> Call Support</a></li>
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="#contact-form"><i class="fas fa-comment"></i> Contact Form</a></li>
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
            <i class="fas fa-headset"></i> Need help? Contact our support team anytime
        </small>
    </div>
</footer>

<script>
// Character count for textarea
function updateCharCount(textarea) {
    const count = textarea.value.length;
    const charCount = document.getElementById('charCount');
    const submitBtn = document.getElementById('submitBtn');
    
    charCount.textContent = `${count} characters`;
    
    if (count < 20) {
        charCount.className = 'char-count error';
        submitBtn.disabled = true;
    } else if (count < 50) {
        charCount.className = 'char-count warning';
        submitBtn.disabled = false;
    } else {
        charCount.className = 'char-count';
        submitBtn.disabled = false;
    }
}

// Initialize character count
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('message');
    if (textarea) {
        updateCharCount(textarea);
    }
});

// Map functionality
function showMap() {
    alert('Interactive campus map would open here. This would show:\n• Administration Building location\n• Parking areas\n• Security posts\n• Meeting points for item exchanges');
    // window.open('campus_map.php', '_blank');
}

// Form validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const category = document.getElementById('category');
    const subject = document.getElementById('subject');
    const message = document.getElementById('message');
    
    if (!name.value.trim()) {
        e.preventDefault();
        alert('Please enter your name.');
        name.focus();
        return;
    }
    
    if (!email.value.trim() || !email.validity.valid) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        email.focus();
        return;
    }
    
    if (!category.value) {
        e.preventDefault();
        alert('Please select an inquiry category.');
        category.focus();
        return;
    }
    
    if (!subject.value.trim()) {
        e.preventDefault();
        alert('Please enter a subject for your message.');
        subject.focus();
        return;
    }
    
    if (!message.value.trim() || message.value.trim().length < 20) {
        e.preventDefault();
        alert('Please provide a detailed message (minimum 20 characters).');
        message.focus();
        return;
    }
});

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

// Auto-select category based on URL parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const inquiryType = urlParams.get('type');
    
    if (inquiryType && document.getElementById('category')) {
        const categorySelect = document.getElementById('category');
        const option = Array.from(categorySelect.options).find(opt => opt.value === inquiryType);
        if (option) {
            categorySelect.value = inquiryType;
        }
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

</script>

</body>
</html>