<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../public/login.php");
    exit();
}

try {
    // Admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistics
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();

    // Recent Activity
    $recentActivity = $pdo->query("
        (SELECT 'lost' as type, item_name as title, description, created_at, 'bg-danger' as color
         FROM lost_items ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'found' as type, item_name as title, description, created_at, 'bg-success' as color
         FROM found_items ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'claim' as type, CONCAT('Claim #', claim_id) as title, status, date_claimed as created_at, 'bg-info' as color
         FROM claims ORDER BY date_claimed DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Urgent items
    $urgentItems = $pdo->query("
        SELECT li.*, u.first_name, u.last_name, ic.category_name
        FROM lost_items li
        LEFT JOIN users u ON li.user_id = u.user_id
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND li.status = 'Lost'
        ORDER BY li.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Announcements
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== General ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f6fa;display:flex;min-height:100vh;overflow-x:hidden;color:#333;}

/* ===== Sidebar ===== */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1e2a38;color:white;display:flex;flex-direction:column;transition:0.3s;z-index:1000;box-shadow:3px 0 15px rgba(0,0,0,0.1);}
.sidebar.folded{width:70px;}
.sidebar.show{left:0;}
.sidebar.hide{left:-220px;}
.sidebar .logo{font-size:20px;font-weight:bold;text-align:center;padding:20px 0;background:#16212b;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:0.3s;}
.sidebar.folded .logo span{display:none;}
.sidebar ul{list-style:none;padding:20px 0;flex:1;}
.sidebar ul li{padding:15px 20px;cursor:pointer;position:relative;display:flex;align-items:center;border-left:3px solid transparent;transition:0.3s;}
.sidebar ul li:hover{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li.active{background:#2c3e50;border-left-color:#1e90ff;}
.sidebar ul li i{margin-right:15px;width:20px;text-align:center;transition:0.3s;}
.sidebar.folded ul li span{display:none;}
.sidebar ul li .tooltip{position:absolute;left:100%;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:5px 10px;border-radius:5px;font-size:14px;white-space:nowrap;display:none;z-index:1001;}
.sidebar.folded ul li:hover .tooltip{display:block;}

/* ===== Main ===== */
.main{margin-left:220px;padding:20px;flex:1;transition:0.3s;min-height:100vh;max-width:100%;}
.sidebar.folded ~ .main{margin-left:70px;}

/* ===== Header ===== */
.header{display:flex;align-items:center;justify-content:space-between;background:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.1);position:sticky;top:0;z-index:100;}
.user-info{font-weight:bold;display:flex;align-items:center;gap:10px;color:#1e2a38;}
.user-info i{color:#1e90ff;font-size:18px;}

/* Search bar */
.search-bar{position:relative;width:250px;}
.search-bar input{width:100%;padding:8px 35px 8px 10px;border-radius:8px;border:1px solid #ccc;outline:none;}
.search-bar i{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#888;}
.search-results{position:absolute;top:38px;left:0;width:100%;max-height:300px;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:8px;overflow-y:auto;display:none;z-index:2000;}
.search-results .result-item{padding:10px 15px;cursor:pointer;transition:0.3s;}
.search-results .result-item:hover{background:#f0f4ff;}

/* Quick Actions */
.quick-actions{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.action-btn{flex:1 1 150px;min-width:120px;padding:20px;border-radius:12px;text-align:center;background:#1e90ff;color:white;font-weight:bold;font-size:16px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:10px;box-shadow:0 5px 15px rgba(30,144,255,0.3);position:relative;overflow:hidden;transition: transform 0.3s, box-shadow 0.3s;}
.action-btn i{font-size:24px;}
.action-btn:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(30,144,255,0.3);}

/* Dashboard Boxes */
.dashboard-boxes{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;}
.box{flex:1 1 150px;min-width:120px;background:white;padding:30px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);transition: transform 0.3s, box-shadow 0.3s;}
.box:hover{transform:translateY(-6px);box-shadow:0 8px 20px rgba(0,0,0,0.15);}
.box h2{font-size:36px;color:#1e90ff;margin-bottom:10px;text-shadow:1px 1px 2px rgba(0,0,0,0.1);}
.box p{font-size:18px;color:#555;font-weight:500;}

/* Panels */
.dashboard-layout{display:grid;grid-template-columns:2fr 1fr;gap:20px;}
.left-column,.right-column{display:flex;flex-direction:column;gap:20px;}
.recent-activity,.urgent-items,.charts,.announcements{background:white;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1);position:relative;margin-bottom:20px;}
.activity-item{padding:12px 0;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px;border-radius:5px;}
.activity-item:last-child{border-bottom:none;}
.activity-badge{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.bg-success{background:#28a745;}
.bg-danger{background:#dc3545;}
.bg-info{background:#17a2b8;}

/* Responsive */
@media(max-width:900px){.sidebar{left:-220px;}.sidebar.show{left:0;}.main{margin-left:0;padding:15px;}.dashboard-layout{grid-template-columns:1fr;}}
@media(max-width:600px){.quick-actions,.dashboard-boxes{flex-direction:column;}}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
        <span>LoFIMS Admin</span>
    </div>
    <ul>
        <li class="active" data-page="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></li>
        <li data-page="manage_users/"><i class="fas fa-users"></i><span>Manage Users</span></li>
        <li data-page="generate_reports/"><i class="fas fa-chart-line"></i><span>Reports</span></li>
        <li data-page="categories.php"><i class="fas fa-tags"></i><span>Categories</span></li>
        <li data-page="announcements.php"><i class="fas fa-bullhorn"></i><span>Announcements</span></li>
        <li data-page="logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></li>
    </ul>
</div>

<!-- Main -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info user-info-center">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($admin['first_name'].' '.$admin['last_name']) ?>
        </div>
        <div class="search-bar">
            <input type="text" id="globalSearch" placeholder="Search items, users, reports...">
            <i class="fas fa-search"></i>
            <div class="search-results"></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <div class="action-btn" onclick="window.location='manage_users/'"><i class="fas fa-users"></i><span>Manage Users</span></div>
        <div class="action-btn" onclick="window.location='generate_reports/'"><i class="fas fa-chart-line"></i><span>Reports</span></div>
        <div class="action-btn" onclick="window.location='categories.php'"><i class="fas fa-tags"></i><span>Categories</span></div>
        <div class="action-btn" onclick="window.location='announcements.php'"><i class="fas fa-bullhorn"></i><span>Announcements</span></div>
    </div>

    <!-- Dashboard Boxes -->
    <div class="dashboard-boxes">
        <div class="box"><h2 id="totalUsers"><?= $totalUsers ?></h2><p>Total Users</p></div>
        <div class="box"><h2 id="totalLost"><?= $totalLost ?></h2><p>Total Lost Items</p></div>
        <div class="box"><h2 id="totalFound"><?= $totalFound ?></h2><p>Total Found Items</p></div>
        <div class="box"><h2 id="totalClaims"><?= $totalClaims ?></h2><p>Total Claims</p></div>
    </div>

    <!-- Panels -->
    <div class="dashboard-layout">
        <div class="left-column">
            <div class="charts"><canvas id="systemChart" style="max-height:300px;"></canvas></div>
            <div class="recent-activity">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php foreach($recentActivity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-badge <?= $activity['color'] ?>"></div>
                    <div>
                        <strong><?= htmlspecialchars($activity['title']) ?></strong><br>
                        <small><?= htmlspecialchars(substr($activity['description'] ?? '',0,50)) ?>...</small><br>
                        <span style="font-size:12px;color:#999;"><?= date("M d, Y H:i", strtotime($activity['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="right-column">
            <div class="urgent-items">
                <h3><i class="fas fa-exclamation-triangle"></i> Urgent Items (Last 24h)</h3>
                <?php foreach($urgentItems as $item): ?>
                <div class="activity-item">
                    <div class="activity-badge bg-danger"></div>
                    <div>
                        <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                        <small><?= htmlspecialchars($item['category_name'] ?? 'Recently Lost') ?> by <?= htmlspecialchars($item['first_name'].' '.$item['last_name']) ?></small><br>
                        <span style="font-size:12px;color:#999;">Lost <?= date("M d H:i", strtotime($item['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="announcements">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
                <?php foreach($announcements as $announce): ?>
                <div class="activity-item">
                    <div class="activity-badge bg-info"></div>
                    <div>
                        <strong><?= htmlspecialchars($announce['title']) ?></strong><br>
                        <span style="font-size:12px;color:#999;"><?= date("M d, Y", strtotime($announce['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Sidebar toggle for header and sidebar
const sidebar = document.getElementById('sidebar');
const headerToggle = document.getElementById('sidebarToggle');
const sidebarToggle = document.getElementById('toggleSidebar');

[headerToggle, sidebarToggle].forEach(btn => {
    btn.addEventListener('click', () => {
        if(window.innerWidth <= 900){
            sidebar.classList.toggle('show');
        } else {
            sidebar.classList.toggle('folded');
        }
    });
});

// Close sidebar on mobile click outside
document.addEventListener('click', e => {
    if(window.innerWidth <= 900 && !sidebar.contains(e.target) && ![headerToggle, sidebarToggle].some(b=>b.contains(e.target))){
        sidebar.classList.remove('show');
    }
});

// Sidebar nav
document.querySelectorAll('.sidebar ul li').forEach(li=>{
    li.addEventListener('click', function(){
        const page=this.dataset.page;
        if(page && page!="#") window.location=page;
    });
});

// Highlight current page
const currentPage = location.pathname.split("/").pop();
document.querySelectorAll('.sidebar ul li').forEach(li => {
    if(li.dataset.page && currentPage.includes(li.dataset.page)) li.classList.add('active');
});

// Chart.js
const ctx = document.getElementById('systemChart').getContext('2d');
new Chart(ctx, {
    type:'doughnut',
    data:{
        labels:['Users','Lost Items','Found Items','Claims'],
        datasets:[{
            label:'System Stats',
            data:[
                parseInt(document.getElementById('totalUsers').textContent),
                parseInt(document.getElementById('totalLost').textContent),
                parseInt(document.getElementById('totalFound').textContent),
                parseInt(document.getElementById('totalClaims').textContent)
            ],
            backgroundColor:['rgba(255,193,7,0.8)','rgba(255,107,107,0.8)','rgba(30,144,255,0.8)','rgba(76,175,80,0.8)'],
            borderColor:['rgba(255,193,7,1)','rgba(255,107,107,1)','rgba(30,144,255,1)','rgba(76,175,80,1)'],
            borderWidth:2
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom',labels:{padding:20,usePointStyle:true}},title:{display:true,text:'System Overview'}}, cutout:'65%'}
});

// Live Search
const searchInput = document.getElementById('globalSearch');
const searchResults = document.querySelector('.search-results');

searchInput.addEventListener('input', async function() {
    const query = this.value.trim();
    if(query.length < 2){ searchResults.style.display='none'; searchResults.innerHTML=''; return; }
    try {
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        if(data.length>0){
            searchResults.innerHTML = data.map(item=>`<div class="result-item" onclick="window.location='${item.link}'"><strong>${item.name}</strong> <small>(${item.type})</small></div>`).join('');
            searchResults.style.display='block';
        } else {
            searchResults.innerHTML = '<div class="result-item">No results found</div>';
            searchResults.style.display='block';
        }
    } catch(err){ console.error(err); }
});
document.addEventListener('click', e => { if(!searchResults.contains(e.target) && e.target!==searchInput) searchResults.style.display='none'; });
</script>
</body>
</html>
