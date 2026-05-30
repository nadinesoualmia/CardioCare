<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['name'])) {
    header("Location: login.php");
    exit();
}

$userName    = $_SESSION['name'];
$userRole    = $_SESSION['role'];
$userAvatar  = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";

/* GET DOCTOR ID */
$stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
$stmt->execute([$userName]);
$doctor   = $stmt->fetch(PDO::FETCH_ASSOC);
$doctorId = $doctor['id'] ?? 0;

/* STATS - Only count Critical and Warning alerts (not Normal) */
$alertsCountStmt = $conn->prepare("
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
$alertsCountStmt->execute([$doctorId]);
$alertsCount = $alertsCountStmt->fetchColumn();

/* CHANGED: Pending Appointments for TODAY only */
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE doctor_id = ?
      AND status != 'Completed'
      AND payment_status = 'Paid'
      AND DATE(appointment_date) = ?
");
$stmt->execute([$doctorId, $today]);
$pendingAppointmentsToday = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM consultations WHERE doctor_id = ?");
$stmt->execute([$doctorId]);
$consultationsCount = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM exam_requests WHERE doctor_id = ? AND status != 'completed'");
$stmt->execute([$doctorId]);
$examCount = $stmt->fetchColumn();

/* ALERTS - Only show Critical and Warning alerts */
$stmt = $conn->prepare("
    SELECT a.message, p.full_name
    FROM alerts a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.status = 'Active'
    AND a.alert_type IN ('Critical', 'Warning')
    AND a.patient_id IN (
        SELECT DISTINCT patient_id 
        FROM appointments 
        WHERE doctor_id = ?
    )
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$doctorId]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

/* APPOINTMENTS */
$stmt = $conn->prepare("
    SELECT a.*, p.full_name AS patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ?
      AND a.payment_status = 'Paid'
      AND a.service = 'Consultation'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute([$doctorId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by date
$appointmentsByDate = [];
foreach ($appointments as $appt) {
    $date = $appt['appointment_date'];
    if (!isset($appointmentsByDate[$date])) {
        $appointmentsByDate[$date] = [];
    }
    $appointmentsByDate[$date][] = $appt;
}

// Prepare data for JavaScript
$appointmentsByDateJSON = json_encode($appointmentsByDate);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Dashboard - CardioCare</title>
 <link rel="icon" type="image/png" href="heart.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/tables.css">
<link rel="stylesheet" href="css/doctor-dashboard.css">

<style>
/* ========================================
   FIX FOR SIDEBAR AND PROFILE - ADD THIS
   ======================================== */

/* Main content - push right */
.main-content {
    margin-left: 260px;
    min-height: 100vh;
    background: #f1f5f9;
}

/* Sidebar - fixed position */
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

/* Top navbar - at the top */
.top-navbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 12px 24px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    height: 70px;
}

.toggle-sidebar-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
    display: none;
    margin-right: auto;
}

/* User profile */
.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
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

/* Dashboard content */
.dashboard-content {
    padding: 24px;
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
    
    .top-navbar {
        justify-content: space-between;
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
        <li><a href="doctor-dashboard.php" class="nav-item active"><i class="fa-solid fa-stethoscope"></i> Overview</a></li>
        <li><a href="patient-record.php" class="nav-item"><i class="fa-solid fa-file-medical"></i> Medical Records</a></li>
        <li><a href="consultation.php" class="nav-item"><i class="fa-solid fa-user-doctor"></i> Consultation</a></li>
        <li><a href="exam-request.php" class="nav-item"><i class="fa-solid fa-file-prescription"></i> Exam Requests</a></li>
        <li style="position:relative;">
            <a href="alerts.php" class="nav-item"><i class="fa-solid fa-bell"></i> Alerts</a>
            <?php if ($activeCount > 0): ?>
                <span class="alert-badge">
                    <?= $activeCount ?>
                </span>
            <?php endif; ?>
        </li>
    </ul>
    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
<header class="top-navbar">
    <button class="toggle-sidebar-btn"><i class="fa-solid fa-bars"></i></button>
    <div class="user-profile">
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
        <img src="<?= htmlspecialchars($userAvatar) ?>" class="avatar">
    </div>
</header>

<div class="dashboard-content">
    <div class="page-header">
        <h2>Doctor Workspace</h2>
        <p>Welcome back, Dr. <?= htmlspecialchars($userName) ?></p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
           <div class="stat-details"><h3><?= $alertsCount ?></h3><p>Active Alerts (Critical/Warning)</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fa-solid fa-calendar"></i></div>
            <div class="stat-details"><h3><?= $pendingAppointmentsToday ?></h3><p>Pending Appointments Today</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="fa-solid fa-microscope"></i></div>
            <div class="stat-details"><h3><?= $examCount ?></h3><p>Pending Exam Results</p></div>
        </div>
    </div>

    <!-- ALERTS -->
    <div class="card alerts-card">
        <h3>Active Alerts</h3>
        <?php if (empty($alerts)): ?>
            <p class="no-alerts">No alerts for your patients</p>
        <?php else: ?>
            <?php foreach ($alerts as $a): ?>
                <div class="alert-box alert-box-danger">
                    <b><?= htmlspecialchars($a['full_name']) ?></b><br>
                    <?= htmlspecialchars($a['message']) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- CALENDAR VIEW -->
    <div class="calendar-container">
        <div class="calendar-header">
            <h3><i class="fa-solid fa-calendar-alt"></i> My Schedule</h3>
            <div class="calendar-nav">
                <button onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="currentMonthDisplay" class="current-month"></span>
                <button onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                <button onclick="goToToday()">Today</button>
            </div>
        </div>
        <div class="calendar-weekdays">
            <div class="calendar-weekday">Sun</div>
            <div class="calendar-weekday">Mon</div>
            <div class="calendar-weekday">Tue</div>
            <div class="calendar-weekday">Wed</div>
            <div class="calendar-weekday">Thu</div>
            <div class="calendar-weekday">Fri</div>
            <div class="calendar-weekday">Sat</div>
        </div>
        <div id="calendarDays" class="calendar-days"></div>
    </div>
</div>
</main>

<!-- Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fa-solid fa-calendar-check"></i> Appointment Details</h4>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalDetails"></div>
        <div style="margin-top: 1rem; text-align: center;">
            <button onclick="markCompleteFromModal()" id="modalCompleteBtn" class="btn btn-primary modal-complete-btn">Mark as Completed</button>
        </div>
    </div>
</div>

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

// Pass PHP data to JavaScript
const appointmentsByDate = <?php echo $appointmentsByDateJSON; ?>;
</script>
<script src="js/doctor-dashboard.js"></script>
</body>
</html>