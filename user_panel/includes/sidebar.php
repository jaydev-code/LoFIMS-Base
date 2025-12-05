<?php
// includes/sidebar.php
// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);

// Define the base URL for navigation - adjust this based on your installation
$base_url = '/LoFIMS_BASE/user_panel/';
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS</span>
    </div>
    <ul>
        <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>dashboard.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <div class="tooltip">Dashboard</div>
        </li>
        <li class="<?php echo $current_page == 'lost_items.php' ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>lost_items.php" class="nav-link">
                <i class="fas fa-pencil-alt"></i>
                <span>Lost Items</span>
            </a>
            <div class="tooltip">Lost Items</div>
        </li>
        <li class="<?php echo $current_page == 'found_items.php' ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>found_items.php" class="nav-link">
                <i class="fas fa-box"></i>
                <span>Found Items</span>
            </a>
            <div class="tooltip">Found Items</div>
        </li>
        <li class="<?php echo $current_page == 'claims.php' ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>claims.php" class="nav-link">
                <i class="fas fa-hand-holding"></i>
                <span>Claims</span>
            </a>
            <div class="tooltip">Claims</div>
        </li>
        <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>announcements.php" class="nav-link">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <div class="tooltip">Announcements</div>
        </li>
        <li>
            <a href="#" id="logoutLink" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            <div class="tooltip">Logout</div>
        </li>
    </ul>
</div>

<script>
// Add this to handle logout properly
document.addEventListener('DOMContentLoaded', function() {
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            confirmLogout();
        });
    }
});

function confirmLogout() {
    // Create custom confirmation modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(4px);
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            border: 1px solid #e2e8f0;
        ">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-sign-out-alt" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <h3 style="margin: 0 0 10px 0; color: #1e293b;">Confirm Logout</h3>
                <p style="color: #64748b; margin: 0;">Are you sure you want to logout?</p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button id="logoutCancel" style="
                    padding: 12px 24px;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: white;
                    color: #475569;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                    flex: 1;
                ">
                    Cancel
                </button>
                <button id="logoutConfirm" style="
                    padding: 12px 24px;
                    border: none;
                    border-radius: 8px;
                    background: #ef4444;
                    color: white;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                    flex: 1;
                ">
                    Yes, Logout
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners
    const cancelBtn = modal.querySelector('#logoutCancel');
    const confirmBtn = modal.querySelector('#logoutConfirm');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.body.removeChild(modal);
        });
    }
    
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            // Redirect to logout page - use absolute path
           window.location.href = '/LoFIMS_BASE/public/logout.php';
        });
    }
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    });
    
    // Close on ESC key
    const closeOnEsc = function(e) {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', closeOnEsc);
        }
    };
    document.addEventListener('keydown', closeOnEsc);
}
</script>