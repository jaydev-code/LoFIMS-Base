<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $contact_info = trim($_POST['contact_info']);
    $contact_number = trim($_POST['contact_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match!";
        header("Location: ../public/register.php");
        exit;
    }

    try {
        // Check if contact_info or email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE contact_info = :contact_info OR email = :email");
        $stmt->execute([':contact_info' => $contact_info, ':email' => $email]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['register_error'] = "This email or username is already registered!";
            header("Location: ../public/register.php");
            exit;
        }

        // Insert new user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (first_name, last_name, email, student_id, contact_info, contact_number, password, role_id)
            VALUES
            (:first_name, :last_name, :email, :student_id, :contact_info, :contact_number, :password, 2)
        ");

        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':student_id' => $student_id,
            ':contact_info' => $contact_info,
            ':contact_number' => $contact_number,
            ':password' => $hashed_password
        ]);

        $_SESSION['register_success'] = "Registration successful! Please login.";
        header("Location: ../public/login.php");
        exit;

    } catch(PDOException $e){
        $_SESSION['register_error'] = "Database error: " . $e->getMessage();
        header("Location: ../public/register.php");
        exit;
    }

} else {
    header("Location: ../public/register.php");
    exit;
}

