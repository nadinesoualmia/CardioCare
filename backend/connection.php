<?php

$host = "localhost";
$dbName = "cardiocare_db"; 
$dbUser = "root";
$dbPass = ""; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbName", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

?>