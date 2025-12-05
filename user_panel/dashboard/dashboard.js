// ===== USER GUIDE - SIMPLE WORKING VERSION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading...');
    
    // Get the guide elements
    const guideSection = document.getElementById('userGuideSection');
    const toggleBtn = document.getElementById('toggleGuideBtn');
    const floatingBtn = document.getElementById('floatingShowGuide');
    
    // Check if elements exist
    if (!guideSection || !toggleBtn || !floatingBtn) {
        console.log('Guide elements not found, skipping guide functionality');
        return;
    }
    
    console.log('Guide elements found, initializing...');
    
    // Check if guide was hidden before
    const isGuideHidden = localStorage.getItem('guideHidden') === 'true';
    
    // Set initial state
    if (isGuideHidden) {
        // Guide was hidden - keep it hidden
        guideSection.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i> <span>Show Guide</span>';
        floatingBtn.style.display = 'flex'; // Show floating button
        console.log('Guide initialized as HIDDEN');
    } else {
        // Guide should be visible (first time)
        guideSection.style.display = 'block';
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> <span>Hide Guide</span>';
        floatingBtn.style.display = 'none'; // Hide floating button
        console.log('Guide initialized as VISIBLE');
    }
    
    // ===== CLICK TO HIDE GUIDE =====
    toggleBtn.addEventListener('click', function() {
        if (guideSection.style.display === 'none') {
            // Guide is hidden, so show it
            guideSection.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> <span>Hide Guide</span>';
            floatingBtn.style.display = 'none';
            localStorage.setItem('guideHidden', 'false');
            console.log('Guide SHOWN');
        } else {
            // Guide is visible, so hide it
            guideSection.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> <span>Show Guide</span>';
            floatingBtn.style.display = 'flex';
            localStorage.setItem('guideHidden', 'true');
            console.log('Guide HIDDEN');
        }
    });
    
    // ===== CLICK FLOATING BUTTON TO SHOW GUIDE =====
    floatingBtn.addEventListener('click', function() {
        guideSection.style.display = 'block';
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> <span>Hide Guide</span>';
        floatingBtn.style.display = 'none';
        localStorage.setItem('guideHidden', 'false');
        console.log('Guide shown from floating button');
        
        // Optional: Scroll to guide
        guideSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    console.log('User Guide functionality loaded successfully');
});

// ===== SIDEBAR TOGGLE (keep this) =====
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const sidebarLogo = document.getElementById('toggleSidebar');

function toggleSidebar() {
    if (window.innerWidth <= 900) sidebar.classList.toggle('show');
    else sidebar.classList.toggle('folded');
}

if (toggleBtn) {
    toggleBtn.addEventListener('click', toggleSidebar);
}
if (sidebarLogo) {
    sidebarLogo.addEventListener('click', toggleSidebar);
}

// Close sidebar on click outside (for mobile)
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 900 && sidebar && sidebar.classList.contains('show') && 
        !sidebar.contains(e.target) && toggleBtn && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// ===== LOGOUT CONFIRMATION (keep this) =====
function confirmLogout(event) {
    if (event) {
        event.preventDefault();
    }
    
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
    
    const cancelBtn = modal.querySelector('#logoutCancel');
    const confirmBtn = modal.querySelector('#logoutConfirm');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.body.removeChild(modal);
        });
    }
    
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            window.location.href = '../auth/logout.php';
        });
    }
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    });
    
    const closeOnEsc = function(e) {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', closeOnEsc);
        }
    };
    document.addEventListener('keydown', closeOnEsc);
}

// Add event listeners to logout links when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href*="logout"]').forEach(link => {
        if (link.getAttribute('href').includes('logout')) {
            link.addEventListener('click', confirmLogout);
        }
    });
});

// ===== SEARCH FUNCTIONALITY (Optional) =====
const searchInput = document.getElementById('globalSearch');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        }
    });
}