<?php
// Determine active page
$active_page = basename($_SERVER['PHP_SELF']);
$page_titles = [
    'dashboard.php' => 'Dashboard',
    'lost_items.php' => 'Lost Items',
    'found_items.php' => 'Found Items',
    'claims.php' => 'Claims',
    'announcements.php' => 'Announcements'
];
$page_title = $page_titles[$active_page] ?? 'User Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoFIMS - <?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- ABSOLUTELY CRITICAL: Font Awesome MUST load FIRST -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- YOUR CSS - Check the path is correct -->
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Chart.js for dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Prevent caching during development -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>