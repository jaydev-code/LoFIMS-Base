<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logo Test</title>
</head>
<body style="background: #f0f0f0;">
    
    <h1>Logo Test Page</h1>
    
    <!-- Test 1: Direct image -->
    <div style="background: white; padding: 20px; margin: 20px; border: 2px solid red;">
        <h3>Test 1: Direct Image Tag</h3>
        <img src="/assets/images/lofims-logo.png" alt="Test" style="height: 100px; border: 3px solid blue;">
        <p>Path: /assets/images/lofims-logo.png</p>
    </div>
    
    <!-- Test 2: Absolute path -->
    <div style="background: white; padding: 20px; margin: 20px; border: 2px solid green;">
        <h3>Test 2: Absolute URL</h3>
        <?php
        $base_url = "http://" . $_SERVER['HTTP_HOST'];
        $logo_url = $base_url . "/assets/images/lofims-logo.png";
        ?>
        <img src="<?php echo $logo_url; ?>" alt="Test" style="height: 100px; border: 3px solid green;">
        <p>URL: <?php echo $logo_url; ?></p>
        <p><a href="<?php echo $logo_url; ?>" target="_blank">Click to open logo directly</a></p>
    </div>
    
    <!-- Test 3: Try different paths -->
    <div style="background: white; padding: 20px; margin: 20px; border: 2px solid blue;">
        <h3>Test 3: Different Paths</h3>
        
        <?php
        $test_paths = [
            '/assets/images/lofims-logo.png',
            '../assets/images/lofims-logo.png',
            '../../assets/images/lofims-logo.png',
            'assets/images/lofims-logo.png',
            '/LoFIMS_BASE/assets/images/lofims-logo.png'
        ];
        
        foreach ($test_paths as $path) {
            echo '<div style="margin: 10px 0; padding: 10px; background: #f8f8f8;">';
            echo '<strong>Path:</strong> ' . htmlspecialchars($path) . '<br>';
            echo '<img src="' . htmlspecialchars($path) . '" alt="Test" style="height: 50px; border: 1px solid #ccc; margin: 5px 0;">';
            
            // Check if file exists
            $full_path = dirname(__FILE__) . '/' . $path;
            if (file_exists($full_path)) {
                echo ' <span style="color: green;">✓ File exists</span>';
            } else {
                echo ' <span style="color: red;">✗ File not found</span>';
            }
            
            echo '</div>';
        }
        ?>
    </div>
    
    <!-- Test 4: Base URL from config -->
    <div style="background: white; padding: 20px; margin: 20px; border: 2px solid purple;">
        <h3>Test 4: Current Directory Info</h3>
        <?php
        echo '<p><strong>Current file:</strong> ' . __FILE__ . '</p>';
        echo '<p><strong>Document root:</strong> ' . $_SERVER['DOCUMENT_ROOT'] . '</p>';
        echo '<p><strong>Request URI:</strong> ' . $_SERVER['REQUEST_URI'] . '</p>';
        echo '<p><strong>Base URL:</strong> http://' . $_SERVER['HTTP_HOST'] . '</p>';
        
        // Try to find logo
        $possible_locations = [
            $_SERVER['DOCUMENT_ROOT'] . '/assets/images/lofims-logo.png',
            dirname(__FILE__, 2) . '/assets/images/lofims-logo.png', // Go up 2 levels
            dirname(__FILE__, 3) . '/assets/images/lofims-logo.png', // Go up 3 levels
        ];
        
        foreach ($possible_locations as $location) {
            echo '<p>';
            echo '<strong>Checking:</strong> ' . $location . '<br>';
            if (file_exists($location)) {
                echo '<span style="color: green;">✓ FOUND! Size: ' . filesize($location) . ' bytes</span>';
                
                // Calculate relative path
                $relative = str_replace($_SERVER['DOCUMENT_ROOT'], '', $location);
                echo '<br><strong>Use this path:</strong> ' . $relative;
            } else {
                echo '<span style="color: red;">✗ Not found</span>';
            }
            echo '</p>';
        }
        ?>
    </div>

</body>
</html>