<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Set logout message in URL parameter
$message = urlencode('You have been successfully logged out.');

// Redirect to homepage (index.php in public folder) with success message
header("Location: ../public/index.php?logout=success&message=$message");
exit();
?>
