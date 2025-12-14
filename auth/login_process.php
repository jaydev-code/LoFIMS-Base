<?php
// auth/login_process.php
require_once __DIR__ . '/../config/config.php';
session_start();

error_log("[LOGIN] Process started");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';

    error_log("[LOGIN] Form data - Username: $username, Birthdate: $birthdate");

    // Validation
    if (empty($username) || empty($password) || empty($birthdate)) {
        $_SESSION['login_error'] = "All fields are required.";
        $_SESSION['login_form'] = ['username' => $username];
        header("Location: ../public/login.php");
        exit;
    }

    // Validate TUPQ format
    if (!preg_match('/^TUPQ-(00|22|23|24|25)-\d{4}$/', $username)) {
        $_SESSION['login_error'] = "Invalid username format. Use TUPQ-YY-NNNN.";
        $_SESSION['login_form'] = ['username' => $username];
        header("Location: ../public/login.php");
        exit;
    }

    try {
        // Check user exists
        $stmt = $pdo->prepare("
            SELECT user_id, first_name, last_name, email, student_id, role_id, password, birthdate
            FROM users
            WHERE student_id = :username
        ");
        $stmt->execute([':username' => $username]);

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("[LOGIN] User found: {$user['first_name']}, Role: {$user['role_id']}");

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Verify birthdate
                if ($user['birthdate'] == $birthdate) {
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['first_name'] . " " . $user['last_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['username'] = $user['student_id'];
                    $_SESSION['email'] = $user['email'];

                    error_log("[LOGIN] Session set - User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role_id']}");

                    // Log activity
                    try {
                        $logStmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, ip_address, details) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $logStmt->execute([
                            $user['user_id'],
                            'login',
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            'User logged in successfully'
                        ]);
                        error_log("[LOGIN] Activity logged");
                    } catch (Exception $e) {
                        error_log("[LOGIN] Failed to log activity: " . $e->getMessage());
                    }

                    // Redirect based on role
                    if ($user['role_id'] == 1) { // Assuming 1 is admin
                        error_log("[LOGIN] Redirecting to admin panel");
                        header("Location: ../admin_panel/dashboard.php");
                    } else {
                        error_log("[LOGIN] Redirecting to user panel");
                        header("Location: ../user_panel/dashboard.php");
                    }
                    exit();

                } else {
                    error_log("[LOGIN] Birthdate mismatch");
                    $_SESSION['login_error'] = "Birthdate does not match.";
                    $_SESSION['login_form'] = ['username' => $username];
                    header("Location: ../public/login.php");
                    exit;
                }
            } else {
                error_log("[LOGIN] Password incorrect");
                $_SESSION['login_error'] = "Incorrect password.";
                $_SESSION['login_form'] = ['username' => $username];
                header("Location: ../public/login.php");
                exit;
            }

        } else {
            error_log("[LOGIN] User not found");
            $_SESSION['login_error'] = "Account not found.";
            $_SESSION['login_form'] = ['username' => $username];
            header("Location: ../public/login.php");
            exit;
        }

    } catch (PDOException $e) {
        error_log("[LOGIN] Database error: " . $e->getMessage());
        $_SESSION['login_error'] = "Database error: " . $e->getMessage();
        header("Location: ../public/login.php");
        exit;
    }

} else {
    error_log("[LOGIN] Invalid request method");
    header("Location: ../public/login.php");
    exit;
}
?>