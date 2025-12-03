<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Existing statistics
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items WHERE status='Lost'")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status='Found'")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();

    // User's personal statistics
    $myLostItems = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id=? AND status='Lost'");
    $myLostItems->execute([$_SESSION['user_id']]);
    $myLostItems = $myLostItems->fetchColumn();

    $myFoundItems = $pdo->prepare("SELECT COUNT(*) FROM found_items WHERE user_id=? AND status='Found'");
    $myFoundItems->execute([$_SESSION['user_id']]);
    $myFoundItems = $myFoundItems->fetchColumn();

    $myClaims = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE user_id=?");
    $myClaims->execute([$_SESSION['user_id']]);
    $myClaims = $myClaims->fetchColumn();

    // Recovery rate
    $recoveryRate = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM claims WHERE user_id=? AND status='completed') * 100.0 /
            NULLIF((SELECT COUNT(*) FROM claims WHERE user_id=?), 0) as recovery_rate
    ");
    $recoveryRate->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recoveryRate = $recoveryRate->fetchColumn();
    $recoveryRate = $recoveryRate ? round($recoveryRate, 1) : 0;

    // Recent activity
    $recentActivity = $pdo->prepare("
        (SELECT 'lost' as type, item_name as title, description, created_at, 'bg-danger' as color
         FROM lost_items WHERE user_id=? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'found' as type, item_name as title, description, created_at, 'bg-success' as color
         FROM found_items WHERE user_id=? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'claim' as type, CONCAT('Claim #', claim_id) as title, status, date_claimed as created_at, 'bg-info' as color
         FROM claims WHERE user_id=? ORDER BY date_claimed DESC LIMIT 3)
        ORDER BY created_at DESC LIMIT 8
    ");
    $recentActivity->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $recentActivity = $recentActivity->fetchAll(PDO::FETCH_ASSOC);

    // Urgent items (lost in last 24 hours)
    $urgentItems = $pdo->query("
        SELECT li.*, ic.category_name
        FROM lost_items li
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id
        WHERE li.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND li.status = 'Lost'
        ORDER BY li.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Potential matches
    $potentialMatches = $pdo->prepare("
        SELECT l.item_name as lost_item, f.item_name as found_item,
               l.description as lost_desc, f.description as found_desc,
               l.created_at as lost_date, f.created_at as found_date,
               CASE
                   WHEN l.item_name = f.item_name THEN 85
                   WHEN l.description LIKE CONCAT('%', f.item_name, '%') OR f.description LIKE CONCAT('%', l.item_name, '%') THEN 70
                   ELSE ROUND(RAND() * 50 + 30)
               END as match_score
        FROM lost_items l, found_items f
        WHERE l.user_id = ?
        AND l.status = 'Lost'
        AND f.status = 'Found'
        AND l.lost_id != f.found_id
        ORDER BY match_score DESC LIMIT 3
    ");
    $potentialMatches->execute([$_SESSION['user_id']]);
    $potentialMatches = $potentialMatches->fetchAll(PDO::FETCH_ASSOC);

    // Categories for dropdown (if needed)
    $categories = $pdo->query("SELECT * FROM item_categories")->fetchAll(PDO::FETCH_ASSOC);

    // Announcements & Notifications
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $notifications = $pdo->prepare("SELECT claim_id, status, date_claimed FROM claims WHERE user_id=? ORDER BY date_claimed DESC LIMIT 5");
    $notifications->execute([$_SESSION['user_id']]);
    $notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    die("Error fetching data: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== FULL CSS START ===== */
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial,sans-serif; }
body { background:#f4f6fa; color:#333; display:flex; min-height:100vh; overflow-x:hidden; }
.sidebar { position: fixed; left:0; top:0; bottom:0; width:220px; background:#1e2a38; color:white; display:flex; flex-direction:column; transition: all 0.3s ease; z-index:1000; box-shadow: 3px 0 15px rgba(0,0,0,0.1);}
.sidebar.folded { width:70px; }
.sidebar.show { left:0; }
.sidebar.hide { left:-220px; }
.sidebar .logo { font-size:20px; font-weight:bold; text-align:center; padding:20px 0; background:#16212b; cursor:pointer; color:white; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s;}
.sidebar.folded .logo span { display: none; }
.sidebar ul { list-style:none; padding:20px 0; flex:1; }
.sidebar ul li { padding:15px 20px; cursor:pointer; position:relative; transition: all 0.3s; display: flex; align-items: center; border-left: 3px solid transparent;}
.sidebar ul li:hover { background:#2c3e50; border-left-color: #1e90ff; }
.sidebar ul li.active { background:#2c3e50; border-left-color: #1e90ff; }
.sidebar ul li i { margin-right:15px; width:20px; text-align:center; transition: transform 0.3s; }
.sidebar ul li:hover i { transform: scale(1.1); }
.sidebar.folded ul li span { display:none; }
.sidebar ul li .tooltip { position:absolute; left:100%; top:50%; transform:translateY(-50%); background:#333; color:#fff; padding:5px 10px; border-radius:5px; font-size:14px; white-space:nowrap; display:none; z-index: 1001; }
.sidebar.folded ul li:hover .tooltip { display:block; }
.main { margin-left:220px; padding:20px; flex:1; transition: margin-left 0.3s; min-height: 100vh; }
.sidebar.folded ~ .main { margin-left:70px; }
.header { display:flex; justify-content:space-between; align-items:center; background:white; padding:15px 20px; border-radius:10px; margin-bottom:20px; box-shadow:0 3px 8px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
.header .toggle-btn { font-size:20px; cursor:pointer; padding: 8px 12px; border-radius: 5px; transition: background 0.3s;}
.header .toggle-btn:hover { background: #f0f4ff;}
.header .user-info { font-weight:bold; display: flex; align-items: center; gap: 10px; color: #1e2a38;}
.header .user-info i { color: #1e90ff; font-size: 18px;}
.search-bar { display: flex; align-items: center; background: white; border-radius: 25px; padding: 8px 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex: 0 1 300px; }
.search-bar input { border: none; outline: none; padding: 5px 10px; flex: 1; font-size: 14px; }
.search-bar i { color: #666; cursor: pointer; }
.quick-actions { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px; }
.action-btn { flex:1 1 180px; padding:20px; border-radius:12px; text-align:center; background:#1e90ff; color:white; font-weight:bold; font-size:16px; cursor:pointer; transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 5px 15px rgba(30,144,255,0.3); position: relative; overflow: hidden;}
.action-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s;}
.action-btn:hover::before { left: 100%; }
.action-btn:hover { transform:translateY(-5px); box-shadow: 0 8px 20px rgba(30,144,255,0.4); }
.action-btn i { font-size: 24px; }
.dashboard-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
.left-column, .right-column { display: flex; flex-direction: column; gap: 20px; }
.dashboard-boxes { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px; }
.box { flex:1 1 220px; background:white; padding:30px; border-radius:12px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); transition: transform 0.3s, box-shadow 0.3s; cursor:pointer; position: relative; overflow: hidden;}
.box::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #1e90ff; }
.box:hover { transform:translateY(-6px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.box h2 { font-size:36px; color:#1e90ff; margin-bottom:10px; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
.box p { font-size:18px; color:#555; font-weight: 500; }
.my-dashboard { background:white; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: relative;}
.my-dashboard::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #1e90ff; }
.my-dashboard h3 { margin-bottom:15px; color:#1e2a38; display: flex; align-items: center; gap: 10px; }
.my-dashboard h3 i { color: #1e90ff; }
.my-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
.stat-item { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; transition: transform 0.3s; }
.stat-item:hover { transform: translateY(-3px); }
.stat-number { font-size: 24px; font-weight: bold; color: #1e90ff; display: block; }
.stat-label { font-size: 14px; color: #666; margin-top: 5px; }
.recent-activity, .urgent-items, .potential-matches, .charts, .announcements, .notifications { background:white; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: relative; margin-bottom:20px; }
.recent-activity::before, .urgent-items::before, .potential-matches::before, .charts::before, .announcements::before, .notifications::before { content: ''; position: absolute; top:0; left:0; width:100%; height:4px; background:#1e90ff; }
.activity-item { padding:12px 0; border-bottom:1px solid #eee; transition: background 0.3s; border-radius: 5px; padding-left: 10px; display: flex; align-items: center; gap: 10px; }
.activity-item:hover { background: #f8f9fa; }
.activity-item:last-child { border-bottom:none; }
.activity-badge { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.bg-success { background: #28a745; }
.bg-danger { background: #dc3545; }
.bg-info { background: #17a2b8; }
.bg-warning { background: #ffc107; }
.urgent-items { border-left: 4px solid #dc3545; }
.urgent-items h3 i { color:#dc3545; }
.potential-matches::before { background: #28a745; }
.potential-matches h3 i { color:#28a745; }
.match-item { padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #28a745; }
.match-score { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; float: right; }
.status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 8px; }
.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d1ecf1; color: #0c5460; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-completed { background: #d4edda; color: #155724; }

/* ===== SEARCH MODAL / FLOATING PAGE ===== */
.search-modal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  background: rgba(0,0,0,0.45);
}
.search-modal.show { display:flex; }
.search-modal .dialog {
  width: min(1100px, 95%);
  max-height: 85vh;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.35);
  overflow: hidden;
  display:flex;
  flex-direction: column;
}
.search-modal .dialog .head {
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 16px;
  background:#f7f9ff;
  border-bottom:1px solid #eee;
}
.search-modal .dialog .head h4 { margin:0; font-size:16px; color:#1e2a38; }
.search-modal .dialog .head .close-btn {
  margin-left:auto;
  background:transparent;
  border:none;
  font-size:18px;
  cursor:pointer;
}
.search-modal .dialog .body {
  padding:16px;
  overflow:auto;
  flex:1;
}
.search-modal .dialog .footer {
  padding:12px 16px;
  border-top:1px solid #eee;
  display:flex;
  gap:10px;
  justify-content: flex-end;
  background:#fafafa;
}

/* small screens */
@media(max-width:900px){
  .search-modal .dialog { width: 95%; height: 85vh; }
}

/* ===== FULL CSS END ===== */
</style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="logo" id="toggleSidebar"><i class="fas fa-bars"></i> <span>LoFIMS</span></div>
    <ul>
        <li data-page="/LoFIMS_BASE/user_panel/dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span><div class="tooltip">Dashboard</div></li>
        <li data-page="/LoFIMS_BASE/public/lost_items.php"><i class="fas fa-pencil-alt"></i><span>Lost Items</span><div class="tooltip">Lost Items</div></li>
        <li data-page="/LoFIMS_BASE/public/found_items.php"><i class="fas fa-box"></i><span>Found Items</span><div class="tooltip">Found Items</div></li>
        <li data-page="/LoFIMS_BASE/public/claim_item.php"><i class="fas fa-hand-holding"></i><span>Claims</span><div class="tooltip">Claims</div></li>
        <li data-page="/LoFIMS_BASE/public/announcements.php"><i class="fas fa-bullhorn"></i><span>Announcements</span><div class="tooltip">Announcements</div></li>
        <li data-page="/LoFIMS_BASE/public/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span><div class="tooltip">Logout</div></li>
    </ul>
</div>

<div class="main">
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info"><i class="fas fa-user-circle"></i> Hello, <?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search items or claims...">
            <i class="fas fa-search"></i>
        </div>
    </div>

    <div class="quick-actions">
        <div class="action-btn" onclick="window.location='/LoFIMS_BASE/public/lost_items.php'"><i class="fas fa-pencil-alt"></i><span>Add Lost Item</span></div>
        <div class="action-btn" onclick="window.location='/LoFIMS_BASE/public/found_items.php'"><i class="fas fa-box"></i><span>Add Found Item</span></div>
        <div class="action-btn" onclick="window.location='/LoFIMS_BASE/public/claim_item.php'"><i class="fas fa-hand-holding"></i><span>My Claims</span></div>
    </div>

    <!-- My Dashboard Section -->
    <div class="my-dashboard">
        <h3><i class="fas fa-tachometer-alt"></i> My Dashboard</h3>
        <div class="my-stats">
            <div class="stat-item">
                <span class="stat-number"><?= $myLostItems ?></span>
                <div class="stat-label">My Lost Items</div>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $myFoundItems ?></span>
                <div class="stat-label">My Found Items</div>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $recoveryRate ?>%</span>
                <div class="stat-label">Recovery Rate</div>
            </div>
        </div>
    </div>

    <div class="dashboard-layout">
        <div class="left-column">
            <!-- System Statistics -->
            <div class="dashboard-boxes">
                <div class="box"><h2><?= $totalLost ?></h2><p>Total Lost Items</p></div>
                <div class="box"><h2><?= $totalFound ?></h2><p>Total Found Items</p></div>
                <div class="box"><h2><?= $totalClaims ?></h2><p>Total Claims</p></div>
            </div>

            <!-- Chart -->
            <div class="charts">
                <canvas id="itemsChart" style="max-height:300px;"></canvas>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php if($recentActivity): ?>
                    <?php foreach($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-badge <?= $activity['color'] ?>"></div>
                            <div>
                                <strong><?= htmlspecialchars($activity['title']) ?></strong><br>
                                <small><?= htmlspecialchars(substr($activity['description'] ?? '', 0, 50)) ?>...</small><br>
                                <span style="font-size:12px; color:#999;">
                                    <?= date("M d, Y H:i", strtotime($activity['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-column">
            <!-- Urgent Items -->
            <div class="urgent-items">
                <h3><i class="fas fa-exclamation-triangle"></i> Urgent Items</h3>
                <?php if($urgentItems): ?>
                    <?php foreach($urgentItems as $item): ?>
                        <div class="activity-item">
                            <div class="activity-badge bg-danger"></div>
                            <div>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                                <small><?= htmlspecialchars($item['category_name'] ?? 'Recently Lost') ?></small><br>
                                <span style="font-size:12px; color:#999;">
                                    Lost <?= date("M d H:i", strtotime($item['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No urgent items reported recently.</p>
                <?php endif; ?>
            </div>

            <!-- Potential Matches -->
            <div class="potential-matches">
                <h3><i class="fas fa-handshake"></i> Potential Matches</h3>
                <?php if($potentialMatches): ?>
                    <?php foreach($potentialMatches as $match): ?>
                        <div class="match-item">
                            <strong><?= htmlspecialchars($match['lost_item']) ?></strong>
                            <span class="match-score"><?= $match['match_score'] ?>%</span><br>
                            <small>Matches: <?= htmlspecialchars($match['found_item']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No potential matches found.</p>
                <?php endif; ?>
            </div>

            <!-- Announcements -->
            <div class="announcements">
                <h3><i class="fas fa-bullhorn"></i> Recent Announcements</h3>
                <?php if($announcements): ?>
                    <?php foreach($announcements as $announce): ?>
                        <div class="activity-item">
                            <div class="activity-badge bg-info"></div>
                            <div>
                                <strong><?= htmlspecialchars($announce['title']) ?></strong><br>
                                <span style="font-size:12px; color:#999;">
                                    <i class="far fa-calendar"></i> <?= date("M d, Y", strtotime($announce['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No announcements available.</p>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <div class="notifications">
                <h3><i class="fas fa-bell"></i> Latest Notifications</h3>
                <?php if($notifications): ?>
                    <?php foreach($notifications as $notif): ?>
                        <div class="activity-item">
                            <div class="activity-badge bg-warning"></div>
                            <div>
                                <strong>Claim #<?= $notif['claim_id'] ?></strong>
                                <span class="status-badge status-<?= strtolower($notif['status']) ?>">
                                    <?= $notif['status'] ?>
                                </span><br>
                                <span style="font-size:12px; color:#999;">
                                    <i class="far fa-calendar"></i> <?= date("M d, Y", strtotime($notif['date_claimed'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No notifications.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SEARCH MODAL -->
<div class="search-modal" id="searchModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="dialog" role="document">
    <div class="head">
      <h4 id="searchModalTitle">Search Results</h4>
      <button class="close-btn" id="closeSearchModal" aria-label="Close search results"><i class="fas fa-times"></i></button>
    </div>
    <div class="body" id="searchModalBody">
      <p style="color:#666">Type a query and press Enter in the search box to load results here.</p>
    </div>
    <div class="footer">
      <a id="openFullResults" href="#" target="_blank" class="action-btn" style="background:#fff;color:#1e2a38;padding:8px 14px;border-radius:8px;border:1px solid #ddd;text-decoration:none;font-weight:600;">Open full results</a>
      <button id="closeSearchModalFooter" class="action-btn" style="background:#1e90ff;">Close</button>
    </div>
  </div>
</div>

<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const sidebarLogo = document.getElementById('toggleSidebar');

toggleBtn.addEventListener('click', () => {
    if(window.innerWidth <= 900) sidebar.classList.toggle('show');
    else sidebar.classList.toggle('folded');
});
sidebarLogo.addEventListener('click', () => {
    if(window.innerWidth <= 900) sidebar.classList.toggle('show');
    else sidebar.classList.toggle('folded');
});
document.addEventListener('click', function(e){
    if(window.innerWidth <= 900 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)){
        sidebar.classList.remove('show');
    }
});

// Navigation links - use absolute paths from data-page
document.querySelectorAll('.sidebar ul li').forEach(item => {
    item.addEventListener('click', function() {
        const page = this.dataset.page;
        if(page && page!="#") window.location = page;
    });
});

// GLOBAL SEARCH - open floating modal and load results into it
const searchInput = document.getElementById('globalSearch');
const searchModal = document.getElementById('searchModal');
const searchModalBody = document.getElementById('searchModalBody');
const searchModalTitle = document.getElementById('searchModalTitle');
const closeSearchModal = document.getElementById('closeSearchModal');
const closeSearchModalFooter = document.getElementById('closeSearchModalFooter');
const openFullResults = document.getElementById('openFullResults');

function openModal() {
  searchModal.classList.add('show');
  searchModal.setAttribute('aria-hidden', 'false');
}
function closeModal() {
  searchModal.classList.remove('show');
  searchModal.setAttribute('aria-hidden', 'true');
}

// Listen for Enter in the search input
searchInput.addEventListener('keypress', function(e) {
    if(e.key === 'Enter') {
        const query = this.value.trim();
        if(!query) return;
        // Update title and open modal
        searchModalTitle.textContent = `Search Results for: "${query}"`;
        openModal();
        // Update 'open full results' link
        openFullResults.href = `../public/search_results.php?query=${encodeURIComponent(query)}`;

        // Clear previous results and show loading
        searchModalBody.innerHTML = '<p style="color:#666">Loading results...</p>';

        // Fetch the search results page fragment (server should return HTML snippet or full page)
        fetch(`../public/search_results.php?query=${encodeURIComponent(query)}`)
          .then(resp => {
            if(!resp.ok) throw new Error('Network response was not ok');
            return resp.text();
          })
          .then(html => {
            // Try to extract a main results container from fetched HTML.
            // If search_results.php outputs a full page, we'll inject it into modal body.
            searchModalBody.innerHTML = html;
            // optionally, if the fetched html contains scripts or relative links, you might need additional handling.
          })
          .catch(err => {
            console.error('Search fetch error:', err);
            searchModalBody.innerHTML = `<p style="color:#c00">Failed to load search results. <a href="../public/search_results.php?query=${encodeURIComponent(query)}" target="_blank">Open full results</a></p>`;
          });
    }
});

// close handlers
closeSearchModal.addEventListener('click', closeModal);
closeSearchModalFooter.addEventListener('click', closeModal);
// close on background click
searchModal.addEventListener('click', (e) => {
  if(e.target === searchModal) closeModal();
});
// close on Esc
document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape') closeModal();
});

// Chart
const ctx = document.getElementById('itemsChart').getContext('2d');
const itemsChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Lost Items', 'Found Items', 'Claims'],
        datasets: [{
            label: 'System Statistics',
            data: [<?= $totalLost ?>, <?= $totalFound ?>, <?= $totalClaims ?>],
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
</script>
</body>
</html>
