<?php
// admin_panel/auth/logout.php
session_start();

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// JavaScript to clear localStorage and redirect
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, #1e2a38, #2c3e50);
            font-family: Arial, sans-serif;
        }
        .logout-message {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1e90ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-message">
        <h2>Logging out...</h2>
        <p>You are being redirected to the home page.</p>
        <div class="spinner"></div>
    </div>
    
    <script>
        // Clear sidebar state from localStorage
        localStorage.removeItem('sidebarFolded');
        
        // Redirect to home page after 2 seconds
        setTimeout(function() {
            window.location.href = '../../public/index.php';
        }, 2000);
        
        // Immediate redirect as fallback
        setTimeout(function() {
            window.location.href = '../../public/index.php';
        }, 3000);
    </script>
</body>
</html>
<?php
// Fallback PHP redirect (in case JavaScript is disabled)
header("Refresh: 3; url=../../public/index.php");
exit();
?>