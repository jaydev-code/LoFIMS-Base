<?php
// generate_correct_hash.php
$password = 'admin123';
echo "Hash for '$password':<br>";
echo password_hash($password, PASSWORD_DEFAULT);
?>
