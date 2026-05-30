<?php
session_start();
header('Content-Type: application/json');
include 'connection.php';

$nurse_id = $_GET['nurse_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

$stmt = $conn->prepare("
    SELECT a.id, a.appointment_time, a.nurse_id,
           p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE a.service = 'Consultation'
    AND a.appointment_date = CURDATE()
    AND a.payment_status = 'Paid'
    AND a.status IN ('Waiting', 'Scheduled')
    AND (a.nurse_id = ? OR a.nurse_id IS NULL)
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$nurse_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($appointments);
?>