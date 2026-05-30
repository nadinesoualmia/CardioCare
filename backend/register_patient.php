<?php
session_start();
header('Content-Type: application/json');
require 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = $_POST['fullName'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $emergency = $_POST['emergency'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $nin = $_POST['nin'] ?? '';

    // Validate required fields
    if (!$fullName || !$phone || !$gender || !$dob) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields (Name, Phone, Gender, Date of Birth)']);
        exit;
    }

    // Validate emergency number format if provided
    if (!empty($emergency)) {
        $emergencyPattern = '/^(05|06|07)\d{8}$|^(\+213)(5|6|7)\d{8}$/';
        if (!preg_match($emergencyPattern, $emergency)) {
            echo json_encode(['status' => 'error', 'message' => 'Emergency contact must be a valid Algerian number']);
            exit;
        }
    }

    // Check duplicate by phone or by NIN
    $duplicateMessage = '';
    
    // Check by phone
    $stmt = $conn->prepare("SELECT full_name FROM patients WHERE phone = :phone LIMIT 1");
    $stmt->execute([':phone' => $phone]);
    $existingByPhone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingByPhone) {
        $duplicateMessage = 'This phone number already belongs to: ' . $existingByPhone['full_name'];
    }
    
    // Check by NIN (only if NIN is provided)
    if (empty($duplicateMessage) && !empty($nin)) {
        $stmt = $conn->prepare("SELECT full_name FROM patients WHERE nin = :nin LIMIT 1");
        $stmt->execute([':nin' => $nin]);
        $existingByNin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingByNin) {
            $duplicateMessage = 'This National ID (NIN) already belongs to: ' . $existingByNin['full_name'];
        }
    }
    
    // If duplicate found
    if (!empty($duplicateMessage)) {
        echo json_encode(['status' => 'duplicate', 'message' => $duplicateMessage]);
        exit;
    }

    // Insert new patient
    $stmt = $conn->prepare("INSERT INTO patients (full_name, phone, emergency_contact, gender, dob, email, address, nin) 
                            VALUES (:full, :phone, :emergency, :gender, :dob, :email, :address, :nin)");
    
    $success = $stmt->execute([
        ':full' => $fullName,
        ':phone' => $phone,
        ':emergency' => !empty($emergency) ? $emergency : null,
        ':gender' => $gender,
        ':dob' => $dob,
        ':email' => !empty($email) ? $email : null,
        ':address' => !empty($address) ? $address : null,
        ':nin' => !empty($nin) ? $nin : null
    ]);

    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Patient registered successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to register patient']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>