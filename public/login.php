<?php
// Start session AFTER including config (since config now sets session ini settings)
require_once __DIR__ . '/../config/config.php';
session_start(); // ← Moved AFTER config include

if (isset($_SESSION['user_id'])) {
    header("Location: ../user_panel/dashboard.php");
    exit;
}
// Initialize form data with empty string if not set
$formData = $_SESSION['login_form'] ?? [];
$username = $formData['username'] ?? '';

$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';
$forgot_password_error = $_SESSION['forgot_password_error'] ?? '';
$forgot_password_success = $_SESSION['forgot_password_success'] ?? '';

unset($_SESSION['login_form'], $_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['forgot_password_error'], $_SESSION['forgot_password_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - LoFIMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial', sans-serif; }
body {
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    background:#1e2a38;
    position:relative;
}

/* Aurora blobs with animated gradients */
.layer {
    position:absolute;
    border-radius:50%;
    width: 700px;
    height: 700px;
    top:-20%;
    left:-15%;
    background: radial-gradient(circle at center, #ff9a9e, #fad0c4);
    animation: floatLayer 30s linear infinite, gradientShift 15s ease-in-out infinite alternate;
    z-index:0;
}

.layer2 { width:500px; height:500px; top:40%; left:60%; animation-duration:35s, 18s; background: radial-gradient(circle at center, #a18cd1, #fbc2eb); }
.layer3 { width:300px; height:300px; top:60%; left:20%; animation-duration:28s, 20s; background: radial-gradient(circle at center, #fbc2eb, #a6c1ee); }

@keyframes floatLayer {
    0% { transform: translate(0,0) rotate(0deg); }
    25% { transform: translate(50px,-30px) rotate(90deg); }
    50% { transform: translate(-30px,40px) rotate(180deg); }
    75% { transform: translate(40px,20px) rotate(270deg); }
    100% { transform: translate(0,0) rotate(360deg); }
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Floating small circles */
.floating-circle {
    position:absolute;
    width:15px;
    height:15px;
    background: rgba(255,255,255,0.1);
    border-radius:50%;
    animation: floatCircle linear infinite;
}
.floating-circle:nth-child(1){top:10%; left:20%; animation-duration:18s;}
.floating-circle:nth-child(2){top:50%; left:70%; animation-duration:22s;}
.floating-circle:nth-child(3){top:70%; left:30%; animation-duration:20s;}

@keyframes floatCircle {
    0% { transform: translateY(0px) translateX(0px); opacity:0.5; }
    50% { transform: translateY(-20px) translateX(15px); opacity:0.8; }
    100% { transform: translateY(20px) translateX(-10px); opacity:0.5; }
}

/* Login container */
.login-container {
    background: white;
    backdrop-filter: blur(10px);
    border-radius:20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    overflow:hidden;
    width:100%;
    max-width:500px;
    position:relative;
    z-index:1;
    animation: floatUpDown 6s ease-in-out infinite;
}

@keyframes floatUpDown {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

.login-header {
    background: linear-gradient(135deg, #1e2a38 0%, #16212b 100%);
    color:white;
    padding:30px;
    text-align:center;
    position:relative;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.login-header::before {
    content:'';
    position:absolute;
    bottom:0;
    left:0;
    width:100%;
    height:4px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size:200% 100%;
    animation: gradientShift 3s ease infinite;
}

.login-header .logo {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:15px;
    margin-bottom:15px;
}

/* LOGO WITH WHITE BACKGROUND AND NO BORDER */
.login-header .logo img {
    height: 60px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.3));
    border-radius: 15px;
    background: white;
    padding: 5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.login-header .logo img:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(30, 144, 255, 0.3);
}

.login-header .logo h1 {
    font-size:28px;
    font-weight:900;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.login-header p { color:#b0b0b0; font-size:14px; }

.login-form{
    padding:30px;
    background: white;
}
.form-group{
    margin-bottom:20px;
    position:relative;
}
.form-group label {
    display:block;
    margin-bottom:8px;
    font-weight:600;
    color:#1e2a38;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:8px;
}
.form-control{
    width:100%;
    padding:12px 15px;
    border:2px solid #e1e5e9;
    border-radius:10px;
    font-size:14px;
    transition: all 0.3s;
    background:#f8f9fa;
    color:#1e2a38;
}
.form-control::placeholder {
    color: #64748b;
}
.form-control:focus{
    outline:none;
    border-color:#1e90ff;
    background: white;
    box-shadow:0 0 0 3px rgba(30,144,255,0.1);
}

/* Password toggle eye */
.password-wrapper {
    position: relative;
}
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #64748b;
    background: none;
    border: none;
    font-size: 16px;
}

.buttons{
    display:flex; 
    gap:10px; 
    margin-top:20px;
}
.buttons .btn{
    flex:1;
    padding:12px 25px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition: all 0.3s;
}
.btn-primary {
    background: linear-gradient(45deg,#1e90ff,#4facfe);
    color:white;
    border:none;
    box-shadow:0 4px 15px rgba(30,144,255,0.3);
}
.btn-primary:hover:not(:disabled) { 
    transform:translateY(-2px); 
    box-shadow:0 6px 20px rgba(30,144,255,0.4);
}
.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.btn-secondary{
    background:transparent;
    color:#1e2a38;
    border:2px solid #1e2a38;
}
.btn-secondary:hover{
    background:rgba(30,42,56,0.1); 
    transform:translateY(-2px);
}

.alert {
    padding:12px 15px; 
    border-radius:8px; 
    margin-bottom:20px; 
    font-size:14px; 
    display:flex; 
    align-items:center; 
    gap:10px;
}
.alert-error{
    background:#f8d7da;
    color:#721c24;
    border:1px solid #f5c6cb;
}
.alert-success{
    background:#d4edda;
    color:#155724;
    border:1px solid #c3e6cb;
}

/* Birthdate picker styling */
.birthdate-wrapper {
    position: relative;
}
.birthdate-wrapper:after {
    content: '\f073';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}

@media(max-width:480px){
    .login-container{margin:10px;}
    .login-form{padding:20px;}
    .buttons{flex-direction:column;}
    .login-header .logo img {
        height: 50px;
    }
    .login-header .logo h1 {
        font-size: 24px;
    }
}

/* Particle canvas overlay */
#particle-canvas {
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    pointer-events:none;
    z-index:0;
}

/* Flatpickr custom styling */
.flatpickr-calendar {
    font-family: 'Arial', sans-serif !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
    border-radius: 10px !important;
    border: none !important;
}

.flatpickr-month {
    background: #1e2a38 !important;
    border-radius: 10px 10px 0 0 !important;
    height: 50px !important;
}

.flatpickr-current-month {
    padding-top: 10px !important;
    font-size: 16px !important;
    color: white !important;
}

.flatpickr-weekdays {
    background: #f1f5f9 !important;
}

.flatpickr-day.selected {
    background: #1e90ff !important;
    border-color: #1e90ff !important;
}

.flatpickr-day.today {
    border-color: #1e90ff !important;
}

.flatpickr-day:hover {
    background: #e2e8f0 !important;
}

.flatpickr-months .flatpickr-prev-month, 
.flatpickr-months .flatpickr-next-month {
    color: white !important;
    fill: white !important;
    top: 10px !important;
}

.flatpickr-months .flatpickr-prev-month:hover, 
.flatpickr-months .flatpickr-next-month:hover {
    color: #a6c1ee !important;
    fill: #a6c1ee !important;
}

.flatpickr-year-select {
    font-weight: 600 !important;
}

/* Year quick select buttons */
.year-quick-select {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
    padding: 10px;
    background: #f1f5f9;
    border-radius: 8px;
    border-left: 3px solid #1e90ff;
}

.year-quick-select button {
    padding: 5px 10px;
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
    color: #1e2a38;
}

.year-quick-select button:hover {
    background: #e2e8f0;
}

/* Forgot Password Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    margin-bottom: 20px;
    text-align: center;
}

.modal-header h3 {
    font-size: 22px;
    color: #1e2a38;
    margin-bottom: 8px;
}

.modal-header p {
    color: #64748b;
    font-size: 14px;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 20px;
    color: #64748b;
    cursor: pointer;
    transition: color 0.3s;
}

.close-modal:hover {
    color: #1e90ff;
}

.forgot-password-form .form-group {
    margin-bottom: 15px;
}

.btn-block {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
}

.forgot-password-link {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e1e5e9;
}

.forgot-password-link a {
    color: #1e90ff;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.forgot-password-link a:hover {
    text-decoration: underline;
}

.security-verification {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #1e90ff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.security-verification h4 {
    color: #1e2a38;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.security-note {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #856404;
}

.security-note i {
    color: #f39c12;
}

.temp-password-display {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    padding: 12px;
    border-radius: 5px;
    margin-top: 15px;
    text-align: center;
    font-family: monospace;
    font-size: 16px;
    font-weight: bold;
    color: #0c5460;
}

.password-strength {
    margin-top: 5px;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0%;
    background: #28a745;
    transition: width 0.3s;
}

.verification-step {
    display: none;
}

.verification-step.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    position: relative;
}

.step-indicator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 10%;
    right: 10%;
    height: 2px;
    background: #e1e5e9;
    transform: translateY(-50%);
    z-index: 1;
}

.step {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e1e5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-weight: bold;
    position: relative;
    z-index: 2;
    transition: all 0.3s;
}

.step.active {
    background: #1e90ff;
    color: white;
    box-shadow: 0 0 0 4px rgba(30, 144, 255, 0.2);
}

.step.completed {
    background: #28a745;
    color: white;
}

.step-label {
    position: absolute;
    top: 35px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    color: #64748b;
    white-space: nowrap;
}

.loading-spinner {
    display: none;
    text-align: center;
    margin: 10px 0;
    padding: 10px;
}

.loading-spinner i {
    font-size: 20px;
    color: #1e90ff;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Logo in modal - WHITE BACKGROUND NO BORDER */
.modal-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 15px;
}

.modal-logo img {
    height: 40px;
    width: auto;
    border-radius: 10px;
    background: white;
    padding: 3px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.modal-logo h4 {
    font-size: 18px;
    color: #1e2a38;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Success state styles */
.success-close-btn {
    margin-top: 20px;
}

.success-actions {
    text-align: center;
    margin-top: 20px;
}

.copy-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.copy-btn:hover {
    background: #218838;
}

/* Hides the form when success is shown */
.form-hidden {
    display: none !important;
}

/* Styling for success message content */
.success-message-content {
    text-align: center;
}

.success-message-content strong {
    color: #1e2a38;
    display: block;
    margin: 10px 0;
}

.success-message-content code {
    background: #1e2a38;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    font-size: 18px;
    font-weight: bold;
    display: inline-block;
    margin: 10px 0;
    font-family: monospace;
}

.success-message-content p {
    color: #64748b;
    margin-top: 15px;
    font-size: 14px;
}
</style>
</head>
<body>
    <!-- Aurora layers -->
    <div class="layer layer1"></div>
    <div class="layer layer2"></div>
    <div class="layer layer3"></div>

    <!-- Floating small circles -->
    <div class="floating-circle"></div>
    <div class="floating-circle"></div>
    <div class="floating-circle"></div>

    <!-- Particle canvas -->
    <canvas id="particle-canvas"></canvas>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <img src="../assets/images/lofims-logo.png" alt="LoFIMS Logo">
                <h1>LoFIMS</h1>
            </div>
            <p>Sign in to your account</p>
        </div>

        <div class="login-form">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if($forgot_password_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($forgot_password_error) ?>
                </div>
            <?php endif; ?>

            <?php if($forgot_password_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($forgot_password_success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../auth/login_process.php" id="loginForm">
                <!-- Username Field -->
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" class="form-control" id="username" name="username" required
                        value="<?= htmlspecialchars($username) ?>"
                        placeholder="TUPQ-00-0000">
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password" required
                            placeholder="Enter your password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Birthdate Field with enhanced calendar -->
                <div class="form-group">
                    <label for="birthdate"><i class="fas fa-calendar-alt"></i> Birthdate</label>
                    <div class="birthdate-wrapper">
                        <input type="text" class="form-control" id="birthdate" name="birthdate" required
                            placeholder="YYYY-MM-DD" readonly>
                    </div>
                </div>

                <div class="buttons">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../public/index.php'">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>

                <div class="forgot-password-link">
                    <a href="#" id="forgotPasswordLink">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- SIMPLIFIED Forgot Password Modal -->
    <div class="modal-overlay" id="forgotPasswordModal">
        <div class="modal-content">
            <button class="close-modal" id="closeModal">&times;</button>
            
            <div class="modal-header">
                <div class="modal-logo">
                    <img src="../assets/images/lofims-logo.png" alt="LoFIMS Logo" style="height: 35px;">
                    <h4>Password Reset</h4>
                </div>
                <p>Reset your forgotten password</p>
            </div>

            <?php if($forgot_password_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($forgot_password_error) ?>
                </div>
            <?php endif; ?>

            <?php if($forgot_password_success): ?>
                <!-- Success message with clean display - FORM IS COMPLETELY HIDDEN -->
                <div class="success-message-content">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($forgot_password_success) ?>
                    </div>
                    
                    <button type="button" class="copy-btn" id="copyPasswordBtn">
                        <i class="fas fa-copy"></i> Copy Password
                    </button>
                    
                    <div class="buttons success-close-btn">
                        <button type="button" class="btn btn-primary btn-block" id="closeAfterSuccess">
                            <i class="fas fa-check"></i> Close & Login
                        </button>
                    </div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Hide the form when success is shown
                    document.getElementById('forgotPasswordForm').style.display = 'none';
                    
                    // Add copy password functionality
                    document.getElementById('copyPasswordBtn').addEventListener('click', function() {
                        const successMessage = document.querySelector('.alert-success').textContent;
                        const tempPasswordMatch = successMessage.match(/<code>(.*?)<\/code>/);
                        
                        if (tempPasswordMatch && tempPasswordMatch[1]) {
                            navigator.clipboard.writeText(tempPasswordMatch[1]).then(function() {
                                alert('Temporary password copied to clipboard!');
                            });
                        }
                    });
                    
                    // Add click handler for close button
                    document.getElementById('closeAfterSuccess').addEventListener('click', function() {
                        closeForgotPasswordModal();
                        // Focus on username in main form
                        document.getElementById('username').focus();
                    });
                });
                </script>
            <?php else: ?>
                <!-- Only show the form if there's no success message -->
                <form method="POST" action="../auth/forgot_password_process.php" id="forgotPasswordForm">
                    <div class="form-group">
                        <label for="recovery_username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" class="form-control" id="recovery_username" name="username" required
                            placeholder="TUPQ-00-0000">
                    </div>
                    
                    <div class="form-group">
                        <label for="verify_birthdate"><i class="fas fa-calendar-alt"></i> Birthdate</label>
                        <div class="birthdate-wrapper">
                            <input type="text" class="form-control" id="verify_birthdate" name="birthdate" required
                                placeholder="YYYY-MM-DD" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_reset" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="confirm_reset" name="confirm_reset" required style="margin-top: 3px;">
                            <span>I understand my password will be reset to a temporary one that I must change after login.</span>
                        </label>
                    </div>

                    <div class="loading-spinner" id="forgotPasswordLoading">
                        <i class="fas fa-spinner fa-spin"></i> Processing your request...
                    </div>
                    
                    <div class="buttons">
                        <button type="button" class="btn btn-secondary btn-block" id="cancelForgotPassword">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-block" id="submitForgotPassword">
                            <i class="fas fa-sync-alt"></i> Reset Password Now
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

<!-- Include Flatpickr library -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// Particle system
const canvas = document.getElementById('particle-canvas');
const ctx = canvas.getContext('2d');
let particles = [];
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});

const particleCount = 50;

// Initialize particles
for(let i=0;i<particleCount;i++){
    particles.push({
        x: Math.random()*canvas.width,
        y: Math.random()*canvas.height,
        size: Math.random()*4+1,
        speedX: (Math.random()-0.5)*0.5,
        speedY: (Math.random()-0.5)*0.5,
        color: `rgba(255,255,255,${Math.random()*0.3+0.1})`
    });
}

// Draw particles
function drawParticles(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    particles.forEach(p=>{
        ctx.beginPath();
        ctx.arc(p.x,p.y,p.size,0,Math.PI*2);
        ctx.fillStyle=p.color;
        ctx.fill();
        p.x+=p.speedX;
        p.y+=p.speedY;

        if(p.x<0||p.x>canvas.width) p.speedX*=-1;
        if(p.y<0||p.y>canvas.height) p.speedY*=-1;
    });
    requestAnimationFrame(drawParticles);
}

drawParticles();

// Particle trail effect
canvas.addEventListener('mousemove', e=>{
    particles.push({
        x:e.clientX,
        y:e.clientY,
        size:Math.random()*3+1,
        speedX:(Math.random()-0.5)*0.5,
        speedY:(Math.random()-0.5)*0.5,
        color:`rgba(255,255,255,0.3)`
    });
    if(particles.length>100) particles.splice(0, particles.length-100);
});

// Password toggle functionality
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Enhanced Date Picker with Flatpickr for main login
const birthdatePicker = flatpickr("#birthdate", {
    dateFormat: "Y-m-d",
    maxDate: "today",
    yearRange: [1970, new Date().getFullYear()],
    minDate: "1900-01-01",
    allowInput: false,
    showClearButton: false,
    nextArrow: '<i class="fas fa-chevron-right"></i>',
    prevArrow: '<i class="fas fa-chevron-left"></i>',
    theme: "light",
    disableMobile: true,
    clickOpens: true,
    static: false,
    locale: {
        firstDayOfWeek: 0
    }
});

// Also add a manual year selector button
const yearQuickSelect = document.createElement('div');
yearQuickSelect.className = 'year-quick-select';

const commonYears = ['2000', '2001', '2002', '2003', '2004', '2005', '2006', '2007', '2008', '2009', '2010'];

commonYears.forEach(year => {
    const yearBtn = document.createElement('button');
    yearBtn.type = 'button';
    yearBtn.textContent = year;
    yearBtn.onclick = () => {
        birthdatePicker.setDate(year + '-01-01');
        birthdatePicker.open();
    };
    yearQuickSelect.appendChild(yearBtn);
});

// Insert after the birthdate wrapper
document.querySelector('.birthdate-wrapper').parentNode.insertBefore(yearQuickSelect, document.querySelector('.birthdate-wrapper').nextSibling);

// Form validation for main login
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const birthdate = document.getElementById('birthdate').value;
    const submitBtn = document.getElementById('submitBtn');
    
    // Validate username format
    const usernamePattern = /^TUPQ-(00|22|23|24|25)-\d{4}$/;
    if (!usernamePattern.test(username)) {
        e.preventDefault();
        alert('Invalid username format!\n\nCorrect format: TUPQ-YY-NNNN\nAllowed YY: 00, 22, 23, 24, or 25\nExample: TUPQ-00-0000 or TUPQ-23-1234');
        document.getElementById('username').focus();
        return false;
    }
    
    // Validate password
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long.');
        document.getElementById('password').focus();
        return false;
    }
    
    // Validate birthdate
    if (!birthdate) {
        e.preventDefault();
        alert('Please select your birthdate.');
        birthdatePicker.open();
        return false;
    }
    
    // Change button text to show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    submitBtn.disabled = true;
    
    return true;
});

// Forgot Password Modal Functionality
const forgotPasswordModal = document.getElementById('forgotPasswordModal');
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
const closeModal = document.getElementById('closeModal');
const cancelForgotPassword = document.getElementById('cancelForgotPassword');
const forgotPasswordForm = document.getElementById('forgotPasswordForm');
const recoveryUsername = document.getElementById('recovery_username');
const submitForgotPassword = document.getElementById('submitForgotPassword');
const forgotPasswordLoading = document.getElementById('forgotPasswordLoading');

// Flatpickr for birthdate verification
const verifyBirthdatePicker = flatpickr("#verify_birthdate", {
    dateFormat: "Y-m-d",
    maxDate: "today",
    minDate: "1900-01-01",
    yearRange: [1970, new Date().getFullYear()],
    allowInput: false,
    showClearButton: false,
    nextArrow: '<i class="fas fa-chevron-right"></i>',
    prevArrow: '<i class="fas fa-chevron-left"></i>',
    theme: "light",
    disableMobile: true
});

// Open modal when forgot password link is clicked
forgotPasswordLink.addEventListener('click', function(e) {
    e.preventDefault();
    forgotPasswordModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Check if there's already a success message
    const hasSuccess = <?php echo $forgot_password_success ? 'true' : 'false'; ?>;
    if (!hasSuccess) {
        recoveryUsername.focus();
    }
});

// Close modal functions
function closeForgotPasswordModal() {
    forgotPasswordModal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Reset form only if it exists and is visible
    if (forgotPasswordForm && forgotPasswordForm.style.display !== 'none') {
        forgotPasswordForm.reset();
    }
    
    // Reset loading spinner
    if (forgotPasswordLoading) {
        forgotPasswordLoading.style.display = 'none';
    }
    
    // Reset submit button
    if (submitForgotPassword) {
        submitForgotPassword.disabled = false;
        submitForgotPassword.innerHTML = '<i class="fas fa-sync-alt"></i> Reset Password Now';
    }
}

closeModal.addEventListener('click', closeForgotPasswordModal);
cancelForgotPassword.addEventListener('click', closeForgotPasswordModal);

// Close modal when clicking outside
forgotPasswordModal.addEventListener('click', function(e) {
    if (e.target === forgotPasswordModal) {
        closeForgotPasswordModal();
    }
});

// Forgot password form submission
if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', function(e) {
        console.log("Form submit triggered");
        
        const username = recoveryUsername.value.trim();
        const birthdate = document.getElementById('verify_birthdate').value;
        const confirmReset = document.getElementById('confirm_reset').checked;
        
        console.log("Username:", username);
        console.log("Birthdate:", birthdate);
        console.log("Confirm Reset:", confirmReset);
        
        // Validate username format
        const usernamePattern = /^TUPQ-(00|22|23|24|25)-\d{4}$/;
        if (!usernamePattern.test(username)) {
            e.preventDefault();
            alert('Invalid username format!\n\nCorrect format: TUPQ-YY-NNNN\nExample: TUPQ-00-0000 or TUPQ-23-1234');
            recoveryUsername.focus();
            return false;
        }
        
        if (!birthdate) {
            e.preventDefault();
            alert('Please select your birthdate.');
            verifyBirthdatePicker.open();
            return false;
        }
        
        if (!confirmReset) {
            e.preventDefault();
            alert('Please confirm that you understand the password will be reset.');
            return false;
        }
        
        console.log("All validations passed, showing loading...");
        
        // Show loading
        forgotPasswordLoading.style.display = 'block';
        submitForgotPassword.disabled = true;
        submitForgotPassword.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        console.log("Form will submit now");
        
        // Allow the form to submit normally
        return true;
    });
}

// Auto-focus username field on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('username').focus();
    
    // If there's a forgot password message, open the modal
    <?php if($forgot_password_error || $forgot_password_success): ?>
        forgotPasswordModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    <?php endif; ?>
});

// Enter key to submit form
document.getElementById('password').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('loginForm').submit();
    }
});

// Also trigger calendar on icon click
document.querySelector('.birthdate-wrapper').addEventListener('click', function(e) {
    if (e.target === this || e.target.classList.contains('fa-calendar-alt')) {
        birthdatePicker.open();
    }
});

// Escape key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && forgotPasswordModal.style.display === 'flex') {
        closeForgotPasswordModal();
    }
});

// Add year quick select buttons for verify birthdate too
const verifyYearQuickSelect = document.createElement('div');
verifyYearQuickSelect.className = 'year-quick-select';

commonYears.forEach(year => {
    const yearBtn = document.createElement('button');
    yearBtn.type = 'button';
    yearBtn.textContent = year;
    yearBtn.onclick = () => {
        verifyBirthdatePicker.setDate(year + '-01-01');
        verifyBirthdatePicker.open();
    };
    verifyYearQuickSelect.appendChild(yearBtn);
});

// Insert after the verify birthdate wrapper
document.querySelector('#verify_birthdate').parentNode.parentNode.insertBefore(verifyYearQuickSelect, document.querySelector('#verify_birthdate').parentNode.nextSibling);

// Open calendar when clicking on the birthdate field
document.getElementById('verify_birthdate').addEventListener('click', function() {
    verifyBirthdatePicker.open();
});
</script>
</body>
</html>