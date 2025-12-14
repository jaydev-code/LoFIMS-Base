<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Get filter parameters
    $filterStatus = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    
    // Build query for user's claims
    $sql = "SELECT c.*, 
                   l.item_name as lost_item_name,
                   l.description as lost_description,
                   l.photo as lost_photo,
                   l.location_lost as lost_location,
                   l.date_reported as lost_date,
                   l.status as lost_status,
                   
                   f.item_name as found_item_name,
                   f.description as found_description,
                   f.photo as found_photo,
                   f.place_found as found_location,
                   f.date_found as found_date,
                   f.status as found_status,
                   
                   ic_lost.category_name as lost_category,
                   ic_found.category_name as found_category,
                   
                   CONCAT(a.first_name, ' ', a.last_name) as approver_name
            FROM claims c
            LEFT JOIN lost_items l ON c.lost_id = l.lost_id
            LEFT JOIN found_items f ON c.found_id = f.found_id
            LEFT JOIN item_categories ic_lost ON l.category_id = ic_lost.category_id
            LEFT JOIN item_categories ic_found ON f.category_id = ic_found.category_id
            LEFT JOIN users a ON c.approved_by = a.user_id
            WHERE c.user_id = ?";
    
    $params = [$_SESSION['user_id']];
    
    // Add status filter
    if ($filterStatus && $filterStatus !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $filterStatus;
    }
    
    // Add search filter
    if ($searchQuery) {
        $searchParam = "%$searchQuery%";
        $sql .= " AND (l.item_name LIKE ? OR f.item_name LIKE ? OR c.claim_id LIKE ?)";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Order by most recent first
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get claim statistics for user
    $statsQuery = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing
        FROM claims WHERE user_id = ?";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$_SESSION['user_id']]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'processing' => 0
    ];

    // Get notifications count
    $notificationsQuery = "SELECT COUNT(*) as unread_count 
                          FROM notifications 
                          WHERE user_id = ? AND is_read = 0";
    $notificationsStmt = $pdo->prepare($notificationsQuery);
    $notificationsStmt->execute([$_SESSION['user_id']]);
    $unreadCount = $notificationsStmt->fetchColumn();

} catch(PDOException $e) {
    error_log("Database error in claims.php: " . $e->getMessage());
    die("Error loading claims data. Please try again.");
}

// Check for success/error messages
$success = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Include the header and sidebar like dashboard does
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
/* Add custom styles for claims page */
.claims-container {
    padding: 20px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    cursor: pointer;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.stat-card h2 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.stat-card p {
    color: #666;
    font-weight: 500;
}

.stat-card.total { border-top: 4px solid #3498db; }
.stat-card.pending { border-top: 4px solid #f39c12; }
.stat-card.processing { border-top: 4px solid #9b59b6; }
.stat-card.approved { border-top: 4px solid #2ecc71; }
.stat-card.rejected { border-top: 4px solid #e74c3c; }

/* Filters */
.filter-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.form-group select,
.form-group input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    grid-column: 1 / -1;
}

.btn {
    padding: 8px 20px;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

/* Alerts */
.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Table */
.table-container {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.claims-table {
    width: 100%;
    border-collapse: collapse;
}

.claims-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #eee;
}

.claims-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.claims-table tr:hover {
    background: #f8f9fa;
}

/* Status Badges */
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-processing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    background: #f8f9fa;
    color: #6c757d;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: #e9ecef;
}

.btn-view:hover {
    color: #3498db;
}

.btn-delete:hover {
    color: #e74c3c;
}

/* No Data Message */
.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-data i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.3;
}

.no-data h3 {
    margin-bottom: 10px;
    color: #333;
}

.no-data p {
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<!-- Main Content -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
    <a href="profile/view.php" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 10px; cursor: pointer;">
        <i class="fas fa-user-circle"></i> 
        Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
    </a>
</div>
        <div class="search-bar" role="search">
            <input type="text" placeholder="Search claims..." id="globalSearch">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-handshake"></i>
            My Claims
        </div>

        <!-- Alerts -->
        <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="filterClaims('all')">
                <h2><?php echo $stats['total']; ?></h2>
                <p>Total Claims</p>
            </div>
            <div class="stat-card pending" onclick="filterClaims('Pending')">
                <h2><?php echo $stats['pending']; ?></h2>
                <p>Pending</p>
            </div>
            <div class="stat-card processing" onclick="filterClaims('Processing')">
                <h2><?php echo $stats['processing']; ?></h2>
                <p>Processing</p>
            </div>
            <div class="stat-card approved" onclick="filterClaims('Approved')">
                <h2><?php echo $stats['approved']; ?></h2>
                <p>Approved</p>
            </div>
            <div class="stat-card rejected" onclick="filterClaims('Rejected')">
                <h2><?php echo $stats['rejected']; ?></h2>
                <p>Rejected</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-container">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $filterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $filterStatus === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Approved" <?php echo $filterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $filterStatus === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" 
                           placeholder="Search by item name or claim ID..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.location.href='claims/add.php'">
                        <i class="fas fa-plus"></i> File New Claim
                    </button>
                </div>
            </form>
        </div>

        <!-- Claims Table -->
        <div class="table-container">
            <table class="claims-table">
                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Lost Item</th>
                        <th>Found Item</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($claims)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="no-data">
                                <i class="fas fa-inbox"></i>
                                <h3>No Claims Found</h3>
                                <?php if($filterStatus || $searchQuery): ?>
                                    <p>No claims match your filters. <a href="claims.php" style="color:#3498db;">Clear filters</a></p>
                                <?php else: ?>
                                    <p>You haven't filed any claims yet.</p>
                                <?php endif; ?>
                                <button class="btn btn-primary" onclick="window.location.href='claims/add.php'">
                                    <i class="fas fa-plus"></i> File Your First Claim
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($claims as $claim): 
                        $statusClass = strtolower($claim['status']);
                        $createdDate = date('M d, Y', strtotime($claim['created_at']));
                    ?>
                    <tr>
                        <td><strong>#<?php echo $claim['claim_id']; ?></strong></td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($claim['lost_item_name'] ?? 'N/A'); ?></strong></div>
                            <div style="font-size:12px;color:#666;">
                                <?php echo htmlspecialchars($claim['lost_category'] ?? ''); ?>
                            </div>
                        </td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($claim['found_item_name'] ?? 'N/A'); ?></strong></div>
                            <div style="font-size:12px;color:#666;">
                                <?php echo htmlspecialchars($claim['found_category'] ?? ''); ?>
                            </div>
                        </td>
                        <td><?php echo $createdDate; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                <?php echo $claim['status']; ?>
                            </span>
                            <?php if($claim['approver_name'] && $claim['status'] === 'Approved'): ?>
                            <div style="font-size:11px;color:#666;margin-top:3px;">
                                Approved by: <?php echo htmlspecialchars($claim['approver_name']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon btn-view" title="View Details" onclick="viewClaim(<?php echo $claim['claim_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if($claim['status'] === 'Pending'): ?>
                                <button class="btn-icon btn-delete" title="Cancel Claim" onclick="cancelClaim(<?php echo $claim['claim_id']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Filter functions
function filterClaims(status) {
    window.location.href = 'claims.php?status=' + encodeURIComponent(status);
}

function resetFilters() {
    window.location.href = 'claims.php';
}

// View claim details
function viewClaim(claimId) {
    window.location.href = 'claims/view.php?id=' + claimId;
}

// Cancel claim
function cancelClaim(claimId) {
    if (confirm('Are you sure you want to cancel this claim?')) {
        window.location.href = 'claims/delete.php?id=' + claimId;
    }
}

// Search functionality
document.getElementById('search').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Copy sidebar toggle functionality from dashboard
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>