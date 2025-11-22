<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';

try {
    $totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items WHERE status='Lost'")->fetchColumn();
    $totalFound = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status='Found'")->fetchColumn();
    $totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lost & Found Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }

        /* HEADER */
        header {
            background-color: #ff8787;
            padding: 15px 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        header h2 {
            margin: 0;
        }

        .logout-btn {
            background: #ff4d4d;
            padding: 10px 18px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: 0.2s;
        }
        .logout-btn:hover {
            background: #ff1a1a;
        }

        /* DASHBOARD BOXES */
        .container {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 30px;
        }

        .box {
            width: 220px;
            height: 150px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            transition: 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .box:hover {
            transform: scale(1.05);
            background: #ffe5e5;
        }

        .box h2 {
            font-size: 36px;
            margin: 0;
            color: #007bff;
        }
        .box p {
            margin: 5px 0 0;
            font-size: 18px;
            color: #555;
        }
    </style>
</head>
<body>

<header>
    <h2>Lost & Found System</h2>
    <a class="logout-btn" href="auth/logout.php">Logout</a>
</header>

<div class="container">
    <a href="lost_items.php" class="box">
        <h2><?= $totalLost ?></h2>
        <p>Lost Items</p>
    </a>

    <a href="found_items.php" class="box">
        <h2><?= $totalFound ?></h2>
        <p>Found Items</p>
    </a>

    <a href="claim_item.php" class="box">
        <h2><?= $totalClaims ?></h2>
        <p>Claims</p>
    </a>
</div>

</body>
</html>
