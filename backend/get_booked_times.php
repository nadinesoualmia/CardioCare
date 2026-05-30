<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_GET['doctor_id']) || !isset($_GET['date'])) {
    echo json_encode(["error" => "Missing parameters"]);
    exit();
}

$doctor_id = intval($_GET['doctor_id']);
$date = $_GET['date'];

// Check if doctor is in time off (vacation) - supports date ranges
$offStmt = $conn->prepare("
    SELECT off_date, end_date, reason 
    FROM doctor_time_off 
    WHERE doctor_id = ? 
    AND off_date <= ? 
    AND end_date >= ?
");
$offStmt->execute([$doctor_id, $date, $date]);
$timeOff = $offStmt->fetch(PDO::FETCH_ASSOC);

// If doctor is on vacation/time off
if ($timeOff) {
    $startDate = date('d M Y', strtotime($timeOff['off_date']));
    $endDate = date('d M Y', strtotime($timeOff['end_date']));
    $reason = $timeOff['reason'] ?? 'Vacation / Time Off';
    
    echo json_encode([
        "off" => true,
        "vacation" => true,
        "message" => "Doctor is on {$reason} from {$startDate} to {$endDate}",
        "start_date" => $timeOff['off_date'],
        "end_date" => $timeOff['end_date'],
        "reason" => $reason
    ]);
    exit();
}

// Get day of week (0=Sunday, 1=Monday, etc.)
$dayOfWeek = date('w', strtotime($date));

// Get doctor working hours for this day
$scheduleStmt = $conn->prepare("
    SELECT start_time, end_time 
    FROM doctor_schedules
    WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
");
$scheduleStmt->execute([$doctor_id, $dayOfWeek]);
$schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

// If no schedule → doctor not working this day
if (!$schedule) {
    echo json_encode([
        "off" => true,
        "vacation" => false,
        "message" => "Doctor is not scheduled to work on this day"
    ]);
    exit();
}

// Get booked appointment times for this doctor on this date
$stmt = $conn->prepare("
    SELECT TIME_FORMAT(appointment_time, '%H:%i') as appointment_time
    FROM appointments 
    WHERE doctor_id = ? 
    AND appointment_date = ?
    AND status NOT IN ('Cancelled', 'Completed')
");
$stmt->execute([$doctor_id, $date]);
$bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Return all data
echo json_encode([
    "off" => false,
    "booked" => $bookedTimes,
    "start" => substr($schedule['start_time'], 0, 5),
    "end" => substr($schedule['end_time'], 0, 5)
]);
?>