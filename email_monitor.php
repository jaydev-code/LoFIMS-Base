<?php
// Check recent email deliveries
$logs = shell_exec('sudo grep "status=sent" /var/log/mail.log | tail -10');
echo "<h3>Recent Email Deliveries:</h3>";
echo "<pre>" . htmlspecialchars($logs) . "</pre>";

// Check queue
echo "<h3>Current Mail Queue:</h3>";
echo "<pre>" . shell_exec('sudo mailq 2>&1') . "</pre>";

// Check Postfix status
echo "<h3>Postfix Status:</h3>";
echo "<pre>" . shell_exec('sudo systemctl status postfix --no-pager 2>&1') . "</pre>";
?>
