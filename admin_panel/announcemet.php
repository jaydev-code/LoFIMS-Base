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
<title>Announcements</title>
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
    <h1 class="page-title">Announcements</h1>

    <!-- Announcements Table -->
    <div class="card">
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Content</th>
                    <th>Date Created</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $stmt = $pdo->query("SELECT announcement_id, title, content, created_at FROM announcements ORDER BY created_at DESC");
                    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach($announcements as $announcement):
                ?>
                <tr>
                    <td><?= htmlspecialchars($announcement['announcement_id']) ?></td>
                    <td><?= htmlspecialchars($announcement['title']) ?></td>
                    <td><?= htmlspecialchars($announcement['content']) ?></td>
                    <td><?= htmlspecialchars($announcement['created_at']) ?></td>
                </tr>
                <?php
                    endforeach;
                } catch(PDOException $e){
                    echo "<tr><td colspan='4'>Error fetching data: ".$e->getMessage()."</td></tr>";
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
