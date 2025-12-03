<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, role_id, password 
                               FROM users 
                               WHERE email = :email");
        $stmt->execute([':email' => $email]);

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $user['password'])) {

                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['first_name'] . " " . $user['last_name'];
                $_SESSION['role_id'] = $user['role_id'];

                // Redirect by role
                if ($user['role_id'] == 1) {
                    header("Location: ../admin_panel/dashboard.php");
                } else {
                    header("Location: ../user_panel/dashboard.php");
                }
                exit;

            } else {
                $_SESSION['login_error'] = "Incorrect password!";
                $_SESSION['login_form'] = ['email' => $email];
                header("Location: ../public/login.php");
                exit;
            }

        } else {
            $_SESSION['login_error'] = "Account not found!";
            $_SESSION['login_form'] = ['email' => $email];
            header("Location: ../public/login.php");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['login_error'] = "Database error: " . $e->getMessage();
        header("Location: ../public/login.php");
        exit;
    }

} else {
    header("Location: ../public/login.php");
    exit;
}
