    </div> <!-- Close page-content -->
</div> <!-- Close main -->

<!-- Search Modal -->
<div class="search-modal" id="searchModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="dialog" role="document">
        <div class="head">
            <h4 id="searchModalTitle">Search Results</h4>
            <button class="close-btn" id="closeSearchModal" aria-label="Close search results">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="body" id="searchModalBody">
            <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                <i class="fas fa-search fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Type a query and press Enter in the search box to load results here.</p>
            </div>
        </div>
        <div class="footer">
            <button id="closeSearchModalFooter" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<script>
// Global JavaScript for all pages
document.addEventListener('DOMContentLoaded', function() {
    // Set today's date as max for date inputs
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if (!input.getAttribute('max')) {
            input.max = new Date().toISOString().split('T')[0];
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

// ----- SIDEBAR TOGGLE -----
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const sidebarLogo = document.getElementById('toggleSidebar');

function toggleSidebar() {
    if (window.innerWidth <= 1024) {
        // Mobile: slide in/out
        sidebar.classList.toggle('show');
        sidebar.classList.remove('folded');
    } else {
        // Desktop: fold/unfold
        sidebar.classList.toggle('folded');
        sidebar.classList.remove('show');
    }
}

if (toggleBtn) {
    toggleBtn.addEventListener('click', toggleSidebar);
}

if (sidebarLogo) {
    sidebarLogo.addEventListener('click', toggleSidebar);
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 1024 && 
        sidebar.classList.contains('show') &&
        !sidebar.contains(e.target) && 
        toggleBtn && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// ----- SEARCH MODAL -----
const searchInput = document.getElementById('globalSearch');
const searchModal = document.getElementById('searchModal');
const searchModalBody = document.getElementById('searchModalBody');
const searchModalTitle = document.getElementById('searchModalTitle');
const closeSearchModal = document.getElementById('closeSearchModal');
const closeSearchModalFooter = document.getElementById('closeSearchModalFooter');
const searchIcon = document.getElementById('searchIcon');

function openSearchModal() {
    searchModal.classList.add('show');
    document.body.classList.add('modal-open');
}

function closeSearchModalFunc() {
    searchModal.classList.remove('show');
    document.body.classList.remove('modal-open');
}

// Search on Enter key
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (!query) return;
            
            searchModalTitle.textContent = `Search Results for: "${query}"`;
            openSearchModal();
            
            // Show loading
            searchModalBody.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: #3b82f6; margin-bottom: 20px;"></i>
                    <p>Searching for "${query}"...</p>
                </div>
            `;

            // Simulate search (replace with actual AJAX call)
            setTimeout(() => {
                searchModalBody.innerHTML = `
                    <div style="padding: 20px;">
                        <h5 style="margin-bottom: 15px; color: #475569;">Found results for "${query}":</h5>
                        <div class="search-results">
                            <div class="card" style="margin-bottom: 10px; cursor: pointer;" onclick="window.location.href='lost_items.php'">
                                <h6>Lost: ${query}</h6>
                                <p style="color: #64748b; font-size: 14px;">Reported on Jan 15, 2024</p>
                            </div>
                            <div class="card" style="margin-bottom: 10px; cursor: pointer;" onclick="window.location.href='found_items.php'">
                                <h6>Found: Similar ${query}</h6>
                                <p style="color: #64748b; font-size: 14px;">Found on Jan 10, 2024</p>
                            </div>
                            <div class="card" style="cursor: pointer;" onclick="window.location.href='claims.php'">
                                <h6>Claim related to ${query}</h6>
                                <p style="color: #64748b; font-size: 14px;">Status: Pending</p>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
        }
    });

    // Search on icon click
    if (searchIcon) {
        searchIcon.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query) {
                searchInput.dispatchEvent(new KeyboardEvent('keypress', {key: 'Enter'}));
            } else {
                searchInput.focus();
            }
        });
    }
}

// Close modal handlers
if (closeSearchModal) {
    closeSearchModal.addEventListener('click', closeSearchModalFunc);
}

if (closeSearchModalFooter) {
    closeSearchModalFooter.addEventListener('click', closeSearchModalFunc);
}

// Close modal on ESC key or click outside
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && searchModal.classList.contains('show')) {
        closeSearchModalFunc();
    }
});

searchModal.addEventListener('click', (e) => {
    if (e.target === searchModal) {
        closeSearchModalFunc();
    }
});

// ----- HANDLE WINDOW RESIZE -----
window.addEventListener('resize', () => {
    if (window.innerWidth > 1024 && sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
    }
    
    if (window.innerWidth <= 1024 && sidebar.classList.contains('folded')) {
        sidebar.classList.remove('folded');
    }
});

// ----- NOTIFICATION BADGE EXAMPLE -----
function updateNotificationBadge(count) {
    let badge = document.getElementById('notificationBadge');
    if (!badge && count > 0) {
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            badge = document.createElement('span');
            badge.id = 'notificationBadge';
            badge.style.cssText = `
                background: #ef4444;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                position: absolute;
                top: -5px;
                right: -5px;
            `;
            badge.textContent = count;
            
            const iconWrapper = userInfo.querySelector('i');
            if (iconWrapper) {
                iconWrapper.style.position = 'relative';
                iconWrapper.appendChild(badge);
            }
        }
    } else if (badge) {
        badge.textContent = count;
        if (count === 0) {
            badge.remove();
        }
    }
}

// Initialize with 0 notifications
updateNotificationBadge(0);

// ----- PAGE-SPECIFIC SCRIPTS -----
// This can be overridden by individual pages
if (typeof pageSpecificScripts === 'function') {
    pageSpecificScripts();
}


// Highlight active page in sidebar
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.sidebar ul li');
    
    navItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        }
    });
});

</script>
</body>
</html>