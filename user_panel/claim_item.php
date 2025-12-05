<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT cl.claim_id,
               li.item_name AS lost_item,
               fi.item_name AS found_item,
               cl.claimant_name,
               cl.date_claimed
        FROM claims cl
        LEFT JOIN lost_items li ON cl.lost_id = li.lost_id
        LEFT JOIN found_items fi ON cl.found_id = fi.found_id
        ORDER BY cl.date_claimed DESC
    ");
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching claims: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim Items List</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            text-align: center;
        }
        h1 { 
            color: #ff4d4d; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background-color: #ffcccc; 
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }

        /* Centered red add button */
        .add-btn {
            display: inline-block;
            padding: 10px 18px;
            background-color: #ff4d4d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 15px;
            transition: 0.2s;
        }
        .add-btn:hover { 
            background-color: #ff1a1a; 
        }
    </style>
</head>
<body>

    <h1>Claim Items</h1>

    <a class="add-btn" href="add_claim_items.php">Add Claim</a>

    <?php if (!empty($claims)): ?>
    <table>
        <tr>
            <th>Claim ID</th>
            <th>Lost Item</th>
            <th>Found Item</th>
            <th>Claimant Name</th>
            <th>Date Claimed</th>
        </tr>

        <?php foreach($claims as $cl): ?>
        <tr>
            <td><?= htmlspecialchars($cl['claim_id']) ?></td>
            <td><?= htmlspecialchars($cl['lost_item'] ?? '—') ?></td>
            <td><?= htmlspecialchars($cl['found_item'] ?? '—') ?></td>
            <td><?= htmlspecialchars($cl['claimant_name']) ?></td>
            <td><?= htmlspecialchars($cl['date_claimed']) ?></td>
        </tr>
        <?php endforeach; ?>

    </table>
    <?php else: ?>
        <p>No claims to display.</p>
    <?php endif; ?>

</body>
</html>
zc