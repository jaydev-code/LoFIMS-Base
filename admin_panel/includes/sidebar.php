<style>
/* Sidebar */
#responsive-sidebar {
    width: 250px;
    background: #0a3d62;
    position: fixed;
    left: 0;
    top: 60px; /* Start below header */
    bottom: 0;
    z-index: 9998;
    color: white;
    transition: left 0.3s ease;
    overflow-y: auto;
}

/* Mobile: sidebar hides */
@media (max-width: 900px) {
    #responsive-sidebar {
        left: -250px;
    }
    
    #responsive-sidebar.mobile-open {
        left: 0;
        box-shadow: 2px 0 15px rgba(0,0,0,0.2);
    }
}
</style>

<!-- Sidebar -->
<div id="responsive-sidebar">
    <!-- Your existing sidebar content -->
    <div class="logo toggle-btn">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li class="active" data-page="dashboard.php">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </li>
        <li data-page="manage_users.php">
            <i class="fas fa-users"></i>
            <span>Manage Users</span>
        </li>
        <li data-page="reports.php">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </li>
        <li data-page="categories.php">
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </li>
        <li data-page="announcements.php">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </li>
        <li onclick="saveSidebarState(); window.location.href='claims.php'">
            <i class="fas fa-handshake"></i>
            <span>Manage Claims</span>
        </li>
        <li data-page="logout.php">
            <i class="fas fa-right-from-bracket"></i>
            <span>Logout</span>
        </li>
    </ul>
</div>

<script>
// Mobile toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('responsive-sidebar');
    const hamburger = document.getElementById('mobileHamburger');
    
    if (hamburger && sidebar) {
        // Toggle sidebar
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('mobile-open');
            }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 900 && sidebar.classList.contains('mobile-open')) {
                if (!sidebar.contains(e.target) && e.target !== hamburger) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 900) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        // Close sidebar when clicking menu items (on mobile)
        const menuItems = sidebar.querySelectorAll('li');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 900) {
                    setTimeout(() => {
                        sidebar.classList.remove('mobile-open');
                    }, 300);
                }
            });
        });
    }
});
</script>