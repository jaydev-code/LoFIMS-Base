// ----- Sidebar Toggle -----
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

// ----- Floating Search Modal -----
const searchInput = document.getElementById('globalSearch');
const searchModal = document.getElementById('searchModal');
const searchModalBody = document.getElementById('searchModalBody');
const closeModal = document.getElementById('closeSearchModal');
const closeModalFooter = document.getElementById('closeSearchModalFooter');
const openFullResults = document.getElementById('openFullResults');

function showSearchModal(content, query) {
    if (searchModalBody) {
        searchModalBody.innerHTML = content;
    }
    if (searchModal) {
        searchModal.classList.add('show');
        document.body.classList.add('modal-open');
    }
    if (openFullResults) {
        openFullResults.href = '../public/search_results.php?query=' + encodeURIComponent(query);
    }
}

function hideSearchModal() {
    if (searchModal) {
        searchModal.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
}

if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (!query) return;

            // Fetch search results via AJAX
            fetch('../public/search_results_ajax.php?query=' + encodeURIComponent(query))
                .then(res => res.text())
                .then(data => {
                    // Convert to cards
                    let cards = '';
                    const parser = new DOMParser();
                    const htmlDoc = parser.parseFromString(data, 'text/html');
                    htmlDoc.querySelectorAll('.result-item').forEach(item => {
                        const title = item.querySelector('.title')?.innerText || '';
                        const desc = item.querySelector('.desc')?.innerText || '';
                        const date = item.querySelector('.date')?.innerText || '';
                        cards += `<div class="search-card"><h5>${title}</h5><p>${desc}</p><small>${date}</small></div>`;
                    });
                    showSearchModal(cards || '<p>No results found.</p>', query);
                })
                .catch(err => showSearchModal('<p style="color:red;">Error fetching results.</p>', query));
        }
    });
}

if (closeModal) closeModal.addEventListener('click', hideSearchModal);
if (closeModalFooter) closeModalFooter.addEventListener('click', hideSearchModal);

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && searchModal && searchModal.classList.contains('show')) {
        hideSearchModal();
    }
});

// Close modal on outside click
if (searchModal) {
    searchModal.addEventListener('click', (e) => {
        if (e.target === searchModal) {
            hideSearchModal();
        }
    });
}

// ----- Chart.js Doughnut Chart -----
const ctx = document.getElementById('itemsChart');
if (ctx) {
    // Get chart data from data attributes or global variables
    const totalLost = window.totalLost || 0;
    const totalFound = window.totalFound || 0;
    const totalClaims = window.totalClaims || 0;
    
    const itemsChart = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Lost Items', 'Found Items', 'Claims'],
            datasets: [{
                label: 'System Statistics',
                data: [totalLost, totalFound, totalClaims],
                backgroundColor: [
                    'rgba(255, 107, 107, 0.8)',
                    'rgba(30, 144, 255, 0.8)',
                    'rgba(76, 175, 80, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 107, 107, 1)',
                    'rgba(30, 144, 255, 1)',
                    'rgba(76, 175, 80, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
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
                    text: 'System Overview' 
                }
            },
            cutout: '65%'
        }
    });
}

// ----- LOGOUT CONFIRMATION -----
function confirmLogout(event) {
    if (event) {
        event.preventDefault(); // Prevent default link behavior
    }
    
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
            // Redirect to logout page which will redirect to homepage
            window.location.href = '../auth/logout.php';
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

// Add event listeners to logout links when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Find logout links and attach the confirmLogout function
    document.querySelectorAll('a[href*="logout"]').forEach(link => {
        if (link.getAttribute('href').includes('logout')) {
            link.addEventListener('click', confirmLogout);
        }
    });
});