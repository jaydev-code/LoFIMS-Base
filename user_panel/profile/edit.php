<?php
// ============================================
// PROFILE EDIT PAGE - EDITABLE FIELDS ONLY
// ============================================
session_start();
require_once __DIR__ . '/../../config/config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Validate user_id
$user_id = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if (!$user_id || $user_id <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Initialize variables
$errors = [];
$success = false;
$user = null;

// Get user data with FIXED fields (course, year, category, role)
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id, 
               u.contact_number, u.course, u.category, u.year, u.birthdate,
               u.notification_email, u.notification_sms,
               r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Profile edit error: " . $e->getMessage());
    die("Error loading profile");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize EDITABLE inputs only
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $notification_email = isset($_POST['notification_email']) ? 1 : 0;
    $notification_sms = isset($_POST['notification_sms']) ? 1 : 0;
    
    // Validation
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = "First name must be at least 2 characters";
    }
    
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = "Last name must be at least 2 characters";
    }
    
    if (!$email) {
        $errors[] = "Valid email is required";
    }
    
    // Check if email already exists (if changed)
    if ($email && $email !== $user['email']) {
        try {
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $checkStmt->execute([$email, $user_id]);
            if ($checkStmt->fetch()) {
                $errors[] = "Email already exists";
            }
        } catch(PDOException $e) {
            error_log("Email check error: " . $e->getMessage());
        }
    }
    
    // If no errors, update database (EDITABLE fields only)
    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, 
                    contact_number = ?, notification_email = ?, 
                    notification_sms = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $updateStmt->execute([
                $first_name, $last_name, $email, 
                $contact_number, $notification_email, 
                $notification_sms, $user_id
            ]);
            
            $success = true;
            
            // Update session user data
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['user_email'] = $email;
            
            // Refresh user data for display
            $user['first_name'] = $first_name;
            $user['last_name'] = $last_name;
            $user['email'] = $email;
            $user['contact_number'] = $contact_number;
            $user['notification_email'] = $notification_email;
            $user['notification_sms'] = $notification_sms;
            
        } catch(PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Profile - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        /* Same styling as other pages */
        .page-title {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .page-title i {
            color: #8b5cf6;
            background: #f5f3ff;
            padding: 10px;
            border-radius: 10px;
        }
        
        .profile-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            color: #1e293b;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .form-control[readonly] {
            background-color: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            color: #475569;
        }
        
        .user-avatar {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-icon {
            width: 100px;
            height: 100px;
            background: #f5f3ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: #8b5cf6;
        }
        
        .fixed-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #94a3b8;
            margin: 20px 0;
        }
        
        .fixed-info h4 {
            margin: 0 0 10px 0;
            color: #475569;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .fixed-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 3px;
        }
        
        .info-value {
            color: #1e293b;
            font-size: 15px;
        }
    </style>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
            <a href="view.php" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <i class="fas fa-user-circle"></i> 
                Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </a>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-user-edit"></i>
            Edit Profile
        </div>

        <!-- Success/Error Messages -->
        <?php if($success): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            Profile updated successfully!
        </div>
        <?php endif; ?>
        
        <?php if(!empty($errors)): ?>
        <div class="error-alert">
            <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Profile Form -->
        <div class="profile-container">
            <div class="user-avatar">
                <div class="avatar-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div style="font-size: 18px; font-weight: 600; color: #1e293b;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </div>
                <div style="color: #64748b; font-size: 14px;">
                    Student ID: <?php echo htmlspecialchars($user['student_id'] ?: 'Not set'); ?>
                </div>
            </div>
            
            <!-- Fixed Information (Cannot be changed) -->
            <div class="fixed-info">
                <h4><i class="fas fa-lock"></i> Fixed Information (Cannot be changed)</h4>
                <div class="fixed-info-grid">
                    <div class="info-item">
                        <div class="info-label">Student ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['student_id'] ?: 'Not set'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Course</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['course'] ?: 'Not set'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Year Level</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['year'] ? 'Year ' . $user['year'] : 'Not set'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?php echo htmlspecialchars(ucfirst($user['category'] ?: 'student')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['role_name'] ?: 'Student'); ?></div>
                    </div>
                </div>
                <p style="margin: 10px 0 0 0; font-size: 13px; color: #94a3b8;">
                    <i class="fas fa-info-circle"></i> To change fixed information, please contact the administrator.
                </p>
            </div>
            
            <form method="POST" action="" id="profileForm">
                <h3 style="margin: 25px 0 20px 0; color: #1e293b; font-size: 18px;">
                    <i class="fas fa-edit"></i> Editable Information
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" 
                               name="first_name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                               required
                               maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" 
                               name="last_name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                               required
                               maxlength="50">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Email Address</label>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               required
                               maxlength="100">
                        <small style="color: #64748b; font-size: 13px; margin-top: 5px; display: block;">
                            This will be used for account verification and notifications
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" 
                               name="contact_number" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>"
                               maxlength="20"
                               placeholder="e.g., 09123456789">
                        <small style="color: #64748b; font-size: 13px; margin-top: 5px; display: block;">
                            For SMS notifications and contact purposes
                        </small>
                    </div>
                </div>
                
                <!-- Notification Preferences -->
                <div class="form-group" style="margin-top: 30px;">
                    <h4 style="margin-bottom: 15px; color: #1e293b; font-size: 16px;">
                        <i class="fas fa-bell"></i> Notification Preferences
                    </h4>
                    <div class="checkbox-group">
                        <input type="checkbox" 
                               name="notification_email" 
                               id="notification_email" 
                               value="1" 
                               <?php echo ($user['notification_email'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="notification_email">Receive email notifications</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" 
                               name="notification_sms" 
                               id="notification_sms" 
                               value="1" 
                               <?php echo ($user['notification_sms'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="notification_sms">Receive SMS notifications</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            
            <!-- Password Change Link -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
                <p style="color: #64748b; margin-bottom: 15px;">
                    Need to change your password?
                </p>
                <a href="change-password.php" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Include the common footer with JavaScript -->
<?php require_once '../includes/footer.php'; ?>

<script>
// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const firstName = document.querySelector('input[name="first_name"]');
    const lastName = document.querySelector('input[name="last_name"]');
    const email = document.querySelector('input[name="email"]');
    let isValid = true;
    
    // Clear previous errors
    document.querySelectorAll('.form-control').forEach(input => {
        input.classList.remove('error');
        const errorSpan = input.nextElementSibling;
        if (errorSpan && errorSpan.classList.contains('error-message')) {
            errorSpan.remove();
        }
    });
    
    // Validate first name
    if (!firstName.value.trim() || firstName.value.trim().length < 2) {
        showError(firstName, 'First name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate last name
    if (!lastName.value.trim() || lastName.value.trim().length < 2) {
        showError(lastName, 'Last name must be at least 2 characters');
        isValid = false;
    }
    
    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value.trim())) {
        showError(email, 'Please enter a valid email address');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = document.querySelector('.form-control.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }
});

function showError(input, message) {
    input.classList.add('error');
    const errorSpan = document.createElement('span');
    errorSpan.className = 'error-message';
    errorSpan.textContent = message;
    input.parentNode.appendChild(errorSpan);
}

// Auto-hide success message after 5 seconds
const successMessage = document.querySelector('.success-message');
if (successMessage) {
    setTimeout(() => {
        successMessage.style.transition = 'opacity 0.5s';
        successMessage.style.opacity = '0';
        setTimeout(() => successMessage.remove(), 500);
    }, 5000);
}
</script>
</body>
</html>

