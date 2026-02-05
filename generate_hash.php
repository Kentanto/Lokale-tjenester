<?php
// Generate bcrypt hash for password: Lokale-Tjenester123!
$password = '';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
