<?php
// Simple Email Test for LoFIMS
echo "<h1>LoFIMS Email System Test</h1>";

// Test 1: Check if PHP mail() works
echo "<h2>Test 1: PHP mail() Function</h2>";
$to = "eljayfelismino@gmail.com";
$subject = "LoFIMS Test " . date('H:i:s');
$message = "Testing email system...";
$headers = "From: test@lofims.com\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "<p style='color: green;'>✅ mail() returned TRUE</p>";
} else {
    echo "<p style='color: red;'>❌ mail() returned FALSE</p>";
}

// Test 2: Check configuration
echo "<h2>Test 2: PHP Configuration</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "Server: " . $_SERVER['SERVER_NAME'] . "\n";
echo "</pre>";

// Test 3: Check file paths
echo "<h2>Test 3: File Locations</h2>";
echo "<pre>";
echo "Current dir: " . __DIR__ . "\n";
echo "Postfix config: /etc/postfix/main.cf\n";
echo "Credentials: /etc/postfix/sasl_passwd\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Check mail logs: <code>sudo tail -f /var/log/mail.log</code></li>";
echo "<li>Check mail queue: <code>sudo mailq</code></li>";
echo "<li>Check Postfix status: <code>sudo systemctl status postfix</code></li>";
echo "</ol>";
?>
