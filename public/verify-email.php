<?php
require_once '../config/config.php';
require_once '../src/Services/VerificationService.php';

session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $verificationService = new VerificationService($db);
    $result = $verificationService->verifyEmailToken($token);
    
    if ($result['success']) {
        $_SESSION['success'] = "Email verified successfully! You can now receive email notifications.";
        
        // Auto-login if not already logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['email'] = $result['email'];
        }
        
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = $result['error'];
        header("Location: login.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid verification link";
    header("Location: index.php");
    exit;
}
