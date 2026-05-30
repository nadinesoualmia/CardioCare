<?php
include 'connection.php';

$action = $_POST['action'] ?? '';

// DELETE vital
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';

    // Delete related alert first
    $stmt = $conn->prepare("DELETE FROM alerts WHERE vital_id = ?");
    $stmt->execute([$id]);

    // Delete vital
    $stmt = $conn->prepare("DELETE FROM vitals WHERE id = ?");
    echo $stmt->execute([$id]) ? 'success' : 'error';
}

// GET vital data for edit modal
elseif ($action === 'get') {
    $id = $_POST['id'] ?? '';
    $stmt = $conn->prepare("
        SELECT v.*, p.full_name AS patient_name
        FROM vitals v
        LEFT JOIN patients p ON v.patient_id = p.id
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data);
}

// UPDATE vital
elseif ($action === 'update') {
    $id            = $_POST['id']             ?? '';
    $heart_rate    = intval($_POST['heart_rate']    ?? 0);
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $temperature   = floatval($_POST['temperature'] ?? 0);
    $weight        = floatval($_POST['weight']       ?? 0);
    $spo2          = intval($_POST['spo2']           ?? 0);

    $stmt = $conn->prepare("
        UPDATE vitals 
        SET heart_rate=?, blood_pressure=?, temperature=?, weight=?, spo2=?
        WHERE id=?
    ");
    $result = $stmt->execute([$heart_rate, $blood_pressure, $temperature, $weight, $spo2, $id]);

    if ($result) {
        // Get patient_id for alert update
        $stmt2 = $conn->prepare("SELECT patient_id FROM vitals WHERE id = ?");
        $stmt2->execute([$id]);
        $vital = $stmt2->fetch(PDO::FETCH_ASSOC);
        $patient_id = $vital['patient_id'];

        // Recalculate alert
        $isCritical = ($heart_rate < 50 || $heart_rate > 120 || $spo2 < 90 || $temperature > 39.5);
        $isWarning  = (!$isCritical) && ($heart_rate < 60 || $heart_rate > 100 || $spo2 < 95 || $temperature > 38.5);

        if ($isCritical) {
            $alert_type = 'Critical';
            $msgs = [];
            if ($heart_rate < 50)    $msgs[] = "Heart rate critically LOW: {$heart_rate} bpm";
            if ($heart_rate > 120)   $msgs[] = "Heart rate critically HIGH: {$heart_rate} bpm";
            if ($spo2 < 90)          $msgs[] = "SpO2 critically LOW: {$spo2}%";
            if ($temperature > 39.5) $msgs[] = "Temperature critically HIGH: {$temperature}°C";
            $message = implode(' | ', $msgs);
        } elseif ($isWarning) {
            $alert_type = 'Warning';
            $msgs = [];
            if ($heart_rate < 60)    $msgs[] = "Heart rate low: {$heart_rate} bpm";
            if ($heart_rate > 100)   $msgs[] = "Heart rate high: {$heart_rate} bpm";
            if ($spo2 < 95)          $msgs[] = "SpO2 low: {$spo2}%";
            if ($temperature > 38.5) $msgs[] = "Temperature high: {$temperature}°C";
            $message = implode(' | ', $msgs);
        } else {
            $alert_type = 'Normal';
            $message = "All vitals within normal range.";
        }

        // Update existing alert or insert new one
        $stmt3 = $conn->prepare("SELECT id FROM alerts WHERE vital_id = ?");
        $stmt3->execute([$id]);
        $existingAlert = $stmt3->fetch(PDO::FETCH_ASSOC);

        if ($existingAlert) {
            $stmt4 = $conn->prepare("UPDATE alerts SET alert_type=?, message=?, status='Active' WHERE vital_id=?");
            $stmt4->execute([$alert_type, $message, $id]);
        } else {
            $stmt4 = $conn->prepare("INSERT INTO alerts (patient_id, vital_id, alert_type, message) VALUES (?,?,?,?)");
            $stmt4->execute([$patient_id, $id, $alert_type, $message]);
        }

        echo 'success';
    } else {
        echo 'error';
    }
}
?>