<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['name'])) {
    header("Location: login.php");
    exit();
}

$userName   = $_SESSION['name'];
$userRole   = $_SESSION['role'];
$userAvatar = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";

/* ── Get doctor ID from session name ── */
$stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
$stmt->execute([$userName]);
$doctor   = $stmt->fetch(PDO::FETCH_ASSOC);
$doctorId = $doctor['id'] ?? 0;

/* ── Get doctor's patients (from their appointments) ── */
$stmt = $conn->prepare("
    SELECT DISTINCT p.id, p.full_name
    FROM patients p
    INNER JOIN appointments a ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY p.full_name ASC
");
$stmt->execute([$doctorId]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active alert count for sidebar badge
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
$activeCountStmt->execute([$doctorId]);
$activeCount = $activeCountStmt->fetchColumn();

$success = '';
$error   = '';

/* ════════════════════════════════
   POST: Insert exam request
   Status → 'requested' (NOT pending!)
   Reception will see it and schedule it.
════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patient_value = $_POST['patient']    ?? '';
    $department    = $_POST['department'] ?? '';
    $exam_type     = $_POST['exam_type']  ?? '';
    $urgency       = $_POST['urgency']    ?? 'routine';
    $notes         = trim($_POST['notes'] ?? '');

    /* Extract patient ID from "Name - ID: X" */
    preg_match('/ID:\s*(\d+)/', $patient_value, $match);
    $patient_id = intval($match[1] ?? 0);

    // Verify this patient belongs to the doctor (security check)
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE patient_id = ? AND doctor_id = ?
    ");
    $checkStmt->execute([$patient_id, $doctorId]);
    $patientBelongsToDoctor = $checkStmt->fetchColumn() > 0;

    if ($patient_id && $department && $exam_type && $patientBelongsToDoctor) {
        $stmt = $conn->prepare("
            INSERT INTO exam_requests
                (patient_id, doctor_id, department, exam_type, urgency, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'requested')
        ");
        $stmt->execute([$patient_id, $doctorId, $department, $exam_type, $urgency, $notes ?: null]);
        $success = "Exam request submitted! The receptionist will schedule an appointment.";
    } else {
        $error = "Please fill all required fields correctly or select a valid patient.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Exam - CardioCare</title>
     <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/exam-request.css">
    
    <style>
        /* ========================================
           SIDEBAR & PROFILE FIX CSS
           ======================================== */
        
        /* Fix for main content - push right */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        /* Fix for sidebar - ensure fixed position */
        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
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
        
        /* Fix for top navbar */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            height: 70px;
        }
        
        /* Fix for user profile */
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
        
        /* Dashboard content padding */
        .dashboard-content {
            padding: 24px;
        }
        
        /* ========================================
           EXAM REQUEST CONTAINER
           ======================================== */
        .exam-container {
            padding: 24px;
            max-width: 650px;
            margin: 0 auto;
        }
        
        /* Header with title and cancel button */
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .exam-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .exam-header h2 i {
            color: #2563eb;
        }
        
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
        
        /* Alert messages */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Card styles */
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }
        
        /* Button container */
        .button-container {
            text-align: center;
            margin-top: 30px;
        }
        
        /* BUTTON - SMALL WIDTH (fit to content) */
        .btn-submit {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 502;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
        }
        
        .btn-submit:hover {
            background: #1d4ed8;
        }
        
        .btn-submit:disabled {
            background: #9ca3af;
            cursor: not-allowed;
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
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .optional {
            color: #9ca3af;
            font-weight: normal;
            font-size: 0.7rem;
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
            
            .dashboard-content {
                padding: 16px;
            }
            
            .btn-submit {
                width: 100% !important;
                white-space: normal;
                justify-content: center;
            }
            
            .form-row-2 {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .exam-container {
                padding: 16px;
            }
        }
    </style>
</head>
<body class="dashboard-body">

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo"><i class="fa-solid fa-heart-pulse"></i><span>CardioCare</span></div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="doctor-dashboard.php" class="nav-item"><i class="fa-solid fa-stethoscope"></i> Overview</a></li>
        <li><a href="patient-record.php" class="nav-item"><i class="fa-solid fa-file-medical"></i> Medical Records</a></li>
        <li><a href="consultation.php" class="nav-item"><i class="fa-solid fa-user-doctor"></i> Consultation</a></li>
        <li><a href="exam-request.php" class="nav-item active"><i class="fa-solid fa-file-prescription"></i> Exam Requests</a></li>
        <li style="position: relative;">
            <a href="alerts.php" class="nav-item">
                <i class="fa-solid fa-bell"></i> Alerts
            </a>
            <?php if ($activeCount > 0): ?>
                <span class="alert-badge"><?= $activeCount ?></span>
            <?php endif; ?>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<main class="main-content">
    
    <!-- TOP NAVBAR WITH PROFILE -->
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
    
    <!-- EXAM REQUEST CONTENT -->
    <div class="exam-container">

        <!-- HEADER with title and cancel button -->
        <div class="exam-header">
            <h2>
                <i class="fa-solid fa-vial"></i> Request Medical Exam
            </h2>
            <a href="doctor-dashboard.php" class="btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- ERROR MESSAGE -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Warning if no patients assigned -->
        <?php if (empty($patients)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i> You don't have any patients assigned to you yet.
            </div>
        <?php endif; ?>

        <!-- FORM CARD -->
        <div class="card">
            <form method="POST" id="examRequestForm">
                <div class="form-group">
                    <label>Patient</label>
                    <input type="text" name="patient" id="patientSearch" placeholder="Search patient…"
                           list="patientList" required autocomplete="off">
                    <datalist id="patientList">
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= htmlspecialchars($p['full_name']) ?> - ID: <?= $p['id'] ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Target Department</label>
                        <select id="deptSel" name="department" required>
                            <option value="" disabled selected>Select…</option>
                            <option value="radiology">Radiology</option>
                            <option value="laboratory">Laboratory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Type</label>
                        <select id="examSel" name="exam_type" required disabled>
                            <option>Select department first…</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Urgency Level</label>
                    <select name="urgency" required>
                        <option value="routine">Routine</option>
                        <option value="urgent">Urgent</option>
                        <option value="stat">STAT</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Clinical Notes <span class="optional">(Optional)</span></label>
                    <textarea name="notes" rows="4" placeholder="Clinical reason, special instructions…"></textarea>
                </div>

                <!-- Button container for centering -->
                <div class="button-container">
                    <button type="submit" class="btn-submit" <?= empty($patients) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-paper-plane"></i> Submit Request
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
</script>

<script src="js/exam-request.js"></script>
</body>
</html>