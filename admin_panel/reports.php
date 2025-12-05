<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Get filter parameters
$reportPeriod = $_GET['period'] ?? '30';
$reportType = $_GET['type'] ?? 'all';
$startDate = $_GET['start'] ?? '';
$endDate = $_GET['end'] ?? '';

// Calculate date conditions for each table
$dateConditionLost = "";
$dateConditionFound = "";
$dateConditionClaims = "";

if ($reportPeriod === 'custom' && $startDate && $endDate) {
    $dateConditionLost = "AND li.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
    $dateConditionFound = "AND fi.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
    $dateConditionClaims = "AND c.date_claimed BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
} else {
    $days = (int)$reportPeriod;
    $dateConditionLost = "AND li.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $dateConditionFound = "AND fi.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $dateConditionClaims = "AND c.date_claimed >= DATE_SUB(NOW(), INTERVAL $days DAY)";
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Report statistics with filters - USING SPECIFIC DATE CONDITIONS FOR EACH TABLE
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items li WHERE 1=1 $dateConditionLost")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items fi WHERE 1=1 $dateConditionFound")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims c WHERE 1=1 $dateConditionClaims")->fetchColumn();
    $resolvedClaims = $pdo->query("SELECT COUNT(*) FROM claims c WHERE status = 'Resolved' $dateConditionClaims")->fetchColumn();
    
    // Recent reports based on filters
    $recentLost = [];
    $recentFound = [];
    
    if ($reportType === 'all' || $reportType === 'lost') {
        $lostQuery = "
            SELECT li.*, u.first_name, u.last_name, ic.category_name
            FROM lost_items li
            LEFT JOIN users u ON li.user_id = u.user_id
            LEFT JOIN item_categories ic ON li.category_id = ic.category_id
            WHERE 1=1 $dateConditionLost
            ORDER BY li.created_at DESC
            LIMIT 10
        ";
        $recentLost = $pdo->query($lostQuery)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($reportType === 'all' || $reportType === 'found') {
        $foundQuery = "
            SELECT fi.*, u.first_name, u.last_name, ic.category_name
            FROM found_items fi
            LEFT JOIN users u ON fi.user_id = u.user_id
            LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
            WHERE 1=1 $dateConditionFound
            ORDER BY fi.created_at DESC
            LIMIT 10
        ";
        $recentFound = $pdo->query($foundQuery)->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Lost Items Header
    fputcsv($output, ['Lost Items Report']);
    fputcsv($output, ['Item Name', 'Category', 'Reported By', 'Date Reported', 'Status', 'Description']);
    
    foreach($recentLost as $item) {
        fputcsv($output, [
            $item['item_name'],
            $item['category_name'] ?? 'Uncategorized',
            $item['first_name'] . ' ' . $item['last_name'],
            $item['created_at'],
            'Lost',
            substr($item['description'] ?? '', 0, 100)
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // Found Items Header
    fputcsv($output, ['Found Items Report']);
    fputcsv($output, ['Item Name', 'Category', 'Found By', 'Date Found', 'Status', 'Description']);
    
    foreach($recentFound as $item) {
        fputcsv($output, [
            $item['item_name'],
            $item['category_name'] ?? 'Uncategorized',
            $item['first_name'] . ' ' . $item['last_name'],
            $item['created_at'],
            'Found',
            substr($item['description'] ?? '', 0, 100)
        ]);
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== General ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f6fa;display:flex;min-height:100vh;overflow-x:hidden;color:#333;}

/* ===== Sidebar ===== */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1e2a38;color:white;display:flex;flex-direction:column;transition:0.3s;z-index:1000;box-shadow:3px 0 15px rgba(0,0,0,0.1);}
.sidebar.folded{width:70px;}
.sidebar.show{left:0;}
.sidebar.hide{left:-220px;}
.sidebar .logo{font-size:20px;font-weight:bold;text-align:center;padding:20px 0;background:#16212b;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:0.3s;}
.sidebar.folded .logo span{display:none;}
.sidebar ul{list-style:none;padding:20px 0;flex:1;}
.sidebar ul li{padding:15px 20px;cursor:pointer;position:relative;display:flex;align-items:center;border-left:3px solid transparent;transition:0.3s;}
.sidebar ul li:hover{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li.active{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li i{margin-right:15px;width:20px;text-align:center;transition:0.3s;}
.sidebar.folded ul li span{display:none;}
.sidebar ul li .tooltip{position:absolute;left:100%;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:5px 10px;border-radius:5px;font-size:14px;white-space:nowrap;display:none;z-index:1001;}
.sidebar.folded ul li:hover .tooltip{display:block;}

/* ===== Main Content ===== */
.main {
    margin-left: 220px;
    padding: 20px;
    flex: 1;
    transition: 0.3s;
    min-height: 100vh;
    max-width: calc(100% - 220px);
    width: 100%;
    overflow-x: hidden;
}

.sidebar.folded ~ .main {
    margin-left: 70px;
    max-width: calc(100% - 70px);
}

/* ===== Header ===== */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
    width: 100%;
}

.user-info {
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e2a38;
}

.user-info i {
    color: #1e90ff;
    font-size: 18px;
}

/* Search bar */
.search-bar {
    position: relative;
    width: 250px;
    max-width: 100%;
}

.search-bar input {
    width: 100%;
    padding: 8px 35px 8px 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    outline: none;
}

.search-bar i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
}

.search-results {
    position: absolute;
    top: 38px;
    left: 0;
    width: 100%;
    max-height: 300px;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(6px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    overflow-y: auto;
    display: none;
    z-index: 2000;
}

.search-results .result-item {
    padding: 10px 15px;
    cursor: pointer;
    transition: 0.3s;
}

.search-results .result-item:hover {
    background: #f0f4ff;
}

/* Page Header */
.page-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.page-header h1 {
    color: #1e2a38;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header p {
    color: #666;
    margin-top: 5px;
}

/* Filter Info */
.filter-info {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #1e90ff;
}

.filter-info span {
    font-weight: 500;
    color: #1e2a38;
}

/* Report Cards */
.report-cards {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.report-card {
    flex: 1 1 200px;
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.report-card h2 {
    font-size: 32px;
    color: #1e90ff;
    margin-bottom: 10px;
}

.report-card p {
    color: #555;
    font-weight: 500;
    margin-bottom: 10px;
}

.report-card .card-icon {
    font-size: 24px;
    margin-bottom: 15px;
    color: #1e90ff;
}

/* Report Sections */
.report-sections {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.report-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.report-section h3 {
    color: #1e2a38;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Table Styles */
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.report-table th, .report-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.report-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.report-table tr:hover {
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

.status-badge.lost {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.found {
    background: #d4edda;
    color: #155724;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.resolved {
    background: #d1ecf1;
    color: #0c5460;
}

/* Chart Container */
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.chart-container h3 {
    color: #1e2a38;
    margin-bottom: 15px;
}

/* Filter Controls */
.filter-controls {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.filter-group {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group label {
    font-weight: 500;
    color: #495057;
}

.filter-group select, .filter-group input {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    min-width: 150px;
}

.filter-btn {
    background: #1e90ff;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-btn:hover {
    background: #1c7ed6;
    transform: translateY(-2px);
}

.filter-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.filter-btn.export {
    background: #28a745;
}

.filter-btn.export:hover {
    background: #218838;
}

/* Responsive */
@media(max-width: 900px){
    .sidebar {
        left: -220px;
    }
    .sidebar.show {
        left: 0;
    }
    .main {
        margin-left: 0 !important;
        padding: 15px;
        max-width: 100% !important;
        width: 100% !important;
    }
    .report-sections {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 768px){
    .header {
        flex-wrap: wrap;
        gap: 10px;
    }
    .search-bar {
        width: 100%;
    }
    .report-cards {
        flex-direction: column;
    }
    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li onclick="saveSidebarState(); window.location.href='dashboard.php'">
            <i class="fas fa-home"></i><span>Dashboard</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='manage_user.php'">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='reports.php'">
            <i class="fas fa-chart-line"></i><span>Reports</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='categories.php'">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        <li onclick="confirmLogout()">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </li>
    </ul>
</div>

<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search reports...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
        <p>System statistics and report analysis</p>
    </div>

    <!-- Current Filter Info -->
    <?php if($reportPeriod !== '30' || $reportType !== 'all'): ?>
    <div class="filter-info">
        <i class="fas fa-filter"></i> Currently viewing: 
        <span>
            <?php 
            echo $reportType === 'all' ? 'All Reports' : ucfirst($reportType) . ' Reports';
            echo ' for ';
            if($reportPeriod === 'custom') {
                echo 'Custom Range: ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate);
            } else {
                echo 'Last ' . htmlspecialchars($reportPeriod) . ' days';
            }
            ?>
        </span>
        <a href="reports.php" style="margin-left: 10px; color: #1e90ff; text-decoration: none;">
            <i class="fas fa-times"></i> Clear filters
        </a>
    </div>
    <?php endif; ?>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-group">
            <label for="reportPeriod">Report Period:</label>
            <select id="reportPeriod" onchange="toggleCustomRange()">
                <option value="7" <?= $reportPeriod === '7' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $reportPeriod === '30' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $reportPeriod === '90' ? 'selected' : '' ?>>Last 90 days</option>
                <option value="365" <?= $reportPeriod === '365' ? 'selected' : '' ?>>Last year</option>
                <option value="custom" <?= $reportPeriod === 'custom' ? 'selected' : '' ?>>Custom Range</option>
            </select>
            
            <div id="customRange" style="display:<?= $reportPeriod === 'custom' ? 'flex' : 'none' ?>; align-items:center; gap:10px;">
                <input type="date" id="startDate" value="<?= htmlspecialchars($startDate) ?>">
                <span>to</span>
                <input type="date" id="endDate" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            
            <label for="reportType">Report Type:</label>
            <select id="reportType">
                <option value="all" <?= $reportType === 'all' ? 'selected' : '' ?>>All Reports</option>
                <option value="lost" <?= $reportType === 'lost' ? 'selected' : '' ?>>Lost Items</option>
                <option value="found" <?= $reportType === 'found' ? 'selected' : '' ?>>Found Items</option>
                <option value="claims" <?= $reportType === 'claims' ? 'selected' : '' ?>>Claims</option>
            </select>
            
            <button class="filter-btn" onclick="generateReport()" id="generateBtn">
                <i class="fas fa-filter"></i> Generate Report
            </button>
            
            <button class="filter-btn export" onclick="exportReport()" id="exportBtn">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="report-cards">
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-search"></i>
            </div>
            <h2 id="totalLostCount"><?= $totalLost ?></h2>
            <p>Lost Items</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 id="totalFoundCount"><?= $totalFound ?></h2>
            <p>Found Items</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h2 id="totalClaimsCount"><?= $totalClaims ?></h2>
            <p>Total Claims</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 id="resolvedClaimsCount"><?= $resolvedClaims ?></h2>
            <p>Resolved Claims</p>
            <small>In selected period</small>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="chart-container">
        <h3><i class="fas fa-chart-bar"></i> System Overview</h3>
        <canvas id="reportChart" style="max-height:400px;"></canvas>
    </div>

    <!-- Recent Reports -->
    <div class="report-sections">
        <div class="report-section">
            <h3><i class="fas fa-search"></i> Recent Lost Items</h3>
            <?php 
            $lostTitle = 'Recent Lost Items';
            if($reportPeriod === 'custom') {
                $lostTitle .= ' (' . date('M d', strtotime($startDate)) . ' - ' . date('M d', strtotime($endDate)) . ')';
            } else {
                $lostTitle .= ' (Last ' . $reportPeriod . ' days)';
            }
            ?>
            <h4 style="color:#666; font-size:14px; margin-bottom:10px;"><?= $lostTitle ?></h4>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Reported By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recentLost)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:20px;color:#666;">
                            <?= $reportType === 'all' || $reportType === 'lost' ? 'No lost items in selected period' : 'Lost items filtered out' ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($recentLost as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($item['item_name'], 0, 30)) . (strlen($item['item_name']) > 30 ? '...' : '') ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                        <td><?= date('M d', strtotime($item['created_at'])) ?></td>
                        <td><span class="status-badge lost">Lost</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="report-section">
            <h3><i class="fas fa-check-circle"></i> Recent Found Items</h3>
            <?php 
            $foundTitle = 'Recent Found Items';
            if($reportPeriod === 'custom') {
                $foundTitle .= ' (' . date('M d', strtotime($startDate)) . ' - ' . date('M d', strtotime($endDate)) . ')';
            } else {
                $foundTitle .= ' (Last ' . $reportPeriod . ' days)';
            }
            ?>
            <h4 style="color:#666; font-size:14px; margin-bottom:10px;"><?= $foundTitle ?></h4>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Found By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recentFound)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:20px;color:#666;">
                            <?= $reportType === 'all' || $reportType === 'found' ? 'No found items in selected period' : 'Found items filtered out' ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($recentFound as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($item['item_name'], 0, 30)) . (strlen($item['item_name']) > 30 ? '...' : '') ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                        <td><?= date('M d', strtotime($item['created_at'])) ?></td>
                        <td><span class="status-badge found">Found</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ===== BASIC LOGOUT FUNCTIONS =====
function confirmLogout() {
    if (confirm('Are you sure you want to logout? You will be redirected to home page.')) {
        saveSidebarState();
        window.location.href = 'auth/logout.php';
    }
}

function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== BASIC PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Reports: Page loaded');
    
    // Load sidebar state
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const savedState = localStorage.getItem('sidebarFolded');
            console.log('Saved sidebar state:', savedState);
            if (savedState === 'true') {
                sidebar.classList.add('folded');
            } else {
                sidebar.classList.remove('folded');
            }
        }
    }
    
    // Highlight active page
    highlightActivePage();
    
    // Basic sidebar toggle
    initSidebarToggle();
    
    // Initialize chart
    initReportChart();
    
    // Setup filter controls
    setupFilterControls();
    
    // Initialize search
    initSearch();
});

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'reports.php';
    const menuItems = document.querySelectorAll('.sidebar ul li');
    
    menuItems.forEach(item => {
        const onclick = item.getAttribute('onclick') || '';
        if (onclick.includes(currentPage)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const toggleSidebarLogo = document.getElementById('toggleSidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            if(window.innerWidth <= 900){
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('folded');
                localStorage.setItem('sidebarFolded', sidebar.classList.contains('folded'));
            }
        });
    }
    
    if (toggleSidebarLogo && sidebar) {
        toggleSidebarLogo.addEventListener('click', function() {
            if(window.innerWidth <= 900){
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('folded');
                localStorage.setItem('sidebarFolded', sidebar.classList.contains('folded'));
            }
        });
    }
    
    // Close sidebar on mobile click outside
    document.addEventListener('click', function(e) {
        if(window.innerWidth <= 900 && sidebar && !sidebar.contains(e.target)) {
            if (sidebarToggle && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
}

// ===== SEARCH FUNCTIONALITY =====
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        searchTimeout = setTimeout(() => {
            performReportSearch(query);
        }, 300);
    });
}

function performReportSearch(query) {
    const queryLower = query.toLowerCase();
    
    // Search in Lost Items table
    const lostRows = document.querySelectorAll('.report-section:first-child .report-table tbody tr');
    let lostFoundAny = false;
    
    lostRows.forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        
        const rowText = row.textContent.toLowerCase();
        if (rowText.includes(queryLower)) {
            row.style.display = '';
            lostFoundAny = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Add/remove "no results" message for lost items
    const lostTable = document.querySelector('.report-section:first-child .report-table tbody');
    const lostNoResults = lostTable.querySelector('tr td[colspan]');
    
    if (!lostFoundAny && query.length > 0) {
        if (!lostNoResults || !lostNoResults.textContent.includes('No lost items')) {
            // Remove existing message
            if (lostNoResults) lostNoResults.parentNode.remove();
            
            // Add new message
            const messageRow = document.createElement('tr');
            messageRow.innerHTML = `<td colspan="5" style="text-align:center;padding:20px;color:#666;">No lost items matching "${query}"</td>`;
            lostTable.appendChild(messageRow);
        }
    } else if (query.length === 0) {
        // Clear search - show all rows
        lostRows.forEach(row => row.style.display = '');
        if (lostNoResults && lostNoResults.textContent.includes('matching')) {
            lostNoResults.parentNode.remove();
        }
    }
    
    // Search in Found Items table
    const foundRows = document.querySelectorAll('.report-section:last-child .report-table tbody tr');
    let foundFoundAny = false;
    
    foundRows.forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        
        const rowText = row.textContent.toLowerCase();
        if (rowText.includes(queryLower)) {
            row.style.display = '';
            foundFoundAny = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Add/remove "no results" message for found items
    const foundTable = document.querySelector('.report-section:last-child .report-table tbody');
    const foundNoResults = foundTable.querySelector('tr td[colspan]');
    
    if (!foundFoundAny && query.length > 0) {
        if (!foundNoResults || !foundNoResults.textContent.includes('No found items')) {
            // Remove existing message
            if (foundNoResults) foundNoResults.parentNode.remove();
            
            // Add new message
            const messageRow = document.createElement('tr');
            messageRow.innerHTML = `<td colspan="5" style="text-align:center;padding:20px;color:#666;">No found items matching "${query}"</td>`;
            foundTable.appendChild(messageRow);
        }
    } else if (query.length === 0) {
        // Clear search - show all rows
        foundRows.forEach(row => row.style.display = '');
        if (foundNoResults && foundNoResults.textContent.includes('matching')) {
            foundNoResults.parentNode.remove();
        }
    }
}

// ===== REPORT FILTER FUNCTIONS =====
function setupFilterControls() {
    const reportPeriod = document.getElementById('reportPeriod');
    const customRange = document.getElementById('customRange');
    
    if (reportPeriod && customRange) {
        // Set initial state
        if (reportPeriod.value === 'custom') {
            customRange.style.display = 'flex';
            customRange.style.alignItems = 'center';
            customRange.style.gap = '10px';
        }
        
        reportPeriod.addEventListener('change', function() {
            if (this.value === 'custom') {
                customRange.style.display = 'flex';
                customRange.style.alignItems = 'center';
                customRange.style.gap = '10px';
            } else {
                customRange.style.display = 'none';
            }
        });
    }
    
    // Set default dates for custom range if empty
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];
    
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (startDate && endDate) {
        if (!startDate.value) startDate.value = thirtyDaysAgoStr;
        if (!endDate.value) endDate.value = today;
    }
}

function toggleCustomRange() {
    const reportPeriod = document.getElementById('reportPeriod');
    const customRange = document.getElementById('customRange');
    
    if (reportPeriod.value === 'custom') {
        customRange.style.display = 'flex';
        customRange.style.alignItems = 'center';
        customRange.style.gap = '10px';
    } else {
        customRange.style.display = 'none';
    }
}

function generateReport() {
    const period = document.getElementById('reportPeriod').value;
    const reportType = document.getElementById('reportType').value;
    let startDate = '';
    let endDate = '';
    
    if (period === 'custom') {
        startDate = document.getElementById('startDate').value;
        endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates for custom range.');
            return;
        }
        
        // Validate dates
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date.');
            return;
        }
    }
    
    // Show loading
    const generateBtn = document.getElementById('generateBtn');
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    generateBtn.disabled = true;
    
    // Build query string
    let queryString = `?period=${period}&type=${reportType}`;
    if (period === 'custom') {
        queryString += `&start=${startDate}&end=${endDate}`;
    }
    
    // Reload page with filters (this actually generates the report)
    window.location.href = `reports.php${queryString}`;
}

function exportReport() {
    const period = document.getElementById('reportPeriod').value;
    const reportType = document.getElementById('reportType').value;
    let startDate = '';
    let endDate = '';
    
    if (period === 'custom') {
        startDate = document.getElementById('startDate').value;
        endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates for custom range.');
            return;
        }
    }
    
    // Show loading
    const exportBtn = document.getElementById('exportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    exportBtn.disabled = true;
    
    try {
        // Build export URL
        let queryString = `?export=csv&period=${period}&type=${reportType}`;
        if (period === 'custom') {
            queryString += `&start=${startDate}&end=${endDate}`;
        }
        
        // Trigger download
        window.location.href = `reports.php${queryString}`;
        
        // Wait a bit before restoring button (download takes time)
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 2000);
        
    } catch (error) {
        console.error('Error exporting report:', error);
        alert('Error exporting report. Please try again.');
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }
}

// Report Chart
function initReportChart() {
    const ctx = document.getElementById('reportChart');
    if (!ctx) return;
    
    const totalLost = parseInt(document.getElementById('totalLostCount')?.textContent || 0);
    const totalFound = parseInt(document.getElementById('totalFoundCount')?.textContent || 0);
    const totalClaims = parseInt(document.getElementById('totalClaimsCount')?.textContent || 0);
    const resolvedClaims = parseInt(document.getElementById('resolvedClaimsCount')?.textContent || 0);
    
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Lost Items', 'Found Items', 'Total Claims', 'Resolved Claims'],
            datasets: [{
                label: 'System Statistics',
                data: [totalLost, totalFound, totalClaims, resolvedClaims],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'System Report Overview'
                }
            }
        }
    });
}

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in reports.php:');
    console.log('- confirmLogout:', typeof confirmLogout);
    console.log('- saveSidebarState:', typeof saveSidebarState);
    console.log('- initSearch:', typeof initSearch);
    console.log('- generateReport:', typeof generateReport);
    console.log('- exportReport:', typeof exportReport);
    console.log('- setupFilterControls:', typeof setupFilterControls);
    console.log('- toggleCustomRange:', typeof toggleCustomRange);
}, 1000);
</script>

</body>
</html>