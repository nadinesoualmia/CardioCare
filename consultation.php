<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get doctor ID from session
$doctor_id = $_SESSION['user_id'];

// Get user info for profile
$userName = $_SESSION['name'] ?? 'Doctor';
$userRole = $_SESSION['role'] ?? 'Doctor';
$userAvatar = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";

// Get active alert count for THIS doctor's patients only (Critical & Warning)
$activeCountStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM alerts a
    WHERE a.status = 'Active'
    AND a.alert_type IN ('Critical', 'Warning')
    AND a.patient_id IN (
        SELECT DISTINCT patient_id 
        FROM appointments 
        WHERE doctor_id = ?
    )
");
$activeCountStmt->execute([$doctor_id]);
$activeCount = $activeCountStmt->fetchColumn();

$success = false;

/* load patients - ONLY patients who have appointments with THIS doctor */
$stmt = $conn->prepare("
    SELECT DISTINCT p.id, p.full_name 
    FROM patients p
    JOIN appointments a ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY p.full_name ASC
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patient_id     = $_POST['patient_id'];
    $doctor_id      = $_SESSION['user_id'];

    $blood_type     = $_POST['blood_type'];
    $cv_risk        = $_POST['cv_risk'];
    $family_history = $_POST['family_history'];
    $clinical_exam  = $_POST['clinical_exam'];
    $diagnosis      = $_POST['diagnosis'];
    $treatment      = $_POST['treatment'];

    $stmt = $conn->prepare("
        INSERT INTO consultations 
        (patient_id, doctor_id, diagnosis, treatment, blood_type, cv_risk, family_history, clinical_exam)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $patient_id,
        $doctor_id,
        $diagnosis,
        $treatment,
        $blood_type,
        $cv_risk,
        $family_history,
        $clinical_exam
    ]);

    $success = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Consultation - CardioCare</title>
 <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/consultation.css">
    
    <style>
        /* ========================================
           OVERRIDE CONSULTATION.CSS CONFLICTS
           ======================================== */
        
        /* Fix main content - remove padding-top that pushes content down */
        .main-content {
            margin-left: 260px;
            padding-top: 0 !important;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        /* Fix sidebar - remove static position */
        .sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        /* Sidebar header */
        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .sidebar-header .logo i {
            color: #2563eb;
            font-size: 24px;
        }
        
        /* Sidebar nav */
        .sidebar-nav {
            flex: 1;
            padding: 20px 12px;
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: 8px;
            position: relative;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-item i {
            width: 20px;
            font-size: 18px;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: #eff6ff;
            color: #2563eb;
        }
        
        /* Alert badge */
        .alert-badge {
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 1px 7px;
            font-size: 0.7rem;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
        }
        
        /* Sidebar footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .logout-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            background: #ef4444 !important;
            color: white !important;
            text-decoration: none !important;
            padding: 10px 16px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: background 0.2s !important;
        }
        
        .logout-btn:hover {
            background: #dc2626 !important;
        }
        
        /* Top navbar - AT THE VERY TOP */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            height: 70px;
            width: 100%;
        }
        
        /* User profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
        }
        
        .user-role {
            font-size: 12px;
            color: #6b7280;
        }
        
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        
        /* Mobile toggle button */
        .toggle-sidebar-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #64748b;
            display: none;
        }
        
        /* Consultation container */
        .consultation-container {
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* ========================================
           SMALLER BUTTON - SAME STYLE AS CANCEL
           ======================================== */
        .form-submit {
            text-align: center;
            margin-top: 24px;
        }
        
        .btn-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 502;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: #1d4ed8;
        }
        
        /* Cancel button style (same as exam-request.php) */
        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e5e7eb;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-cancel:hover {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }
        
        /* Consultation header */
        .consultation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .consultation-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .consultation-header h2 i {
            color: #2563eb;
        }
        
        /* Success message */
        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .warning-message {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        /* Card styles */
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }
        
        /* Form groups */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #374151;
        }
        
        .form-group label .optional {
            font-weight: normal;
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-row-2-1 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .toggle-sidebar-btn {
                display: block;
            }
            
            .consultation-container {
                padding: 16px;
            }
            
            .form-row-2-1,
            .form-row-2 {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
    </style>
</head>

<body class="dashboard-body">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CardioCare</span>
        </div>
    </div>

    <ul class="sidebar-nav">
        <li><a href="doctor-dashboard.php" class="nav-item"><i class="fa-solid fa-stethoscope"></i> Overview</a></li>
        <li><a href="patient-record.php" class="nav-item"><i class="fa-solid fa-file-medical"></i> Medical Records</a></li>
        <li><a href="consultation.php" class="nav-item active"><i class="fa-solid fa-user-doctor"></i> Consultation</a></li>
        <li><a href="exam-request.php" class="nav-item"><i class="fa-solid fa-file-prescription"></i> Exam Requests</a></li>
        <li style="position: relative;">
            <a href="alerts.php" class="nav-item">
                <i class="fa-solid fa-bell"></i> Alerts
            </a>
            <?php if ($activeCount > 0): ?>
                <span class="alert-badge">
                    <?= $activeCount ?>
                </span>
            <?php endif; ?>
        </li> 
    </ul>

    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<!-- MAIN -->
<main class="main-content">

    <!-- TOP NAVBAR WITH PROFILE - AT THE VERY TOP -->
    <header class="top-navbar">
        <button class="toggle-sidebar-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="user-profile">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" class="avatar" alt="avatar">
        </div>
    </header>

    <!-- CONSULTATION CONTENT -->
    <div class="consultation-container">

        <!-- HEADER -->
        <div class="consultation-header">
            <h2>
                <i class="fa-solid fa-notes-medical"></i> New Consultation
            </h2>

            <a href="doctor-dashboard.php" class="btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
        </div>

        <!-- SUCCESS -->
        <?php if ($success): ?>
        <div class="success-message">
            <i class="fa-solid fa-circle-check"></i> Consultation saved successfully
        </div>
        <?php endif; ?>

        <!-- Warning if no patients assigned -->
        <?php if (empty($patients)): ?>
        <div class="warning-message">
            <i class="fa-solid fa-circle-exclamation"></i> You don't have any patients assigned to you yet.
        </div>
        <?php endif; ?>

        <!-- FORM -->
        <div class="card">

        <form method="POST" id="consultationForm">

        <h3 class="form-title">
            <i class="fa-solid fa-file-signature"></i> Patient Information
        </h3>

        <div class="form-row-2-1">
            <!-- PATIENT SEARCH -->
            <div class="form-group">
                <label>Patient Name</label>
                <input type="text" list="patientDataList" id="patientSearch" required placeholder="Select a patient...">
                <datalist id="patientDataList">
                    <?php foreach($patients as $p): ?>
                        <option data-id="<?= $p['id'] ?>" value="<?= htmlspecialchars($p['full_name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="patient_id" id="patient_id">
            </div>

            <!-- BLOOD -->
            <div class="form-group">
                <label>Blood Type</label>
                <select name="blood_type" required>
                    <option value="" disabled selected>Select...</option>
                    <option>A+</option><option>A-</option>
                    <option>B+</option><option>B-</option>
                    <option>AB+</option><option>AB-</option>
                    <option>O+</option><option>O-</option>
                </select>
            </div>
        </div>

        <!-- RISK -->
        <div class="form-row-2">
            <div class="form-group">
                <label>Cardiovascular Risk Factors <span class="optional">(Optional)</span></label>
                <input type="text" name="cv_risk" placeholder="Hypertension, Smoking...">
            </div>

            <div class="form-group">
                <label>Family History <span class="optional">(Optional)</span></label>
                <input type="text" name="family_history">
            </div>
        </div>

        <!-- CLINICAL -->
        <div class="form-group">
            <label>Clinical Examination & Symptoms</label>
            <textarea name="clinical_exam" rows="4" required></textarea>
        </div>

        <!-- DIAGNOSIS -->
        <div class="form-group">
            <label>Diagnosis</label>
            <input type="text" name="diagnosis" required>
        </div>

        <!-- TREATMENT -->
        <div class="form-group">
            <label>Treatment Plan</label>
            <textarea name="treatment" rows="4" required></textarea>
        </div>

        <!-- BUTTON - SMALLER & CENTERED -->
        <div class="form-submit">
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-save"></i> Save Consultation
            </button>
        </div>

        </form>
        </div>

    </div>

</main>

<script>
// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
});

// Patient search handler
const patientSearch = document.getElementById('patientSearch');
const patientIdHidden = document.getElementById('patient_id');

if (patientSearch && patientIdHidden) {
    patientSearch.addEventListener('change', function() {
        const selectedOption = Array.from(document.querySelectorAll('#patientDataList option')).find(
            option => option.value === this.value
        );
        if (selectedOption) {
            patientIdHidden.value = selectedOption.getAttribute('data-id');
        } else {
            patientIdHidden.value = '';
        }
    });
}
</script>

<script src="js/consultation.js"></script>
</body>
</html>