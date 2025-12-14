<?php
// ============================================
// PROFILE VIEW PAGE
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

// Get user data
try {
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, student_id, 
               contact_number, course, category, year, birthdate,
               notification_email, notification_sms, created_at,
               email_verified, phone_verified
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Profile view error: " . $e->getMessage());
    die("Error loading profile");
}

// Get user statistics
try {
    // Lost items count
    $lostStmt = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ?");
    $lostStmt->execute([$user_id]);
    $lost_count = $lostStmt->fetchColumn();
    
    // Found items count
    $foundStmt = $pdo->prepare("SELECT COUNT(*) FROM found_items WHERE user_id = ?");
    $foundStmt->execute([$user_id]);
    $found_count = $foundStmt->fetchColumn();
    
    // Claims count
    $claimsStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE user_id = ?");
    $claimsStmt->execute([$user_id]);
    $claims_count = $claimsStmt->fetchColumn();
    
    // Recovery rate
    $recoveryStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM claims WHERE user_id = ? AND status = 'completed') * 100.0 /
            NULLIF((SELECT COUNT(*) FROM claims WHERE user_id = ?), 0) as recovery_rate
    ");
    $recoveryStmt->execute([$user_id, $user_id]);
    $recovery_rate = $recoveryStmt->fetchColumn();
    $recovery_rate = $recovery_rate ? round($recovery_rate, 1) : 0;
    
} catch(PDOException $e) {
    error_log("Profile stats error: " . $e->getMessage());
    $lost_count = $found_count = $claims_count = $recovery_rate = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Profile - LoFIMS</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }
        
        .profile-info h2 {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 24px;
        }
        
        .profile-info p {
            margin: 0 0 5px 0;
            color: #64748b;
        }
        
        .member-since {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            color: #475569;
            margin-top: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            border-color: #8b5cf6;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #8b5cf6;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .detail-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .detail-section h3 {
            margin: 0 0 15px 0;
            color: #1e293b;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .detail-section h3 i {
            color: #8b5cf6;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .info-value {
            color: #1e293b;
            font-size: 15px;
            padding: 8px 0;
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .unverified {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            justify-content: center;
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
        
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .quick-action-btn {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            border-color: #8b5cf6;
        }
        
        .quick-action-btn i {
            font-size: 24px;
            color: #8b5cf6;
        }
        
        .quick-action-btn span {
            font-weight: 600;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-details {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
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
            <i class="fas fa-user-circle"></i> 
            Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-user"></i>
            My Profile
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="view.php" class="quick-action-btn" style="border-left: 4px solid #8b5cf6;">
                <i class="fas fa-user"></i>
                <span>View Profile</span>
            </a>
            <a href="edit.php" class="quick-action-btn">
                <i class="fas fa-user-edit"></i>
                <span>Edit Profile</span>
            </a>
            <a href="../dashboard.php" class="quick-action-btn">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../lost_items.php" class="quick-action-btn">
                <i class="fas fa-exclamation-circle"></i>
                <span>Lost Items</span>
            </a>
        </div>

        <!-- Profile Container -->
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php 
                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                    echo $initials;
                    ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['student_id'] ?: 'No Student ID'); ?></p>
                    <span class="member-since">
                        <i class="far fa-calendar"></i>
                        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </span>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='../lost_items.php'">
                    <div class="stat-number"><?php echo $lost_count; ?></div>
                    <div class="stat-label">Lost Items</div>
                </div>
                <div class="stat-card" onclick="window.location.href='../found_items.php'">
                    <div class="stat-number"><?php echo $found_count; ?></div>
                    <div class="stat-label">Found Items</div>
                </div>
                <div class="stat-card" onclick="window.location.href='../claims.php'">
                    <div class="stat-number"><?php echo $claims_count; ?></div>
                    <div class="stat-label">Claims Filed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $recovery_rate; ?>%</div>
                    <div class="stat-label">Recovery Rate</div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <!-- Personal Information -->
                <div class="detail-section">
                    <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    
                    <div class="info-group">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Email Address</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($user['email']); ?>
                            <span class="verification-badge <?php echo $user['email_verified'] ? 'verified' : 'unverified'; ?>">
                                <i class="fas fa-<?php echo $user['email_verified'] ? 'check' : 'times'; ?>"></i>
                                <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($user['contact_number'] ?: 'Not provided'); ?>
                            <?php if($user['contact_number']): ?>
                            <span class="verification-badge <?php echo $user['phone_verified'] ? 'verified' : 'unverified'; ?>">
                                <i class="fas fa-<?php echo $user['phone_verified'] ? 'check' : 'times'; ?>"></i>
                                <?php echo $user['phone_verified'] ? 'Verified' : 'Unverified'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Student ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['student_id'] ?: 'Not provided'); ?></div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="detail-section">
                    <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                    
                    <div class="info-group">
                        <div class="info-label">Course</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['course'] ?: 'Not specified'); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Year Level</div>
                        <div class="info-value">
                            <?php 
                            if ($user['year']) {
                                echo 'Year ' . htmlspecialchars($user['year']);
                            } else {
                                echo 'Not specified';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?php echo htmlspecialchars(ucfirst($user['category'] ?: 'student')); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Birthdate</div>
                        <div class="info-value">
                            <?php 
                            if ($user['birthdate']) {
                                echo date('F d, Y', strtotime($user['birthdate']));
                            } else {
                                echo 'Not provided';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="detail-section">
                    <h3><i class="fas fa-cog"></i> Account Settings</h3>
                    
                    <div class="info-group">
                        <div class="info-label">Account Created</div>
                        <div class="info-value"><?php echo date('F d, Y \a\t h:i A', strtotime($user['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">User ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Notification Preferences</div>
                        <div class="info-value">
                            <div style="margin: 8px 0;">
                                <i class="fas fa-envelope" style="color: <?php echo $user['notification_email'] ? '#10b981' : '#94a3b8'; ?>; margin-right: 8px;"></i>
                                Email Notifications: <?php echo $user['notification_email'] ? 'Enabled' : 'Disabled'; ?>
                            </div>
                            <div>
                                <i class="fas fa-sms" style="color: <?php echo $user['notification_sms'] ? '#10b981' : '#94a3b8'; ?>; margin-right: 8px;"></i>
                                SMS Notifications: <?php echo $user['notification_sms'] ? 'Enabled' : 'Disabled'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="edit.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
                <a href="change-password.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Include the common footer with JavaScript -->
<?php require_once '../includes/footer.php'; ?>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effect to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add click animation to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});
</script>
</body>
</html>
