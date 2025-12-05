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
