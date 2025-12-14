<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/notifications.php';
// Only admin can access
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
            error_log("CSRF token validation failed in claims.php");
            die("Invalid request. Please try again.");
        }
    }
}

// Validation functions
function validateClaimStatus($status) {
    $allowedStatuses = ['', 'all', 'Pending', 'Approved', 'Rejected'];
    return in_array($status, $allowedStatuses) ? $status : '';
}

function validateDateFilter($date) {
    $allowedDates = ['', 'today', 'week', 'month'];
    return in_array($date, $allowedDates) ? $date : '';
}

function sanitizeSearchQuery($query) {
    $query = trim($query ?? '');
    $query = strip_tags($query);
    $query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
    return substr($query, 0, 100);
}

function validateClaimId($id) {
    $id = (int)($id ?? 0);
    return $id > 0 ? $id : 0;
}

function validateAdminNotes($notes) {
    $notes = trim($notes ?? '');
    $notes = strip_tags($notes);
    $notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
    return substr($notes, 0, 500);
}

function validateBulkAction($action) {
    $allowedActions = ['approve', 'reject', 'delete'];
    return in_array($action, $allowedActions) ? $action : '';
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        error_log("Admin not found in claims.php - user_id: " . $_SESSION['user_id']);
        die("Administrator account not found.");
    }

    // Get and validate filter parameters
    $filterStatus = validateClaimStatus($_GET['status'] ?? '');
    $filterDate = validateDateFilter($_GET['date'] ?? '');
    $searchQuery = sanitizeSearchQuery($_GET['search'] ?? '');

    // For display in HTML (safe version)
    $safeSearchQuery = htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8');

    // Build base query with prepared statements
    $sql = "SELECT c.*, 
                   l.item_name as lost_item_name,
                   l.description as lost_description,
                   l.photo as lost_photo,
                   l.location_lost as lost_location,
                   l.date_reported as lost_date,
                   CONCAT(lu.first_name, ' ', lu.last_name) as lost_reporter_name,
                   lu.email as lost_reporter_email,
                   lu.contact_number as lost_reporter_phone,
                   lu.student_id as lost_reporter_student_id,
                   lu.course as lost_reporter_course,
                   lu.year as lost_reporter_year,
                   
                   f.item_name as found_item_name,
                   f.description as found_description,
                   f.photo as found_photo,
                   f.place_found as found_location,
                   f.date_found as found_date,
                   CONCAT(fu.first_name, ' ', fu.last_name) as finder_name,
                   fu.email as finder_email,
                   fu.contact_number as finder_phone,
                   fu.student_id as finder_student_id,
                   fu.course as finder_course,
                   fu.year as finder_year,
                   
                   CONCAT(u.first_name, ' ', u.last_name) as claimant_name,
                   u.email as claimant_email,
                   u.contact_number as claimant_phone,
                   u.student_id as claimant_student_id,
                   u.course as claimant_course,
                   u.year as claimant_year,
                   
                   CONCAT(a.first_name, ' ', a.last_name) as approver_name,
                   a.email as approver_email,
                   
                   ic_lost.category_name as lost_category,
                   ic_found.category_name as found_category
            FROM claims c
            LEFT JOIN lost_items l ON c.lost_id = l.lost_id
            LEFT JOIN found_items f ON c.found_id = f.found_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN users lu ON l.user_id = lu.user_id
            LEFT JOIN users fu ON f.user_id = fu.user_id
            LEFT JOIN users a ON c.approved_by = a.user_id
            LEFT JOIN item_categories ic_lost ON l.category_id = ic_lost.category_id
            LEFT JOIN item_categories ic_found ON f.category_id = ic_found.category_id
            WHERE 1=1";

    $params = [];
    $conditions = [];

    // Add status filter
    if (!empty($filterStatus) && $filterStatus !== 'all') {
        $conditions[] = "c.status = ?";
        $params[] = $filterStatus;
    }

    // Add date filter using prepared statements
    if (!empty($filterDate)) {
        switch ($filterDate) {
            case 'today':
                $conditions[] = "DATE(c.created_at) = CURDATE()";
                break;
            case 'week':
                $interval = date('Y-m-d H:i:s', strtotime('-7 days'));
                $conditions[] = "c.created_at >= ?";
                $params[] = $interval;
                break;
            case 'month':
                $interval = date('Y-m-d H:i:s', strtotime('-30 days'));
                $conditions[] = "c.created_at >= ?";
                $params[] = $interval;
                break;
        }
    }

    // Add search filter
    if (!empty($searchQuery)) {
        $searchParam = "%$searchQuery%";
        $conditions[] = "(c.claim_id LIKE ? 
                          OR l.item_name LIKE ? 
                          OR f.item_name LIKE ? 
                          OR u.first_name LIKE ? 
                          OR u.last_name LIKE ? 
                          OR lu.first_name LIKE ?
                          OR lu.last_name LIKE ?
                          OR fu.first_name LIKE ?
                          OR fu.last_name LIKE ?)";
        $params = array_merge($params, 
            array_fill(0, 9, $searchParam));
    }

    // Add conditions to SQL
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    // Order by (Pending first, then by date)
    $sql .= " ORDER BY 
                CASE 
                    WHEN c.status = 'Pending' THEN 1
                    WHEN c.status = 'Approved' THEN 2
                    WHEN c.status = 'Rejected' THEN 3
                    ELSE 4
                END,
                c.created_at DESC";

    // Get all claims with prepared statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics with single query
    $statsQuery = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM claims";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'today' => 0
    ];

    // Get Pending Claims count
    $pendingClaims = $stats['pending'] ?? 0;

} catch(PDOException $e){
    error_log("Database error in claims.php: " . $e->getMessage());
    die("An error occurred while loading claims data. Please try again later.");
}

// ===== HANDLE CLAIM APPROVAL/REJECTION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    // Handle single claim update
    if (isset($_POST['update_claim'])) {
        $claimId = validateClaimId($_POST['claim_id'] ?? 0);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $adminNotes = validateAdminNotes($_POST['admin_notes'] ?? '');
        
        // Validate status
        $allowedStatuses = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($status, $allowedStatuses)) {
            $_SESSION['error_message'] = "Invalid status selected!";
            header("Location: claims.php");
            exit();
        }
        
        // Require notes for rejections
        if ($status === 'Rejected' && strlen($adminNotes) < 10) {
            $_SESSION['error_message'] = "Please provide a reason (minimum 10 characters) for rejecting this claim.";
            header("Location: claims.php");
            exit();
        }
        
        if ($claimId <= 0) {
            $_SESSION['error_message'] = "Invalid claim ID!";
            header("Location: claims.php");
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Check if claim exists and user has permission
            $checkStmt = $pdo->prepare("SELECT claim_id FROM claims WHERE claim_id = ?");
            $checkStmt->execute([$claimId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("Claim not found");
            }
            
            // Prepare the notes
            $timestamp = date('Y-m-d H:i:s');
            $adminName = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8');
            $newNote = "\n\n[$timestamp] Status changed to $status by Admin ($adminName).\n";
            if (!empty($adminNotes)) {
                $newNote .= "Notes: " . htmlspecialchars($adminNotes, ENT_QUOTES, 'UTF-8') . "\n";
            }
            
            // Update claim
            $updateSql = "UPDATE claims SET 
                          status = ?, 
                          notes = CONCAT(IFNULL(notes, ''), ?), 
                          approved_by = ?, 
                          date_claimed = ?
                          WHERE claim_id = ?";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $status,
                $newNote,
                $_SESSION['user_id'],
                $status === 'Approved' ? $timestamp : null,
                $claimId
            ]);
            
            // If approved, update item statuses
            if ($status === 'Approved') {
                // Get lost and found item IDs
                $itemStmt = $pdo->prepare("SELECT lost_id, found_id, user_id FROM claims WHERE claim_id = ?");
                $itemStmt->execute([$claimId]);
                $claimData = $itemStmt->fetch();
                
                if ($claimData && $claimData['lost_id']) {
                    $pdo->prepare("UPDATE lost_items SET status = 'Claimed' WHERE lost_id = ?")
                        ->execute([$claimData['lost_id']]);
                }
                
                if ($claimData && $claimData['found_id']) {
                    $pdo->prepare("UPDATE found_items SET status = 'Claimed' WHERE found_id = ?")
                        ->execute([$claimData['found_id']]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Claim #$claimId has been $status successfully!";
            header("Location: claims.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error updating claim #$claimId: " . $e->getMessage());
            $_SESSION['error_message'] = "Error updating claim: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header("Location: claims.php");
            exit();
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action'])) {
        $action = validateBulkAction($_POST['bulk_action'] ?? '');
        $selectedClaims = $_POST['selected_claims'] ?? [];
        
        if (!$action) {
            $_SESSION['error_message'] = "Invalid bulk action!";
            header("Location: claims.php");
            exit();
        }
        
        if (empty($selectedClaims)) {
            $_SESSION['error_message'] = "Please select at least one claim.";
            header("Location: claims.php");
            exit();
        }
        
        // Validate all claim IDs are integers
        $validClaimIds = [];
        foreach ($selectedClaims as $claimId) {
            $id = validateClaimId($claimId);
            if ($id > 0) {
                $validClaimIds[] = $id;
            }
        }
        
        if (empty($validClaimIds)) {
            $_SESSION['error_message'] = "No valid claims selected.";
            header("Location: claims.php");
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            $successCount = 0;
            $failedCount = 0;
            
            foreach ($validClaimIds as $claimId) {
                try {
                    // Verify claim exists before processing
                    $checkStmt = $pdo->prepare("SELECT claim_id FROM claims WHERE claim_id = ?");
                    $checkStmt->execute([$claimId]);
                    if (!$checkStmt->fetch()) {
                        $failedCount++;
                        continue;
                    }
                    
                    switch ($action) {
                        case 'approve':
                            $stmt = $pdo->prepare("
                                UPDATE claims SET 
                                status = 'Approved', 
                                approved_by = ?, 
                                date_claimed = NOW(),
                                notes = CONCAT(IFNULL(notes, ''), '\n\n[', NOW(), '] Status changed to Approved by Admin (Bulk Action).')
                                WHERE claim_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id'], $claimId]);
                            
                            // Update item statuses
                            $itemStmt = $pdo->prepare("SELECT lost_id, found_id FROM claims WHERE claim_id = ?");
                            $itemStmt->execute([$claimId]);
                            $items = $itemStmt->fetch();
                            
                            if ($items && $items['lost_id']) {
                                $pdo->prepare("UPDATE lost_items SET status = 'Claimed' WHERE lost_id = ?")
                                    ->execute([$items['lost_id']]);
                            }
                            
                            if ($items && $items['found_id']) {
                                $pdo->prepare("UPDATE found_items SET status = 'Claimed' WHERE found_id = ?")
                                    ->execute([$items['found_id']]);
                            }
                            $successCount++;
                            break;
                            
                        case 'reject':
                            $stmt = $pdo->prepare("
                                UPDATE claims SET 
                                status = 'Rejected', 
                                notes = CONCAT(IFNULL(notes, ''), '\n\n[', NOW(), '] Status changed to Rejected by Admin (Bulk Action).')
                                WHERE claim_id = ?
                            ");
                            $stmt->execute([$claimId]);
                            $successCount++;
                            break;
                            
                        case 'delete':
                            $stmt = $pdo->prepare("DELETE FROM claims WHERE claim_id = ?");
                            $stmt->execute([$claimId]);
                            $successCount++;
                            break;
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    error_log("Error processing claim #$claimId: " . $e->getMessage());
                }
            }
            
            $pdo->commit();
            
            if ($successCount > 0) {
                $_SESSION['success_message'] = "Processed $successCount claim(s) successfully";
                if ($failedCount > 0) {
                    $_SESSION['success_message'] .= ", $failedCount claim(s) failed";
                }
                $_SESSION['success_message'] .= "!";
            } else {
                $_SESSION['error_message'] = "No claims were processed. Please try again.";
            }
            
            header("Location: claims.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing bulk action: " . $e->getMessage());
            $_SESSION['error_message'] = "Error processing bulk action: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header("Location: claims.php");
            exit();
        }
    }
}

// Handle single delete via GET with CSRF protection
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid security token!";
        header("Location: claims.php");
        exit();
    }
    
    $claimId = validateClaimId($_GET['delete']);
    
    if ($claimId <= 0) {
        $_SESSION['error_message'] = "Invalid claim ID!";
        header("Location: claims.php");
        exit();
    }
    
    try {
        // Verify claim exists before deletion
        $checkStmt = $pdo->prepare("SELECT claim_id FROM claims WHERE claim_id = ?");
        $checkStmt->execute([$claimId]);
        if (!$checkStmt->fetch()) {
            $_SESSION['error_message'] = "Claim not found!";
            header("Location: claims.php");
            exit();
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM claims WHERE claim_id = ?");
        $deleteStmt->execute([$claimId]);
        
        $_SESSION['success_message'] = "Claim #$claimId deleted successfully!";
        header("Location: claims.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Error deleting claim #$claimId: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting claim: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: claims.php");
        exit();
    }
}

// Check for messages
$success = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $success = htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Claims - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

/* ===== Main ===== */
.main{margin-left:220px;padding:20px;flex:1;transition:0.3s;min-height:100vh;max-width:calc(100% - 220px);width:100%;}
.sidebar.folded ~ .main{margin-left:70px;max-width:calc(100% - 70px);}

/* ===== Header ===== */
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);position:sticky;top:0;z-index:100;}
.user-info{font-weight:bold;display:flex;align-items:center;gap:10px;color:#1e2a38;}
.user-info i{color:#1e90ff;font-size:18px;}

/* Page Header */
.page-header{background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.page-header h1{color:#1e2a38;font-size:28px;display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.page-header p{color:#666;}

/* Dashboard Boxes */
.dashboard-boxes{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.box{flex:1 1 150px;min-width:120px;background:white;padding:30px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition:transform 0.3s,box-shadow 0.3s;cursor:pointer;}
.box:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.box h2{font-size:36px;color:#1e90ff;margin-bottom:10px;text-shadow:1px 1px 2px rgba(0,0,0,0.1);}
.box p{font-size:18px;color:#555;font-weight:500;}
.box.warning{border-left:4px solid #ffc107;background:#fff9e6;}
.box.warning h2{color:#ffc107;}

/* Filter Container */
.filter-container{background:white;padding:20px;border-radius:12px;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;align-items:end;}
.filter-group{display:flex;flex-direction:column;gap:5px;}
.filter-group label{font-size:12px;font-weight:600;color:#495057;text-transform:uppercase;}
.filter-group select,.filter-group input{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;}
.filter-actions{display:flex;gap:10px;grid-column:1/-1;}
.filter-btn{padding:8px 20px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;transition:0.3s;}
.filter-btn.apply{background:#1e90ff;color:white;border:none;}
.filter-btn.apply:hover{background:#1c7ed6;}
.filter-btn.reset{background:#f8f9fa;color:#495057;border:1px solid #ddd;}
.filter-btn.reset:hover{background:#e9ecef;}

/* Table Styles */
.table-container{background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.claims-table{width:100%;border-collapse:collapse;}
.claims-table th,.claims-table td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;}
.claims-table th{background:#f8f9fa;font-weight:600;color:#495057;}
.claims-table tr:hover{background:#f8f9fa;}

/* Status Badges */
.status-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.status-badge.pending{background:#fff3cd;color:#856404;}
.status-badge.approved{background:#d4edda;color:#155724;}
.status-badge.rejected{background:#f8d7da;color:#721c24;}

/* Action Buttons */
.action-buttons{display:flex;gap:5px;}
.btn-icon{background:none;border:none;color:#6c757d;cursor:pointer;padding:5px;border-radius:4px;transition:0.2s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;}
.btn-icon:hover{background:#f8f9fa;color:#495057;}
.btn-icon.view:hover{color:#1e90ff;}
.btn-icon.approve:hover{color:#28a745;}
.btn-icon.reject:hover{color:#dc3545;}
.btn-icon.delete:hover{color:#dc3545;}

/* Modals for claims */
#claimModal,
#updateModal,
#imageModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 3000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* Claim details modal */
#claimModal .modal-content,
#updateModal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

#claimModal .modal-content h2,
#updateModal .modal-content h2 {
    color: #1e2a38;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

/* Claim Details Styles */
.claim-details-grid{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.detail-section{
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border-left: 4px solid #1e90ff;
}

.detail-section h3{
    color: #1e2a38;
    margin-bottom: 15px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-row{
    margin-bottom: 10px;
    display: flex;
    flex-wrap: wrap;
}

.detail-label{
    font-weight: 600;
    color: #495057;
    min-width: 120px;
    margin-bottom: 5px;
}

.detail-value{
    color: #333;
    flex: 1;
    word-break: break-word;
}

/* Photo display in details */
.item-photo-container {
    margin-top: 10px;
}

.item-photo {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid #ddd;
    cursor: pointer;
    transition: transform 0.3s;
}

.item-photo:hover {
    transform: scale(1.02);
    border-color: #1e90ff;
}

.photo-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    display: block;
}

.photo-link {
    font-size: 12px;
    color: #1e90ff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 5px;
}

.photo-link:hover {
    text-decoration: underline;
}

/* Photo comparison section */
.photo-comparison {
    margin: 25px 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea0d, #764ba20d);
    border-radius: 10px;
    border: 2px dashed #dee2e6;
}

.photo-comparison h4 {
    color: #495057;
    margin-bottom: 15px;
    text-align: center;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.comparison-item {
    text-align: center;
}

.comparison-photo {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s;
}

.comparison-photo:hover {
    transform: translateY(-5px);
    border-color: #1e90ff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.comparison-label {
    margin-top: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

/* Image Modal */
#imageModal .modal-content {
    background: transparent;
    border: none;
    max-width: 95vw;
    max-height: 95vh;
    position: relative;
}

#imageModal img {
    max-width: 100%;
    max-height: 85vh;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.image-modal-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: white;
    color: #333;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.image-modal-close:hover {
    background: #f8f9fa;
    transform: scale(1.1);
}

.image-modal-title {
    position: absolute;
    bottom: -40px;
    left: 0;
    width: 100%;
    text-align: center;
    color: white;
    font-size: 16px;
    padding: 10px;
    background: rgba(0,0,0,0.7);
    border-radius: 4px;
}

/* Notes Display */
.notes-container{background:#fff9e6;border:1px solid #ffeaa7;border-radius:8px;padding:15px;margin:15px 0;}
.notes-container h4{margin:0 0 10px 0;color:#856404;display:flex;align-items:center;gap:8px;}
.notes-content{color:#856404;line-height:1.5;white-space:pre-wrap;font-size:14px;}

/* Bulk Actions */
.bulk-actions-container{background:#e7f3ff;border:1px solid #b3d7ff;border-radius:8px;padding:15px;margin:15px 0;display:none;}
.bulk-actions-container.show{display:block;animation:fadeIn 0.3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.bulk-actions-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.bulk-actions-buttons{display:flex;gap:10px;flex-wrap:wrap;}
.bulk-btn{padding:8px 16px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;border:none;transition:0.3s;}
.bulk-btn.approve{background:#28a745;color:white;}
.bulk-btn.approve:hover{background:#218838;}
.bulk-btn.reject{background:#dc3545;color:white;}
.bulk-btn.reject:hover{background:#c82333;}
.bulk-btn.delete{background:#6c757d;color:white;}
.bulk-btn.delete:hover{background:#5a6268;}
.bulk-btn.clear{background:#ffc107;color:#333;}
.bulk-btn.clear:hover{background:#e0a800;}

/* Checkbox for bulk selection */
.claim-checkbox{margin-right:10px;}

/* ===== LOGOUT MODAL ===== */
#logoutModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

#logoutModal > .modal-content {
    background: white;
    width: 90%;
    max-width: 420px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#logoutModal > .modal-content > .modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

#logoutModal > .modal-content > .modal-header i {
    font-size: 24px;
}

#logoutModal > .modal-content > .modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

#logoutModal > .modal-content > .modal-body {
    padding: 30px 25px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-body .warning-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ff9500, #ff5e3a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

#logoutModal > .modal-content > .modal-body .warning-icon i {
    font-size: 30px;
    color: white;
}

#logoutModal > .modal-content > .modal-body p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.5;
}

#logoutModal > .modal-content > .modal-body .logout-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    text-align: left;
}

#logoutModal > .modal-content > .modal-body .detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

#logoutModal > .modal-content > .modal-body .detail-item i {
    color: #667eea;
    width: 20px;
    text-align: center;
}

#logoutModal > .modal-content > .modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel,
#logoutModal > .modal-content > .modal-footer .btn-logout {
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

#logoutModal > .modal-content > .modal-footer .btn-cancel {
    background: #f1f3f5;
    color: #495057;
}

#logoutModal > .modal-content > .modal-footer .btn-cancel:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

#logoutModal > .modal-content > .modal-footer .btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
}

#logoutModal > .modal-content > .modal-footer .btn-logout:hover {
    background: linear-gradient(135deg, #ff2b53, #ff341b);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 65, 108, 0.4);
}

/* Responsive */
@media(max-width: 1200px){
    .claim-details-grid{
        grid-template-columns: 1fr;
    }
    .comparison-grid{
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media(max-width: 900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0 !important;padding:15px;max-width:100% !important;}
    .dashboard-boxes,
    .filter-grid{grid-template-columns:1fr;}
    .detail-row{
        flex-direction: column;
    }
    .detail-label{
        min-width: auto;
        margin-bottom: 2px;
    }
    #logoutModal > .modal-content > .modal-footer{flex-direction:column;}
    #logoutModal > .modal-content > .modal-footer .btn-cancel,
    #logoutModal > .modal-content > .modal-footer .btn-logout{width:100%;justify-content:center;}
}

@media(max-width: 768px){
    .header{flex-wrap:wrap;gap:10px;}
    .comparison-grid{
        grid-template-columns: 1fr;
    }
    .comparison-photo{
        height: 120px;
    }
    #logoutModal > .modal-content{width:95%;max-width:95%;}
}

@media(max-width: 480px){
    .action-buttons{
        flex-wrap: wrap;
    }
    .bulk-actions-buttons{
        flex-direction: column;
    }
    .bulk-btn{
        width: 100%;
        justify-content: center;
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

        <li onclick="saveSidebarState(); window.location.href='manage_items.php'">
            <i class="fas fa-boxes"></i><span>Manage Items</span>
        </li>
        
        <li class="active">
            <i class="fas fa-handshake"></i><span>Manage Claims</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='categories.php'">
            <i class="fas fa-tags"></i><span>Categories</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='announcements.php'">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </li>
        
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
            <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-handshake"></i> Manage Claims</h1>
        <p>Review, approve, or reject user claims for lost and found items</p>
    </div>

    <!-- Alert Messages -->
    <?php if($success): ?>
    <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="dashboard-boxes">
        <div class="box" onclick="filterClaims('all')">
            <h2><?= htmlspecialchars($stats['total'] ?? 0, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Total Claims</p>
        </div>
        <div class="box warning" onclick="filterClaims('Pending')">
            <h2><?= htmlspecialchars($stats['pending'] ?? 0, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><i class="fas fa-exclamation-triangle"></i> Pending Review</p>
        </div>
        <div class="box" onclick="filterClaims('Approved')">
            <h2><?= htmlspecialchars($stats['approved'] ?? 0, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Approved Claims</p>
        </div>
        <div class="box" onclick="filterClaims('Rejected')">
            <h2><?= htmlspecialchars($stats['rejected'] ?? 0, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Rejected Claims</p>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions-container" id="bulkActions">
        <div class="bulk-actions-header">
            <div style="font-weight:600;color:#0c5460;">
                <i class="fas fa-check-square"></i>
                <span id="selectedCount">0 claims</span> selected
            </div>
            <button class="bulk-btn clear" onclick="clearSelection()">
                <i class="fas fa-times-circle"></i> Clear Selection
            </button>
        </div>
        <div class="bulk-actions-buttons">
            <button class="bulk-btn approve" onclick="bulkAction('approve')">
                <i class="fas fa-check"></i> Approve Selected
            </button>
            <button class="bulk-btn reject" onclick="bulkAction('reject')">
                <i class="fas fa-times"></i> Reject Selected
            </button>
            <button class="bulk-btn delete" onclick="bulkAction('delete')">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-container">
        <form method="GET" action="" id="filterForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date">Date Range</label>
                    <select name="date" id="date">
                        <option value="">All Time</option>
                        <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $filterDate === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $filterDate === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" 
                           placeholder="Claim ID, item name, or claimant..." 
                           value="<?= $safeSearchQuery ?>"
                           maxlength="100">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn apply">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button type="button" class="filter-btn reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Claims Table -->
    <div class="table-container">
        <table class="claims-table">
            <thead>
                <tr>
                    <th style="width: 50px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th>Claim ID</th>
                    <th>Lost Item</th>
                    <th>Found Item</th>
                    <th>Claimant</th>
                    <th>Date Claimed</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($claims)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;color:#666;">
                        <?php if(!empty($filterStatus) || !empty($filterDate) || !empty($searchQuery)): ?>
                            No claims found matching your filters. <a href="claims.php" style="color:#1e90ff;">Clear filters</a>
                        <?php else: ?>
                            No claims found. Claims will appear here when users submit them.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($claims as $claim): 
                    $claimId = htmlspecialchars($claim['claim_id'] ?? '', ENT_QUOTES, 'UTF-8');
                    $lostItemName = htmlspecialchars($claim['lost_item_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                    $lostCategory = htmlspecialchars($claim['lost_category'] ?? '', ENT_QUOTES, 'UTF-8');
                    $lostPhoto = htmlspecialchars($claim['lost_photo'] ?? '', ENT_QUOTES, 'UTF-8');
                    $foundItemName = htmlspecialchars($claim['found_item_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                    $foundCategory = htmlspecialchars($claim['found_category'] ?? '', ENT_QUOTES, 'UTF-8');
                    $foundPhoto = htmlspecialchars($claim['found_photo'] ?? '', ENT_QUOTES, 'UTF-8');
                    $proofPhoto = htmlspecialchars($claim['proof_photo'] ?? '', ENT_QUOTES, 'UTF-8');
                    $claimantName = htmlspecialchars($claim['claimant_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                    $claimDate = htmlspecialchars(date('M d, Y', strtotime($claim['created_at'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $status = htmlspecialchars($claim['status'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                <tr data-claim-id="<?= $claimId ?>"
                    data-lost-photo="<?= $lostPhoto ?>"
                    data-found-photo="<?= $foundPhoto ?>"
                    data-proof-photo="<?= $proofPhoto ?>"
                    data-lost-description="<?= htmlspecialchars($claim['lost_description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-found-description="<?= htmlspecialchars($claim['found_description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-lost-location="<?= htmlspecialchars($claim['lost_location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-found-location="<?= htmlspecialchars($claim['found_location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-lost-date="<?= htmlspecialchars($claim['lost_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-found-date="<?= htmlspecialchars($claim['found_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-claimant-student-id="<?= htmlspecialchars($claim['claimant_student_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-claimant-phone="<?= htmlspecialchars($claim['claimant_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-claimant-email="<?= htmlspecialchars($claim['claimant_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-claimant-course="<?= htmlspecialchars($claim['claimant_course'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-claimant-year="<?= htmlspecialchars($claim['claimant_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-notes="<?= htmlspecialchars($claim['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td>
                        <input type="checkbox" class="claim-checkbox" 
                               name="selected_claims[]" 
                               value="<?= $claimId ?>"
                               onchange="updateSelection()">
                    </td>
                    <td>#<?= $claimId ?></td>
                    <td>
                        <div style="font-weight:600;"><?= $lostItemName ?></div>
                        <?php if($lostCategory): ?>
                        <div style="font-size:12px;color:#666;"><?= $lostCategory ?></div>
                        <?php endif; ?>
                        <?php if($lostPhoto): ?>
                        <div style="font-size:11px;color:#1e90ff;margin-top:3px;">
                            <i class="fas fa-camera"></i> Photo available
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?= $foundItemName ?></div>
                        <?php if($foundCategory): ?>
                        <div style="font-size:12px;color:#666;"><?= $foundCategory ?></div>
                        <?php endif; ?>
                        <?php if($foundPhoto): ?>
                        <div style="font-size:11px;color:#1e90ff;margin-top:3px;">
                            <i class="fas fa-camera"></i> Photo available
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?= $claimantName ?></td>
                    <td><?= $claimDate ?></td>
                    <td>
                        <span class="status-badge <?= strtolower($status) ?>">
                            <?= $status ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Details" onclick="viewClaimDetails(<?= $claimId ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if($status === 'Pending'): ?>
                            <button class="btn-icon approve" title="Approve Claim" onclick="showUpdateModal(<?= $claimId ?>, 'Approved')">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn-icon reject" title="Reject Claim" onclick="showUpdateModal(<?= $claimId ?>, 'Rejected')">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn-icon delete" title="Delete Claim" onclick="deleteClaim(<?= $claimId ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Logout Modal -->
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
                    <span>User: <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name'], ENT_QUOTES, 'UTF-8') ?></span>
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

<!-- Claim Details Modal -->
<div class="modal" id="claimModal">
    <div class="modal-content">
        <h2><i class="fas fa-file-alt"></i> Claim Details #<span id="modalClaimId"></span></h2>
        
        <!-- Photo Comparison Section -->
        <div class="photo-comparison">
            <h4><i class="fas fa-images"></i> Photo Comparison</h4>
            <div class="comparison-grid" id="photoComparisonGrid">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Claim Details Grid -->
        <div class="claim-details-grid">
            <div class="detail-section">
                <h3><i class="fas fa-search"></i> Lost Item Details</h3>
                <div id="lostItemDetails">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-check-circle"></i> Found Item Details</h3>
                <div id="foundItemDetails">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-user"></i> Claimant Information</h3>
                <div id="claimantDetails">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-info-circle"></i> Claim Information</h3>
                <div id="claimInfo">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
        
        <!-- Notes Display -->
        <div id="notesDisplay" class="notes-container" style="display:none;">
            <h4><i class="fas fa-sticky-note"></i> Admin Notes</h4>
            <div id="notesContent"></div>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button class="filter-btn apply" onclick="closeClaimModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="filter-btn apply" onclick="printClaimDetails()" id="printBtn">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="updateModal">
    <div class="modal-content">
        <h2><i class="fas fa-edit"></i> Update Claim Status</h2>
        <form id="updateForm" method="POST">
            <input type="hidden" name="claim_id" id="updateClaimId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="update_claim" value="1">
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">New Status *</label>
                <select name="status" id="updateStatus" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;">
                    <option value="">Select Status</option>
                    <option value="Approved">Approve Claim</option>
                    <option value="Rejected">Reject Claim</option>
                    <option value="Pending">Keep as Pending</option>
                </select>
                <small style="color:#666;display:block;margin-top:5px;">
                    <i class="fas fa-info-circle"></i> Approving will mark both items as "Claimed"
                </small>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">Admin Notes (Optional)</label>
                <textarea name="admin_notes" id="adminNotes" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;" 
                          placeholder="Add notes about your decision... (Required for rejections)" 
                          oninput="updateCharCount(this)"
                          maxlength="500"></textarea>
                <div style="color:#666;font-size:13px;margin-top:5px;">
                    <span id="charCount">0</span>/500 characters
                </div>
            </div>
            
            <div style="background:#fff3cd;border:1px solid #ffeaa7;border-radius:5px;padding:10px;margin:15px 0;display:none;" id="rejectionWarning">
                <i class="fas fa-exclamation-triangle"></i>
                <span style="color:#856404;">Please provide a reason for rejection to help the user understand your decision.</span>
            </div>
            
            <div style="display:flex;gap:10px;">
                <button type="submit" class="filter-btn apply" id="submitBtn">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" class="filter-btn reset" onclick="closeUpdateModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Action Form -->
<form id="bulkForm" method="POST" style="display:none;">
    <input type="hidden" name="bulk_action" id="bulkAction">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
</form>

<script>
// CSRF token for delete actions
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';

// ===== GLOBAL VARIABLES =====
let selectedClaims = new Set();

// ===== LOGOUT MODAL FUNCTIONS =====
function initLogoutModal() {
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelLogout');
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    
    if (!logoutTrigger || !logoutModal) {
        console.log('Logout modal elements not found');
        return;
    }
    
    // Show modal when logout is clicked
    logoutTrigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Update current time
        const now = new Date();
        document.getElementById('currentTime').textContent = 
            now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        // Show modal with animation
        logoutModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Add keyboard shortcut (Esc to close)
        document.addEventListener('keydown', handleLogoutModalKeydown);
    });
    
    // Close modal when clicking cancel
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            closeLogoutModal();
        });
    }
    
    // Close modal when clicking outside
    logoutModal.addEventListener('click', function(e) {
        if (e.target === logoutModal) {
            closeLogoutModal();
        }
    });
    
    // Confirm logout
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
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
}

function closeLogoutModal() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.style.display = 'none';
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleLogoutModalKeydown);
    }
}

function handleLogoutModalKeydown(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
    }
}

// ===== SIDEBAR FUNCTIONS =====
function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    // Load sidebar state
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const savedState = localStorage.getItem('sidebarFolded');
            if (savedState === 'true') {
                sidebar.classList.add('folded');
            } else {
                sidebar.classList.remove('folded');
            }
        }
    }
    
    // Setup sidebar toggle
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
    
    // Highlight active page
    highlightActivePage();
    
    // Initialize logout modal
    initLogoutModal();
});

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'claims.php';
    const menuItems = document.querySelectorAll('.sidebar ul li');
    
    menuItems.forEach(item => {
        const onclick = item.getAttribute('onclick') || '';
        if (onclick.includes(currentPage) || item.classList.contains('active')) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// ===== FILTER FUNCTIONS =====
function filterClaims(status) {
    const safeStatus = encodeURIComponent(status);
    window.location.href = 'claims.php?status=' + safeStatus;
}

function resetFilters() {
    window.location.href = 'claims.php';
}

// ===== BULK SELECTION FUNCTIONS =====
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.claim-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        if (checkbox.checked) {
            selectedClaims.add(cb.value);
        } else {
            selectedClaims.delete(cb.value);
        }
    });
    updateBulkActions();
}

function updateSelection() {
    selectedClaims.clear();
    document.querySelectorAll('.claim-checkbox:checked').forEach(cb => {
        selectedClaims.add(cb.value);
    });
    
    // Update "Select All" checkbox
    const totalCheckboxes = document.querySelectorAll('.claim-checkbox').length;
    const checkedCount = selectedClaims.size;
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = checkedCount === totalCheckboxes && totalCheckboxes > 0;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCheckboxes;
    
    updateBulkActions();
}

function updateBulkActions() {
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedClaims.size > 0) {
        bulkActions.classList.add('show');
        selectedCount.textContent = `${selectedClaims.size} claim${selectedClaims.size !== 1 ? 's' : ''}`;
    } else {
        bulkActions.classList.remove('show');
    }
}

function clearSelection() {
    selectedClaims.clear();
    document.querySelectorAll('.claim-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    document.getElementById('selectAll').indeterminate = false;
    updateBulkActions();
}

function bulkAction(action) {
    if (selectedClaims.size === 0) {
        alert('Please select at least one claim.');
        return;
    }
    
    let actionText = action === 'approve' ? 'approve' : action === 'reject' ? 'reject' : 'delete';
    let confirmMessage = `Are you sure you want to ${actionText} ${selectedClaims.size} claim(s)?`;
    
    if (action === 'delete') {
        confirmMessage += '\n\nThis action cannot be undone!';
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Create form for bulk action
    const form = document.getElementById('bulkForm');
    const actionInput = document.getElementById('bulkAction');
    
    actionInput.value = action;
    
    // Add selected claims to form
    const existingInputs = form.querySelectorAll('input[name="selected_claims[]"]');
    existingInputs.forEach(input => input.remove());
    
    selectedClaims.forEach(claimId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_claims[]';
        input.value = claimId;
        form.appendChild(input);
    });
    
    // Submit form
    form.submit();
}

// ===== CLAIM DETAILS FUNCTIONS =====
function viewClaimDetails(claimId) {
    // Find the claim row
    const row = document.querySelector(`tr[data-claim-id="${claimId}"]`);
    if (!row) {
        alert('Claim details not found.');
        return;
    }
    
    // Get data from data attributes
    const lostPhoto = row.dataset.lostPhoto;
    const foundPhoto = row.dataset.foundPhoto;
    const proofPhoto = row.dataset.proofPhoto;
    
    // Set modal title
    document.getElementById('modalClaimId').textContent = claimId;
    
    // Build photo comparison section
    const comparisonGrid = document.getElementById('photoComparisonGrid');
    let comparisonHtml = '';
    
    if (lostPhoto) {
        const lostPhotoUrl = '../uploads/lost_items/' + lostPhoto;
        comparisonHtml += `
            <div class="comparison-item">
                <img src="${lostPhotoUrl}" 
                     alt="Lost Item" 
                     class="comparison-photo"
                     onclick="showImageModal('${lostPhotoUrl}', 'Lost Item')">
                <div class="comparison-label">Lost Item</div>
            </div>`;
    }
    
    if (foundPhoto) {
        const foundPhotoUrl = '../uploads/found_items/' + foundPhoto;
        comparisonHtml += `
            <div class="comparison-item">
                <img src="${foundPhotoUrl}" 
                     alt="Found Item" 
                     class="comparison-photo"
                     onclick="showImageModal('${foundPhotoUrl}', 'Found Item')">
                <div class="comparison-label">Found Item</div>
            </div>`;
    }
    
    if (proofPhoto) {
        const proofPhotoUrl = '../uploads/claims/' + proofPhoto;
        comparisonHtml += `
            <div class="comparison-item">
                <img src="${proofPhotoUrl}" 
                     alt="Claimant Proof" 
                     class="comparison-photo"
                     onclick="showImageModal('${proofPhotoUrl}', 'Claimant Proof')">
                <div class="comparison-label">Claimant's Proof</div>
            </div>`;
    }
    
    comparisonGrid.innerHTML = comparisonHtml;
    
    // Get other data
    const cells = row.cells;
    const lostItemName = cells[2].querySelector('div').textContent;
    const lostCategory = cells[2].querySelector('div:nth-child(2)')?.textContent || 'N/A';
    const foundItemName = cells[3].querySelector('div').textContent;
    const foundCategory = cells[3].querySelector('div:nth-child(2)')?.textContent || 'N/A';
    const claimantName = cells[4].textContent;
    const claimDate = cells[5].textContent;
    const status = cells[6].querySelector('.status-badge').textContent;
    
    // Build lost item details
    let lostPhotoHtml = '';
    if (lostPhoto) {
        const lostPhotoUrl = '../uploads/lost_items/' + lostPhoto;
        lostPhotoHtml = `
            <div class="item-photo-container">
                <img src="${lostPhotoUrl}" 
                     alt="Lost Item Photo" 
                     class="item-photo"
                     onclick="showImageModal('${lostPhotoUrl}', 'Lost Item: ${escapeHtml(lostItemName)}')">
                <span class="photo-label">Lost Item Photo</span>
                <a href="${lostPhotoUrl}" target="_blank" class="photo-link">
                    <i class="fas fa-external-link-alt"></i> View full size
                </a>
            </div>`;
    }
    
    document.getElementById('lostItemDetails').innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Item Name:</span>
            <span class="detail-value">${escapeHtml(lostItemName)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Category:</span>
            <span class="detail-value">${escapeHtml(lostCategory)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Description:</span>
            <span class="detail-value">${escapeHtml(row.dataset.lostDescription || 'No description provided')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Lost Location:</span>
            <span class="detail-value">${escapeHtml(row.dataset.lostLocation || 'N/A')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date Reported:</span>
            <span class="detail-value">${formatDate(row.dataset.lostDate) || 'N/A'}</span>
        </div>
        ${lostPhotoHtml}`;
    
    // Build found item details
    let foundPhotoHtml = '';
    if (foundPhoto) {
        const foundPhotoUrl = '../uploads/found_items/' + foundPhoto;
        foundPhotoHtml = `
            <div class="item-photo-container">
                <img src="${foundPhotoUrl}" 
                     alt="Found Item Photo" 
                     class="item-photo"
                     onclick="showImageModal('${foundPhotoUrl}', 'Found Item: ${escapeHtml(foundItemName)}')">
                <span class="photo-label">Found Item Photo</span>
                <a href="${foundPhotoUrl}" target="_blank" class="photo-link">
                    <i class="fas fa-external-link-alt"></i> View full size
                </a>
            </div>`;
    }
    
    document.getElementById('foundItemDetails').innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Item Name:</span>
            <span class="detail-value">${escapeHtml(foundItemName)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Category:</span>
            <span class="detail-value">${escapeHtml(foundCategory)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Description:</span>
            <span class="detail-value">${escapeHtml(row.dataset.foundDescription || 'No description provided')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Found Location:</span>
            <span class="detail-value">${escapeHtml(row.dataset.foundLocation || 'N/A')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date Found:</span>
            <span class="detail-value">${formatDate(row.dataset.foundDate) || 'N/A'}</span>
        </div>
        ${foundPhotoHtml}`;
    
    // Build claimant details
    let proofPhotoHtml = '';
    if (proofPhoto) {
        const proofPhotoUrl = '../uploads/claims/' + proofPhoto;
        proofPhotoHtml = `
            <div class="item-photo-container">
                <img src="${proofPhotoUrl}" 
                     alt="Claimant Proof Photo" 
                     class="item-photo"
                     onclick="showImageModal('${proofPhotoUrl}', 'Claimant Proof')">
                <span class="photo-label">Claimant's Proof Photo</span>
                <a href="${proofPhotoUrl}" target="_blank" class="photo-link">
                    <i class="fas fa-external-link-alt"></i> View full size
                </a>
            </div>`;
    }
    
    document.getElementById('claimantDetails').innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Full Name:</span>
            <span class="detail-value">${escapeHtml(claimantName)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Student ID:</span>
            <span class="detail-value">${escapeHtml(row.dataset.claimantStudentId || 'N/A')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Course/Year:</span>
            <span class="detail-value">${escapeHtml(row.dataset.claimantCourse || '')} ${escapeHtml(row.dataset.claimantYear || '')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Contact Number:</span>
            <span class="detail-value">${escapeHtml(row.dataset.claimantPhone || 'N/A')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${escapeHtml(row.dataset.claimantEmail || 'N/A')}</span>
        </div>
        ${proofPhotoHtml}`;
    
    // Build claim info
    document.getElementById('claimInfo').innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Claim ID:</span>
            <span class="detail-value">#${claimId}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="detail-value">${escapeHtml(status)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date Claimed:</span>
            <span class="detail-value">${escapeHtml(claimDate)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date Submitted:</span>
            <span class="detail-value">${formatDateTime(row.dataset.createdAt || new Date().toISOString())}</span>
        </div>`;
    
    // Show notes if available
    const notesDisplay = document.getElementById('notesDisplay');
    const notesContent = document.getElementById('notesContent');
    
    if (row.dataset.notes && row.dataset.notes.trim() !== '') {
        notesContent.textContent = row.dataset.notes;
        notesDisplay.style.display = 'block';
    } else {
        notesDisplay.style.display = 'none';
    }
    
    // Show modal
    document.getElementById('claimModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeClaimModal() {
    document.getElementById('claimModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function printClaimDetails() {
    const printContent = document.querySelector('#claimModal .modal-content').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Claim Details #${document.getElementById('modalClaimId').textContent}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #1e2a38; border-bottom: 2px solid #1e90ff; padding-bottom: 10px; }
                .photo-comparison { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
                .comparison-grid { display: flex; gap: 15px; flex-wrap: wrap; }
                .comparison-item { flex: 1; min-width: 200px; }
                .comparison-photo { width: 100%; max-width: 250px; height: auto; border-radius: 5px; }
                .detail-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                .detail-section h3 { margin-top: 0; color: #333; }
                .detail-row { display: flex; margin-bottom: 8px; }
                .detail-label { font-weight: bold; min-width: 150px; }
                .item-photo { max-width: 200px; max-height: 150px; border-radius: 5px; }
                @media print { 
                    .no-print { display: none; }
                    body { font-size: 12px; }
                    .photo-comparison { break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <h1>Claim Details #${document.getElementById('modalClaimId').textContent}</h1>
            ${printContent}
            <div class="no-print" style="margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #1e90ff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// ===== IMAGE MODAL FUNCTIONS =====
function showImageModal(imageUrl, title) {
    const modalHtml = `
        <div class="image-modal" id="imageModal" onclick="closeImageModal()">
            <div class="modal-content" onclick="event.stopPropagation()">
                <button class="image-modal-close" onclick="closeImageModal()">
                    <i class="fas fa-times"></i>
                </button>
                <img src="${imageUrl}" alt="${escapeHtml(title)}">
                <div class="image-modal-title">${escapeHtml(title)}</div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('imageModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    setTimeout(() => {
        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }, 10);
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
    }
}

// ===== UPDATE STATUS FUNCTIONS =====
function showUpdateModal(claimId, status) {
    document.getElementById('updateClaimId').value = claimId;
    document.getElementById('updateStatus').value = status;
    document.getElementById('adminNotes').value = '';
    document.getElementById('charCount').textContent = '0';
    
    // Show/hide rejection warning
    const warning = document.getElementById('rejectionWarning');
    const submitBtn = document.getElementById('submitBtn');
    
    if (status === 'Rejected') {
        warning.style.display = 'block';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Reject Claim';
        submitBtn.style.background = '#dc3545';
    } else if (status === 'Approved') {
        warning.style.display = 'none';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Approve Claim';
        submitBtn.style.background = '#28a745';
    } else {
        warning.style.display = 'none';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Status';
        submitBtn.style.background = '#1e90ff';
    }
    
    // Focus on notes field for rejections
    if (status === 'Rejected') {
        setTimeout(() => {
            document.getElementById('adminNotes').focus();
        }, 100);
    }
    
    document.getElementById('updateModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function updateCharCount(textarea) {
    const charCount = document.getElementById('charCount');
    charCount.textContent = textarea.value.length;
    
    if (textarea.value.length > 450) {
        charCount.style.color = '#dc3545';
        charCount.style.fontWeight = 'bold';
    } else if (textarea.value.length > 400) {
        charCount.style.color = '#ffc107';
        charCount.style.fontWeight = 'bold';
    } else {
        charCount.style.color = '#666';
        charCount.style.fontWeight = 'normal';
    }
}

// Validate form before submission
document.getElementById('updateForm').addEventListener('submit', function(e) {
    const status = document.getElementById('updateStatus').value;
    const notes = document.getElementById('adminNotes').value.trim();
    
    if (!status) {
        e.preventDefault();
        alert('Please select a status');
        return false;
    }
    
    if (status === 'Rejected' && notes.length < 10) {
        e.preventDefault();
        if (!confirm('You are rejecting this claim without providing detailed reasons. Are you sure?')) {
            return false;
        }
    }
    
    if (notes.length > 500) {
        e.preventDefault();
        alert('Notes must be 500 characters or less');
        return false;
    }
    
    return true;
});

// ===== DELETE FUNCTION =====
function deleteClaim(claimId) {
    if (confirm(`Are you sure you want to delete Claim #${claimId}? This action cannot be undone.`)) {
        window.location.href = `claims.php?delete=${claimId}&csrf_token=${encodeURIComponent(csrfToken)}`;
    }
}

// ===== UTILITY FUNCTIONS =====
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString || dateString === 'N/A') return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch (e) {
        return 'N/A';
    }
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    try {
        const date = new Date(dateTimeString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return 'N/A';
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const claimModal = document.getElementById('claimModal');
    const updateModal = document.getElementById('updateModal');
    const logoutModal = document.getElementById('logoutModal');
    
    if (claimModal && e.target === claimModal) {
        closeClaimModal();
    }
    
    if (updateModal && e.target === updateModal) {
        closeUpdateModal();
    }
    
    if (logoutModal && e.target === logoutModal) {
        closeLogoutModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeClaimModal();
        closeUpdateModal();
        closeLogoutModal();
        closeImageModal();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+A to select all claims (only when not in input/textarea)
    if (e.ctrlKey && e.key === 'a' && 
        document.activeElement.tagName !== 'INPUT' && 
        document.activeElement.tagName !== 'TEXTAREA' &&
        !document.activeElement.isContentEditable) {
        e.preventDefault();
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = !selectAll.checked;
            toggleSelectAll(selectAll);
        }
    }
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
</body>
</html>