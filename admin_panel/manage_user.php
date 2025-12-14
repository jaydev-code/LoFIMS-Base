<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for session messages
if(isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

if(isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get admin info
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Course options
$courseOptions = ['bet-CpEt', 'bet-Eet', 'bet-Aet', 'bet-Ct', 'bet-Eset', 'bet-Hvac'];

// Category options with display labels
$categoryOptions = [
    'student' => 'Student',
    'instructor/prof' => 'Instructor/Professor',
    'faculty staff' => 'Faculty Staff',
    'others' => 'Others'
];

// Year options with display labels
$yearOptions = [
    '1' => '1st Year',
    '2' => '2nd Year',
    '3' => '3rd Year',
    '4' => '4th Year',
    'others' => 'Others'
];

// Allowed values for validation
$allowedCourses = ['bet-CpEt', 'bet-Eet', 'bet-Aet', 'bet-Ct', 'bet-Eset', 'bet-Hvac'];
$allowedCategories = ['student', 'instructor/prof', 'faculty staff', 'others'];
$allowedYears = ['1', '2', '3', '4', 'others'];

// Get filter/sort parameters with validation
$filterCourse = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$filterCategory = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';

// Validate filter values
if ($filterCourse && !in_array($filterCourse, $allowedCourses)) {
    $filterCourse = '';
}
if ($filterCategory && !in_array($filterCategory, $allowedCategories)) {
    $filterCategory = '';
}
if ($filterYear && !in_array($filterYear, $allowedYears)) {
    $filterYear = '';
}

$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sanitize search query for safe display
$safeSearchQuery = htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8');

// Build query with filters and sorting
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

// Add search filter
if (!empty($searchQuery)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ? OR course LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Add course filter
if (!empty($filterCourse)) {
    $sql .= " AND course = ?";
    $params[] = $filterCourse;
}

// Add category filter
if (!empty($filterCategory)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}

// Add year filter
if (!empty($filterYear)) {
    $sql .= " AND year = ?";
    $params[] = $filterYear;
}

// Add sorting
switch ($sortBy) {
    case 'name_asc':
        $sql .= " ORDER BY first_name ASC, last_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY first_name DESC, last_name DESC";
        break;
    case 'course_asc':
        $sql .= " ORDER BY course ASC";
        break;
    case 'course_desc':
        $sql .= " ORDER BY course DESC";
        break;
    case 'year_asc':
        $sql .= " ORDER BY year ASC";
        break;
    case 'year_desc':
        $sql .= " ORDER BY year DESC";
        break;
    case 'created_at_asc':
        $sql .= " ORDER BY created_at ASC";
        break;
    case 'created_at_desc':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

try {
    // Get all users with filters and sorting
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Validate CSRF token on POST requests
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = "Invalid CSRF token. Please try again.";
            header("Location: manage_user.php");
            exit();
        }
    }
}

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    validateCSRF();
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? 'bet-CpEt');
    $category = trim($_POST['category'] ?? 'student');
    $year = trim($_POST['year'] ?? '1');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 2);
    $contactInfo = trim($_POST['contact_info'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($studentId) || empty($birthdate)) {
        $_SESSION['error'] = "All fields marked with * are required!";
        header("Location: manage_user.php");
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address format!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($course, $allowedCourses)) {
        $_SESSION['error'] = "Invalid course selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($category, $allowedCategories)) {
        $_SESSION['error'] = "Invalid category selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($year, $allowedYears)) {
        $_SESSION['error'] = "Invalid year selection!";
        header("Location: manage_user.php");
        exit();
    } elseif ($roleId !== 1 && $roleId !== 2) {
        $_SESSION['error'] = "Invalid role selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $_SESSION['error'] = "Invalid birthdate format! Use YYYY-MM-DD format.";
        header("Location: manage_user.php");
        exit();
    } else {
        // Validate birthdate is not in future and user is at least 10 years old
        $birthDateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
        $today = new DateTime();
        $age = $today->diff($birthDateObj)->y;
        
        if ($birthDateObj > $today) {
            $_SESSION['error'] = "Birthdate cannot be in the future!";
            header("Location: manage_user.php");
            exit();
        } elseif ($age < 10) {
            $_SESSION['error'] = "User must be at least 10 years old!";
            header("Location: manage_user.php");
            exit();
        } else {
            // Validate Student ID format
            $studentIdPattern = '/^TUPQ-(00|22|23|24|25)-\d{4}$/';
            if (!preg_match($studentIdPattern, $studentId)) {
                $_SESSION['error'] = "Invalid Student ID format!<br>Format: TUPQ-YY-NNNN<br>Allowed YY: 00, 22, 23, 24, 25<br>Example: TUPQ-00-0000";
                header("Location: manage_user.php");
                exit();
            } else {
                try {
                    // Check if email already exists
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $checkStmt->execute([$email]);
                    $emailExists = $checkStmt->fetchColumn();
                    
                    if ($emailExists > 0) {
                        $_SESSION['error'] = "Email already exists! Please use a different email.";
                        header("Location: manage_user.php");
                        exit();
                    } else {
                        // Check if Student ID already exists
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");
                        $checkStmt->execute([$studentId]);
                        $studentIdExists = $checkStmt->fetchColumn();
                        
                        if ($studentIdExists > 0) {
                            $_SESSION['error'] = "Student ID already exists! Please use a different Student ID.";
                            header("Location: manage_user.php");
                            exit();
                        } else {
                            // Set password as last name in CAPITAL LETTERS
                            $password = strtoupper($lastName);
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insert new user with ALL fields including category
                            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, student_id, course, category, year, birthdate, role_id, contact_info, contact_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $firstName, 
                                $lastName, 
                                $email, 
                                $studentId, 
                                $course, 
                                $category, 
                                $year, 
                                $birthdate, 
                                $roleId, 
                                $contactInfo, 
                                $contactNumber, 
                                $hashedPassword
                            ]);
                            
                            $newUserId = $pdo->lastInsertId();
                            
                            // Log the action
                            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                            $logStmt->execute([$_SESSION['user_id'], "Added new user: $firstName $lastName ($email)", $ipAddress]);
                            
                            // Success - redirect
                            $_SESSION['success'] = "User #$newUserId added successfully!<br>Password: <strong>$password</strong><br>Birthdate: <strong>$birthdate</strong>";
                            header("Location: manage_user.php");
                            exit();
                        }
                    }
                } catch(PDOException $e) {
                    $_SESSION['error'] = "Error adding user. Please try again.";
                    header("Location: manage_user.php");
                    exit();
                }
            }
        }
    }
}

// Handle Edit User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    validateCSRF();
    
    $userId = (int)($_POST['user_id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? 'bet-CpEt');
    $category = trim($_POST['category'] ?? 'student');
    $year = trim($_POST['year'] ?? '1');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 2);
    $contactInfo = trim($_POST['contact_info'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    
    // Validate required fields
    if ($userId <= 0) {
        $_SESSION['error'] = "Invalid user ID!";
        header("Location: manage_user.php");
        exit();
    } elseif (empty($firstName) || empty($lastName) || empty($email) || empty($studentId) || empty($birthdate)) {
        $_SESSION['error'] = "All fields marked with * are required!";
        header("Location: manage_user.php");
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address format!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($course, $allowedCourses)) {
        $_SESSION['error'] = "Invalid course selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($category, $allowedCategories)) {
        $_SESSION['error'] = "Invalid category selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!in_array($year, $allowedYears)) {
        $_SESSION['error'] = "Invalid year selection!";
        header("Location: manage_user.php");
        exit();
    } elseif ($roleId !== 1 && $roleId !== 2) {
        $_SESSION['error'] = "Invalid role selection!";
        header("Location: manage_user.php");
        exit();
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $_SESSION['error'] = "Invalid birthdate format! Use YYYY-MM-DD format.";
        header("Location: manage_user.php");
        exit();
    } else {
        // Validate birthdate is not in future and user is at least 10 years old
        $birthDateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
        $today = new DateTime();
        $age = $today->diff($birthDateObj)->y;
        
        if ($birthDateObj > $today) {
            $_SESSION['error'] = "Birthdate cannot be in the future!";
            header("Location: manage_user.php");
            exit();
        } elseif ($age < 10) {
            $_SESSION['error'] = "User must be at least 10 years old!";
            header("Location: manage_user.php");
            exit();
        } else {
            // Validate Student ID format
            $studentIdPattern = '/^TUPQ-(00|22|23|24|25)-\d{4}$/';
            if (!preg_match($studentIdPattern, $studentId)) {
                $_SESSION['error'] = "Invalid Student ID format!<br>Format: TUPQ-YY-NNNN<br>Allowed YY: 00, 22, 23, 24, 25<br>Example: TUPQ-00-0000";
                header("Location: manage_user.php");
                exit();
            } else {
                try {
                    // Check if email already exists for another user
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                    $checkStmt->execute([$email, $userId]);
                    $emailExists = $checkStmt->fetchColumn();
                    
                    if ($emailExists > 0) {
                        $_SESSION['error'] = "Email already exists for another user!";
                        header("Location: manage_user.php");
                        exit();
                    } else {
                        // Check if Student ID already exists for another user
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ? AND user_id != ?");
                        $checkStmt->execute([$studentId, $userId]);
                        $studentIdExists = $checkStmt->fetchColumn();
                        
                        if ($studentIdExists > 0) {
                            $_SESSION['error'] = "Student ID already exists for another user!";
                            header("Location: manage_user.php");
                            exit();
                        } else {
                            // Update user with ALL fields including category
                            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, student_id = ?, course = ?, category = ?, year = ?, birthdate = ?, role_id = ?, contact_info = ?, contact_number = ?, updated_at = NOW() WHERE user_id = ?");
                            $stmt->execute([
                                $firstName, 
                                $lastName, 
                                $email, 
                                $studentId, 
                                $course, 
                                $category, 
                                $year, 
                                $birthdate, 
                                $roleId, 
                                $contactInfo, 
                                $contactNumber, 
                                $userId
                            ]);
                            
                            // Log the action
                            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                            $logStmt->execute([$_SESSION['user_id'], "Updated user: $firstName $lastName (ID: $userId)", $ipAddress]);
                            
                            // Success - redirect
                            $_SESSION['success'] = "User #$userId updated successfully!";
                            header("Location: manage_user.php");
                            exit();
                        }
                    }
                } catch(PDOException $e) {
                    $_SESSION['error'] = "Error updating user. Please try again.";
                    header("Location: manage_user.php");
                    exit();
                }
            }
        }
    }
}

// Handle Reset Password Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    validateCSRF();
    
    $userId = (int)($_POST['user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $passwordOption = $_POST['password_option'] ?? 'lastname';
    
    // Validation
    if ($userId <= 0) {
        $_SESSION['error'] = "Invalid user ID!";
        header("Location: manage_user.php");
        exit();
    } elseif ($passwordOption === 'custom' && empty($newPassword)) {
        $_SESSION['error'] = "New password is required for custom option!";
        header("Location: manage_user.php");
        exit();
    } elseif ($passwordOption === 'custom' && $newPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: manage_user.php");
        exit();
    } elseif ($passwordOption === 'custom' && strlen($newPassword) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters!";
        header("Location: manage_user.php");
        exit();
    } else {
        try {
            // Get user info
            $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $_SESSION['error'] = "User not found!";
                header("Location: manage_user.php");
                exit();
            } else {
                // Determine new password
                if ($passwordOption === 'lastname') {
                    $newPassword = strtoupper($user['last_name']);
                } elseif ($passwordOption === 'random') {
                    $newPassword = bin2hex(random_bytes(4)); // 8 character random
                }
                
                // Hash the password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Log the action
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], "Reset password for user: {$user['first_name']} {$user['last_name']} (ID: $userId)", $ipAddress]);
                
                // Success - redirect
                $_SESSION['success'] = "Password reset for user #$userId! New password: <strong>$newPassword</strong>";
                header("Location: manage_user.php");
                exit();
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error resetting password. Please try again.";
            header("Location: manage_user.php");
            exit();
        }
    }
}

// ============================================
// HANDLE DELETE USER - FIXED VERSION (WITH CASCADE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    validateCSRF();
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        $_SESSION['error'] = "Invalid user ID!";
        header("Location: manage_user.php");
        exit();
    }
    
    // Prevent deleting yourself
    if ($userId == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
        header("Location: manage_user.php");
        exit();
    }
    
    try {
        // Get user info before deletion
        $checkStmt = $pdo->prepare("SELECT first_name, last_name, email, student_id FROM users WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $_SESSION['error'] = "User not found!";
            header("Location: manage_user.php");
            exit();
        }
        
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $userEmail = $user['email'];
        $studentId = $user['student_id'];
        
        // Check for related records (for informational purposes only)
        $tablesToCheck = [
            'lost_items' => 'lost_id',
            'found_items' => 'found_id',
            'claims' => 'claim_id',
            'activity_logs' => 'log_id'
        ];
        
        $relatedRecords = [];
        
        foreach ($tablesToCheck as $table => $idField) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $relatedRecords[] = [
                    'table' => $table,
                    'count' => $count
                ];
            }
        }
        
        // If there are related records, show confirmation page
        if (!empty($relatedRecords)) {
            $_SESSION['delete_check'] = [
                'user_id' => $userId,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'student_id' => $studentId,
                'related_records' => $relatedRecords
            ];
            
            header("Location: manage_user.php?action=confirm_delete&id=" . $userId);
            exit();
        }
        
        // If no related records, delete directly
        // DATABASE WILL HANDLE CASCADE AUTOMATICALLY - NO NEED TO DISABLE FOREIGN KEY CHECKS
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        
        $rowsAffected = $deleteStmt->rowCount();
        
        if ($rowsAffected > 0) {
            // Log the action
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], "Deleted user: $userName ($userEmail) - Student ID: $studentId", $ipAddress]);
            
            $_SESSION['success'] = "User '$userName' deleted successfully!";
            header("Location: manage_user.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to delete user. User may not exist.";
            header("Location: manage_user.php");
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: manage_user.php");
        exit();
    }
}

// ============================================
// HANDLE FORCE DELETE (Database handles cascade automatically)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_delete'])) {
    validateCSRF();
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        $_SESSION['error'] = "Invalid user ID!";
        header("Location: manage_user.php");
        exit();
    }
    
    // Prevent deleting yourself
    if ($userId == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
        header("Location: manage_user.php");
        exit();
    }
    
    try {
        // Get user info before deletion
        $checkStmt = $pdo->prepare("SELECT first_name, last_name, email, student_id FROM users WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $_SESSION['error'] = "User not found!";
            header("Location: manage_user.php");
            exit();
        }
        
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $userEmail = $user['email'];
        $studentId = $user['student_id'];
        
        // SIMPLY DELETE THE USER - DATABASE WILL HANDLE CASCADE AUTOMATICALLY
        // NO NEED TO DISABLE FOREIGN KEY CHECKS OR MANUALLY DELETE RELATED RECORDS
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        
        $rowsAffected = $deleteStmt->rowCount();
        
        if ($rowsAffected > 0) {
            // Log the action
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], "Deleted user: $userName ($userEmail) - Student ID: $studentId with all related records", $ipAddress]);
            
            // Clear session data
            unset($_SESSION['delete_check']);
            
            // Success - redirect
            $_SESSION['success'] = "User '$userName' and all related records have been deleted successfully!";
            header("Location: manage_user.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to delete user. User may not exist.";
            header("Location: manage_user.php");
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
        header("Location: manage_user.php");
        exit();
    }
}

// Cancel delete action
if (isset($_GET['action']) && $_GET['action'] == 'cancel_delete') {
    unset($_SESSION['delete_check']);
    header("Location: manage_user.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - LoFIMS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Flatpickr CSS for better date picker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
.main{margin-left:220px;padding:20px;flex:1;transition:0.3s;min-height:100vh;max-width:100%;}
.sidebar.folded ~ .main{margin-left:70px;}

/* ===== Header ===== */
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);position:sticky;top:0;z-index:100;}
.user-info{font-weight:bold;display:flex;align-items:center;gap:10px;color:#1e2a38;}
.user-info i{color:#1e90ff;font-size:18px;}

/* Search bar */
.search-bar{position:relative;width:250px;}
.search-bar input{width:100%;padding:8px 35px 8px 10px;border-radius:8px;border:1px solid #ccc;outline:none;}
.search-bar i{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#888;}
.search-results{position:absolute;top:38px;left:0;width:100%;max-height:300px;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:8px;overflow-y:auto;display:none;z-index:2000;}
.search-results .result-item{padding:10px 15px;cursor:pointer;transition:0.3s;}
.search-results .result-item:hover{background:#f0f4ff;}

/* Page Header */
.page-header{background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.page-header h1{color:#1e2a38;font-size:28px;display:flex;align-items:center;gap:10px;}
.page-header p{color:#666;margin-top:5px;}

/* Filters and Search Container */
.filters-container {background:white;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);}
.filters-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.filters-header h2 {margin:0;font-size:18px;color:#1e2a38;display:flex;align-items:center;gap:10px;}
.filter-row {display:grid;grid-template-columns:1fr 1fr 1fr 2fr auto;gap:15px;align-items:end;}
.filter-group {display:flex;flex-direction:column;gap:5px;}
.filter-group label {font-size:12px;font-weight:600;color:#495057;text-transform:uppercase;}
.filter-group select, .filter-group input {width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;background:white;}
.search-box {position:relative;}
.search-box input {padding-left:35px;}
.search-box i {position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#888;}
.filter-actions {display:flex;gap:10px;}
.filter-btn {padding:8px 20px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;transition:0.3s;}
.filter-btn.apply {background:#1e90ff;color:white;border:none;}
.filter-btn.apply:hover {background:#1c7ed6;}
.filter-btn.reset {background:#f8f9fa;color:#495057;border:1px solid #ddd;}
.filter-btn.reset:hover {background:#e9ecef;}

/* Sort Controls */
.sort-controls {display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.sort-label {font-weight:600;color:#495057;}
.sort-btn {background:#f8f9fa;border:1px solid #ddd;padding:6px 12px;border-radius:6px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:5px;transition:0.3s;}
.sort-btn:hover {background:#e9ecef;}
.sort-btn.active {background:#1e90ff;color:white;border-color:#1e90ff;}

/* Table Styles */
.table-container{background:white;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.users-table{width:100%;border-collapse:collapse;}
.users-table th, .users-table td{padding:12px 15px;text-align:left;border-bottom:1px solid #eee;}
.users-table th{background:#f8f9fa;font-weight:600;color:#495057;position:relative;cursor:pointer;}
.users-table th:hover {background:#e9ecef;}
.users-table th i {margin-left:5px;font-size:12px;opacity:0.5;}
.users-table th:hover i {opacity:1;}
.users-table th.sort-asc i, .users-table th.sort-desc i {opacity:1;color:#1e90ff;}
.users-table tr:hover{background:#f8f9fa;}
.users-table tr:last-child td{border-bottom:none;}

/* Badge Styles */
.role-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.role-badge.admin{background:#d4edda;color:#155724;}
.role-badge.user{background:#fff3cd;color:#856404;}
.course-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#e7f3ff;color:#0066cc;display:inline-block;}
.category-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#fff0e6;color:#d35400;display:inline-block;}
.year-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:#f0f8ff;color:#663399;display:inline-block;}

/* Action Buttons */
.action-buttons{display:flex;gap:5px;}
.btn-icon{background:none;border:none;color:#6c757d;cursor:pointer;padding:5px;border-radius:4px;transition:0.2s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;}
.btn-icon:hover{background:#f8f9fa;color:#495057;}
.btn-icon.edit:hover{color:#1e90ff;}
.btn-icon.delete:hover{color:#dc3545;}
.btn-icon.password:hover{color:#28a745;}
.btn-icon.view:hover{color:#17a2b8;}

/* Add User Button */
.add-user-btn{background:#1e90ff;color:white;border:none;padding:10px 20px;border-radius:8px;font-weight:bold;cursor:pointer;display:flex;align-items:center;gap:8px;transition:0.3s;margin-bottom:20px;}
.add-user-btn:hover{background:#1c7ed6;transform:translateY(-2px);box-shadow:0 5px 15px rgba(30,144,255,0.3);}

/* Form Grid */
.form-grid {display: grid;grid-template-columns: 1fr 1fr;gap: 15px;margin-bottom: 15px;}
.form-group {margin-bottom: 15px;}
.form-group label {display: block;margin-bottom: 5px;font-weight: 500;}
.form-group input, .form-group select {width: 100%;padding: 8px;border: 1px solid #ccc;border-radius: 5px;}
.form-group.full-width {grid-column: 1 / -1;}

/* Custom Date Input */
.date-input-container {position: relative;}
.date-input-container input {padding-right: 35px !important;}
.date-input-container .calendar-icon {position: absolute;right: 10px;top: 50%;transform: translateY(-50%);color: #666;cursor: pointer;z-index: 10;}
.date-input-container .calendar-icon:hover {color: #1e90ff;}

/* Age Display */
.age-display {font-size: 12px;color: #666;margin-top: 5px;font-style: italic;}

/* Date Quick Select */
.date-quick-select {display: flex;gap: 5px;margin-top: 5px;}
.date-quick-btn {padding: 3px 8px;font-size: 11px;background: #f0f0f0;border: 1px solid #ddd;border-radius: 3px;cursor: pointer;}
.date-quick-btn:hover {background: #e0e0e0;}

/* Required field asterisk */
.required-field::after {
    content: " *";
    color: #dc3545;
    font-weight: bold;
}

/* Student ID Format Helper */
.student-id-helper {
    font-size: 11px;
    color: #666;
    margin-top: 3px;
    display: block;
}
.student-id-helper .format {
    background: #f0f0f0;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
    margin-left: 5px;
}

/* Important Note */
.important-note {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
    font-size: 14px;
    color: #856404;
}
.important-note i {
    color: #f39c12;
    margin-right: 10px;
}

/* Stats Badge */
.stats-badge {display:inline-block;background:#f8f9fa;padding:5px 10px;border-radius:20px;font-size:13px;color:#495057;margin-left:10px;}

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

/* Password Option Cards */
.password-option-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.password-option-card:hover {
    border-color: #1e90ff;
    background: #f8faff;
}

.password-option-card.selected {
    border-color: #1e90ff;
    background: #f0f7ff;
}

.password-option-card h4 {
    margin: 0 0 5px 0;
    color: #1e2a38;
    display: flex;
    align-items: center;
    gap: 8px;
}

.password-option-card p {
    margin: 0;
    color: #666;
    font-size: 13px;
}

.password-preview {
    background: #f8f9fa;
    border: 1px dashed #ccc;
    border-radius: 5px;
    padding: 10px;
    margin-top: 10px;
    text-align: center;
    font-family: monospace;
    font-weight: bold;
    color: #28a745;
}

/* Force Delete Modal */
.force-delete-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 4000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.force-delete-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.warning-section {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin: 15px 0;
    border-radius: 5px;
}

.danger-section {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 5px;
    padding: 15px;
    margin: 15px 0;
}

/* Responsive */
@media(max-width:900px){
    .sidebar{left:-220px;}
    .sidebar.show{left:0;}
    .main{margin-left:0;padding:15px;}
    .filter-row {grid-template-columns:1fr;gap:10px;}
    .modal-footer{flex-direction:column;}
    .btn-cancel, .btn-logout{width:100%;justify-content:center;}
}

@media(max-width:600px){
    .sort-controls {flex-direction:column;align-items:stretch;}
    .sort-btn {justify-content:center;}
    .table-container{overflow-x:auto;}
    .users-table{min-width:1200px;}
    .header{flex-wrap:wrap;gap:10px;}
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
        
        <li class="active">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </li>
        
        <li onclick="saveSidebarState(); window.location.href='reports.php'">
            <i class="fas fa-chart-line"></i><span>Reports</span>
        </li>

        <li onclick="saveSidebarState(); window.location.href='manage_items.php'">
            <i class="fas fa-boxes"></i><span>Manage Items</span>
        </li>
        
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

<!-- Main -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info user-info-center">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search users...">
            <i class="fas fa-search"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h1><i class="fas fa-users"></i> Manage Users</h1>
                <p>View and manage all system users <span class="stats-badge">Total Users: <?= count($users) ?></span></p>
            </div>
            <button class="add-user-btn" onclick="showAddUserModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Important Note -->
    <div class="important-note">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Important:</strong> Birthdate is required for all users as it's used as part of the login authentication process. Users must provide their correct birthdate to log in.
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($successMessage)): ?>
    <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $successMessage ?? '' ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-container">
        <div class="filters-header">
            <h2><i class="fas fa-filter"></i> Filter & Search Users</h2>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter_course">Course</label>
                    <select name="filter_course" id="filter_course">
                        <option value="">All Courses</option>
                        <?php foreach($courseOptions as $course): ?>
                            <option value="<?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8') ?>" <?= $filterCourse == $course ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_category">Category</label>
                    <select name="filter_category" id="filter_category">
                        <option value="">All Categories</option>
                        <?php foreach($categoryOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filterCategory == $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_year">Year</label>
                    <select name="filter_year" id="filter_year">
                        <option value="">All Years</option>
                        <?php foreach($yearOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filterYear == $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group search-box">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Search by name, email, student ID..." value="<?= $safeSearchQuery ?>">
                    <i class="fas fa-search"></i>
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
            <input type="hidden" name="sort" id="sortInput" value="<?= htmlspecialchars($sortBy, ENT_QUOTES, 'UTF-8') ?>">
        </form>
    </div>

    <!-- Sort Controls -->
    <div class="sort-controls">
        <span class="sort-label">Sort by:</span>
        <button class="sort-btn <?= $sortBy == 'created_at_desc' ? 'active' : '' ?>" onclick="setSort('created_at_desc')">
            <i class="fas fa-sort-amount-down"></i> Newest First
        </button>
        <button class="sort-btn <?= $sortBy == 'created_at_asc' ? 'active' : '' ?>" onclick="setSort('created_at_asc')">
            <i class="fas fa-sort-amount-up"></i> Oldest First
        </button>
        <button class="sort-btn <?= $sortBy == 'name_asc' ? 'active' : '' ?>" onclick="setSort('name_asc')">
            <i class="fas fa-sort-alpha-down"></i> Name A-Z
        </button>
        <button class="sort-btn <?= $sortBy == 'name_desc' ? 'active' : '' ?>" onclick="setSort('name_desc')">
            <i class="fas fa-sort-alpha-up"></i> Name Z-A
        </button>
        <button class="sort-btn <?= $sortBy == 'course_asc' ? 'active' : '' ?>" onclick="setSort('course_asc')">
            <i class="fas fa-sort-alpha-down"></i> Course A-Z
        </button>
        <button class="sort-btn <?= $sortBy == 'year_asc' ? 'active' : '' ?>" onclick="setSort('year_asc')">
            <i class="fas fa-sort-numeric-down"></i> Year Low-High
        </button>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th onclick="sortTable('id')">ID <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('name')">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('email')">Email <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('student_id')">Student ID <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('course')">Course <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('category')">Category <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('year')">Year <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('birthdate')">Birthdate <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('age')">Age <i class="fas fa-sort"></i></th>
                    <th>Contact</th>
                    <th onclick="sortTable('created_at')">Registered <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                <tr>
                    <td colspan="12" style="text-align:center;padding:30px;color:#666;">
                        <?php if(!empty($searchQuery) || !empty($filterCourse) || !empty($filterCategory) || !empty($filterYear)): ?>
                            No users found matching your filters. <a href="manage_user.php" style="color:#1e90ff;text-decoration:underline;">Clear filters</a>
                        <?php else: ?>
                            No users found. Click "Add New User" to add users.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($users as $user): 
                    $age = '';
                    if (!empty($user['birthdate'])) {
                        $birthDate = new DateTime($user['birthdate']);
                        $today = new DateTime();
                        $age = $today->diff($birthDate)->y;
                    }
                ?>
                <tr>
                    <td>#<?= htmlspecialchars($user['user_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['student_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="course-badge">
                            <?= htmlspecialchars($user['course'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <span class="category-badge">
                            <?= isset($user['category']) && isset($categoryOptions[$user['category']]) ? htmlspecialchars($categoryOptions[$user['category']], ENT_QUOTES, 'UTF-8') : 'N/A' ?>
                        </span>
                    </td>
                    <td>
                        <span class="year-badge">
                            <?= isset($user['year']) && isset($yearOptions[$user['year']]) ? htmlspecialchars($yearOptions[$user['year']], ENT_QUOTES, 'UTF-8') : 'N/A' ?>
                        </span>
                    </td>
                    <td><?= !empty($user['birthdate']) ? htmlspecialchars(date('M d, Y', strtotime($user['birthdate'])), ENT_QUOTES, 'UTF-8') : 'Not Set' ?></td>
                    <td><?= !empty($age) ? htmlspecialchars($age . ' years', ENT_QUOTES, 'UTF-8') : 'N/A' ?></td>
                    <td><?= htmlspecialchars($user['contact_number'] ?? $user['contact_info'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon edit" title="Edit User" onclick="editUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['first_name']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['last_name']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['email']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['student_id']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['course']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['category']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['year']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['birthdate']), ENT_QUOTES, 'UTF-8') ?>', <?= $user['role_id'] ?>, '<?= htmlspecialchars(addslashes($user['contact_info']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['contact_number']), ENT_QUOTES, 'UTF-8') ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon password" title="Reset Password" onclick="showResetPasswordModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn-icon delete" title="Delete User" onclick="showDeleteModal(<?= $user['user_id'] ?? 0 ?>, '<?= htmlspecialchars(addslashes(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>')">
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

<!-- ============================================
FORCE DELETE CONFIRMATION MODAL (for users with related records)
============================================ -->
<?php if(isset($_GET['action']) && $_GET['action'] == 'confirm_delete' && isset($_GET['id']) && isset($_SESSION['delete_check'])): 
    $check = $_SESSION['delete_check'];
?>
<div class="force-delete-modal">
    <div class="force-delete-content">
        <h2 style="color:#dc3545;"><i class="fas fa-exclamation-triangle"></i> Confirm Force Delete</h2>
        
        <div class="warning-section">
            <strong><i class="fas fa-warning"></i> WARNING:</strong> This user has related records in the system.
        </div>
        
        <div style="margin:20px 0;">
            <h4>User Details:</h4>
            <table style="width:100%;background:#f8f9fa;padding:15px;border-radius:5px;">
                <tr>
                    <td><strong>Name:</strong></td>
                    <td><?= htmlspecialchars($check['user_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?= htmlspecialchars($check['user_email']) ?></td>
                </tr>
                <tr>
                    <td><strong>Student ID:</strong></td>
                    <td><?= htmlspecialchars($check['student_id']) ?></td>
                </tr>
            </table>
            
            <h4 style="margin-top:20px;">Related Records Found:</h4>
            <ul style="background:#f8f9fa;padding:15px;border-radius:5px;">
                <?php foreach($check['related_records'] as $record): ?>
                    <li><?= $record['count'] ?> record(s) in <?= htmlspecialchars($record['table']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="danger-section">
            <strong><i class="fas fa-exclamation-circle"></i> This action will:</strong>
            <ul style="margin:10px 0 10px 20px;">
                <li>Permanently delete the user account</li>
                <li>Delete all related records (lost items, found items, claims, etc.)</li>
                <li>This action cannot be undone</li>
            </ul>
        </div>
        
        <form id="forceDeleteForm" method="POST" style="margin-top:20px;">
            <input type="hidden" name="user_id" value="<?= $check['user_id'] ?>">
            <input type="hidden" name="force_delete" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#dc3545;color:white;border:none;padding:12px 24px;border-radius:5px;cursor:pointer;flex:1;font-weight:bold;">
                    <i class="fas fa-trash"></i> DELETE ALL (User + Records)
                </button>
                <button type="button" onclick="window.location.href='manage_user.php?action=cancel_delete'" style="background:#6c757d;color:white;border:none;padding:12px 24px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-times"></i> CANCEL
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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

<!-- Add/Edit User Modal -->
<div id="userModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:650px;max-width:90%;max-height:90%;overflow-y:auto;">
        <h2 id="modalTitle">Add New User</h2>
        <div class="important-note" style="margin-bottom:15px;padding:10px;">
            <i class="fas fa-info-circle"></i>
            <strong>Note:</strong> Birthdate is required for login authentication. Users must provide this to access their account.
        </div>
        <form id="userForm" method="POST" onsubmit="return validateUserForm()">
            <input type="hidden" id="userId" name="user_id">
            <input type="hidden" id="formAction" name="add_user" value="1">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="required-field">First Name</label>
                    <input type="text" name="first_name" id="firstName" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label class="required-field">Last Name</label>
                    <input type="text" name="last_name" id="lastName" required maxlength="50">
                    <small style="color:#666;font-size:12px;">Password will be set to: <strong id="passwordPreview"></strong></small>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="required-field">Email</label>
                    <input type="email" name="email" id="email" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label class="required-field">Student ID</label>
                    <input type="text" name="student_id" id="studentId" required maxlength="20" pattern="TUPQ-(00|22|23|24|25)-\d{4}" title="Format: TUPQ-YY-NNNN (YY: 00, 22, 23, 24, 25)">
                    <span class="student-id-helper">
                        Required format: <span class="format">TUPQ-YY-NNNN</span>
                        <br>Allowed YY: 00, 22, 23, 24, 25
                        <br>Example: <span class="format">TUPQ-00-0000</span> or <span class="format">TUPQ-23-1234</span>
                    </span>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="required-field">Course</label>
                    <select name="course" id="course" required>
                        <?php foreach($courseOptions as $course): ?>
                            <option value="<?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($course, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required-field">Category</label>
                    <select name="category" id="category" required>
                        <?php foreach($categoryOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="required-field">Year</label>
                    <select name="year" id="year" required>
                        <?php foreach($yearOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required-field">Role</label>
                    <select name="role_id" id="roleId" required>
                        <option value="2">User</option>
                        <option value="1">Admin</option>
                    </select>
                </div>
            </div>
            
            <!-- Birthdate with Enhanced Picker - NOW REQUIRED -->
            <div class="form-group full-width">
                <label class="required-field">Birthdate</label>
                <div class="date-input-container">
                    <input type="text" name="birthdate" id="birthdateInput" required placeholder="Select birthdate" readonly maxlength="10">
                    <i class="fas fa-calendar calendar-icon" id="calendarToggle"></i>
                </div>
                <div id="ageDisplay" class="age-display"></div>
                <div class="date-quick-select">
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2000-01-01')">Jan 1, 2000</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2001-01-01')">Jan 1, 2001</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2002-01-01')">Jan 1, 2002</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2003-01-01')">Jan 1, 2003</button>
                    <button type="button" class="date-quick-btn" onclick="setQuickDate('2004-01-01')">Jan 1, 2004</button>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Contact Info (Optional)</label>
                    <input type="text" name="contact_info" id="contactInfo" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label>Contact Number (Optional)</label>
                    <input type="text" name="contact_number" id="contactNumber" maxlength="20">
                </div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" style="background:#1e90ff;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-save"></i> Save User
                </button>
                <button type="button" onclick="closeModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:500px;max-width:90%;">
        <h2><i class="fas fa-key"></i> Reset Password</h2>
        <p id="resetPasswordUserInfo" style="margin-bottom:20px;"></p>
        <form id="resetPasswordForm" method="POST">
            <input type="hidden" id="resetUserId" name="user_id">
            <input type="hidden" name="reset_password" value="1">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- Password Options -->
            <div id="passwordOptions">
                <div class="password-option-card" onclick="selectPasswordOption('lastname')">
                    <h4><i class="fas fa-user"></i> Use Last Name</h4>
                    <p>Reset to user's last name in UPPERCASE</p>
                    <div id="lastnamePreview" class="password-preview" style="display:none;"></div>
                </div>
                
                <div class="password-option-card" onclick="selectPasswordOption('random')">
                    <h4><i class="fas fa-random"></i> Generate Random</h4>
                    <p>Create a secure random password</p>
                    <div id="randomPreview" class="password-preview" style="display:none;"></div>
                </div>
                
                <div class="password-option-card" onclick="selectPasswordOption('custom')">
                    <h4><i class="fas fa-edit"></i> Custom Password</h4>
                    <p>Set your own custom password</p>
                    <div id="customPasswordFields" style="display:none;margin-top:10px;">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" style="width:100%;">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" style="width:100%;">
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="password_option" id="passwordOption" value="lastname">
            
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" style="background:#28a745;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-check"></i> Reset Password
                </button>
                <button type="button" onclick="closeResetPasswordModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:10px;width:400px;max-width:90%;">
        <h2>Delete User</h2>
        <p id="deleteMessage">Are you sure you want to delete this user?</p>
        <form id="deleteForm" method="POST" style="margin-top:20px;">
            <input type="hidden" id="deleteUserId" name="user_id">
            <input type="hidden" name="delete_user" value="1">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#dc3545;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;flex:1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Include Flatpickr JS for enhanced date picker -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- JavaScript -->
<script>
// ===== LOGOUT MODAL FUNCTIONALITY =====
function initLogoutModal() {
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelLogout');
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    
    if (!logoutTrigger || !logoutModal) return;
    
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
            
            // Redirect after short delay
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

function saveSidebarState() {
    if (window.innerWidth > 900) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const isFolded = sidebar.classList.contains('folded');
            localStorage.setItem('sidebarFolded', isFolded);
        }
    }
}

// ===== ENHANCED DATE PICKER =====
let datePicker;

function initDatePicker() {
    const today = new Date();
    const maxDate = new Date();
    maxDate.setFullYear(today.getFullYear() - 10); // Minimum age 10 years
    
    // Configure date picker
    datePicker = flatpickr("#birthdateInput", {
        dateFormat: "Y-m-d",
        maxDate: maxDate,
        minDate: "1900-01-01",
        defaultDate: "2004-01-01", // Default to 2004
        onChange: function(selectedDates, dateStr, instance) {
            calculateAge(dateStr);
        }
    });
    
    // Add calendar icon click handler
    document.getElementById('calendarToggle').addEventListener('click', function() {
        datePicker.open();
    });
    
    // Make birthdate field required
    const birthdateInput = document.getElementById('birthdateInput');
    birthdateInput.required = true;
}

function calculateAge(birthdate) {
    if (!birthdate) {
        document.getElementById('ageDisplay').textContent = '';
        return;
    }
    
    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    document.getElementById('ageDisplay').textContent = `Age: ${age} years`;
    
    // Validate age (must be at least 10 years old)
    if (age < 10) {
        document.getElementById('ageDisplay').style.color = '#dc3545';
        document.getElementById('ageDisplay').innerHTML += ' <small style="color:#dc3545;">(Must be at least 10 years old)</small>';
    } else {
        document.getElementById('ageDisplay').style.color = '#28a745';
    }
}

function setQuickDate(dateString) {
    if (datePicker) {
        datePicker.setDate(dateString, true);
        calculateAge(dateString);
    }
}

// ===== PASSWORD PREVIEW =====
function updatePasswordPreview() {
    const lastNameInput = document.getElementById('lastName');
    const passwordPreview = document.getElementById('passwordPreview');
    
    if (lastNameInput && passwordPreview) {
        lastNameInput.addEventListener('input', function() {
            const lastName = this.value.trim().toUpperCase();
            passwordPreview.textContent = lastName || '[LAST NAME]';
        });
        
        // Initial preview
        const initialLastName = lastNameInput.value.trim().toUpperCase();
        passwordPreview.textContent = initialLastName || '[LAST NAME]';
    }
}

// ===== STUDENT ID VALIDATION =====
function validateStudentId(studentId) {
    const studentIdPattern = /^TUPQ-(00|22|23|24|25)-\d{4}$/;
    return studentIdPattern.test(studentId);
}

// ===== BIRTHDATE VALIDATION =====
function validateBirthdate(birthdate) {
    if (!birthdate) return false;
    
    const birthDate = new Date(birthdate);
    const today = new Date();
    
    // Check if valid date
    if (isNaN(birthDate.getTime())) return false;
    
    // Check if not in future
    if (birthDate > today) return false;
    
    // Check if at least 10 years old
    const age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        return age - 1 >= 10;
    }
    
    return age >= 10;
}

function validateUserForm() {
    const studentId = document.getElementById('studentId').value.trim();
    const birthdate = document.getElementById('birthdateInput').value.trim();
    
    if (!validateStudentId(studentId)) {
        alert('Invalid Student ID format!\n\nCorrect format: TUPQ-YY-NNNN\nAllowed YY: 00, 22, 23, 24, 25\nExample: TUPQ-00-0000 or TUPQ-23-1234');
        document.getElementById('studentId').focus();
        document.getElementById('studentId').select();
        return false;
    }
    
    if (!validateBirthdate(birthdate)) {
        alert('Invalid birthdate!\n\nBirthdate must be:\n- In YYYY-MM-DD format\n- Not in the future\n- User must be at least 10 years old');
        document.getElementById('birthdateInput').focus();
        if (datePicker) datePicker.open();
        return false;
    }
    
    return true;
}

// ===== USER MANAGEMENT FUNCTIONS =====
function showAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('formAction').name = 'add_user';
    document.getElementById('formAction').value = '1';
    document.getElementById('ageDisplay').textContent = '';
    
    // Set default values
    document.getElementById('course').value = 'bet-CpEt';
    document.getElementById('category').value = 'student';
    document.getElementById('year').value = '1';
    document.getElementById('roleId').value = '2';
    document.getElementById('studentId').value = '';
    
    // Reset date picker to 2004
    if (datePicker) {
        datePicker.setDate('2004-01-01', true);
        calculateAge('2004-01-01');
    }
    
    // Update password preview
    updatePasswordPreview();
    
    document.getElementById('userModal').style.display = 'flex';
}

function editUser(userId, firstName, lastName, email, studentId, course, category, year, birthdate, roleId, contactInfo, contactNumber) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userId').value = userId;
    document.getElementById('firstName').value = firstName || '';
    document.getElementById('lastName').value = lastName || '';
    document.getElementById('email').value = email || '';
    document.getElementById('studentId').value = studentId || '';
    document.getElementById('course').value = course || 'bet-CpEt';
    document.getElementById('category').value = category || 'student';
    document.getElementById('year').value = year || '1';
    document.getElementById('roleId').value = roleId || 2;
    document.getElementById('contactInfo').value = contactInfo || '';
    document.getElementById('contactNumber').value = contactNumber || '';
    
    // Set birthdate if exists
    const birthdateInput = document.getElementById('birthdateInput');
    if (birthdate && birthdate !== '0000-00-00') {
        birthdateInput.value = birthdate;
        if (datePicker) {
            datePicker.setDate(birthdate, true);
        }
        calculateAge(birthdate);
    } else {
        // If no birthdate, set to default
        birthdateInput.value = '';
        if (datePicker) {
            datePicker.setDate('2004-01-01', true);
            calculateAge('2004-01-01');
        }
    }
    
    // Change form action to edit
    document.getElementById('formAction').name = 'edit_user';
    document.getElementById('formAction').value = '1';
    
    // Update password preview
    const passwordPreview = document.getElementById('passwordPreview');
    if (passwordPreview) {
        passwordPreview.textContent = lastName ? lastName.toUpperCase() : '[LAST NAME]';
    }
    
    document.getElementById('userModal').style.display = 'flex';
}

function showResetPasswordModal(userId, userName, userLastName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetPasswordUserInfo').textContent = `Reset password for: ${userName}`;
    
    // Set up password options
    document.getElementById('lastnamePreview').textContent = userLastName ? userLastName.toUpperCase() : '';
    document.getElementById('lastnamePreview').style.display = 'block';
    
    // Generate random password preview
    const randomPassword = generateRandomPassword();
    document.getElementById('randomPreview').textContent = randomPassword;
    document.getElementById('randomPreview').style.display = 'block';
    
    // Reset custom fields
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('customPasswordFields').style.display = 'none';
    
    // Reset selection
    document.querySelectorAll('.password-option-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelectorAll('.password-option-card')[0].classList.add('selected');
    document.getElementById('passwordOption').value = 'lastname';
    
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function selectPasswordOption(option) {
    // Remove selected class from all cards
    document.querySelectorAll('.password-option-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    const cards = document.querySelectorAll('.password-option-card');
    if (option === 'lastname') cards[0].classList.add('selected');
    else if (option === 'random') cards[1].classList.add('selected');
    else cards[2].classList.add('selected');
    
    // Show/hide custom password fields
    if (option === 'custom') {
        document.getElementById('customPasswordFields').style.display = 'block';
    } else {
        document.getElementById('customPasswordFields').style.display = 'none';
    }
    
    document.getElementById('passwordOption').value = option;
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return password;
}

// ===== DELETE FUNCTIONS =====
function showDeleteModal(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteMessage').textContent = `Are you sure you want to delete user "${userName}"?`;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

// ===== FILTER AND SORT FUNCTIONS =====
function setSort(sortType) {
    document.getElementById('sortInput').value = sortType;
    document.getElementById('filterForm').submit();
}

function resetFilters() {
    window.location.href = 'manage_user.php';
}

function sortTable(column) {
    let currentSort = '<?= htmlspecialchars($sortBy, ENT_QUOTES, 'UTF-8') ?>';
    let newSort = '';
    
    switch(column) {
        case 'name':
            newSort = currentSort === 'name_asc' ? 'name_desc' : 'name_asc';
            break;
        case 'course':
            newSort = currentSort === 'course_asc' ? 'course_desc' : 'course_asc';
            break;
        case 'year':
            newSort = currentSort === 'year_asc' ? 'year_desc' : 'year_asc';
            break;
        case 'created_at':
            newSort = currentSort === 'created_at_asc' ? 'created_at_desc' : 'created_at_asc';
            break;
        default:
            newSort = 'created_at_desc';
    }
    
    document.getElementById('sortInput').value = newSort;
    document.getElementById('filterForm').submit();
}

// ===== BASIC PAGE INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Manage Users: Page loaded');
    
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
    
    // Basic sidebar toggle
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
    
    // Initialize enhanced date picker
    initDatePicker();
    
    // Initialize password preview
    updatePasswordPreview();
    
    // Initialize logout modal
    initLogoutModal();
});

// Add force delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    if (forceDeleteForm) {
        forceDeleteForm.addEventListener('submit', function(e) {
            if(!confirm('FINAL WARNING: This will delete ALL records associated with this user. This cannot be undone. Are you absolutely sure?')) {
                e.preventDefault();
            }
        });
    }
});
</script>
</body>
</html>