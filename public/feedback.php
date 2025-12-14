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
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Get statistics for footer
try {
    $lostCount = $pdo->query("SELECT COUNT(*) as count FROM lost_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $foundCount = $pdo->query("SELECT COUNT(*) as count FROM found_items WHERE status NOT IN ('Claimed', 'Resolved')")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $categoriesCount = $pdo->query("SELECT COUNT(*) as count FROM item_categories")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $usersCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Get feedback statistics
    $totalFeedback = $pdo->query("SELECT COUNT(*) as count FROM feedback")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $avgRating = $pdo->query("SELECT AVG(rating) as avg FROM feedback WHERE rating > 0")->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    $dbError = false;
} catch (PDOException $e) {
    $lostCount = $foundCount = $categoriesCount = $usersCount = $totalFeedback = $avgRating = 0;
    $dbError = true;
}

// Process feedback submission
$feedbackMessage = '';
$feedbackSubmitted = false;
$referenceId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $category = isset($_POST['category']) ? htmlspecialchars(trim($_POST['category'])) : '';
    $comments = isset($_POST['comments']) ? htmlspecialchars(trim($_POST['comments'])) : '';
    
    // Validate feedback
    $errors = [];
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please provide a rating (1-5 stars).";
    }
    
    if (empty($category) || $category === '') {
        $errors[] = "Please select a feedback category.";
    }
    
    if (empty($comments) || strlen(trim($comments)) < 10) {
        $errors[] = "Please provide detailed comments (minimum 10 characters).";
    }
    
    if (empty($errors)) {
        try {
            // Store feedback in database - YES, just like items!
            $stmt = $pdo->prepare("
                INSERT INTO feedback 
                (user_id, rating, category, comments, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $rating,
                $category,
                $comments,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $feedbackId = $pdo->lastInsertId();
            $referenceId = 'FB-' . str_pad($feedbackId, 6, '0', STR_PAD_LEFT);
            
            $feedbackMessage = '<div class="success-message">
                <i class="fas fa-check-circle"></i> 
                <strong>Thank you! Your feedback has been saved.</strong><br>
                <small>Reference ID: ' . $referenceId . ' • Your feedback helps us improve LoFIMS!</small>
            </div>';
            
            $feedbackSubmitted = true;
            
        } catch (PDOException $e) {
            $feedbackMessage = '<div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Database Error:</strong> Could not save your feedback. Please try again later.
                <br><small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>
            </div>';
        }
    } else {
        $feedbackMessage = '<div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> 
            ' . implode('<br>', $errors) . '
        </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback - LoFIMS TUP Lopez</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

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
.logo-placeholder {
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:bold;
    color:#0a3d62;
    font-size:18px;
    cursor: pointer;
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

/* FEEDBACK STATS BANNER */
.feedback-stats {
    background: rgba(52,152,219,0.1);
    padding: 20px;
    border-radius: 15px;
    margin: 30px auto;
    max-width: 800px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #0a3d62;
    display: block;
}

.stat-label {
    font-size: 14px;
    color: #555;
    margin-top: 5px;
}

.stat-rating {
    color: #ffc107;
    font-size: 24px;
    letter-spacing: 2px;
}

/* FEEDBACK CONTAINER */
.feedback-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* MESSAGES */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    border: 2px solid #c3e6cb;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    animation: fadeIn 0.5s;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    border: 2px solid #f5c6cb;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* FEEDBACK FORM */
.feedback-form {
    background: rgba(255,255,255,0.95);
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    margin-bottom: 40px;
    transition: transform 0.3s;
}

.feedback-form:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.feedback-form h2 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.feedback-form h2::after {
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

/* RATING STARS */
.rating-container {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
}

.rating-title {
    color: #0a3d62;
    font-weight: 600;
    margin-bottom: 20px;
    font-size: 20px;
}

.star-rating {
    display: inline-block;
    direction: rtl;
    margin-bottom: 15px;
}

.star-rating input {
    display: none;
}

.star-rating label {
    font-size: 42px;
    color: #ddd;
    cursor: pointer;
    padding: 8px;
    transition: all 0.3s;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107;
    transform: scale(1.1);
}

.rating-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    color: #666;
    font-size: 14px;
    font-weight: 500;
}

/* FORM ELEMENTS */
.form-group {
    margin-bottom: 30px;
}

.form-group label {
    display: block;
    color: #0a3d62;
    font-weight: 600;
    margin-bottom: 12px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label i {
    color: #1e90ff;
}

.form-group select,
.form-group textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s;
    background: white;
}

.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.1);
}

.form-group textarea {
    height: 160px;
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
    border-radius: 10px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
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

/* DATABASE INFO SECTION */
.database-info {
    background: rgba(52,152,219,0.1);
    padding: 25px;
    border-radius: 15px;
    margin: 40px 0;
    border-left: 5px solid #1e90ff;
}

.database-info h3 {
    color: #0a3d62;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.database-info p {
    color: #555;
    line-height: 1.6;
    margin-bottom: 10px;
}

.database-info ul {
    list-style-type: none;
    padding-left: 20px;
}

.database-info li {
    color: #555;
    margin-bottom: 8px;
    padding-left: 25px;
    position: relative;
}

.database-info li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #2ecc71;
    font-weight: bold;
}

/* THANK YOU MESSAGE */
.thank-you-section {
    text-align: center;
    padding: 50px;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 20px;
    margin-top: 60px;
    position: relative;
    overflow: hidden;
}

.thank-you-section::before {
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

.thank-you-section i {
    font-size: 70px;
    color: #1e90ff;
    margin-bottom: 25px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.thank-you-section h3 {
    color: #0a3d62;
    font-size: 28px;
    margin-bottom: 20px;
}

.thank-you-section p {
    color: #555;
    font-size: 17px;
    line-height: 1.7;
    max-width: 600px;
    margin: 0 auto 20px;
}

.support-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: white;
    color: #1e90ff;
    padding: 12px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 20px;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.support-link:hover {
    background: #1e90ff;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(30,144,255,0.2);
    border-color: white;
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
    
    .feedback-form {
        padding: 25px;
    }
    
    .star-rating label {
        font-size: 32px;
        padding: 5px;
    }
    
    .feedback-stats {
        flex-direction: column;
        gap: 15px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo-placeholder" onclick="window.location.href='index.php'">
        <i class="fas fa-search" style="font-size: 50px; color: #0a3d62;"></i>
        <div style="display: flex; flex-direction: column;">
            <span style="font-weight: 800; color: #0a3d62; font-size: 24px;">LoFIMS</span>
            <span style="font-size: 13px; color: #1e90ff;">Lost & Found Management System</span>
        </div>
    </div>
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost</a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> Found</a></li>
            <li><a href="claim_item.php"><i class="fas fa-hand-holding"></i> Claims</a></li>
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

<!-- MAIN CONTENT -->
<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">Your Feedback Matters</h1>
        <p class="page-subtitle">Help us improve LoFIMS by sharing your experience with us</p>
    </div>
</div>

<!-- FEEDBACK CONTENT -->
<div class="feedback-container">
    <!-- FEEDBACK STATS BANNER -->
    <div class="feedback-stats">
        <div class="stat-item">
            <span class="stat-number"><?php echo $totalFeedback; ?></span>
            <span class="stat-label">Total Feedback</span>
        </div>
        <div class="stat-item">
            <?php if ($avgRating > 0): ?>
            <span class="stat-rating">
                <?php 
                // Show stars for average rating
                $fullStars = floor($avgRating);
                $halfStar = ($avgRating - $fullStars) >= 0.5;
                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                
                echo str_repeat('★', $fullStars);
                echo $halfStar ? '½' : '';
                echo str_repeat('☆', $emptyStars);
                ?>
            </span>
            <span class="stat-label">Average Rating (<?php echo number_format($avgRating, 1); ?>/5)</span>
            <?php else: ?>
            <span class="stat-rating">☆☆☆☆☆</span>
            <span class="stat-label">No ratings yet</span>
            <?php endif; ?>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $usersCount; ?></span>
            <span class="stat-label">Users</span>
        </div>
    </div>

    <?php if (!$feedbackSubmitted): ?>
    <!-- FEEDBACK FORM -->
    <div class="feedback-form">
        <h2>Share Your Experience</h2>
        
        <?php echo $feedbackMessage; ?>
        
        <form method="POST" action="">
            <!-- RATING -->
            <div class="rating-container">
                <div class="rating-title">How would you rate your experience with LoFIMS?</div>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required>
                    <label for="star5" title="Excellent">★</label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4" title="Very Good">★</label>
                    <input type="radio" id="star3" name="rating" value="3">
                    <label for="star3" title="Good">★</label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2" title="Fair">★</label>
                    <input type="radio" id="star1" name="rating" value="1">
                    <label for="star1" title="Poor">★</label>
                </div>
                <div class="rating-labels">
                    <span>Poor</span>
                    <span>Fair</span>
                    <span>Good</span>
                    <span>Very Good</span>
                    <span>Excellent</span>
                </div>
            </div>

            <!-- FORM FIELDS -->
            <div class="form-group">
                <label for="category"><i class="fas fa-folder"></i> Feedback Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <option value="general">General Feedback</option>
                    <option value="search">Search Function</option>
                    <option value="reporting">Item Reporting</option>
                    <option value="claims">Claim Process</option>
                    <option value="ui">User Interface</option>
                    <option value="performance">System Performance</option>
                    <option value="security">Security & Privacy</option>
                    <option value="suggestion">Feature Suggestion</option>
                    <option value="problem">Problem Report</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="comments"><i class="fas fa-comment"></i> Your Feedback *</label>
                <textarea id="comments" name="comments" required 
                          placeholder="Please share your detailed experience, suggestions for improvement, or any issues you encountered. (Minimum 10 characters)"
                          oninput="updateCharCount(this)"></textarea>
                <div id="charCount" class="char-count">0 characters</div>
            </div>

            <button type="submit" name="submit_feedback" class="submit-btn" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Feedback
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- DATABASE INFORMATION SECTION -->
    <div class="database-info">
        <h3><i class="fas fa-database"></i> How Feedback is Stored in Our Database</h3>
        <p><strong>Yes, feedback is stored in the database just like items!</strong> Here's how it works:</p>
        
        <ul>
            <li>Each feedback entry gets a unique ID (like FB-000001)</li>
            <li>Rating (1-5 stars), category, and comments are stored</li>
            <li>If you're logged in, your user ID is linked to the feedback</li>
            <li>IP address and timestamp are recorded for security</li>
            <li>Feedback can be viewed and managed by administrators</li>
            <li>Your feedback helps us improve the system for everyone</li>
        </ul>
        
        <p><strong>Example of what gets saved:</strong></p>
        <div style="background: white; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 14px; margin-top: 10px;">
            <strong>Table:</strong> feedback<br>
            <strong>Fields:</strong> feedback_id, user_id, rating, category, comments, created_at<br>
            <strong>Sample data:</strong> FB-000123, 45, 5, "ui", "Great interface! Easy to use.", "2024-03-20 14:30:00"
        </div>
    </div>

    <!-- THANK YOU MESSAGE -->
    <div class="thank-you-section">
        <i class="fas fa-heart"></i>
        <h3>We Value Your Feedback!</h3>
        <p>Every suggestion and comment helps us improve LoFIMS for the entire TUP Lopez community. We read every piece of feedback carefully and use it to make our system better for everyone.</p>
        
        <?php if ($feedbackSubmitted && !empty($referenceId)): ?>
        <div style="background: white; padding: 15px; border-radius: 10px; margin: 20px 0; border: 2px solid #1e90ff;">
            <h4 style="color: #0a3d62; margin-bottom: 10px;">Your Feedback Has Been Recorded</h4>
            <p><strong>Reference ID:</strong> <?php echo $referenceId; ?></p>
            <p><strong>Timestamp:</strong> <?php echo date('F d, Y H:i:s'); ?></p>
            <p><small>You can reference this ID if you need to follow up on your feedback.</small></p>
        </div>
        <?php endif; ?>
        
        <?php if (!$feedbackSubmitted): ?>
        <a href="contact.php" class="support-link">
            <i class="fas fa-headset"></i> Need Technical Support?
        </a>
        <?php endif; ?>
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
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Feedback</a></li>
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
                <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> Give Feedback</a></li>
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
            <i class="fas fa-chart-bar"></i> Statistics: 
            <?php echo $categoriesCount; ?> Categories • 
            <?php echo $lostCount + $foundCount; ?> Active Items • 
            <?php echo $usersCount; ?> Registered Users •
            <?php echo $totalFeedback; ?> Feedback Entries
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
    
    if (count < 10) {
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
    const textarea = document.getElementById('comments');
    if (textarea) {
        updateCharCount(textarea);
    }
});

// Star rating interaction
document.querySelectorAll('.star-rating input').forEach(star => {
    star.addEventListener('change', function() {
        const ratingValue = this.value;
        console.log('Selected rating:', ratingValue);
    });
});

// Form validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const rating = document.querySelector('input[name="rating"]:checked');
    const category = document.getElementById('category');
    const comments = document.getElementById('comments');
    
    if (!rating) {
        e.preventDefault();
        alert('Please select a rating before submitting.');
        return;
    }
    
    if (!category.value) {
        e.preventDefault();
        alert('Please select a feedback category.');
        category.focus();
        return;
    }
    
    if (comments.value.trim().length < 10) {
        e.preventDefault();
        alert('Please provide more detailed feedback (minimum 10 characters).');
        comments.focus();
        return;
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