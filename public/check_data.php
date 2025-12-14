<?php
// check_data.php
$host = 'localhost';
$dbname = 'LoFIMS_BASE';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Checking Your Actual Database Data</h2>";
    echo "<pre>";
    
    // 1. Check lost items
    echo "=== LOST ITEMS ===\n";
    $lost = $conn->query("SELECT lost_id, item_name, status FROM lost_items LIMIT 5");
    foreach($lost as $row) {
        print_r($row);
    }
    echo "\n";
    
    // 2. Check found items
    echo "=== FOUND ITEMS ===\n";
    $found = $conn->query("SELECT found_id, item_name, status FROM found_items LIMIT 5");
    foreach($found as $row) {
        print_r($row);
    }
    echo "\n";
    
    // 3. Check claims
    echo "=== CLAIMS ===\n";
    $claims = $conn->query("SELECT claim_id, status FROM claims LIMIT 5");
    foreach($claims as $row) {
        print_r($row);
    }
    echo "\n";
    
    // 4. Check users
    echo "=== USERS ===\n";
    $users = $conn->query("SELECT user_id, first_name, last_name FROM users LIMIT 5");
    foreach($users as $row) {
        print_r($row);
    }
    echo "\n";
    
    // 5. Check announcements
    echo "=== ANNOUNCEMENTS ===\n";
    $announcements = $conn->query("SELECT id, title FROM announcements LIMIT 5");
    foreach($announcements as $row) {
        print_r($row);
    }
    echo "\n";
    
    // 6. Real statistics
    echo "=== REAL STATISTICS ===\n";
    
    $stats = [
        'total_lost' => $conn->query("SELECT COUNT(*) as c FROM lost_items")->fetch()['c'],
        'total_found' => $conn->query("SELECT COUNT(*) as c FROM found_items")->fetch()['c'],
        'total_claims' => $conn->query("SELECT COUNT(*) as c FROM claims")->fetch()['c'],
        'total_users' => $conn->query("SELECT COUNT(*) as c FROM users")->fetch()['c'],
        'total_announcements' => $conn->query("SELECT COUNT(*) as c FROM announcements")->fetch()['c'],
    ];
    
    // Check status distribution
    echo "Lost Items Status:\n";
    $lostStatus = $conn->query("SELECT status, COUNT(*) as count FROM lost_items GROUP BY status");
    foreach($lostStatus as $row) {
        echo "  {$row['status']}: {$row['count']}\n";
    }
    
    echo "\nFound Items Status:\n";
    $foundStatus = $conn->query("SELECT status, COUNT(*) as count FROM found_items GROUP BY status");
    foreach($foundStatus as $row) {
        echo "  {$row['status']}: {$row['count']}\n";
    }
    
    echo "\nClaims Status:\n";
    $claimsStatus = $conn->query("SELECT status, COUNT(*) as count FROM claims GROUP BY status");
    foreach($claimsStatus as $row) {
        echo "  {$row['status']}: {$row['count']}\n";
    }
    
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>