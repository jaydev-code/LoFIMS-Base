<?php
// admin_panel/auth/logout.php
session_start();

// Security headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Log the logout event
error_log("User logout - User ID: " . ($_SESSION['user_id'] ?? 'unknown') . 
          ", IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . 
          ", Time: " . date('Y-m-d H:i:s'));

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Also clear from $_COOKIE array
if (isset($_COOKIE[session_name()])) {
    unset($_COOKIE[session_name()]);
}

// Destroy the session
if (session_id() !== '') {
    session_destroy();
}

// Close session
session_write_close();

// Set a final redirect header for browsers without JavaScript
header("Refresh: 4; url=../../public/index.php");

// Now output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out | LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: floatIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .success-icon {
            margin: 0 auto 20px;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: successPulse 2s infinite;
        }
        
        .success-icon i {
            font-size: 32px;
            color: white;
        }
        
        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 30px rgba(40, 167, 69, 0.4);
            }
        }
        
        h2 {
            color: #1e2a38;
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .message {
            color: #666;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 25px;
            opacity: 0.8;
        }
        
        .progress-container {
            background: rgba(40, 167, 69, 0.1);
            border-radius: 10px;
            height: 8px;
            margin: 30px 0;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 10px;
            animation: progress 3s linear forwards;
        }
        
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        .countdown {
            font-size: 13px;
            color: #28a745;
            font-weight: 600;
            margin-top: 10px;
            display: block;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .security-badge {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }
        
        .security-badge i {
            color: #667eea;
            font-size: 16px;
        }
        
        .security-badge span {
            color: #1e2a38;
            font-size: 13px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h2>Successfully Logged Out</h2>
        <p class="message">
            You have been securely logged out of the admin panel.<br>
            Redirecting to home page...
        </p>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <span class="countdown" id="countdown">Redirecting in 3s</span>
        
        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Session Cleared • All Data Secured</span>
        </div>
        
        <a href="../../public/index.php" class="login-btn">
            <i class="fas fa-home"></i> Go to Home Page
        </a>
    </div>
    
    <script>
        // Countdown timer
        let seconds = 3;
        const countdownElement = document.getElementById('countdown');
        const countdownInterval = setInterval(() => {
            countdownElement.textContent = `Redirecting in ${seconds}s`;
            seconds--;
            
            if (seconds < 0) {
                clearInterval(countdownInterval);
                window.location.replace('../../public/index.php');
            }
        }, 1000);
        
        // Fallback redirect after 5 seconds
        setTimeout(() => {
            if (!window.location.href.includes('index.php')) {
                window.location.href = '../../public/index.php';
            }
        }, 5000);
    </script>
</body>
</html>