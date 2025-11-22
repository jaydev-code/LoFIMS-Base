<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $item_name = $_POST['item_name'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $date_reported = $_POST['date_reported'];
    $status = 'Lost';

    try {
        $stmt = $pdo->prepare("INSERT INTO lost_items 
            (user_id, item_name, category_id, description, date_reported, status)
            VALUES (:user_id, :item_name, :category_id, :description, :date_reported, :status)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':item_name' => $item_name,
            ':category_id' => $category_id,
            ':description' => $description,
            ':date_reported' => $date_reported,
            ':status' => $status
        ]);
        $success = "Lost item reported successfully!";
    } catch (PDOException $e) {
        $error = "Error reporting lost item: " . $e->getMessage();
    }
}

// Fetch users and categories
$users = $pdo->query("SELECT user_id, first_name, last_name FROM users")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT category_id, category_name FROM item_categories")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Lost Item</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; text-align: center; }
        h1 { background: #ff8787; color: white; padding: 20px 0; margin: 0; }
        form { background: white; display: inline-block; padding: 20px; border-radius: 12px; margin-top: 40px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: left; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; margin-top: 5px; border-radius: 6px; border: 1px solid #ccc; }
        input[type="submit"] { width: auto; background: #ff4d4d; color: white; border: none; cursor: pointer; margin-top: 15px; }
        input[type="submit"]:hover { background: #ff1a1a; }
        .message { margin-top: 15px; font-weight: bold; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Report Lost Item</h1>

    <?php if (!empty($success)) echo "<p class='message success'>$success</p>"; ?>
    <?php if (!empty($error)) echo "<p class='message error'>$error</p>"; ?>

    <form method="post" action="">
        <label>User:</label>
        <select name="user_id" required>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['user_id'] ?>"><?= $user['first_name'] . ' ' . $user['last_name'] ?></option>
            <?php endforeach; ?>
        </select>

        <label>Item Name:</label>
        <input type="text" name="item_name" required>

        <label>Category:</label>
        <select name="category_id" required>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>"><?= $cat['category_name'] ?></option>
            <?php endforeach; ?>
        </select>

        <label>Description:</label>
        <textarea name="description" rows="4"></textarea>

        <label>Date Reported:</label>
        <input type="date" name="date_reported" required>

        <input type="submit" value="Report Lost Item">
    </form>
</body>
</html>
