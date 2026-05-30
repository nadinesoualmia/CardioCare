<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log everything to a file so we can see what's happening
$log = date('H:i:s') . " | POST: " . json_encode($_POST) . "\n";
file_put_contents(__DIR__ . '/debug.log', $log, FILE_APPEND);

include 'connection.php';

if (!isset($conn)) {
    echo 'error: connection failed';
    exit();
}

$action = $_POST['action'] ?? 'NONE';

if ($action === 'markComplete') {
    $queue_number = $_POST['queue_number'] ?? '';
    $stmt = $conn->prepare("UPDATE appointments SET status='Completed' WHERE queue_number=?");
    $result = $stmt->execute([$queue_number]);
    $err = $stmt->errorInfo();
    echo $result ? 'success' : 'error: ' . $err[2];
}

elseif ($action === 'delete') {
    $id = $_POST['id'] ?? '';

    // Delete related payments first
    try {
        $stmt0 = $conn->prepare("DELETE FROM payments WHERE appointment_id = ?");
        $stmt0->execute([$id]);
    } catch (Exception $e) {
        // ignore if no payments
    }

    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $result = $stmt->execute([$id]);
    $err = $stmt->errorInfo();
    echo $result ? 'success' : 'error: ' . $err[2];
}

elseif ($action === 'edit') {
    $id = $_POST['id'] ?? '';
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.full_name AS patient_name,
               u.full_name AS doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data);
}

elseif ($action === 'update') {
    $id = $_POST['id'] ?? '';
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET service=?, doctor_id=?, case_type=?, price=?, appointment_date=?, appointment_time=?
        WHERE id=?
    ");
    $result = $stmt->execute([
        $_POST['service']          ?? '',
        $_POST['doctor_id']        ?? '',
        $_POST['case_type']        ?? '',
        $_POST['price']            ?? '',
        $_POST['appointment_date'] ?? '',
        $_POST['appointment_time'] ?? '',
        $id
    ]);
    $err = $stmt->errorInfo();
    echo $result ? 'success' : 'error: ' . $err[2];
}

elseif ($action === 'pay') {
    $id = $_POST['id'] ?? '';
    $stmt = $conn->prepare("SELECT price FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) { echo 'error: not found'; exit(); }

    $stmt1 = $conn->prepare("UPDATE appointments SET payment_status='Paid' WHERE id=?");
    $res1  = $stmt1->execute([$id]);

    $stmt2 = $conn->prepare("INSERT INTO payments (appointment_id, amount, method, status) VALUES (?, ?, 'Cash', 'Paid')");
    $res2  = $stmt2->execute([$id, $appointment['price']]);

    echo ($res1 && $res2) ? 'success' : 'error: pay failed';
}

else {
    echo 'error: unknown action = [' . htmlspecialchars($action) . ']';
}
?>