<!DOCTYPE html>
<html>
<head>
    <title>TUP Lost & Found System</title>
    <style>
        html, body { height: 100%; margin: 0; font-family: Arial, sans-serif; }
        body {
            display: flex; justify-content: center; align-items: center;
            background: url('assets/images/Background_Images.jpg') no-repeat center center fixed;
            background-size: cover; position: relative;
        }
        body::before {
            content: ""; position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; background: inherit; filter: blur(8px); z-index: 0;
        }
        .welcome-box {
            position: relative; z-index: 1; background-color: rgba(88,45,47,0.85);
            padding: 50px; border-radius: 10px; text-align: center; color: white;
        }
        .welcome-box h1 { font-size: 42px; margin-bottom: 20px; text-shadow: 2px 2px 8px rgba(0,0,0,0.7);}
        .welcome-box p { font-size: 18px; margin-bottom: 30px; }
        .welcome-box a {
            display: inline-block; margin: 10px; padding: 12px 25px; background: #ff4d4d;
            color: white; text-decoration: none; border-radius: 5px; font-weight: bold; transition: 0.2s;
        }
        .welcome-box a:hover { background: #b01f24; }
    </style>
</head>
<body>

<div class="welcome-box">
    <h1>Welcome to TUP Lost & Found System</h1>
    <p>Track, report, and claim lost or found items easily.</p>
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
    <a href="lost_items.php">View Lost Items</a>
    <a href="found_items.php">View Found Items</a>
</div>

</body>
</html>
