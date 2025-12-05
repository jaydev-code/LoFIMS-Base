// admin.js - Shared JavaScript for all admin pages

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar state from localStorage
    initSidebarState();
    
    // Setup sidebar toggle
    initSidebarToggle();
    
    // Setup search functionality
    initGlobalSearch();
    
    // Setup navigation
    initNavigation();
});

// ===== SIDEBAR STATE MANAGEMENT =====
function initSidebarState() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    // Get saved state from localStorage
    const savedState = localStorage.getItem('sidebarState');
    const isMobile = window.innerWidth <= 900;
    
    if (!isMobile) {
        // On desktop: apply saved state
        if (savedState === 'folded') {
            sidebar.classList.add('folded');
        } else {
            sidebar.classList.remove('folded');
        }
    } else {
        // On mobile: always start hidden
        sidebar.classList.remove('folded');
        sidebar.classList.remove('show');
    }
}

function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const logoBtn = document.getElementById('toggleSidebar');
    
    if (!sidebar) return;
    
    // Toggle from header button
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar(sidebar);
        });
    }
    
    // Toggle from sidebar logo
    if (logoBtn) {
        logoBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar(sidebar);
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 900 && sidebar && !sidebar.contains(e.target)) {
            if (toggleBtn && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
}

function toggleSidebar(sidebar) {
    const isMobile = window.innerWidth <= 900;
    
    if (isMobile) {
        // Mobile: toggle show/hide
        sidebar.classList.toggle('show');
    } else {
        // Desktop: toggle folded/unfolded
        sidebar.classList.toggle('folded');
        
        // Save state to localStorage
        const isFolded = sidebar.classList.contains('folded');
        localStorage.setItem('sidebarState', isFolded ? 'folded' : 'unfolded');
    }
}

// ===== GLOBAL SEARCH =====
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performGlobalSearch(query);
        }, 300);
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });
}

async function performGlobalSearch(query) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    try {
        searchResults.innerHTML = '<div class="search-loading">Searching...</div>';
        searchResults.style.display = 'block';
        
        const response = await fetch('search.php?q=' + encodeURIComponent(query));
        const data = await response.json();
        
        if (data && data.length > 0) {
            searchResults.innerHTML = data.map(item => `
                <div class="result-item" onclick="window.location='${item.url}'">
                    <i class="fas fa-${item.icon}"></i>
                    <div>
                        <strong>${item.title}</strong>
                        <small>${item.type} • ${item.date}</small>
                    </div>
                </div>
            `).join('');
        } else {
            searchResults.innerHTML = '<div class="no-results">No results found</div>';
        }
        
        searchResults.style.display = 'block';
    } catch (error) {
        console.error('Search error:', error);
        searchResults.innerHTML = '<div class="search-error">Error performing search</div>';
        searchResults.style.display = 'block';
    }
}

// ===== NAVIGATION =====
function initNavigation() {
    // Handle sidebar navigation clicks
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // For logout, let it redirect normally
            if (this.href.includes('logout.php')) return;
            
            // For other pages, we're already using the template system
            // No need to prevent default, but we can add loading indicator
            addLoadingIndicator();
        });
    });
}

function addLoadingIndicator() {
    const mainContent = document.getElementById('mainContent');
    if (mainContent) {
        mainContent.classList.add('loading');
    }
}

// Window resize handler to adjust sidebar
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    if (window.innerWidth <= 900) {
        // On mobile: ensure it's not folded
        sidebar.classList.remove('folded');
    } else {
        // On desktop: restore saved state
        const savedState = localStorage.getItem('sidebarState');
        if (savedState === 'folded') {
            sidebar.classList.add('folded');
        } else {
            sidebar.classList.remove('folded');
        }
    }
});