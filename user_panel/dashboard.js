// ----- Sidebar Toggle -----
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const sidebarLogo = document.getElementById('toggleSidebar');

function toggleSidebar() {
    if(window.innerWidth <= 900) sidebar.classList.toggle('show');
    else sidebar.classList.toggle('folded');
}

toggleBtn.addEventListener('click', toggleSidebar);
sidebarLogo.addEventListener('click', toggleSidebar);

// Close sidebar on click outside (for mobile)
document.addEventListener('click', (e) => {
    if(window.innerWidth <= 900 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)){
        sidebar.classList.remove('show');
    }
});

// ----- Navigation Links -----
document.querySelectorAll('.sidebar ul li').forEach(item => {
    item.addEventListener('click', function() {
        const page = this.dataset.page;
        if(page && page!="#") window.location = page;
    });
});

// ----- Floating Search Modal -----
const searchInput = document.getElementById('globalSearch');
const searchModal = document.getElementById('searchModal');
const searchModalBody = document.getElementById('searchModalBody');
const closeModal = document.getElementById('closeSearchModal');
const closeModalFooter = document.getElementById('closeSearchModalFooter');
const openFullResults = document.getElementById('openFullResults');

function showSearchModal(content, query){
    searchModalBody.innerHTML = content;
    searchModal.classList.add('show');
    if(openFullResults) openFullResults.href = '../public/search_results.php?query=' + encodeURIComponent(query);
}

function hideSearchModal(){
    searchModal.classList.remove('show');
}

searchInput.addEventListener('keypress', function(e){
    if(e.key === 'Enter'){
        const query = this.value.trim();
        if(!query) return;

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

if(closeModal) closeModal.addEventListener('click', hideSearchModal);
if(closeModalFooter) closeModalFooter.addEventListener('click', hideSearchModal);

// ----- Chart.js Doughnut Chart -----
const ctx = document.getElementById('itemsChart')?.getContext('2d');
if(ctx){
    const itemsChart = new Chart(ctx, {
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
            responsive:true,
            plugins:{
                legend:{ position:'bottom', labels:{ padding: 20, usePointStyle:true } },
                title:{ display:true, text:'System Overview' }
            },
            cutout: '65%'
        }
    });
}
