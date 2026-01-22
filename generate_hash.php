<?php
// Generate bcrypt hash for password: Kent250804
$password = 'Kent250804';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
