<?php
session_start();
include 'connection.php';

$appointment_id = intval($_POST['appointment_id'] ?? 0);
$nurse_id = intval($_POST['nurse_id'] ?? 0);

if (!$appointment_id || !$nurse_id) {
    echo 'error: missing parameters';
    exit();
}

$stmt = $conn->prepare("UPDATE appointments SET nurse_id = ? WHERE id = ?");
$result = $stmt->execute([$nurse_id, $appointment_id]);

echo $result ? 'success' : 'error';
?>