<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
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

            header("Location: ../index.php");
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
        html, body {
            height: 100%;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: url('../../assets/images/Background_Images.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            filter: blur(8px);
            z-index: 0;
        }

        .welcome-title {
            position: absolute;
            top: 20px; /* near the top edge */
            width: 100%;
            text-align: center;
            z-index: 1;
            color: white;
            font-size: 48px;
            font-weight: bold;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.7);
        }

        .login-box {
    position: relative;
    z-index: 1;
    width: 350px;
    padding: 30px;
    background-color: rgba(88, 45, 47, 0.9);
    border-radius: 10px;
    box-shadow: 0px 8px 20px rgba(0,0,0,0.5);
    color: white;
    text-align: center;

    height: 260px; /* smaller box height */
    display: flex;
    flex-direction: column;
    justify-content: center; /* keeps inputs centered */
}

        .login-box input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: none;
            box-sizing: border-box;
        }

        .login-box button {
            width: 100%;
            padding: 10px;
            background-color: #8e1718;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .login-box button:hover {
            background-color: #b01f24;
        }

        .error {
            color: yellow;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="welcome-title">TUP Lost and Found System</div>

<div class="login-box">
    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
