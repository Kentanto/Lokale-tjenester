<?php
// Generate bcrypt hash for password: Kent250804
$password = '123456789';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
