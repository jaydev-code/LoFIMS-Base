<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../user_panel/dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, role_id, password FROM users WHERE contact_info = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['name'] = $user['first_name'] . " " . $user['last_name'];

            header("Location: ../user_panel/dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>LoFIMS Login</title>
    <style>
        html, body { height: 100%; margin: 0; font-family: Arial, sans-serif; }
        body {
            display: flex; justify-content: center; align-items: center;
            background: url('assets/images/Background_Images.jpg') no-repeat center center fixed;
            background-size: cover; position: relative;
        }
        body::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: inherit; filter: blur(8px); z-index: 0; }
        .login-box { position: relative; z-index: 1; width: 350px; padding: 30px; background-color: rgba(88,45,47,0.9); border-radius: 10px; color:white; text-align:center; display:flex; flex-direction:column; justify-content:center; }
        .login-box input, .login-box button { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:none; }
        .login-box button { background-color: #8e1718; color:white; font-weight:bold; cursor:pointer; }
        .login-box button:hover { background-color: #b01f24; }
        .error { color: yellow; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="login-box">
    <h2>Login</h2>
    <?php if($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <form method="POST">
        <input type="text" name="email" placeholder="Email or Phone" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p style="color:white;">No account? <a href="register.php" style="color:lightblue;">Register</a></p>
</div>
</body>
</html>
