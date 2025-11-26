<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lost_id = $_POST['lost_id'];
    $found_id = $_POST['found_id'];
    $claimant_name = $_POST['claimant_name'];
    $date_claimed = $_POST['date_claimed'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO claims (lost_id, found_id, claimant_name, date_claimed)
            VALUES (:lost_id, :found_id, :claimant_name, :date_claimed)
        ");
        $stmt->execute([
            ':lost_id' => $lost_id,
            ':found_id' => $found_id,
            ':claimant_name' => $claimant_name,
            ':date_claimed' => $date_claimed
        ]);

        $success = "Claim added successfully!";

    } catch (PDOException $e) {
        $error = "Error adding claim: " . $e->getMessage();
    }
}

// Fetch lost items
$lostItems = $pdo->query("
    SELECT lost_id, item_name 
    FROM lost_items 
    ORDER BY item_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch found items
$foundItems = $pdo->query("
    SELECT found_id, item_name 
    FROM found_items 
    ORDER BY item_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Claim</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #ff4d4d; }

        .form-box {
            width: 450px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }

        label { font-weight: bold; }

        input, select, textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .submit-btn {
            padding: 10px 18px;
            background-color: #ff4d4d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        .submit-btn:hover {
            background-color: #ff1a1a;
        }

        .msg-success { color: green; font-weight: bold; }
        .msg-error { color: red; font-weight: bold; }
    </style>
</head>
<body>

<h1>Add Claim</h1>

<div class="form-box">

    <?php if (!empty($success)): ?>
        <p class="msg-success"><?= $success ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p class="msg-error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Lost Item:</label>
        <select name="lost_id" required>
            <option value="">Select Lost Item</option>
            <?php foreach ($lostItems as $li): ?>
                <option value="<?= $li['lost_id'] ?>"><?= htmlspecialchars($li['item_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Found Item:</label>
        <select name="found_id" required>
            <option value="">Select Found Item</option>
            <?php foreach ($foundItems as $fi): ?>
                <option value="<?= $fi['found_id'] ?>"><?= htmlspecialchars($fi['item_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Claimant Name:</label>
        <input type="text" name="claimant_name" required>

        <label>Date Claimed:</label>
        <input type="date" name="date_claimed" required>

        <button type="submit" class="submit-btn">Add Claim</button>
    </form>
</div>

</body>
</html>
