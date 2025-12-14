<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// CSRF Protection - Generate token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token for POST requests
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed in reports.php");
            die("Invalid request. Please try again.");
        }
    }
}

// Validate and sanitize input parameters
function validateReportPeriod($period) {
    $allowedPeriods = ['7', '30', '90', '365', 'custom'];
    return in_array($period, $allowedPeriods) ? $period : '30';
}

function validateReportType($type) {
    $allowedTypes = ['all', 'lost', 'found', 'claims'];
    return in_array($type, $allowedTypes) ? $type : 'all';
}

function validateDate($date) {
    if (empty($date)) return null;
    
    // Check if date is in valid format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    
    // Check if date is valid
    $dateParts = explode('-', $date);
    if (count($dateParts) !== 3) return null;
    
    list($year, $month, $day) = $dateParts;
    if (!checkdate($month, $day, $year)) {
        return null;
    }
    
    // Ensure date is not in the future
    if (strtotime($date) > time()) {
        return null;
    }
    
    return $date;
}

// Get and validate filter parameters
$reportPeriod = validateReportPeriod($_GET['period'] ?? '30');
$reportType = validateReportType($_GET['type'] ?? 'all');
$startDate = validateDate($_GET['start'] ?? '');
$endDate = validateDate($_GET['end'] ?? '');

// Validate custom date range
if ($reportPeriod === 'custom') {
    if (!$startDate || !$endDate) {
        // Default to last 30 days if invalid custom range
        $reportPeriod = '30';
        $startDate = null;
        $endDate = null;
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        // Swap dates if start is after end
        $temp = $startDate;
        $startDate = $endDate;
        $endDate = $temp;
    }
}

// Sanitize for safe display
$safeStartDate = $startDate ? htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') : '';
$safeEndDate = $endDate ? htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') : '';

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initialize parameters for prepared statements
    $params = [];
    
    // Build date conditions using prepared statements
    $dateConditionLost = "";
    $dateConditionFound = "";
    $dateConditionClaims = "";
    
    // For custom date range
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        // For COUNT queries (no alias)
        $dateConditionLost = "AND created_at BETWEEN ? AND ?";
        $dateConditionFound = "AND created_at BETWEEN ? AND ?";
        $dateConditionClaims = "AND created_at BETWEEN ? AND ?";
        
        // Add parameters
        $params['lost_start'] = $startDate . ' 00:00:00';
        $params['lost_end'] = $endDate . ' 23:59:59';
        $params['found_start'] = $startDate . ' 00:00:00';
        $params['found_end'] = $endDate . ' 23:59:59';
        $params['claims_start'] = $startDate . ' 00:00:00';
        $params['claims_end'] = $endDate . ' 23:59:59';
    } 
    // For predefined periods
    elseif ($reportPeriod !== 'custom') {
        $days = (int)$reportPeriod;
        $intervalDate = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // For COUNT queries (no alias)
        $dateConditionLost = "AND created_at >= ?";
        $dateConditionFound = "AND created_at >= ?";
        $dateConditionClaims = "AND created_at >= ?";
        
        // Add parameters
        $params['lost_interval'] = $intervalDate;
        $params['found_interval'] = $intervalDate;
        $params['claims_interval'] = $intervalDate;
    }
    // For "all time" (no date condition) - already handled by empty conditions

    // Report statistics with prepared statements
    // Lost items count
    $lostQuery = "SELECT COUNT(*) FROM lost_items WHERE 1=1 $dateConditionLost";
    $lostStmt = $pdo->prepare($lostQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $lostStmt->execute([$params['lost_start'], $params['lost_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $lostStmt->execute([$params['lost_interval']]);
    } else {
        $lostStmt->execute();
    }
    $totalLost = $lostStmt->fetchColumn();
    
    // Found items count
    $foundQuery = "SELECT COUNT(*) FROM found_items WHERE 1=1 $dateConditionFound";
    $foundStmt = $pdo->prepare($foundQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $foundStmt->execute([$params['found_start'], $params['found_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $foundStmt->execute([$params['found_interval']]);
    } else {
        $foundStmt->execute();
    }
    $totalFound = $foundStmt->fetchColumn();
    
    // Total claims count
    $claimsQuery = "SELECT COUNT(*) FROM claims WHERE 1=1 $dateConditionClaims";
    $claimsStmt = $pdo->prepare($claimsQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $claimsStmt->execute([$params['claims_start'], $params['claims_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $claimsStmt->execute([$params['claims_interval']]);
    } else {
        $claimsStmt->execute();
    }
    $totalClaims = $claimsStmt->fetchColumn();
    
    // Resolved claims count
    $resolvedQuery = "SELECT COUNT(*) FROM claims WHERE status = 'Approved' $dateConditionClaims";
    $resolvedStmt = $pdo->prepare($resolvedQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $resolvedStmt->execute([$params['claims_start'], $params['claims_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $resolvedStmt->execute([$params['claims_interval']]);
    } else {
        $resolvedStmt->execute();
    }
    $resolvedClaims = $resolvedStmt->fetchColumn();
    
    // Recent reports based on filters with prepared statements
    $recentLost = [];
    $recentFound = [];
    $recentClaims = [];
    
    if ($reportType === 'all' || $reportType === 'lost') {
        $lostQuery = "
            SELECT li.*, u.first_name, u.last_name, ic.category_name
            FROM lost_items li
            LEFT JOIN users u ON li.user_id = u.user_id
            LEFT JOIN item_categories ic ON li.category_id = ic.category_id
            WHERE 1=1 
        ";
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $lostQuery .= " AND li.created_at BETWEEN ? AND ?";
            $lostStmt = $pdo->prepare($lostQuery);
            $lostStmt->execute([$params['lost_start'], $params['lost_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $lostQuery .= " AND li.created_at >= ?";
            $lostStmt = $pdo->prepare($lostQuery);
            $lostStmt->execute([$params['lost_interval']]);
        } else {
            $lostStmt = $pdo->prepare($lostQuery);
            $lostStmt->execute();
        }
        
        $lostQuery .= " ORDER BY li.created_at DESC LIMIT 10";
        $lostStmt = $pdo->prepare($lostQuery);
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $lostStmt->execute([$params['lost_start'], $params['lost_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $lostStmt->execute([$params['lost_interval']]);
        } else {
            $lostStmt->execute();
        }
        
        $recentLost = $lostStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($reportType === 'all' || $reportType === 'found') {
        $foundQuery = "
            SELECT fi.*, u.first_name, u.last_name, ic.category_name
            FROM found_items fi
            LEFT JOIN users u ON fi.user_id = u.user_id
            LEFT JOIN item_categories ic ON fi.category_id = ic.category_id
            WHERE 1=1 
        ";
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $foundQuery .= " AND fi.created_at BETWEEN ? AND ?";
            $foundStmt = $pdo->prepare($foundQuery);
            $foundStmt->execute([$params['found_start'], $params['found_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $foundQuery .= " AND fi.created_at >= ?";
            $foundStmt = $pdo->prepare($foundQuery);
            $foundStmt->execute([$params['found_interval']]);
        } else {
            $foundStmt = $pdo->prepare($foundQuery);
            $foundStmt->execute();
        }
        
        $foundQuery .= " ORDER BY fi.created_at DESC LIMIT 10";
        $foundStmt = $pdo->prepare($foundQuery);
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $foundStmt->execute([$params['found_start'], $params['found_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $foundStmt->execute([$params['found_interval']]);
        } else {
            $foundStmt->execute();
        }
        
        $recentFound = $foundStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($reportType === 'all' || $reportType === 'claims') {
        $claimsQuery = "
            SELECT c.*, 
                   l.item_name as lost_item_name,
                   f.item_name as found_item_name,
                   u.first_name as claimant_first,
                   u.last_name as claimant_last,
                   a.first_name as approved_first,
                   a.last_name as approved_last
            FROM claims c
            LEFT JOIN lost_items l ON c.lost_id = l.lost_id
            LEFT JOIN found_items f ON c.found_id = f.found_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN users a ON c.approved_by = a.user_id
            WHERE 1=1 
        ";
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $claimsQuery .= " AND c.created_at BETWEEN ? AND ?";
            $claimsStmt = $pdo->prepare($claimsQuery);
            $claimsStmt->execute([$params['claims_start'], $params['claims_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $claimsQuery .= " AND c.created_at >= ?";
            $claimsStmt = $pdo->prepare($claimsQuery);
            $claimsStmt->execute([$params['claims_interval']]);
        } else {
            $claimsStmt = $pdo->prepare($claimsQuery);
            $claimsStmt->execute();
        }
        
        $claimsQuery .= " ORDER BY c.created_at DESC LIMIT 10";
        $claimsStmt = $pdo->prepare($claimsQuery);
        
        if ($reportPeriod === 'custom' && $startDate && $endDate) {
            $claimsStmt->execute([$params['claims_start'], $params['claims_end']]);
        } elseif ($reportPeriod !== 'custom') {
            $claimsStmt->execute([$params['claims_interval']]);
        } else {
            $claimsStmt->execute();
        }
        
        $recentClaims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get claims by status with prepared statements
    $pendingQuery = "SELECT COUNT(*) FROM claims WHERE status = 'Pending' $dateConditionClaims";
    $pendingStmt = $pdo->prepare($pendingQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $pendingStmt->execute([$params['claims_start'], $params['claims_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $pendingStmt->execute([$params['claims_interval']]);
    } else {
        $pendingStmt->execute();
    }
    $pendingClaims = $pendingStmt->fetchColumn();
    
    $approvedQuery = "SELECT COUNT(*) FROM claims WHERE status = 'Approved' $dateConditionClaims";
    $approvedStmt = $pdo->prepare($approvedQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $approvedStmt->execute([$params['claims_start'], $params['claims_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $approvedStmt->execute([$params['claims_interval']]);
    } else {
        $approvedStmt->execute();
    }
    $approvedClaims = $approvedStmt->fetchColumn();
    
    $rejectedQuery = "SELECT COUNT(*) FROM claims WHERE status = 'Rejected' $dateConditionClaims";
    $rejectedStmt = $pdo->prepare($rejectedQuery);
    if ($reportPeriod === 'custom' && $startDate && $endDate) {
        $rejectedStmt->execute([$params['claims_start'], $params['claims_end']]);
    } elseif ($reportPeriod !== 'custom') {
        $rejectedStmt->execute([$params['claims_interval']]);
    } else {
        $rejectedStmt->execute();
    }
    $rejectedClaims = $rejectedStmt->fetchColumn();

} catch(PDOException $e){
    error_log("Database error in reports.php: " . $e->getMessage());
    die("An error occurred while generating the report. Please try again.");
}

// Handle CSV Export with security checks
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Validate CSRF token for export
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for CSV export");
        header('HTTP/1.0 403 Forbidden');
        die('Invalid request.');
    }
    
    // Validate export parameters match current session filters
    $exportPeriod = validateReportPeriod($_GET['period'] ?? '30');
    $exportType = validateReportType($_GET['type'] ?? 'all');
    $exportStart = validateDate($_GET['start'] ?? '');
    $exportEnd = validateDate($_GET['end'] ?? '');
    
    // Ensure export matches current filters to prevent data leakage
    if ($exportPeriod !== $reportPeriod || 
        $exportType !== $reportType ||
        $exportStart !== $startDate || 
        $exportEnd !== $endDate) {
        error_log("Export parameters mismatch detected");
        header('HTTP/1.0 403 Forbidden');
        die('Invalid export parameters.');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Report header
    fputcsv($output, ['LoFIMS System Report']);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Report Period: ' . ($reportPeriod === 'custom' ? "$startDate to $endDate" : "Last $reportPeriod days")]);
    fputcsv($output, ['Report Type: ' . ucfirst($reportType)]);
    fputcsv($output, []); // Empty row
    
    // Summary Statistics
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Metric', 'Count']);
    fputcsv($output, ['Lost Items', $totalLost]);
    fputcsv($output, ['Found Items', $totalFound]);
    fputcsv($output, ['Total Claims', $totalClaims]);
    fputcsv($output, ['Approved Claims', $resolvedClaims]);
    fputcsv($output, ['Pending Claims', $pendingClaims]);
    fputcsv($output, ['Rejected Claims', $rejectedClaims]);
    fputcsv($output, []); // Empty row
    
    // Lost Items Report
    if ($reportType === 'all' || $reportType === 'lost') {
        fputcsv($output, ['LOST ITEMS REPORT']);
        fputcsv($output, ['Item ID', 'Item Name', 'Category', 'Reported By', 'Date Reported', 'Location', 'Status', 'Description']);
        
        foreach($recentLost as $item) {
            fputcsv($output, [
                $item['lost_id'] ?? '',
                htmlspecialchars_decode($item['item_name'] ?? '', ENT_QUOTES),
                htmlspecialchars_decode($item['category_name'] ?? 'Uncategorized', ENT_QUOTES),
                htmlspecialchars_decode(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES),
                $item['created_at'] ?? '',
                htmlspecialchars_decode($item['location_lost'] ?? '', ENT_QUOTES),
                'Lost',
                substr(htmlspecialchars_decode($item['description'] ?? '', ENT_QUOTES), 0, 200)
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Found Items Report
    if ($reportType === 'all' || $reportType === 'found') {
        fputcsv($output, ['FOUND ITEMS REPORT']);
        fputcsv($output, ['Item ID', 'Item Name', 'Category', 'Found By', 'Date Found', 'Location Found', 'Status', 'Description']);
        
        foreach($recentFound as $item) {
            fputcsv($output, [
                $item['found_id'] ?? '',
                htmlspecialchars_decode($item['item_name'] ?? '', ENT_QUOTES),
                htmlspecialchars_decode($item['category_name'] ?? 'Uncategorized', ENT_QUOTES),
                htmlspecialchars_decode(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES),
                $item['created_at'] ?? '',
                htmlspecialchars_decode($item['place_found'] ?? '', ENT_QUOTES),
                'Found',
                substr(htmlspecialchars_decode($item['description'] ?? '', ENT_QUOTES), 0, 200)
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Claims Report
    if ($reportType === 'all' || $reportType === 'claims') {
        fputcsv($output, ['CLAIMS REPORT']);
        fputcsv($output, ['Claim ID', 'Lost Item', 'Found Item', 'Claimant', 'Status', 'Date Claimed', 'Approved By', 'Notes']);
        
        foreach($recentClaims as $claim) {
            fputcsv($output, [
                $claim['claim_id'] ?? '',
                htmlspecialchars_decode($claim['lost_item_name'] ?? 'N/A', ENT_QUOTES),
                htmlspecialchars_decode($claim['found_item_name'] ?? 'N/A', ENT_QUOTES),
                htmlspecialchars_decode(($claim['claimant_first'] ?? '') . ' ' . ($claim['claimant_last'] ?? ''), ENT_QUOTES),
                $claim['status'] ?? '',
                $claim['created_at'] ?? '',
                !empty($claim['approved_first']) ? htmlspecialchars_decode($claim['approved_first'] . ' ' . $claim['approved_last'], ENT_QUOTES) : 'N/A',
                substr(htmlspecialchars_decode($claim['notes'] ?? '', ENT_QUOTES), 0, 200)
            ]);
        }
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

/* Claims Status Cards */
.claims-status-cards {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.status-card {
    flex: 1;
    background: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.status-card.pending { border-left: 4px solid #ffc107; }
.status-card.approved { border-left: 4px solid #28a745; }
.status-card.rejected { border-left: 4px solid #dc3545; }

.status-card h3 {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.status-card h2 {
    font-size: 24px;
    color: #333;
    margin: 0;
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
    min-height: 300px;
}

.report-section.full-width {
    grid-column: 1 / -1;
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

.status-badge.approved {
    background: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ddd;
}

/* ===== LOGOUT MODAL STYLES ===== */
.logout-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 420px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-origin: center;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header i {
    font-size: 24px;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.modal-body {
    padding: 30px 25px;
    text-align: center;
}

.warning-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ff9500, #ff5e3a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 8px 25px rgba(255, 94, 58, 0.3);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 12px 35px rgba(255, 94, 58, 0.4);
    }
}

.warning-icon i {
    font-size: 30px;
    color: white;
}

.modal-body p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.5;
}

.logout-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    text-align: left;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

.detail-item i {
    color: #667eea;
    width: 20px;
    text-align: center;
}

.modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

.btn-cancel, .btn-logout {
    padding: 12px 25px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-cancel {
    background: #f1f3f5;
    color: #495057;
}

.btn-cancel:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
}

.btn-logout:hover {
    background: linear-gradient(135deg, #ff2b53, #ff341b);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
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
    .report-cards {
        flex-direction: column;
    }
    .claims-status-cards {
        flex-direction: column;
    }
    .modal-footer {
        flex-direction: column;
    }
    .btn-cancel, .btn-logout {
        width: 100%;
        justify-content: center;
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
    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-group select, .filter-group input {
        width: 100%;
    }
}
</style>

</head>
<body>

<!-- Sidebar with Claims Link -->
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
        
        <li class="active">
            <i class="fas fa-chart-line"></i><span>Reports</span>
        </li>

        <li onclick="saveSidebarState(); window.location.href='manage_items.php'">
    <i class="fas fa-boxes"></i><span>Manage Items</span>
</li>
        
        <!-- ADD CLAIMS NAVIGATION LINK -->
        <li onclick="saveSidebarState(); window.location.href='claims.php'">
            <i class="fas fa-handshake"></i><span>Manage Claims</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='categories.php'">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
        
        <!-- Updated Logout Button -->
        <li id="logoutTrigger">
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
            <div class="search-results" id="searchResults"></div>
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
            echo htmlspecialchars($reportType === 'all' ? 'All Reports' : ucfirst($reportType) . ' Reports', ENT_QUOTES, 'UTF-8');
            echo ' for ';
            if($reportPeriod === 'custom') {
                echo 'Custom Range: ' . $safeStartDate . ' to ' . $safeEndDate;
            } else {
                echo 'Last ' . htmlspecialchars($reportPeriod, ENT_QUOTES, 'UTF-8') . ' days';
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
                <input type="date" id="startDate" value="<?= $safeStartDate ?>" max="<?= date('Y-m-d') ?>">
                <span>to</span>
                <input type="date" id="endDate" value="<?= $safeEndDate ?>" max="<?= date('Y-m-d') ?>">
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
            <h2 id="totalLostCount"><?= htmlspecialchars($totalLost, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Lost Items</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 id="totalFoundCount"><?= htmlspecialchars($totalFound, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Found Items</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h2 id="totalClaimsCount"><?= htmlspecialchars($totalClaims, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Total Claims</p>
            <small>In selected period</small>
        </div>
        
        <div class="report-card">
            <div class="card-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 id="resolvedClaimsCount"><?= htmlspecialchars($resolvedClaims, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Approved Claims</p>
            <small>In selected period</small>
        </div>
    </div>

    <!-- Claims Status Cards -->
    <?php if($reportType === 'all' || $reportType === 'claims'): ?>
    <div class="claims-status-cards">
        <div class="status-card pending">
            <h3><i class="fas fa-clock"></i> Pending Claims</h3>
            <h2><?= htmlspecialchars($pendingClaims, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if($pendingClaims > 0): ?>
            <small style="color:#ffc107;"><a href="claims.php?status=pending" style="color:inherit;">View pending claims</a></small>
            <?php endif; ?>
        </div>
        
        <div class="status-card approved">
            <h3><i class="fas fa-check-circle"></i> Approved Claims</h3>
            <h2><?= htmlspecialchars($approvedClaims, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        
        <div class="status-card rejected">
            <h3><i class="fas fa-times-circle"></i> Rejected Claims</h3>
            <h2><?= htmlspecialchars($rejectedClaims, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart Section -->
    <div class="chart-container">
        <h3><i class="fas fa-chart-bar"></i> System Overview</h3>
        <canvas id="reportChart" style="max-height:400px;"></canvas>
    </div>

    <!-- Recent Reports Sections -->
    <div class="report-sections">
        <!-- Lost Items Section -->
        <?php if($reportType === 'all' || $reportType === 'lost'): ?>
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
            <h4 style="color:#666; font-size:14px; margin-bottom:10px;"><?= htmlspecialchars($lostTitle, ENT_QUOTES, 'UTF-8') ?></h4>
            
            <?php if(empty($recentLost)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No lost items in selected period</p>
            </div>
            <?php else: ?>
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
                    <?php foreach($recentLost as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($item['item_name'] ?? '', 0, 30), ENT_QUOTES, 'UTF-8') . (strlen($item['item_name'] ?? '') > 30 ? '...' : '') ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('M d', strtotime($item['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-badge lost">Lost</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Found Items Section -->
        <?php if($reportType === 'all' || $reportType === 'found'): ?>
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
            <h4 style="color:#666; font-size:14px; margin-bottom:10px;"><?= htmlspecialchars($foundTitle, ENT_QUOTES, 'UTF-8') ?></h4>
            
            <?php if(empty($recentFound)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No found items in selected period</p>
            </div>
            <?php else: ?>
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
                    <?php foreach($recentFound as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($item['item_name'] ?? '', 0, 30), ENT_QUOTES, 'UTF-8') . (strlen($item['item_name'] ?? '') > 30 ? '...' : '') ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('M d', strtotime($item['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-badge found">Found</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Claims Section (Full Width) -->
        <?php if($reportType === 'all' || $reportType === 'claims'): ?>
        <div class="report-section full-width">
            <h3><i class="fas fa-handshake"></i> Recent Claims</h3>
            <?php 
            $claimsTitle = 'Recent Claims';
            if($reportPeriod === 'custom') {
                $claimsTitle .= ' (' . date('M d', strtotime($startDate)) . ' - ' . date('M d', strtotime($endDate)) . ')';
            } else {
                $claimsTitle .= ' (Last ' . $reportPeriod . ' days)';
            }
            ?>
            <h4 style="color:#666; font-size:14px; margin-bottom:10px;"><?= htmlspecialchars($claimsTitle, ENT_QUOTES, 'UTF-8') ?></h4>
            
            <?php if(empty($recentClaims)): ?>
            <div class="empty-state">
                <i class="fas fa-handshake"></i>
                <p>No claims in selected period</p>
            </div>
            <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Lost Item</th>
                        <th>Found Item</th>
                        <th>Claimant</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentClaims as $claim): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($claim['claim_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($claim['lost_item_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($claim['found_item_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(($claim['claimant_first'] ?? '') . ' ' . ($claim['claimant_last'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($claim['status'] ?? '') ?>">
                                <?= htmlspecialchars($claim['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($claim['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($claim['approved_first']) ? htmlspecialchars($claim['approved_first'] . ' ' . $claim['approved_last'], ENT_QUOTES, 'UTF-8') : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if(!empty($recentClaims)): ?>
            <div style="margin-top: 15px; text-align: center;">
                <a href="claims.php" style="color: #1e90ff; text-decoration: none;">
                    <i class="fas fa-external-link-alt"></i> Go to Claims Management
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-sign-out-alt"></i>
            <h3>Confirm Logout</h3>
        </div>
        
        <div class="modal-body">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p>Are you sure you want to logout from the admin panel?</p>
            <div class="logout-details">
                <div class="detail-item">
                    <i class="fas fa-user"></i>
                    <span>User: <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span>Time: <span id="currentTime"></span></span>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-cancel" id="cancelLogout">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-logout" id="confirmLogoutBtn">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </button>
        </div>
    </div>
</div>

<script>
// CSRF token for export
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';

// ===== LOGOUT MODAL FUNCTIONALITY =====
function initLogoutModal() {
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelLogout');
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    
    if (!logoutTrigger || !logoutModal) {
        console.log('Logout modal elements not found');
        return;
    }
    
    console.log('Initializing logout modal');
    
    // Show modal when logout is clicked
    logoutTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Logout button clicked');
        
        // Update current time
        const now = new Date();
        document.getElementById('currentTime').textContent = 
            now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        // Show modal with animation
        logoutModal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Add keyboard shortcut (Esc to close)
        document.addEventListener('keydown', handleLogoutModalKeydown);
    });
    
    // Close modal when clicking cancel
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            console.log('Cancel logout clicked');
            closeLogoutModal();
        });
    }
    
    // Close modal when clicking outside
    logoutModal.addEventListener('click', function(e) {
        if (e.target === logoutModal) {
            console.log('Clicked outside modal - closing');
            closeLogoutModal();
        }
    });
    
    // Confirm logout
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            console.log('Confirm logout clicked');
            
            // Add loading state to button
            const originalHTML = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
            confirmBtn.disabled = true;
            
            // Save sidebar state before logout
            saveSidebarState();
            
            // Redirect after short delay for visual feedback
            setTimeout(() => {
                window.location.href = 'auth/logout.php';
            }, 800);
        });
    }
    
    console.log('Logout modal initialized successfully');
}

function closeLogoutModal() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.style.display = 'none';
        document.body.style.overflow = ''; // Re-enable scrolling
        document.removeEventListener('keydown', handleLogoutModalKeydown);
    }
}

function handleLogoutModalKeydown(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
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
    
    // Initialize logout modal
    initLogoutModal();
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
    
    // Search in all tables
    const allRows = document.querySelectorAll('.report-table tbody tr');
    let foundAny = false;
    
    // Reset all rows first
    allRows.forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        row.style.display = '';
    });
    
    // If query is empty, show all
    if (query.length === 0) {
        // Remove any "no results" messages
        allRows.forEach(row => {
            if (row.querySelector('td[colspan]') && 
                row.querySelector('td[colspan]').textContent.includes('matching')) {
                row.remove();
            }
        });
        return;
    }
    
    // Search in each section
    const sections = document.querySelectorAll('.report-section');
    sections.forEach(section => {
        const rows = section.querySelectorAll('.report-table tbody tr');
        let sectionFoundAny = false;
        
        rows.forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            
            const rowText = row.textContent.toLowerCase();
            if (rowText.includes(queryLower)) {
                row.style.display = '';
                sectionFoundAny = true;
                foundAny = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle no results message for this section
        const tableBody = section.querySelector('.report-table tbody');
        const existingMessage = tableBody.querySelector('tr td[colspan]');
        
        if (!sectionFoundAny && query.length > 0) {
            if (!existingMessage || !existingMessage.textContent.includes('matching')) {
                // Remove existing non-search messages
                if (existingMessage && existingMessage.textContent.includes('No items')) {
                    existingMessage.parentNode.remove();
                }
                
                // Add search no results message
                const messageRow = document.createElement('tr');
                messageRow.innerHTML = `<td colspan="7" style="text-align:center;padding:20px;color:#666;">No items matching "${query}"</td>`;
                tableBody.appendChild(messageRow);
            }
        } else if (sectionFoundAny && query.length > 0) {
            // Remove search no results message if it exists
            if (existingMessage && existingMessage.textContent.includes('matching')) {
                existingMessage.parentNode.remove();
            }
        }
    });
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
    let queryString = `?period=${encodeURIComponent(period)}&type=${encodeURIComponent(reportType)}`;
    if (period === 'custom') {
        queryString += `&start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
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
        // Build export URL with CSRF token
        let queryString = `?export=csv&period=${encodeURIComponent(period)}&type=${encodeURIComponent(reportType)}&csrf_token=${encodeURIComponent(csrfToken)}`;
        if (period === 'custom') {
            queryString += `&start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
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

// ===== CHART FUNCTIONS =====
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
            labels: ['Lost Items', 'Found Items', 'Total Claims', 'Approved Claims'],
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
}

// Debug: Check if functions are loaded
setTimeout(function() {
    console.log('Functions check in reports.php:');
    console.log('- saveSidebarState:', typeof saveSidebarState);
    console.log('- initSearch:', typeof initSearch);
    console.log('- generateReport:', typeof generateReport);
    console.log('- exportReport:', typeof exportReport);
    console.log('- initLogoutModal:', typeof initLogoutModal);
    console.log('- closeLogoutModal:', typeof closeLogoutModal);
    
    // Check if logout button exists
    const logoutBtn = document.getElementById('logoutTrigger');
    console.log('Logout button exists:', !!logoutBtn);
    if (logoutBtn) {
        console.log('Logout button text:', logoutBtn.textContent);
    }
}, 1000);
</script>

</body>
</html>