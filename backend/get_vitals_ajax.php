<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') {
    echo json_encode([]);
    exit();
}

$filter_patient = $_POST['filter_patient'] ?? '';
$filter_date = $_POST['filter_date'] ?? '';
$filter_status = $_POST['filter_status'] ?? '';

$query = "
    SELECT v.*, p.full_name AS patient_name, DATE_FORMAT(v.created_at, '%Y-%m-%d %H:%i:%s') as created_at
    FROM vitals v
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE 1=1
";

if ($filter_patient) {
    $query .= " AND p.full_name LIKE :patient";
}
if ($filter_date) {
    $query .= " AND DATE(v.created_at) = :date";
}
if ($filter_status == 'critical') {
    $query .= " AND (v.heart_rate < 50 OR v.heart_rate > 120 OR v.spo2 < 90 OR v.temperature > 39.5)";
} elseif ($filter_status == 'warning') {
    $query .= " AND ((v.heart_rate BETWEEN 50 AND 59 OR v.heart_rate BETWEEN 101 AND 120) OR (v.spo2 BETWEEN 90 AND 94) OR (v.temperature BETWEEN 38.6 AND 39.5))";
} elseif ($filter_status == 'normal') {
    $query .= " AND v.heart_rate BETWEEN 60 AND 100 AND v.spo2 >= 95 AND v.temperature <= 38.5";
}

$query .= " ORDER BY v.created_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
if ($filter_patient) {
    $stmt->bindValue(':patient', "%$filter_patient%");
}
if ($filter_date) {
    $stmt->bindValue(':date', $filter_date);
}
$stmt->execute();
$vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($vitals);
?>