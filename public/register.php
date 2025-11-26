<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../user_panel/dashboard.php");
    exit;
}

$error = "";
$success = "";

if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}

if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>LoFIMS Registration</title>
    <style>
        html, body { height: 100%; margin: 0; font-family: Arial, sans-serif; }
        body {
            display:flex; justify-content:center; align-items:center;
            background:url('assets/images/Background_Images.jpg') no-repeat center center fixed;
            background-size:cover; position:relative;
        }
        body::before { content:""; position:absolute; top:0; left:0; width:100%; height:100%; background:inherit; filter:blur(8px); z-index:0; }
        .register-box { position:relative; z-index:1; width:350px; padding:30px; background-color:rgba(88,45,47,0.9); border-radius:10px; color:white; text-align:center; display:flex; flex-direction:column; justify-content:center; }
        .register-box input, .register-box button { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:none; }
        .register-box button { background-color:#8e1718; color:white; font-weight:bold; cursor:pointer; }
        .register-box button:hover { background-color:#b01f24; }
        .error { color:yellow; margin-bottom:10px; }
        .success { color:lightgreen; margin-bottom:10px; }
    </style>
</head>
<body>
<div class="register-box">
    <h2>Register</h2>
    <?php if($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <form method="POST" action="../auth/register_process.php">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="text" name="contact_info" placeholder="Email or Phone" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit">Register</button>
    </form>
    <p style="color:white;">Already have an account? <a href="login.php" style="color:lightblue;">Login</a></p>
</div>
</body>
</html>
