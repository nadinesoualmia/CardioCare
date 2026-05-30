<?php
session_start();
include 'connection.php';

// Get the logged-in nurse's ID
$created_by = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

$patient_name  = trim($_POST['patient_name'] ?? '');
$heart_rate    = intval($_POST['heart_rate'] ?? 0);
$blood_pressure = trim($_POST['blood_pressure'] ?? '');
$temperature   = floatval($_POST['temperature'] ?? 0);
$weight        = floatval($_POST['weight'] ?? 0);
$spo2          = intval($_POST['spo2'] ?? 0);

// Get patient_id from name
$stmt = $conn->prepare("SELECT id FROM patients WHERE full_name = ?");
$stmt->execute([$patient_name]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo json_encode(['status' => 'error', 'message' => 'Patient not found']);
    exit();
}

$patient_id = $patient['id'];

// Insert vitals with created_by (nurse ID)
$stmt = $conn->prepare("
    INSERT INTO vitals (patient_id, heart_rate, blood_pressure, temperature, weight, spo2, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$result = $stmt->execute([$patient_id, $heart_rate, $blood_pressure, $temperature, $weight, $spo2, $created_by]);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save vitals']);
    exit();
}

$vital_id = $conn->lastInsertId();

// Determine alert type based on thresholds
$is_critical = ($heart_rate < 50 || $heart_rate > 120 || $spo2 < 90 || $temperature > 39.5);
$is_warning  = (!$is_critical) && ($heart_rate < 60 || $heart_rate > 100 || $spo2 < 95 || $temperature > 38.5);

if ($is_critical) {
    $alert_type = 'Critical';
    $messages = [];
    if ($heart_rate < 50)    $messages[] = "Heart Rate critically LOW: {$heart_rate} bpm (normal: 60-100)";
    if ($heart_rate > 120)   $messages[] = "Heart Rate critically HIGH: {$heart_rate} bpm (normal: 60-100)";
    if ($spo2 < 90)          $messages[] = "SpO2 critically LOW: {$spo2}% (normal: ≥95%)";
    if ($temperature > 39.5) $messages[] = "Temperature critically HIGH: {$temperature}°C (normal: ≤38.5°C)";
    $message = implode(' | ', $messages);
    $alert_status = 'Active';
} elseif ($is_warning) {
    $alert_type = 'Warning';
    $messages = [];
    if ($heart_rate < 60)    $messages[] = "Heart Rate LOW: {$heart_rate} bpm (normal: 60-100)";
    if ($heart_rate > 100)   $messages[] = "Heart Rate HIGH: {$heart_rate} bpm (normal: 60-100)";
    if ($spo2 < 95 && $spo2 >= 90) $messages[] = "SpO2 LOW: {$spo2}% (normal: ≥95%)";
    if ($temperature > 38.5 && $temperature <= 39.5) $messages[] = "Temperature HIGH: {$temperature}°C (normal: ≤38.5°C)";
    $message = implode(' | ', $messages);
    $alert_status = 'Active';
} else {
    $alert_type = 'Normal';
    $message = "All vitals are within normal range. HR: {$heart_rate}, BP: {$blood_pressure}, Temp: {$temperature}°C, SpO2: {$spo2}%, Weight: {$weight}kg";
    $alert_status = 'Acknowledged';
}

// Insert alert
$stmt = $conn->prepare("INSERT INTO alerts (patient_id, vital_id, alert_type, message, status)
    VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$patient_id, $vital_id, $alert_type, $message, $alert_status]);

// ✅ MARK APPOINTMENT AS COMPLETED - FIXED FOR YOUR TABLE STRUCTURE
// Update both 'Waiting' and 'Scheduled' statuses to 'Completed'
$updateAppt = $conn->prepare("
    UPDATE appointments 
    SET status = 'Completed', nurse_id = ?
    WHERE patient_id = ? 
    AND appointment_date = CURDATE() 
    AND service = 'Consultation'
    AND payment_status = 'Paid'
    AND status IN ('Waiting', 'Scheduled')
    LIMIT 1
");
$updateAppt->execute([$created_by, $patient_id]);

// Check if any row was updated
$rowCount = $updateAppt->rowCount();

// For debugging - you can remove this after testing
error_log("Appointment update for patient_id: $patient_id, rows updated: $rowCount");

echo json_encode([
    'status'     => 'success',
    'alert_type' => $alert_type,
    'message'    => $message
]);
?>