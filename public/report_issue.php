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

// Process issue report submission
$issueMessage = '';
$issueSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_issue'])) {
    $issueType = isset($_POST['issue_type']) ? htmlspecialchars(trim($_POST['issue_type'])) : '';
    $severity = isset($_POST['severity']) ? htmlspecialchars(trim($_POST['severity'])) : '';
    $title = isset($_POST['title']) ? htmlspecialchars(trim($_POST['title'])) : '';
    $description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description'])) : '';
    $steps = isset($_POST['steps']) ? htmlspecialchars(trim($_POST['steps'])) : '';
    $expected = isset($_POST['expected']) ? htmlspecialchars(trim($_POST['expected'])) : '';
    $actual = isset($_POST['actual']) ? htmlspecialchars(trim($_POST['actual'])) : '';
    
    // Enhanced validation
    if (!empty($issueType) && !empty($severity) && !empty($title) && !empty($description) && strlen($description) >= 30) {
        // Generate unique issue ID
        $issueId = 'ISSUE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // In a real system, save to database
        $issueMessage = '<div class="success-message">
            <i class="fas fa-check-circle"></i> 
            <div>
                <strong>Issue Report Submitted Successfully!</strong>
                <p>Your issue has been logged with reference ID: <span class="issue-id">' . $issueId . '</span></p>
                <p>Our technical team will review your report and get back to you within 24-48 hours.</p>
                <p style="font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> You can track the status of this issue in your dashboard.
                </p>
            </div>
        </div>';
        $issueSubmitted = true;
    } else {
        $issueMessage = '<div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> 
            <div>
                <strong>Please fill in all required fields.</strong>
                <p>Ensure you provide a detailed description (minimum 30 characters) and select both issue type and severity.</p>
            </div>
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Issue - LoFIMS TUP Lopez</title>
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

/* ISSUE CONTAINER */
.issue-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* MESSAGES */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 40px;
    border: 2px solid #c3e6cb;
    display: flex;
    align-items: flex-start;
    gap: 20px;
    animation: slideIn 0.5s;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 40px;
    border: 2px solid #f5c6cb;
    display: flex;
    align-items: flex-start;
    gap: 20px;
    animation: slideIn 0.5s;
}

.success-message i, .error-message i {
    font-size: 32px;
    flex-shrink: 0;
}

.success-message strong, .error-message strong {
    display: block;
    font-size: 18px;
    margin-bottom: 8px;
}

.success-message p, .error-message p {
    margin: 5px 0;
    line-height: 1.6;
}

.issue-id {
    background: #155724;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-family: monospace;
    letter-spacing: 1px;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ISSUE REPORTING GUIDELINES */
.guidelines-section {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.guidelines-section h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.guidelines-section h2::after {
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

.guidelines-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-top: 30px;
}

.guideline-card {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.guideline-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #1e90ff;
}

.guideline-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.guideline-icon i {
    font-size: 30px;
    color: white;
}

.guideline-card h3 {
    color: #0a3d62;
    font-size: 18px;
    margin-bottom: 15px;
}

.guideline-card p {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
}

/* ISSUE FORM SECTION */
.issue-form-section {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.issue-form-section h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.issue-form-section h2::after {
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

/* ISSUE FORM */
.issue-form {
    max-width: 900px;
    margin: 0 auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 25px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 25px;
    }
}

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

.required::after {
    content: ' *';
    color: #f44336;
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
    height: 150px;
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

/* SEVERITY LEVELS */
.severity-levels {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.severity-option {
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
    background: white;
}

.severity-option:hover {
    border-color: #1e90ff;
    background: #f0f8ff;
}

.severity-option.selected {
    border-color: #1e90ff;
    background: #e3f2fd;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.1);
}

.severity-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 8px;
}

.severity-header i {
    font-size: 20px;
}

.severity-option h4 {
    margin: 0;
    font-size: 14px;
    color: #0a3d62;
}

.severity-option p {
    margin: 5px 0 0;
    font-size: 12px;
    color: #666;
    line-height: 1.4;
}

/* SCREENSHOT UPLOAD */
.screenshot-upload {
    margin: 30px 0;
    padding: 25px;
    background: #f8f9fa;
    border-radius: 15px;
    border: 2px dashed #ddd;
}

.screenshot-upload label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    cursor: pointer;
    color: #555;
    font-size: 16px;
    transition: all 0.3s;
}

.screenshot-upload label:hover {
    color: #1e90ff;
}

.screenshot-upload input[type="file"] {
    display: none;
}

.screenshot-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
    display: none;
}

.screenshot-preview.active {
    display: grid;
}

.screenshot-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid #e9ecef;
}

.screenshot-item img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.remove-screenshot {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 25px;
    height: 25px;
    background: #f44336;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s;
}

.remove-screenshot:hover {
    background: #d32f2f;
    transform: scale(1.1);
}

/* SUBMIT BUTTON */
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
    margin-top: 30px;
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

/* COMMON ISSUES */
.common-issues {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.common-issues h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.common-issues h2::after {
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

.issues-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.issue-card {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 15px;
    border-left: 5px solid #1e90ff;
    transition: all 0.3s;
}

.issue-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    background: white;
}

.issue-card h3 {
    color: #0a3d62;
    font-size: 18px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.issue-card p {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.solution-btn {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.solution-btn:hover {
    background: #0d7bd4;
    transform: translateX(5px);
}

/* STATUS TRACKING */
.status-tracking {
    text-align: center;
    padding: 40px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 20px;
    margin-top: 40px;
    position: relative;
    overflow: hidden;
}

.status-tracking::before {
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

.status-tracking i {
    font-size: 60px;
    color: #1e90ff;
    margin-bottom: 20px;
}

.status-tracking h3 {
    color: #0a3d62;
    font-size: 24px;
    margin-bottom: 15px;
}

.status-tracking p {
    color: #555;
    font-size: 16px;
    margin-bottom: 25px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.track-btn {
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

.track-btn:hover {
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
    
    .guidelines-section,
    .issue-form-section,
    .common-issues {
        padding: 25px;
    }
    
    .guidelines-grid {
        grid-template-columns: 1fr;
    }
    
    .issues-grid {
        grid-template-columns: 1fr;
    }
    
    .severity-levels {
        grid-template-columns: 1fr;
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
            <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
            
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
        <h1 class="page-title">Report an Issue</h1>
        <p class="page-subtitle">Help us improve LoFIMS by reporting any problems you encounter</p>
    </div>
</div>

<!-- ISSUE CONTENT -->
<div class="issue-container">
    <!-- GUIDELINES SECTION -->
    <div class="guidelines-section">
        <h2>Before You Report</h2>
        <p style="text-align: center; color: #555; margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;">
            Please follow these guidelines to ensure we can address your issue quickly and effectively.
        </p>
        
        <div class="guidelines-grid">
            <div class="guideline-card">
                <div class="guideline-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Check Existing Issues</h3>
                <p>Search our knowledge base or check common issues below before reporting a new issue.</p>
            </div>
            
            <div class="guideline-card">
                <div class="guideline-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>Provide Details</h3>
                <p>The more information you provide, the faster we can diagnose and fix the problem.</p>
            </div>
            
            <div class="guideline-card">
                <div class="guideline-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h3>Screenshots Help</h3>
                <p>Include screenshots or screen recordings showing the issue whenever possible.</p>
            </div>
            
            <div class="guideline-card">
                <div class="guideline-icon">
                    <i class="fas fa-steps"></i>
                </div>
                <h3>Reproduction Steps</h3>
                <p>Provide clear step-by-step instructions to reproduce the issue on our end.</p>
            </div>
        </div>
    </div>

    <!-- ISSUE FORM -->
    <?php if (!$issueSubmitted): ?>
    <div class="issue-form-section">
        <h2>Report New Issue</h2>
        
        <?php echo $issueMessage; ?>
        
        <form method="POST" action="" class="issue-form" id="issueForm" enctype="multipart/form-data">
            <!-- BASIC INFORMATION -->
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="required"><i class="fas fa-user"></i> Your Name</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $isLoggedIn ? htmlspecialchars($userName) : ''; ?>"
                           <?php echo $isLoggedIn ? 'readonly' : ''; ?>
                           placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email" class="required"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $isLoggedIn ? htmlspecialchars($_SESSION['email'] ?? '') : ''; ?>"
                           <?php echo $isLoggedIn ? 'readonly' : ''; ?>
                           placeholder="your.email@example.com">
                </div>
            </div>

            <!-- ISSUE TYPE & SEVERITY -->
            <div class="form-row">
                <div class="form-group">
                    <label for="issue_type" class="required"><i class="fas fa-tag"></i> Issue Type</label>
                    <select id="issue_type" name="issue_type" required>
                        <option value="">Select issue type</option>
                        <option value="bug">Bug/Error</option>
                        <option value="feature">Feature Request</option>
                        <option value="ui">User Interface Problem</option>
                        <option value="performance">Performance Issue</option>
                        <option value="security">Security Concern</option>
                        <option value="data">Data/Information Error</option>
                        <option value="account">Account/Access Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required"><i class="fas fa-exclamation-triangle"></i> Severity Level</label>
                    <div class="severity-levels">
                        <div class="severity-option" data-value="low">
                            <div class="severity-header">
                                <i class="fas fa-info-circle" style="color: #4caf50;"></i>
                                <h4>Low</h4>
                            </div>
                            <p>Minor issue, doesn't affect functionality</p>
                        </div>
                        
                        <div class="severity-option" data-value="medium">
                            <div class="severity-header">
                                <i class="fas fa-exclamation-circle" style="color: #ff9800;"></i>
                                <h4>Medium</h4>
                            </div>
                            <p>Affects some features but workaround exists</p>
                        </div>
                        
                        <div class="severity-option" data-value="high">
                            <div class="severity-header">
                                <i class="fas fa-times-circle" style="color: #f44336;"></i>
                                <h4>High</h4>
                            </div>
                            <p>Critical issue affecting major functionality</p>
                        </div>
                        
                        <div class="severity-option" data-value="urgent">
                            <div class="severity-header">
                                <i class="fas fa-skull-crossbones" style="color: #9c27b0;"></i>
                                <h4>Urgent</h4>
                            </div>
                            <p>System down or security breach</p>
                        </div>
                    </div>
                    <input type="hidden" id="severity" name="severity" required>
                </div>
            </div>

            <!-- ISSUE DETAILS -->
            <div class="form-group">
                <label for="title" class="required"><i class="fas fa-heading"></i> Issue Title</label>
                <input type="text" id="title" name="title" required 
                       placeholder="Brief, descriptive title of the issue">
            </div>
            
            <div class="form-group">
                <label for="description" class="required"><i class="fas fa-align-left"></i> Detailed Description</label>
                <textarea id="description" name="description" required 
                          placeholder="Please describe the issue in detail. Include what you were trying to do, what happened, and what you expected to happen."
                          oninput="updateCharCount(this, 'descCount')"></textarea>
                <div id="descCount" class="char-count">0 characters</div>
            </div>

            <!-- REPRODUCTION STEPS -->
            <div class="form-group">
                <label for="steps"><i class="fas fa-list-ol"></i> Steps to Reproduce</label>
                <textarea id="steps" name="steps" 
                          placeholder="Step 1: Go to...
Step 2: Click on...
Step 3: Observe that...
(Leave blank if issue cannot be reproduced)"
                          oninput="updateCharCount(this, 'stepsCount')"></textarea>
                <div id="stepsCount" class="char-count">0 characters</div>
            </div>

            <!-- EXPECTED VS ACTUAL -->
            <div class="form-row">
                <div class="form-group">
                    <label for="expected"><i class="fas fa-check-circle"></i> Expected Behavior</label>
                    <textarea id="expected" name="expected" 
                              placeholder="What should have happened?"
                              oninput="updateCharCount(this, 'expectedCount')"></textarea>
                    <div id="expectedCount" class="char-count">0 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="actual"><i class="fas fa-times-circle"></i> Actual Behavior</label>
                    <textarea id="actual" name="actual" 
                              placeholder="What actually happened?"
                              oninput="updateCharCount(this, 'actualCount')"></textarea>
                    <div id="actualCount" class="char-count">0 characters</div>
                </div>
            </div>

            <!-- SCREENSHOTS -->
            <div class="screenshot-upload">
                <label for="screenshots">
                    <i class="fas fa-camera"></i>
                    <span>Upload Screenshots (Optional)</span>
                    <input type="file" id="screenshots" name="screenshots[]" multiple accept="image/*" onchange="previewScreenshots(this)">
                </label>
                <p style="text-align: center; margin-top: 10px; font-size: 14px; color: #666;">
                    Maximum 5 images • Max 5MB each • Supported: JPG, PNG, GIF
                </p>
                
                <div class="screenshot-preview" id="screenshotPreview"></div>
            </div>

            <!-- ADDITIONAL INFO -->
            <div class="form-group">
                <label for="additional"><i class="fas fa-info-circle"></i> Additional Information</label>
                <textarea id="additional" name="additional" 
                          placeholder="Browser version, operating system, device type, or any other relevant information"
                          oninput="updateCharCount(this, 'additionalCount')"></textarea>
                <div id="additionalCount" class="char-count">0 characters</div>
            </div>

            <button type="submit" name="submit_issue" class="submit-btn" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Issue Report
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- COMMON ISSUES -->
    <div class="common-issues">
        <h2>Common Issues & Solutions</h2>
        <p style="text-align: center; color: #555; margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;">
            Check if your issue is listed below before submitting a new report. Click "View Solution" for troubleshooting steps.
        </p>
        
        <div class="issues-grid">
            <!-- Issue 1 -->
            <div class="issue-card">
                <h3><i class="fas fa-sign-in-alt"></i> Login Problems</h3>
                <p>Unable to log in, forgotten password, or account locked.</p>
                <button class="solution-btn" onclick="showSolution('login')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
            
            <!-- Issue 2 -->
            <div class="issue-card">
                <h3><i class="fas fa-upload"></i> File Upload Errors</h3>
                <p>Photos not uploading, file size errors, or format issues.</p>
                <button class="solution-btn" onclick="showSolution('upload')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
            
            <!-- Issue 3 -->
            <div class="issue-card">
                <h3><i class="fas fa-search"></i> Search Not Working</h3>
                <p>Search returning no results or incorrect results.</p>
                <button class="solution-btn" onclick="showSolution('search')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
            
            <!-- Issue 4 -->
            <div class="issue-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Error Messages</h3>
                <p>System error messages, 404 pages, or server errors.</p>
                <button class="solution-btn" onclick="showSolution('errors')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
            
            <!-- Issue 5 -->
            <div class="issue-card">
                <h3><i class="fas fa-mobile-alt"></i> Mobile Issues</h3>
                <p>Problems on mobile devices or responsive design issues.</p>
                <button class="solution-btn" onclick="showSolution('mobile')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
            
            <!-- Issue 6 -->
            <div class="issue-card">
                <h3><i class="fas fa-envelope"></i> Notifications</h3>
                <p>Not receiving email notifications or alerts.</p>
                <button class="solution-btn" onclick="showSolution('notifications')">
                    <i class="fas fa-wrench"></i> View Solution
                </button>
            </div>
        </div>
    </div>

    <!-- STATUS TRACKING -->
    <?php if ($issueSubmitted): ?>
    <div class="status-tracking">
        <i class="fas fa-clipboard-check"></i>
        <h3>Track Your Issue Status</h3>
        <p>You can track the progress of your reported issue and receive updates through your dashboard or email notifications.</p>
        
        <?php if ($isLoggedIn): ?>
        <a href="user_panel/dashboard.php" class="track-btn">
            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
        </a>
        <?php else: ?>
        <a href="login.php" class="track-btn">
            <i class="fas fa-sign-in-alt"></i> Login to Track Issue
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="status-tracking">
        <i class="fas fa-history"></i>
        <h3>Track Existing Issues</h3>
        <p>Already submitted an issue? Track its status and check for updates from our technical team.</p>
        
        <?php if ($isLoggedIn): ?>
        <a href="user_panel/dashboard.php" class="track-btn">
            <i class="fas fa-tachometer-alt"></i> View My Issues
        </a>
        <?php else: ?>
        <a href="login.php" class="track-btn">
            <i class="fas fa-sign-in-alt"></i> Login to View Issues
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
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
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><i class="fas fa-phone"></i> (042) 555-8888</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
            </ul>
        </div>
        
        <!-- TECHNICAL -->
        <div class="footer-section">
            <h3>Technical Support</h3>
            <ul class="footer-links">
                <li><a href="report_issue.php"><i class="fas fa-bug"></i> Report Bug</a></li>
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> Technical FAQ</a></li>
                <li><a href="guide.php"><i class="fas fa-book"></i> Troubleshooting Guide</a></li>
                <li><a href="status.php"><i class="fas fa-server"></i> System Status</a></li>
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
            <i class="fas fa-wrench"></i> Technical issues? Report them using this page for prompt resolution
        </small>
    </div>
</footer>

<!-- SOLUTION MODALS -->
<div id="solutionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #0a3d62; margin: 0;" id="modalTitle"></h3>
            <button onclick="closeSolution()" style="background: none; border: none; font-size: 24px; color: #666; cursor: pointer;">&times;</button>
        </div>
        <div id="modalContent" style="color: #555; line-height: 1.6;"></div>
        <div style="margin-top: 25px; text-align: center;">
            <button onclick="closeSolution()" style="background: #1e90ff; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                Close Solution
            </button>
        </div>
    </div>
</div>

<script>
// Character count for textareas
function updateCharCount(textarea, countId) {
    const count = textarea.value.length;
    const charCount = document.getElementById(countId);
    
    charCount.textContent = `${count} characters`;
    
    if (count < 30 && textarea.id === 'description') {
        charCount.className = 'char-count error';
    } else if (count < 50) {
        charCount.className = 'char-count warning';
    } else {
        charCount.className = 'char-count';
    }
    
    validateForm();
}

// Initialize character counts
document.addEventListener('DOMContentLoaded', function() {
    const textareas = ['description', 'steps', 'expected', 'actual', 'additional'];
    textareas.forEach(id => {
        const textarea = document.getElementById(id);
        if (textarea) {
            updateCharCount(textarea, id + 'Count');
        }
    });
});

// Severity selection
document.querySelectorAll('.severity-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.severity-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        this.classList.add('selected');
        document.getElementById('severity').value = this.getAttribute('data-value');
        validateForm();
    });
});

// Screenshot preview
function previewScreenshots(input) {
    const preview = document.getElementById('screenshotPreview');
    preview.innerHTML = '';
    
    if (input.files.length > 0) {
        preview.classList.add('active');
        
        for (let i = 0; i < Math.min(input.files.length, 5); i++) {
            const file = input.files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const item = document.createElement('div');
                item.className = 'screenshot-item';
                
                item.innerHTML = `
                    <img src="${e.target.result}" alt="Screenshot ${i + 1}">
                    <div class="remove-screenshot" onclick="removeScreenshot(${i})">
                        <i class="fas fa-times"></i>
                    </div>
                `;
                
                preview.appendChild(item);
            }
            
            reader.readAsDataURL(file);
        }
        
        if (input.files.length > 5) {
            const warning = document.createElement('div');
            warning.style.gridColumn = '1 / -1';
            warning.style.textAlign = 'center';
            warning.style.color = '#f44336';
            warning.style.padding = '10px';
            warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Maximum 5 files allowed. Only first 5 shown.';
            preview.appendChild(warning);
        }
    } else {
        preview.classList.remove('active');
    }
}

// Remove screenshot
function removeScreenshot(index) {
    const input = document.getElementById('screenshots');
    const dt = new DataTransfer();
    
    for (let i = 0; i < input.files.length; i++) {
        if (i !== index) {
            dt.items.add(input.files[i]);
        }
    }
    
    input.files = dt.files;
    previewScreenshots(input);
}

// Form validation
function validateForm() {
    const submitBtn = document.getElementById('submitBtn');
    const issueType = document.getElementById('issue_type').value;
    const severity = document.getElementById('severity').value;
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    
    if (issueType && severity && title.trim() && description.trim().length >= 30) {
        submitBtn.disabled = false;
    } else {
        submitBtn.disabled = true;
    }
}

// Initialize form validation
document.addEventListener('DOMContentLoaded', function() {
    validateForm();
    
    // Add input event listeners for validation
    ['issue_type', 'severity', 'title', 'description'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', validateForm);
            element.addEventListener('change', validateForm);
        }
    });
});

// Form submission validation
document.getElementById('issueForm')?.addEventListener('submit', function(e) {
    const issueType = document.getElementById('issue_type').value;
    const severity = document.getElementById('severity').value;
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    
    if (!issueType) {
        e.preventDefault();
        alert('Please select an issue type.');
        document.getElementById('issue_type').focus();
        return;
    }
    
    if (!severity) {
        e.preventDefault();
        alert('Please select a severity level.');
        return;
    }
    
    if (!title.trim()) {
        e.preventDefault();
        alert('Please enter an issue title.');
        document.getElementById('title').focus();
        return;
    }
    
    if (!description.trim() || description.trim().length < 30) {
        e.preventDefault();
        alert('Please provide a detailed description (minimum 30 characters).');
        document.getElementById('description').focus();
        return;
    }
});

// Show solution modal
function showSolution(type) {
    const modal = document.getElementById('solutionModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    
    const solutions = {
        'login': {
            title: 'Login Problems - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">Common Login Issues and Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. Forgotten Password</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Click "Forgot Password" on the login page</li>
                        <li>Enter your registered email address</li>
                        <li>Check your email for password reset link</li>
                        <li>Create a new strong password</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Account Not Verified</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Check your email for verification link</li>
                        <li>Spam folder might contain the email</li>
                        <li>Contact admin@lofims.edu.ph for manual verification</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. Browser Issues</h5>
                    <ul style="margin-left: 20px;">
                        <li>Clear browser cache and cookies</li>
                        <li>Try different browser (Chrome, Firefox, Edge)</li>
                        <li>Disable browser extensions temporarily</li>
                        <li>Update browser to latest version</li>
                    </ul>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 20px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> Still having issues? Contact technical support at tech@lofims.edu.ph
                    </p>
                </div>
            `
        },
        'upload': {
            title: 'File Upload Errors - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">File Upload Issues and Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. File Size Limits</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Maximum file size: 5MB per image</li>
                        <li>Use image compression tools if needed</li>
                        <li>Resize large images before uploading</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Supported Formats</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>JPG/JPEG (Recommended)</li>
                        <li>PNG (For transparent backgrounds)</li>
                        <li>GIF (Animated or static)</li>
                        <li>Not supported: BMP, TIFF, WebP</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. Browser Solutions</h5>
                    <ul style="margin-left: 20px;">
                        <li>Try different browser</li>
                        <li>Disable ad blockers temporarily</li>
                        <li>Check internet connection stability</li>
                        <li>Upload one file at a time</li>
                    </ul>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 20px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> Try using our mobile app for easier photo uploads
                    </p>
                </div>
            `
        },
        'search': {
            title: 'Search Problems - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">Search Issues and Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. No Search Results</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Broaden your search terms</li>
                        <li>Remove filters and try again</li>
                        <li>Check spelling of keywords</li>
                        <li>Try different date ranges</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Advanced Search Tips</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Use quotes for exact phrases: "blue water bottle"</li>
                        <li>Use OR for multiple options: calculator OR scientific</li>
                        <li>Use - to exclude terms: umbrella -black</li>
                        <li>Search by category for better results</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. System Cache Issues</h5>
                    <ul style="margin-left: 20px;">
                        <li>Clear browser cache</li>
                        <li>Refresh the page (Ctrl+F5)</li>
                        <li>Wait a few minutes and try again</li>
                        <li>Report if issue persists for over 24 hours</li>
                    </ul>
                </div>
            `
        },
        'errors': {
            title: 'Error Messages - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">Error Message Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. 404 Page Not Found</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Check the URL for typos</li>
                        <li>Return to homepage and navigate again</li>
                        <li>Clear browser cache</li>
                        <li>Report broken links using this page</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Database Connection Errors</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Wait a few minutes and try again</li>
                        <li>Check your internet connection</li>
                        <li>Try using different device or network</li>
                        <li>Contact support if error persists</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. Server Errors (500)</h5>
                    <ul style="margin-left: 20px;">
                        <li>Refresh the page after 1 minute</li>
                        <li>Try again later if server is busy</li>
                        <li>Clear browser cache and cookies</li>
                        <li>Report persistent server errors</li>
                    </ul>
                </div>
            `
        },
        'mobile': {
            title: 'Mobile Issues - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">Mobile Device Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. Responsive Display Issues</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Rotate device to landscape mode</li>
                        <li>Update mobile browser to latest version</li>
                        <li>Zoom out if elements are overlapping</li>
                        <li>Use desktop mode in mobile browser</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Touch Screen Problems</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Use larger touch targets in settings</li>
                        <li>Enable "Request Desktop Site" in browser</li>
                        <li>Clean screen for better touch response</li>
                        <li>Restart mobile device</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. Mobile Upload Issues</h5>
                    <ul style="margin-left: 20px;">
                        <li>Allow camera/gallery access permissions</li>
                        <li>Use camera instead of gallery for direct upload</li>
                        <li>Check mobile storage space</li>
                        <li>Use WiFi for large file uploads</li>
                    </ul>
                </div>
            `
        },
        'notifications': {
            title: 'Notification Issues - Solutions',
            content: `
                <h4 style="color: #0a3d62; margin-bottom: 15px;">Notification Solutions:</h4>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">1. Email Notifications</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Check spam/junk folder</li>
                        <li>Add lofims.system@gmail.com to contacts</li>
                        <li>Verify email address in account settings</li>
                        <li>Check notification preferences in dashboard</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">2. Browser Notifications</h5>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Allow browser notifications when prompted</li>
                        <li>Check browser notification settings</li>
                        <li>Clear browser cache if notifications blocked</li>
                        <li>Try different browser for testing</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #1e90ff; margin-bottom: 10px;">3. System Preferences</h5>
                    <ul style="margin-left: 20px;">
                        <li>Go to Dashboard → Settings → Notifications</li>
                        <li>Enable email and system notifications</li>
                        <li>Check notification frequency settings</li>
                        <li>Verify contact information is correct</li>
                    </ul>
                </div>
            `
        }
    };
    
    if (solutions[type]) {
        title.textContent = solutions[type].title;
        content.innerHTML = solutions[type].content;
        modal.style.display = 'flex';
    }
}

// Close solution modal
function closeSolution() {
    document.getElementById('solutionModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('solutionModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSolution();
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

// Auto-fill form from URL parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const issueType = urlParams.get('type');
    
    if (issueType && document.getElementById('issue_type')) {
        const typeSelect = document.getElementById('issue_type');
        const option = Array.from(typeSelect.options).find(opt => opt.value === issueType);
        if (option) {
            typeSelect.value = issueType;
            validateForm();
        }
    }
});

// Detect browser info for auto-fill
function detectBrowserInfo() {
    const browserInfo = {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        language: navigator.language
    };
    
    // Auto-fill browser info in additional info if empty
    const additional = document.getElementById('additional');
    if (additional && !additional.value.trim()) {
        let browserName = 'Unknown';
        
        if (navigator.userAgent.includes('Chrome')) browserName = 'Chrome';
        else if (navigator.userAgent.includes('Firefox')) browserName = 'Firefox';
        else if (navigator.userAgent.includes('Safari')) browserName = 'Safari';
        else if (navigator.userAgent.includes('Edge')) browserName = 'Edge';
        
        additional.value = `Browser: ${browserName}\nOS: ${navigator.platform}\nUser Agent: ${navigator.userAgent}`;
        updateCharCount(additional, 'additionalCount');
    }
}

// Run browser detection on page load
window.addEventListener('load', detectBrowserInfo);

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