<?php
$host = "127.0.0.1";
$dbname = "LoFIMS_BASE";
$user = "root";
$pass = "Eljay108598100018"; // replace with your actual MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

