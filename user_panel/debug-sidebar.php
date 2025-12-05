<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<style>
.debug-info {
    background: white;
    padding: 20px;
    margin: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.sidebar-html {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 5px;
    font-family: monospace;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
</style>

<div class="main">
    <div class="header">
        <h1>Sidebar Debug</h1>
    </div>
    
    <div class="debug-info">
        <h2>Current Page: <?php echo basename($_SERVER['PHP_SELF']); ?></h2>
        
        <h3>Sidebar HTML Output:</h3>
        <div class="sidebar-html">
<?php
// Capture sidebar output
ob_start();
require_once 'includes/sidebar.php';
$sidebar_html = ob_get_clean();
echo htmlspecialchars($sidebar_html);
?>
        </div>
        
        <h3>Test Links:</h3>
        <p>
            <a href="dashboard.php">Go to Dashboard</a> | 
            <a href="lost_items.php">Go to Lost Items</a> | 
            <a href="found_items.php">Go to Found Items</a> | 
            <a href="claims.php">Go to Claims</a> | 
            <a href="announcements.php">Go to Announcements</a>
        </p>
        
        <h3>Icon Test:</h3>
        <p>
            <i class="fas fa-home"></i> Home icon<br>
            <i class="fas fa-exclamation-circle"></i> Lost icon<br>
            <i class="fas fa-check-circle"></i> Found icon<br>
            <i class="fas fa-handshake"></i> Claims icon<br>
            <i class="fas fa-bullhorn"></i> Announcements icon
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>