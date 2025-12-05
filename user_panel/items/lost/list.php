 <?php
require_once '../../includes/header.php';

// Fetch user's lost items
$stmt = $pdo->prepare("
    SELECT li.*, ic.category_name 
    FROM lost_items li 
    LEFT JOIN item_categories ic ON li.category_id = ic.category_id 
    WHERE li.user_id = ? 
    ORDER BY li.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$lost_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main">
    <div class="header">
        <div class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> 
            Hello, <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
        </div>
        <div class="search-bar" role="search">
            <input type="text" id="globalSearch" placeholder="Search items...">
            <i class="fas fa-search"></i>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title"><i class="fas fa-pencil-alt"></i> My Lost Items</h1>
        
        <div class="page-actions">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Report Lost Item
            </a>
        </div>
        
        <?php if($lost_items): ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Date Lost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lost_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['category_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($item['date_reported'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($item['status']) ?>">
                                <?= $item['status'] ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="view.php?id=<?= $item['lost_id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit.php?id=<?= $item['lost_id'] ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open fa-3x"></i>
            <h3>No Lost Items</h3>
            <p>You haven't reported any lost items yet.</p>
            <a href="add.php" class="btn btn-primary">Report Your First Lost Item</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
