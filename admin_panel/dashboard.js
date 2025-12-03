// dashboard.js - Unified sidebar toggle functionality

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initSidebar();
    initSearch();
    initDashboardBoxes();
    initChart();
});

// ===== SIDEBAR TOGGLE FUNCTIONALITY =====
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const toggleSidebarLogo = document.getElementById('toggleSidebar');
    
    // If sidebar doesn't exist, exit
    if (!sidebar) return;
    
    // Toggle sidebar when clicking hamburger button in header
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar(sidebar);
        });
    }
    
    // Toggle sidebar when clicking logo in sidebar (if exists)
    if (toggleSidebarLogo) {
        toggleSidebarLogo.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar(sidebar);
        });
    }
    
    // Sidebar navigation
    document.querySelectorAll('.sidebar ul li').forEach(li => {
        li.addEventListener('click', function(e) {
            const page = this.dataset.page;
            if (page && page !== "#") {
                e.preventDefault();
                if (page === 'logout.php') {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = page;
                    }
                } else {
                    window.location.href = page;
                }
            }
        });
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 900) {
            if (sidebar && !sidebar.contains(e.target) && 
                sidebarToggle && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
    
    // Highlight active page in sidebar
    highlightActivePage();
    
    // Add tooltips for folded sidebar
    addSidebarTooltips();
}

function toggleSidebar(sidebar) {
    if (!sidebar) return;
    
    if (window.innerWidth <= 900) {
        // Mobile: show/hide sidebar
        sidebar.classList.toggle('show');
        if (sidebar.classList.contains('folded')) {
            sidebar.classList.remove('folded');
        }
    } else {
        // Desktop: fold/unfold sidebar
        sidebar.classList.toggle('folded');
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    }
}

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const sidebarItems = document.querySelectorAll('.sidebar ul li');
    
    sidebarItems.forEach(item => {
        const page = item.dataset.page;
        if (page && currentPage.includes(page.replace('/', ''))) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

function addSidebarTooltips() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    // Check if sidebar is folded
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                const tooltips = document.querySelectorAll('.sidebar ul li .tooltip');
                if (sidebar.classList.contains('folded')) {
                    tooltips.forEach(tooltip => {
                        tooltip.style.display = 'none';
                    });
                }
            }
        });
    });
    
    observer.observe(sidebar, { attributes: true });
}

// ===== SEARCH FUNCTIONALITY =====
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.querySelector('.search-results');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            performSearch(query, searchResults);
        }, 300);
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });
    
    // Show search results on focus if there's a query
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            performSearch(this.value.trim(), searchResults);
        }
    });
}

async function performSearch(query, searchResults) {
    try {
        // Show loading
        searchResults.innerHTML = '<div class="result-item">Searching...</div>';
        searchResults.style.display = 'block';
        
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.length > 0) {
            searchResults.innerHTML = data.map(item => `
                <div class="result-item" onclick="window.location='${item.link || '#'}'">
                    <strong>${item.name || 'Unnamed'}</strong>
                    <small>(${item.type || 'Unknown'})</small>
                </div>
            `).join('');
        } else {
            searchResults.innerHTML = '<div class="result-item">No results found</div>';
        }
        
        searchResults.style.display = 'block';
    } catch (error) {
        console.error('Search error:', error);
        searchResults.innerHTML = '<div class="result-item">Error searching. Please try again.</div>';
        searchResults.style.display = 'block';
    }
}

// ===== DASHBOARD BOXES =====
function initDashboardBoxes() {
    const boxes = document.querySelectorAll('.dashboard-boxes .box');
    
    boxes.forEach((box, index) => {
        // Add animation
        box.style.animation = `slideFadeIn 0.5s forwards`;
        box.style.animationDelay = `${index * 0.1}s`;
        
        // Add click handler if it has data attributes
        box.addEventListener('click', function() {
            const action = this.dataset.action;
            const link = this.dataset.link;
            
            if (action === 'go' && link) {
                window.location.href = link;
            } else if (action === 'filter-users') {
                // Already on users page
                console.log('Already on users page');
            }
        });
    });
}

// ===== CHART.JS =====
function initChart() {
    const ctx = document.getElementById('systemChart');
    if (!ctx) return;
    
    const totalUsers = parseInt(document.getElementById('totalUsers')?.textContent || 0);
    const totalLost = parseInt(document.getElementById('totalLost')?.textContent || 0);
    const totalFound = parseInt(document.getElementById('totalFound')?.textContent || 0);
    const totalClaims = parseInt(document.getElementById('totalClaims')?.textContent || 0);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Users', 'Lost Items', 'Found Items', 'Claims'],
            datasets: [{
                label: 'System Stats',
                data: [totalUsers, totalLost, totalFound, totalClaims],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(255, 107, 107, 0.8)',
                    'rgba(30, 144, 255, 0.8)',
                    'rgba(76, 175, 80, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(255, 107, 107, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(76, 175, 80, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                title: {
                    display: true,
                    text: 'System Overview',
                    font: {
                        size: 16
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// ===== HELPER FUNCTIONS =====
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    });
}

// Export functions for use in other scripts
window.toggleSidebar = toggleSidebar;
window.showNotification = showNotification;