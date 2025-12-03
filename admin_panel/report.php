<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

// Only admin allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../../public/login.php");
    exit();
}

// Fetch admin name
$userName = "Administrator";
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) $userName = $row['first_name'] . ' ' . $row['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Reports</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../dashboard.css">
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/../sidebar.php'; ?>

<!-- Main content -->
<div class="main">
    <!-- Header -->
    <?php include __DIR__ . '/../header.php'; ?>

    <!-- Page Title -->
    <h1 class="page-title">System Reports</h1>

    <!-- Reports Table -->
    <div class="card">
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>User</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Fetch lost items
                    $stmt = $pdo->query("
                        SELECT 
                            l.lost_id AS record_id,
                            'Lost Item' AS type,
                            l.item_name AS item,
                            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                            l.date_reported AS date_field
                        FROM lost_items l
                        LEFT JOIN users u ON l.user_id = u.user_id
                    ");
                    $lostItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch found items
                    $stmt = $pdo->query("
                        SELECT 
                            f.found_id AS record_id,
                            'Found Item' AS type,
                            f.item_name AS item,
                            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                            f.date_found AS date_field
                        FROM found_items f
                        LEFT JOIN users u ON f.user_id = u.user_id
                    ");
                    $foundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch claims
                    $stmt = $pdo->query("
                        SELECT 
                            c.claim_id AS record_id,
                            'Claim' AS type,
                            CONCAT('Claim for Lost ID ', c.lost_id, ' / Found ID ', c.found_id) AS item,
                            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                            c.date_claimed AS date_field
                        FROM claims c
                        LEFT JOIN users u ON c.user_id = u.user_id
                    ");
                    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Merge all records
                    $allRecords = array_merge($lostItems, $foundItems, $claims);

                    // Sort by date descending
                    usort($allRecords, function($a, $b){
                        return strtotime($b['date_field']) - strtotime($a['date_field']);
                    });

                    foreach($allRecords as $record):
                ?>
                <tr>
                    <td><?= htmlspecialchars($record['record_id']) ?></td>
                    <td><?= htmlspecialchars($record['type']) ?></td>
                    <td><?= htmlspecialchars($record['item']) ?></td>
                    <td><?= htmlspecialchars($record['user_name'] ?: "Unknown User") ?></td>
                    <td><?= htmlspecialchars($record['date_field']) ?></td>
                </tr>
                <?php
                    endforeach;
                } catch(PDOException $e){
                    echo "<tr><td colspan='5'>Error fetching data: ".$e->getMessage()."</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JS -->
<script src="../dashboard.js"></script>
</body>
</html>
