<?php
// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use your existing config file
require_once '../config/config.php';

// Check login status
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;

// Safely get user name if logged in
if ($isLoggedIn) {
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        $firstName = $_SESSION['first_name'];
        $lastName = $_SESSION['last_name'];
        $userName = trim($firstName . ' ' . $lastName);
    } else {
        try {
            $stmt = $pdo->prepare("SELECT first_name, last_name, email, role_id FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $userName = trim($firstName . ' ' . $lastName);
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role_id'] = $user['role_id'] ?? 0;
                $isAdmin = ($user['role_id'] == 1);
            } else {
                $userName = 'User';
                $isLoggedIn = false;
                session_destroy();
            }
        } catch (PDOException $e) {
            $userName = 'User';
        }
    }
    
    if (empty($userName)) {
        $userName = $_SESSION['email'] ?? $_SESSION['username'] ?? 'User';
    }
} else {
    $userName = '';
}

// Logo path
$logoPath = '../assets/images/lofims-logo.png';
$logoExists = file_exists($logoPath);

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// ADD NEW ANNOUNCEMENT
if ($isAdmin && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        $errorMessage = "Title is required!";
    } elseif (empty($content)) {
        $errorMessage = "Content is required!";
    } else {
        try {
            // Your table uses DEFAULT_GENERATED for created_at, so we don't need to specify it
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            $successMessage = "Announcement published successfully!";
            
            // Clear form
            $_POST['title'] = $_POST['content'] = '';
        } catch (PDOException $e) {
            $errorMessage = "Error publishing announcement: " . $e->getMessage();
        }
    }
}

// DELETE ANNOUNCEMENT
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $successMessage = "Announcement deleted successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Error deleting announcement: " . $e->getMessage();
        }
    }
}

// EDIT ANNOUNCEMENT
$editMode = false;
$editId = 0;
$editTitle = '';
$editContent = '';

if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT title, content FROM announcements WHERE id = ?");
            $stmt->execute([$editId]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($announcement) {
                $editMode = true;
                $editTitle = $announcement['title'];
                $editContent = $announcement['content'];
            }
        } catch (PDOException $e) {
            $errorMessage = "Error loading announcement: " . $e->getMessage();
        }
    }
}

// UPDATE ANNOUNCEMENT
if ($isAdmin && isset($_POST['update_announcement'])) {
    $id = (int)($_POST['announcement_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        $errorMessage = "Title is required!";
    } elseif (empty($content)) {
        $errorMessage = "Content is required!";
    } elseif ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
            $successMessage = "Announcement updated successfully!";
            $editMode = false;
            $editId = 0;
        } catch (PDOException $e) {
            $errorMessage = "Error updating announcement: " . $e->getMessage();
        }
    }
}

// CANCEL EDIT
if (isset($_POST['cancel_edit'])) {
    $editMode = false;
    $editId = 0;
}

// Get all announcements (newest first) - UPDATED FOR YOUR TABLE STRUCTURE
try {
    $stmt = $pdo->query("
        SELECT id, title, content, 
               DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') as formatted_date 
        FROM announcements 
        ORDER BY created_at DESC
    ");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalAnnouncements = count($announcements);
} catch (PDOException $e) {
    $announcements = [];
    $totalAnnouncements = 0;
    if (empty($errorMessage)) {
        $errorMessage = "Error loading announcements: " . $e->getMessage();
    }
}

// Get recent announcements (last 7 days) - UPDATED FOR YOUR TABLE STRUCTURE
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM announcements 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $recentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $recentCount = 0;
}

// Get announcement statistics by month for chart
try {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM announcements 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $monthlyStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - LoFIMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" href="<?php echo $logoPath; ?>" type="image/png">
<!-- Chart.js for statistics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f5f9ff; color:#333; }

/* HEADER */
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
    flex-shrink: 0;
}
.logo-container:hover {
    transform: translateX(3px);
}
.logo-container img {
    height:55px;
    width:55px;
    border-radius: 16px;
    transition: transform 0.3s, box-shadow 0.3s;
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
}
.logo-text .logo-title {
    font-weight: 800;
    color: #0a3d62;
    font-size: 20px;
}
.logo-text .logo-subtitle {
    font-size: 11px;
    color: #1e90ff;
    font-weight: 600;
}

/* NAVIGATION */
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

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    font-size: 13px;
    color: #0a3d62;
}
.user-info i {
    color: #1e90ff;
}

.nav-btn {
    padding: 8px 18px;
    background: linear-gradient(45deg,#1e90ff,#4facfe);
    color: white;
    font-size: 13px;
    font-weight: bold;
    border-radius: 10px;
    border: 2px solid rgba(30,144,255,0.8);
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    text-decoration: none;
    display: inline-block;
}
.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    color: white;
}

/* MESSAGES */
.message {
    padding: 15px 20px;
    margin: 20px auto;
    max-width: 1200px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.5s ease-out;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.success-message {
    background: rgba(16, 185, 129, 0.1);
    color: #065f46;
    border-left: 4px solid #10b981;
}
.error-message {
    background: rgba(239, 68, 68, 0.1);
    color: #7f1d1d;
    border-left: 4px solid #ef4444;
}

/* MAIN CONTENT */
.main-container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    position: relative;
}
.page-header h1 {
    font-size: 42px;
    color: #0a3d62;
    margin-bottom: 15px;
    display: inline-block;
}
.page-header h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    border-radius: 2px;
}
.page-subtitle {
    color: #666;
    font-size: 18px;
    max-width: 800px;
    margin: 0 auto 25px;
    line-height: 1.6;
}

/* STATS CARDS */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}
.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
    border-top: 5px solid #1e90ff;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
}
.stat-number {
    font-size: 48px;
    font-weight: 900;
    color: #0a3d62;
    margin-bottom: 10px;
}
.stat-label {
    font-size: 18px;
    color: #666;
    font-weight: 500;
}
.stat-card i {
    font-size: 40px;
    color: #1e90ff;
    margin-bottom: 15px;
}

/* CHART CONTAINER */
.chart-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    margin-bottom: 40px;
}
.chart-container h2 {
    color: #0a3d62;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 24px;
}
.chart-wrapper {
    height: 300px;
    position: relative;
}

/* ANNOUNCEMENT FORM (ADMIN ONLY) */
.form-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    margin-bottom: 40px;
    border-left: 4px solid #1e90ff;
}
.form-section h2 {
    color: #0a3d62;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 24px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #0a3d62;
    font-weight: 500;
}
.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e7ff;
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.3s, box-shadow 0.3s;
}
.form-control:focus {
    outline: none;
    border-color: #1e90ff;
    box-shadow: 0 0 0 3px rgba(30,144,255,0.1);
}
textarea.form-control {
    min-height: 150px;
    resize: vertical;
}
.form-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}
.btn {
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
}
.btn-primary:hover {
    background: linear-gradient(45deg, #0d7bd4, #3a9cfc);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30,144,255,0.3);
}
.btn-secondary {
    background: #f0f4ff;
    color: #0a3d62;
    border: 2px solid #e0e7ff;
}
.btn-secondary:hover {
    background: #e0e7ff;
    transform: translateY(-2px);
}
.btn-danger {
    background: linear-gradient(45deg, #ef4444, #f87171);
    color: white;
}
.btn-danger:hover {
    background: linear-gradient(45deg, #dc2626, #ef4444);
    transform: translateY(-2px);
}

/* ANNOUNCEMENTS LIST */
.announcements-container {
    display: flex;
    flex-direction: column;
    gap: 25px;
}
.announcement-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
}
.announcement-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
}
.announcement-header {
    background: linear-gradient(90deg, #1e90ff, #4facfe);
    color: white;
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.announcement-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    flex: 1;
}
.announcement-date {
    font-size: 14px;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 8px;
}
.announcement-content {
    padding: 25px;
    line-height: 1.6;
    color: #555;
}
.announcement-actions {
    padding: 0 25px 25px;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #f0f0f0;
    padding-top: 25px;
}
.action-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}
.action-btn.edit {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.3);
}
.action-btn.edit:hover {
    background: #10b981;
    color: white;
}
.action-btn.delete {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
}
.action-btn.delete:hover {
    background: #ef4444;
    color: white;
}
.action-btn.view {
    background: rgba(30,144,255,0.1);
    color: #1e90ff;
    border: 1px solid rgba(30,144,255,0.3);
}
.action-btn.view:hover {
    background: #1e90ff;
    color: white;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
.empty-state i {
    font-size: 64px;
    color: #e0e7ff;
    margin-bottom: 20px;
}
.empty-state h3 {
    color: #0a3d62;
    margin-bottom: 10px;
    font-size: 24px;
}
.empty-state p {
    color: #666;
    max-width: 600px;
    margin: 0 auto 25px;
    line-height: 1.6;
}

/* PAGINATION */
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}
.page-item {
    padding: 10px 18px;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    color: #0a3d62;
    font-weight: 500;
    transition: all 0.3s;
    border: 2px solid #e0e7ff;
}
.page-item:hover {
    background: #f0f4ff;
    transform: translateY(-2px);
}
.page-item.active {
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    color: white;
    border-color: #1e90ff;
}

/* FOOTER */
footer {
    width:100%;
    padding:50px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
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
.copyright {
    text-align: center;
    margin-top: 35px;
    padding-top: 18px;
    border-top: 1px solid rgba(0,0,0,0.1);
    color: #0a3d62;
    font-size: 13px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    header {
        padding: 12px 20px;
    }
    nav ul {
        gap: 10px;
    }
    .announcement-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .announcement-actions {
        justify-content: center;
    }
    .form-buttons {
        flex-direction: column;
    }
    .btn {
        width: 100%;
        justify-content: center;
    }
    .stats-container {
        grid-template-columns: 1fr;
    }
    .chart-wrapper {
        height: 250px;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <a href="index.php" class="logo-container">
        <?php if ($logoExists): ?>
            <img src="<?php echo $logoPath; ?>" alt="LoFIMS Logo">
        <?php else: ?>
            <div class="logo-fallback" style="width:55px;height:55px;background:#1e90ff;border-radius:16px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">LF</div>
        <?php endif; ?>
        <div class="logo-text">
            <span class="logo-title">LoFIMS</span>
            <span class="logo-subtitle">Lost & Found Management System</span>
        </div>
    </a>
    
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost Items</a></li>
            <li><a href="found_items.php"><i class="fas fa-box"></i> Found Items</a></li>
            <li><a href="announcements.php" style="color:#1e90ff;"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            
            <?php if ($isLoggedIn): ?>
                <li class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </li>
                <?php if ($isAdmin): ?>
                    <li><a href="../admin_panel/dashboard.php" class="nav-btn">Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="nav-btn">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="nav-btn">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- MESSAGES -->
<?php if ($successMessage): ?>
<div class="message success-message">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($successMessage); ?></span>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="message error-message">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($errorMessage); ?></span>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="main-container">
    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>System Announcements</h1>
        <p class="page-subtitle">Stay updated with the latest news, system updates, and important information about the Lost and Found Management System.</p>
    </div>

    <!-- STATISTICS -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-bullhorn"></i>
            <div class="stat-number"><?php echo $totalAnnouncements; ?></div>
            <div class="stat-label">Total Announcements</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar-week"></i>
            <div class="stat-number"><?php echo $recentCount; ?></div>
            <div class="stat-label">Last 7 Days</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-eye"></i>
            <div class="stat-number"><?php echo $isLoggedIn ? 'Yes' : 'No'; ?></div>
            <div class="stat-label">You are <?php echo $isLoggedIn ? 'Logged In' : 'Not Logged In'; ?></div>
        </div>
        <div class="stat-card">
            <i class="fas fa-user-shield"></i>
            <div class="stat-number"><?php echo $isAdmin ? 'Admin' : 'User'; ?></div>
            <div class="stat-label">Access Level</div>
        </div>
    </div>

    <!-- ANNOUNCEMENTS CHART -->
    <?php if (!empty($monthlyStats)): ?>
    <div class="chart-container">
        <h2><i class="fas fa-chart-line"></i> Announcements Activity (Last 6 Months)</h2>
        <div class="chart-wrapper">
            <canvas id="announcementsChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ANNOUNCEMENT FORM (ADMIN ONLY) -->
    <?php if ($isAdmin): ?>
    <div class="form-section">
        <h2><i class="fas fa-plus-circle"></i> <?php echo $editMode ? 'Edit Announcement' : 'Create New Announcement'; ?></h2>
        
        <form method="POST" action="">
            <?php if ($editMode): ?>
                <input type="hidden" name="announcement_id" value="<?php echo $editId; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="title"><i class="fas fa-heading"></i> Announcement Title</label>
                <input type="text" id="title" name="title" class="form-control" 
                       placeholder="Enter announcement title" 
                       value="<?php echo htmlspecialchars($editMode ? $editTitle : ($_POST['title'] ?? '')); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="content"><i class="fas fa-align-left"></i> Announcement Content</label>
                <textarea id="content" name="content" class="form-control" 
                          placeholder="Enter announcement details..." 
                          required><?php echo htmlspecialchars($editMode ? $editContent : ($_POST['content'] ?? '')); ?></textarea>
                <div id="charCount" style="text-align: right; margin-top: 5px; font-size: 14px; color: #666;">0 characters</div>
            </div>
            
            <div class="form-buttons">
                <?php if ($editMode): ?>
                    <button type="submit" name="update_announcement" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Announcement
                    </button>
                    <button type="submit" name="cancel_edit" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                <?php else: ?>
                    <button type="submit" name="add_announcement" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Publish Announcement
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ANNOUNCEMENTS LIST -->
    <h2 style="color:#0a3d62; margin-bottom:20px; display:flex; align-items:center; gap:12px;">
        <i class="fas fa-list"></i> All Announcements (<?php echo $totalAnnouncements; ?>)
    </h2>
    
    <?php if ($totalAnnouncements > 0): ?>
        <div class="announcements-container">
            <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-card">
                <div class="announcement-header">
                    <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                    <div class="announcement-date">
                        <i class="far fa-clock"></i>
                        <?php echo $announcement['formatted_date']; ?>
                    </div>
                </div>
                <div class="announcement-content">
                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                </div>
                
                <?php if ($isAdmin): ?>
                <div class="announcement-actions">
                    <a href="?edit=<?php echo $announcement['id']; ?>" class="action-btn edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?delete=<?php echo $announcement['id']; ?>" 
                       class="action-btn delete" 
                       onclick="return confirm('Are you sure you want to delete this announcement?');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="far fa-newspaper"></i>
            <h3>No Announcements Yet</h3>
            <p>There are no announcements at the moment. Check back later for updates!</p>
            <?php if ($isAdmin): ?>
                <p style="color:#1e90ff; margin-top:15px;">
                    <i class="fas fa-lightbulb"></i> 
                    Tip: Use the form above to create your first announcement.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- PAGINATION -->
    <?php if ($totalAnnouncements > 0): ?>
    <div class="pagination">
        <a href="#" class="page-item active">1</a>
        <?php if ($totalAnnouncements > 5): ?>
            <a href="#" class="page-item">2</a>
            <a href="#" class="page-item">3</a>
            <a href="#" class="page-item">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- DATABASE INFO -->
    <div style="background: #f0f4ff; padding: 25px; border-radius: 15px; margin-top: 40px;">
        <h4 style="color:#0a3d62; margin-bottom:15px; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-database"></i> Database Information
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div style="background: white; padding: 15px; border-radius: 10px; border-left: 4px solid #1e90ff;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Table Name</div>
                <div style="font-weight: 600; color: #0a3d62;">announcements</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border-left: 4px solid #10b981;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Total Records</div>
                <div style="font-weight: 600; color: #0a3d62;"><?php echo $totalAnnouncements; ?></div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border-left: 4px solid #f97316;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Created At Field</div>
                <div style="font-weight: 600; color: #0a3d62;">datetime</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 10px; border-left: 4px solid #8b5cf6;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Primary Key</div>
                <div style="font-weight: 600; color: #0a3d62;">id (auto_increment)</div>
            </div>
        </div>
        <p style="color:#555; line-height:1.6; font-size:14px;">
            <strong>Table Structure:</strong> id (INT) | title (VARCHAR) | content (TEXT) | created_at (DATETIME)<br>
            <strong>Auto-generated:</strong> created_at uses DEFAULT_GENERATED for automatic timestamping
        </p>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="lost_items.php"><i class="fas fa-search"></i> Lost Items</a></li>
                <li><a href="found_items.php"><i class="fas fa-box"></i> Found Items</a></li>
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Announcements Stats</h3>
            <ul class="footer-links">
                <li><i class="fas fa-file-alt"></i> Total: <?php echo $totalAnnouncements; ?></li>
                <li><i class="fas fa-calendar-week"></i> Recent: <?php echo $recentCount; ?> (7 days)</li>
                <li><i class="fas fa-user-shield"></i> Posted by: Admins only</li>
                <li><i class="fas fa-bell"></i> Notifications: <?php echo $isLoggedIn ? 'Enabled' : 'Login Required'; ?></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Database</h3>
            <ul class="footer-links">
                <li><i class="fas fa-table"></i> Table: announcements</li>
                <li><i class="fas fa-key"></i> PK: id</li>
                <li><i class="fas fa-clock"></i> Timestamp: created_at</li>
                <li><i class="fas fa-columns"></i> Columns: 4</li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h3>Contact</h3>
            <ul class="footer-links">
                <li><i class="fas fa-envelope"></i> lofims@tuplopez.edu.ph</li>
                <li><i class="fas fa-phone"></i> (042) 555-1234</li>
                <li><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</li>
                <li><i class="fas fa-map-marker-alt"></i> TUP Lopez Quezon</li>
            </ul>
        </div>
    </div>
    
    <div class="copyright">
        &copy; 2025 LoFIMS - TUP Lopez. Announcements are managed by system administrators.<br>
        <small>Page generated: <?php echo date('F j, Y \a\t g:i A'); ?> | Table: announcements (<?php echo $totalAnnouncements; ?> records)</small>
    </div>
</footer>

<script>
// Auto-hide messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease';
            message.style.opacity = '0';
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 500);
        }, 5000);
    });
    
    // Character counter for textarea
    const textarea = document.getElementById('content');
    if (textarea) {
        const counter = document.getElementById('charCount');
        
        function updateCounter() {
            const length = textarea.value.length;
            counter.textContent = `${length} characters`;
            if (length < 50) {
                counter.style.color = '#ef4444';
            } else if (length < 100) {
                counter.style.color = '#f97316';
            } else {
                counter.style.color = '#10b981';
            }
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    }
    
    // Confirm delete
    const deleteButtons = document.querySelectorAll('.action-btn.delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Chart.js for announcements statistics
    <?php if (!empty($monthlyStats)): ?>
    const ctx = document.getElementById('announcementsChart').getContext('2d');
    
    // Prepare chart data
    const months = <?php echo json_encode(array_column($monthlyStats, 'month')); ?>;
    const counts = <?php echo json_encode(array_column($monthlyStats, 'count')); ?>;
    
    // Format month labels
    const formattedMonths = months.map(month => {
        const [year, monthNum] = month.split('-');
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
    });
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: formattedMonths,
            datasets: [{
                label: 'Number of Announcements',
                data: counts,
                backgroundColor: [
                    'rgba(30, 144, 255, 0.6)',
                    'rgba(30, 144, 255, 0.7)',
                    'rgba(30, 144, 255, 0.8)',
                    'rgba(30, 144, 255, 0.9)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(20, 124, 225, 0.8)'
                ],
                borderColor: [
                    'rgba(30, 144, 255, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(20, 124, 225, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Announcements'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Announcements: ${context.raw}`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
</body>
</html>