<?php
// backend/generate_hash.php

$password = "admin123"; // change this to whatever password you want
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Your hashed password is: <br><br>";
echo $hash;
?>