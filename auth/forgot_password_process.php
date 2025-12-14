<?php
// auth/forgot_password_process.php
require_once __DIR__ . '/../config/config.php';
session_start();

error_log("[FORGOT_PASSWORD] Process started");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[FORGOT_PASSWORD] Invalid request method");
    header("Location: ../public/login.php");
    exit;
}

// Get form data
$username = trim($_POST['username'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');
$confirm_reset = isset($_POST['confirm_reset']);

error_log("[FORGOT_PASSWORD] Inputs: Username='$username', Birthdate='$birthdate'");

// Validation
if (empty($username) || empty($birthdate) || !$confirm_reset) {
    $_SESSION['forgot_password_error'] = "All fields are required and must be confirmed.";
    header("Location: ../public/login.php");
    exit;
}

// Validate TUPQ format
if (!preg_match('/^TUPQ-(00|22|23|24|25)-\d{4}$/', $username)) {
    $_SESSION['forgot_password_error'] = "Invalid username format. Use TUPQ-YY-NNNN.";
    header("Location: ../public/login.php");
    exit;
}

try {
    // Check user exists with matching birthdate
    $sql = "SELECT user_id, student_id, first_name, last_name, email, birthdate 
            FROM users 
            WHERE student_id = :username 
            AND birthdate = :birthdate";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':birthdate' => $birthdate
    ]);
    
    $user = $stmt->fetch();
    
    if (!$user) {
        error_log("[FORGOT_PASSWORD] User not found: $username with birthdate $birthdate");
        $_SESSION['forgot_password_error'] = "Username and birthdate combination not found.";
        header("Location: ../public/login.php");
        exit;
    }
    
    // Generate temporary password
    $temp_password = generate_random_string(8);
    
    // Hash password
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $updateSql = "UPDATE users 
                  SET password = :password, 
                      updated_at = NOW() 
                  WHERE user_id = :user_id";
    
    $updateStmt = $pdo->prepare($updateSql);
    $updateResult = $updateStmt->execute([
        ':password' => $hashed_password,
        ':user_id' => $user['user_id']
    ]);
    
    if (!$updateResult || $updateStmt->rowCount() === 0) {
        throw new Exception("Failed to update password.");
    }
    
    // Log activity
    try {
        $logSql = "INSERT INTO activity_logs (user_id, action, ip_address, details) 
                   VALUES (:user_id, 'password_reset', :ip, :details)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':user_id' => $user['user_id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':details' => "Password reset via forgot password"
        ]);
    } catch (Exception $e) {
        error_log("[FORGOT_PASSWORD] Failed to log activity: " . $e->getMessage());
    }
    
    // Set success message
    $successMessage = "✅ Password reset successful!<br><br>";
    $successMessage .= "<strong>Username:</strong> " . htmlspecialchars($username) . "<br>";
    $successMessage .= "<strong>Temporary Password:</strong> <code>" . htmlspecialchars($temp_password) . "</code><br><br>";
    $successMessage .= "Please login with this temporary password and change it immediately.";
    
    $_SESSION['forgot_password_success'] = $successMessage;
    $_SESSION['login_form']['username'] = $username;
    
} catch (PDOException $e) {
    error_log("[FORGOT_PASSWORD] Database error: " . $e->getMessage());
    $_SESSION['forgot_password_error'] = "Database error. Please try again.";
} catch (Exception $e) {
    error_log("[FORGOT_PASSWORD] Error: " . $e->getMessage());
    $_SESSION['forgot_password_error'] = "An error occurred: " . $e->getMessage();
}

header("Location: ../public/login.php");
exit;
?>