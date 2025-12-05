<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch lost items
try {
    $stmt = $pdo->prepare("
        SELECT li.*, ic.category_name,
               (SELECT COUNT(*) FROM claims WHERE lost_id = li.lost_id) as claim_count
        FROM lost_items li 
        LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
        WHERE li.user_id = ? 
        ORDER BY li.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $lost_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $total_lost = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ?");
    $total_lost->execute([$_SESSION['user_id']]);
    $total_lost = $total_lost->fetchColumn();
    
    $pending_lost = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ? AND status = 'Lost'");
    $pending_lost->execute([$_SESSION['user_id']]);
    $pending_lost = $pending_lost->fetchColumn();
    
    $recovered_lost = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ? AND status = 'Recovered'");
    $recovered_lost->execute([$_SESSION['user_id']]);
    $recovered_lost = $recovered_lost->fetchColumn();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lost Items - LoFIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Additional styles for lost items page */
        .page-title {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .page-title i {
            color: #3b82f6;
            background: #eff6ff;
            padding: 10px;
            border-radius: 10px;
        }
        
        .quick-actions { 
            display:flex; 
            gap:20px; 
            flex-wrap:wrap; 
            margin-bottom:25px; 
        }
        .action-btn { 
            flex:1 1 200px; 
            padding:25px; 
            border-radius:12px; 
            text-align:center; 
            background:#3b82f6; 
            color:white; 
            font-weight:bold; 
            font-size:16px; 
            cursor:pointer; 
            transition: transform 0.3s, box-shadow 0.3s; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 12px; 
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3); 
            position: relative; 
            overflow: hidden;
            border: none;
            text-decoration: none;
        }
        .action-btn:hover { 
            transform:translateY(-5px); 
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4); 
            background:#2563eb;
        }
        .action-btn i { font-size: 28px; }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
            margin: 25px 0;
        }
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            text-align: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }
        .stat-number { 
            font-size: 36px; 
            font-weight: bold; 
            color: #3b82f6; 
            margin-bottom: 8px;
        }
        .stat-label { 
            color: #64748b; 
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-bar { 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            margin: 30px 0 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        .data-table { 
            width:100%; 
            border-collapse:separate; 
            border-spacing: 0;
            margin-top:20px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: white;
        }
        .data-table th { 
            background:#f8fafc; 
            padding:16px 20px; 
            text-align:left; 
            font-weight:600; 
            color:#1e293b; 
            border-bottom:2px solid #e2e8f0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td { 
            padding:16px 20px; 
            border-bottom:1px solid #e2e8f0; 
            color: #475569;
        }
        .data-table tr:hover { background:#f8fafc; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .status-badge { 
            display: inline-block; 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600; 
            letter-spacing: 0.3px;
        }
        .status-lost { background: #fee2e2; color: #991b1b; }
        .status-recovered { background: #d1fae5; color: #065f46; }
        .status-claimed { background: #dbeafe; color: #1e40af; }
        
        .empty-state { 
            text-align:center; 
            padding:60px 20px; 
            color:#64748b;
            background: white;
            border-radius: 12px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .empty-state i { 
            font-size:64px; 
            color:#cbd5e1; 
            margin-bottom:20px;
            opacity: 0.7;
        }
        .empty-state h3 { 
            margin:15px 0; 
            color:#475569;
            font-size: 22px;
        }
        .empty-state p { 
            margin-bottom:25px; 
            font-size: 16px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<?php require_once 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main">
    <!-- Header -->
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> 
            Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search lost items...">
            <i class="fas fa-search" id="searchIcon"></i>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <div class="page-title">
            <i class="fas fa-pencil-alt"></i>
            My Lost Items
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-btn" onclick="window.location.href='items/lost/add.php'">
                <i class="fas fa-plus"></i>
                <span>Add Lost Item</span>
            </div>
            <div class="action-btn" onclick="window.location.href='found_items.php'">
                <i class="fas fa-search"></i>
                <span>Check Found Items</span>
            </div>
            <div class="action-btn" onclick="window.location.href='claims/add.php'">
                <i class="fas fa-handshake"></i>
                <span>File a Claim</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='lost_items.php'">
                <div class="stat-number"><?php echo $total_lost; ?></div>
                <div class="stat-label">Total Lost Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_lost; ?></div>
                <div class="stat-label">Still Missing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $recovered_lost; ?></div>
                <div class="stat-label">Recovered</div>
            </div>
            <div class="stat-card" onclick="window.location.href='items/lost/add.php'">
                <div class="stat-number"><i class="fas fa-plus"></i></div>
                <div class="stat-label">Add New Item</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-buttons">
                <a href="items/lost/add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Lost Item
                </a>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <input type="text" id="searchItems" placeholder="Search items..." 
                       style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; width: 250px;">
                <select id="statusFilter" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <option value="">All Status</option>
                    <option value="Lost">Lost</option>
                    <option value="Recovered">Recovered</option>
                    <option value="Claimed">Claimed</option>
                </select>
            </div>
        </div>

        <!-- Lost Items Table -->
        <?php if($lost_items): ?>
        <div class="table-responsive">
            <table class="data-table" id="lostItemsTable">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Date Lost</th>
                        <th>Place Lost</th>
                        <th>Status</th>
                        <th>Claims</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lost_items as $item): ?>
                    <tr data-status="<?php echo $item['status']; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                            <small style="color: #64748b;"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                        </td>
                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($item['date_reported'])); ?><br>
                            <small style="color: #94a3b8;"><?php echo date('h:i A', strtotime($item['created_at'])); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($item['place_lost']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                <?php echo $item['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($item['claim_count'] > 0): ?>
                            <span class="badge" style="background: #10b981; color: white; padding: 4px 8px; border-radius: 12px;">
                                <?php echo $item['claim_count']; ?> claim(s)
                            </span>
                            <?php else: ?>
                            <span style="color: #94a3b8;">No claims</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a href="items/lost/view.php?id=<?php echo $item['lost_id']; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="items/lost/edit.php?id=<?php echo $item['lost_id']; ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if($item['status'] == 'Lost'): ?>
                            <button class="btn btn-warning btn-sm" 
                                    onclick="markAsRecovered(<?php echo $item['lost_id']; ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-circle"></i>
            <h3>No Lost Items</h3>
            <p>You haven't reported any lost items yet. If you've lost something, click the button below to add it to the system.</p>
            <a href="items/lost/add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Report Your First Lost Item
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include the common footer with JavaScript -->
<?php require_once 'includes/footer.php'; ?>

<script>
// Table filtering
function filterTable() {
    const status = document.getElementById('statusFilter')?.value.toLowerCase() || '';
    const search = document.getElementById('searchItems')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('#lostItemsTable tbody tr');
    
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status').toLowerCase();
        const rowText = row.textContent.toLowerCase();
        
        const statusMatch = !status || rowStatus === status;
        const searchMatch = !search || rowText.includes(search);
        
        if (statusMatch && searchMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

if (document.getElementById('statusFilter')) {
    document.getElementById('statusFilter').addEventListener('change', filterTable);
}

if (document.getElementById('searchItems')) {
    document.getElementById('searchItems').addEventListener('input', filterTable);
}

// Mark as recovered function
function markAsRecovered(lostId) {
    if (confirm('Mark this item as recovered? This cannot be undone.')) {
        // This would need an actual endpoint to handle the status update
        fetch(`items/lost/update_status.php?id=${lostId}&status=Recovered`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item marked as recovered!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
            });
    }
}
</script>
</body>
</html>