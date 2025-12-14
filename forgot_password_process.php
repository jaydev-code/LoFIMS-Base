<?php
// forgot_password_process.php
session_start();
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    
    // Validate inputs
    if (!preg_match('/^TUPQ-(00|22|23|24|25)-\d{4}$/', $username)) {
        $_SESSION['forgot_password_error'] = 'Invalid username format. Please use TUPQ-YY-NNNN format.';
        header("Location: ../auth/login.php");
        exit;
    }
    
    if (empty($birthdate)) {
        $_SESSION['forgot_password_error'] = 'Birthdate is required for verification.';
        header("Location: ../auth/login.php");
        exit;
    }
    
    try {
        // Connect to database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if user exists with matching username AND birthdate
        $stmt = $pdo->prepare("SELECT id, username, birthdate, email FROM users WHERE username = ? AND birthdate = ?");
        $stmt->execute([$username, $birthdate]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate temporary password (8 characters random)
            $tempPassword = generateTemporaryPassword();
            
            // Update ONLY the password in database (keep birthdate unchanged)
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $user['id']]);
            
            // Optional: Log the password reset
            $logStmt = $pdo->prepare("INSERT INTO password_reset_logs (user_id, reset_at, ip_address) VALUES (?, NOW(), ?)");
            $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
            
            // Store success message with temporary password
            $_SESSION['forgot_password_success'] = "Password reset successful! Your temporary password is: <strong>" . $tempPassword . "</strong>. Please login and change your password immediately.";
            
            // Also pre-fill the username on login page
            $_SESSION['login_form']['username'] = $username;
            
        } else {
            $_SESSION['forgot_password_error'] = "Username and birthdate combination not found. Please verify your information.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['forgot_password_error'] = "System error. Please contact administrator.";
        error_log("Password reset error: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['forgot_password_error'] = "An error occurred. Please try again.";
    }
    
    header("Location: ../auth/login.php");
    exit;
} else {
    header("Location: ../auth/login.php");
    exit;
}

function generateTemporaryPassword($length = 8) {
    // Generate a random alphanumeric password
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
