<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../user_panel/dashboard.php");
    exit;
}

// Ensure variables always exist
$formData = $_SESSION['register_form'] ?? [
    'first_name' => '',
    'last_name' => '',
    'student_id' => '',
    'email' => '',
    'contact_info' => ''
];

$error = $_SESSION['register_error'] ?? '';
$success = $_SESSION['register_success'] ?? '';

// Clear session messages
unset($_SESSION['register_form'], $_SESSION['register_error'], $_SESSION['register_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================= Basic Reset ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* =============== Animated Background Particles =============== */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            width: 400px;
            height: 400px;
            top: -100px;
            left: -100px;
            animation: floatParticles 20s linear infinite;
            z-index: 0;
        }

        body::after {
            width: 600px;
            height: 600px;
            top: 50%;
            left: 60%;
            background: rgba(255, 255, 255, 0.05);
            animation-duration: 30s;
        }

        @keyframes floatParticles {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, 100px) rotate(180deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }

        /* ================= Register Container ================= */
        .register-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 1;
            animation: floatUpDown 6s ease-in-out infinite;
            border: 1px solid rgba(255,255,255,0.3);
        }

        @keyframes floatUpDown {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }

        /* ================= Header ================= */
        .register-header {
            background: linear-gradient(135deg, #1e2a38 0%, #16212b 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .register-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
            background-size: 200% 100%;
            animation: gradientShift 4s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .logo i {
            font-size: 36px;
            color: #1e90ff;
            animation: logoBounce 2s ease-in-out infinite;
        }

        @keyframes logoBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(45deg, #1e90ff, #4facfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: textGlow 3s ease-in-out infinite;
        }

        @keyframes textGlow {
            0%,100% { text-shadow: 2px 2px 6px rgba(30,144,255,0.3); }
            50% { text-shadow: 2px 2px 12px rgba(30,144,255,0.6); }
        }

        .register-header p {
            color: #b0b0b0;
            font-size: 14px;
        }

        .register-form {
            padding: 35px;
            position: relative;
            z-index: 2;
        }

        /* ================= Form Steps ================= */
        .step {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e2a38;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e90ff;
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.15);
        }

        .form-control.error {
            border-color: #dc3545;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ================= Alerts ================= */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* ================= Buttons ================= */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #1e90ff, #4facfe);
            color: white;
            box-shadow: 0 6px 20px rgba(30, 144, 255, 0.3);
            animation: btnGlow 3s ease-in-out infinite;
        }

        @keyframes btnGlow {
            0%, 100% { box-shadow: 0 6px 20px rgba(30,144,255,0.3); }
            50% { box-shadow: 0 8px 30px rgba(30,144,255,0.6); }
        }

        .btn-primary:hover { transform: translateY(-2px); }

        .btn-secondary {
            background: transparent;
            color: #1e2a38;
            border: 2px solid #1e2a38;
        }

        .btn-secondary:hover {
            background: rgba(30, 42, 56, 0.1);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: #666;
            border: 2px solid #e1e5e9;
        }

        .btn-outline:hover { background: #f8f9fa; transform: translateY(-2px); }

        .buttons { display: flex; gap: 10px; margin-top: 20px; }
        .buttons .btn { flex: 1; }

        .login-link { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
        .login-link a { color: #1e90ff; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }

        /* ================= Password Strength ================= */
        .password-strength { margin-top: 5px; height: 5px; background: #e1e5e9; border-radius: 3px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; transition: all 0.3s; border-radius: 2px; }
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }

        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .step-dot { width: 10px; height: 10px; border-radius: 50%; background: #e1e5e9; transition: all 0.3s; }
        .step-dot.active { background: #1e90ff; transform: scale(1.3); }

        @media (max-width: 480px) {
            .register-container { margin: 10px; }
            .register-form { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <i class="fas fa-search"></i>
                <h1>LoFIMS</h1>
            </div>
            <p>Create Your Account</p>
        </div>

        <div class="register-form">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../auth/register_process.php" id="registerForm">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step-dot active" data-step="1"></div>
                    <div class="step-dot" data-step="2"></div>
                </div>

                <!-- Step 1: Personal Information -->
                <div class="step active" id="step1">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required
                                   value="<?= htmlspecialchars($formData['first_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                   value="<?= htmlspecialchars($formData['last_name']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_id"><i class="fas fa-id-card"></i> Student ID</label>
                        <div class="input-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" class="form-control" id="student_id" name="student_id"
                                   value="<?= htmlspecialchars($formData['student_id']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($formData['email']) ?>">
                        </div>
                    </div>

                    <div class="buttons">
                        <button type="button" class="btn btn-outline" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="next1">
                            <i class="fas fa-arrow-right"></i> Continue
                        </button>
                    </div>
                </div>

                <!-- Step 2: Account Setup -->
                <div class="step" id="step2">
                    <div class="form-group">
                        <label for="contact_info"><i class="fas fa-at"></i> Username</label>
                        <div class="input-icon">
                            <i class="fas fa-at"></i>
                            <input type="text" class="form-control" id="contact_info" name="contact_info" required
                                   value="<?= htmlspecialchars($formData['contact_info']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="contact_number"><i class="fas fa-phone"></i> Contact Number</label>
                        <div class="input-icon">
                            <i class="fas fa-phone"></i>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="error-message" id="passwordMatchError" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            Passwords do not match
                        </div>
                    </div>

                    <div class="buttons">
                        <button type="button" class="btn btn-secondary" id="back1">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </div>
                </div>

                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ================= Step Navigation =================
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const next1 = document.getElementById('next1');
        const back1 = document.getElementById('back1');
        const cancelBtn = document.getElementById('cancelBtn');
        const stepDots = document.querySelectorAll('.step-dot');
        const submitBtn = document.getElementById('submitBtn');

        // Password elements
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatchError = document.getElementById('passwordMatchError');
        const passwordStrength = document.getElementById('passwordStrength');

        next1.addEventListener('click', () => {
            step1.classList.remove('active');
            step2.classList.add('active');
            stepDots[0].classList.remove('active');
            stepDots[1].classList.add('active');
        });

        back1.addEventListener('click', () => {
            step2.classList.remove('active');
            step1.classList.add('active');
            stepDots[1].classList.remove('active');
            stepDots[0].classList.add('active');
        });

        cancelBtn.addEventListener('click', () => {
            window.location.href = 'login.php';
        });

        // ================= Password Strength =================
        password.addEventListener('input', () => {
            const val = password.value;
            let strength = 0;
            if (val.length >= 6) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            if (strength <= 1) passwordStrength.className = 'strength-bar strength-weak';
            else if (strength <= 3) passwordStrength.className = 'strength-bar strength-medium';
            else passwordStrength.className = 'strength-bar strength-strong';
        });

        confirmPassword.addEventListener('input', () => {
            if (confirmPassword.value && confirmPassword.value !== password.value) {
                passwordMatchError.style.display = 'flex';
                submitBtn.disabled = true;
            } else {
                passwordMatchError.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
