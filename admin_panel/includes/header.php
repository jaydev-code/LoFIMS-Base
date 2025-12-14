<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- WORKING HEADER WITH CORRECT LOGO PATH -->
<style>
/* Header styles */
.admin-main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 9999;
    border-bottom: 2px solid #1e90ff;
}

/* Logo container */
.header-logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Logo image - USING WORKING PATH */
.header-logo-image {
    height: 45px;
    width: 45px;
    border-radius: 10px;
    border: 3px solid #1e90ff;
    padding: 3px;
    background: white;
    display: block;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

/* Mobile hamburger */
.mobile-hamburger-btn {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 10000;
    background: #0a3d62;
    color: white;
    border: none;
    border-radius: 5px;
    padding: 10px 12px;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Header spacer */
.header-spacing {
    height: 70px;
    width: 100%;
}

/* Desktop: header after sidebar */
@media (min-width: 901px) {
    .admin-main-header {
        padding-left: 270px; /* Space for sidebar */
        transition: padding-left 0.3s ease;
    }
}

/* Mobile styles */
@media (max-width: 900px) {
    .mobile-hamburger-btn {
        display: block;
    }
    
    .admin-main-header {
        padding-left: 60px; /* Space for hamburger */
    }
}
</style>

<!-- THE HEADER - Using WORKING path -->
<div class="admin-main-header">
    <div class="header-logo-area">
        <!-- LOGO WITH WORKING PATH -->
        <img src="../assets/images/lofims-logo.png" 
             alt="LoFIMS Logo" 
             class="header-logo-image"
             onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
        
        <div id="logo-fallback" style="display: none; width: 45px; height: 45px; background: linear-gradient(45deg, #1e90ff, #4facfe); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 3px solid #1e90ff;">
            LF
        </div>
        
        <div>
            <div style="font-weight: bold; font-size: 18px; color: #0a3d62;">LoFIMS</div>
            <div style="font-size: 12px; color: #666;">Admin Panel</div>
        </div>
    </div>
    
    <div>
        <span style="color: #0a3d62; font-size: 14px; background: rgba(30,144,255,0.1); padding: 5px 12px; border-radius: 20px;">
            <i class="fas fa-user-circle" style="color: #1e90ff;"></i>
            <?php 
            $name = isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin';
            echo $name;
            ?>
        </span>
    </div>
</div>

<!-- Mobile hamburger button -->
<button class="mobile-hamburger-btn" id="mobileHamburger">
    <i class="fas fa-bars"></i>
</button>

<!-- Space so content doesn't hide under header -->
<div class="header-spacing"></div>