<?php
// Generate bcrypt hash for password: Techno3Lives
$password = 'Techno3Lives';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
