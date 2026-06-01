<?php
session_start();
include 'backend/connection.php';
 //lhna drna vérification.kan role machi "Nurse", narj3o ll home page index
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') {
    header("Location: index.php");
    exit();
}
// Get user info li ha tt3rd f l top navbar w li rah nst3mloha f l appointments query
$userName   = $_SESSION['name']   ?? 'Nurse';
$userRole   = $_SESSION['role']   ?? 'Nurse';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=f59e0b&color=fff';
$nurse_id   = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

// Get today's consultation appointments for this nurse ghir li khalsso w li ma daroch cancel wla complete
$todayAppointments = $conn->prepare("
    SELECT a.*, p.full_name AS patient_name, d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE a.service = 'Consultation'
    AND a.appointment_date = CURDATE()
    AND a.payment_status = 'Paid'
    AND a.status NOT IN ('Cancelled', 'Completed')
    AND (a.nurse_id = ? OR a.nurse_id IS NULL)
    ORDER BY a.appointment_time ASC
");
$todayAppointments->execute([$nurse_id]);
$todayAppointments = $todayAppointments->fetchAll(PDO::FETCH_ASSOC);

// Get patients with consultation appointments (for vitals form)
$consultationPatients = $conn->prepare("
    SELECT DISTINCT p.id, p.full_name
    FROM patients p
    INNER JOIN appointments a ON a.patient_id = p.id
    WHERE a.service = 'Consultation'
    AND a.payment_status = 'Paid'
    AND a.status NOT IN ('Cancelled', 'Completed')
    ORDER BY p.full_name ASC
");
$consultationPatients->execute();
$consultationPatients = $consultationPatients->fetchAll(PDO::FETCH_ASSOC);

// Get vitals with filters ta3na li rah nst3mloha f l vitals history table
$filter_patient = $_GET['filter_patient'] ?? '';
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_status = $_GET['filter_status'] ?? '';
// Build vitals query with filters
$vitalsQuery = "
    SELECT v.*, p.full_name AS patient_name
    FROM vitals v
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE 1=1
";
// Add filters to query
if ($filter_patient) {
    $vitalsQuery .= " AND p.full_name LIKE :patient";
}
if ($filter_date) {
    $vitalsQuery .= " AND DATE(v.created_at) = :date";
}
if ($filter_status) {
    if ($filter_status == 'critical') {
        $vitalsQuery .= " AND (v.heart_rate < 50 OR v.heart_rate > 120 OR v.spo2 < 90 OR v.temperature > 39.5)";
    } elseif ($filter_status == 'warning') {
        $vitalsQuery .= " AND ((v.heart_rate BETWEEN 50 AND 59 OR v.heart_rate BETWEEN 101 AND 120) OR (v.spo2 BETWEEN 90 AND 94) OR (v.temperature BETWEEN 38.6 AND 39.5))";
    } elseif ($filter_status == 'normal') {
        $vitalsQuery .= " AND v.heart_rate BETWEEN 60 AND 100 AND v.spo2 >= 95 AND v.temperature <= 38.5";
    }
}

$vitalsQuery .= " ORDER BY v.created_at DESC LIMIT 100";
// Prepare and execute query
$stmt = $conn->prepare($vitalsQuery);
if ($filter_patient) {
    $stmt->bindValue(':patient', "%$filter_patient%");
}
if ($filter_date) {
    $stmt->bindValue(':date', $filter_date);
}
$stmt->execute();
$vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);//yjib vitals accending l filters ta3na men database
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nurse Dashboard - CardioCare</title>
<link rel="icon" type="image/png" href="heart.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/tables.css">
<link rel="stylesheet" href="css/forms.css">
<style>
.main-content {
    margin-left: 260px;
    min-height: 100vh;
    background: #f1f5f9;
}

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

.dashboard-content {
    padding: 24px;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.row-normal { background-color: #f0fdf4 !important; }
.row-warning { background-color: #fff7ed !important; }
.row-critical { background-color: #fef2f2 !important; }
.abnormal { color: #b91c1c !important; font-weight: 700; }

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.status-badge.normal { background: #10b981; color: #fff; }
.status-badge.warning { background: #f59e0b; color: #fff; }
.status-badge.critical { background: #ef4444; color: #fff; }

.btn-log { padding: 0.55rem 1.5rem; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-icon { background: none; border: none; cursor: pointer; margin: 0 3px; font-size: 1rem; }
.btn-icon.edit { color: #3b82f6; }
.btn-icon.delete { color: #ef4444; }

.appointment-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-left: 4px solid #10b981;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.appointment-time {
    font-weight: 700;
    color: #10b981;
    min-width: 80px;
}
.appointment-patient { flex: 2; }
.appointment-patient strong { font-size: 1rem; }
.appointment-doctor { color: #6b7280; font-size: 0.85rem; }
.appointment-actions { display: flex; gap: 0.5rem; }

.btn-assign {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
}
.btn-assign:hover { background: #2563eb; }

.btn-complete {
    background: #10b981;
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
}
.btn-complete:hover { background: #059669; }

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #9ca3af;
}

.filter-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filter-group {
    flex: 1;
    min-width: 150px;
}
.filter-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #6b7280;
}
.filter-group select, .filter-group input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.85rem;
}
.btn-filter {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    height: 38px;
}
.btn-filter:hover { background: #2563eb; }
.btn-reset {
    background: #9ca3af;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    height: 38px;
    text-decoration: none;
    display: inline-block;
    line-height: 38px;
}
.btn-reset:hover { background: #6b7280; }

.modal-overlay {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}
.modal-box {
    background: #fff;
    margin: 8% auto;
    padding: 2rem;
    border-radius: 12px;
    width: 550px;
    max-width: 95%;
    position: relative;
}
.modal-box h3 { margin-bottom: 1.5rem; }
.close-modal {
    position: absolute;
    top: 1rem;
    right: 1rem;
    cursor: pointer;
    font-size: 1.2rem;
    color: #64748b;
}
.modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.modal-grid label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.85rem;
    font-weight: 500;
}
.modal-grid input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: inherit;
    font-size: 0.9rem;
    box-sizing: border-box;
}

.section-title {
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-title i { color: #10b981; }

.toast-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.toast-success { background: #10b981; }
.toast-error { background: #ef4444; }
.toast-warning { background: #f59e0b; }
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; }
    .toggle-sidebar-btn { display: block; }
    .dashboard-content { padding: 16px; }
    .vitals-grid { grid-template-columns: 1fr; gap: 0.75rem; }
    .filter-bar { flex-direction: column; }
    .filter-group { width: 100%; }
    .appointment-card { flex-direction: column; text-align: center; }
    .appointment-actions { width: 100%; justify-content: center; }
}
</style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CardioCare</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="nurse-dashboard.php" class="nav-item active">
            <i class="fa-solid fa-notes-medical"></i> Nurse Station
        </a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="top-navbar">
        <button class="toggle-sidebar-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="user-profile">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" class="avatar">
        </div>
    </header>

    <div class="dashboard-content">

        <div class="card">
            <div class="section-title">
                <i class="fa-solid fa-calendar-day"></i>
                <h3>Today's Consultation Appointments</h3>
            </div>
            
            <div id="appointmentsList">
            <?php if (empty($todayAppointments)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-check" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    No consultation appointments scheduled for today.
                </div>
            <?php else: ?>
                <?php foreach($todayAppointments as $appt): ?>
                <div class="appointment-card" data-appointment-id="<?php echo $appt['id']; ?>">
                    <div class="appointment-time">
                        <i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($appt['appointment_time'])); ?>
                    </div>
                    <div class="appointment-patient">
                        <strong><?php echo htmlspecialchars($appt['patient_name']); ?></strong>
                        <div class="appointment-doctor">
                            <i class="fa-solid fa-user-md"></i> Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?>
                        </div>
                    </div>
                    <div class="appointment-actions">
                        <?php if ($appt['nurse_id'] != $nurse_id): ?>
                            <button class="btn-assign" onclick="assignToMe(<?php echo $appt['id']; ?>, this)">
                                <i class="fa-solid fa-hand-peace"></i> Take this patient
                            </button>
                        <?php else: ?>
                            <button class="btn-complete" onclick="completeVitals('<?php echo addslashes($appt['patient_name']); ?>')">
                                <i class="fa-solid fa-stethoscope"></i> Record Vitals
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <i class="fa-solid fa-stethoscope"></i>
                <h3>Record Vital Signs</h3>
            </div>

            <form id="vitalsForm">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Select Patient</label>
                    <input type="text" id="patientInput" list="assignedPatientsList"
                           placeholder="Type patient name..." autocomplete="off" required style="width:100%;">
                    <datalist id="assignedPatientsList">
                        <?php foreach($consultationPatients as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['full_name']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="vitals-grid">
                    <div class="form-group">
                        <label>Heart Rate (bpm)</label>
                        <input type="number" id="heartRate" placeholder="e.g. 72" min="20" max="300" required>
                    </div>
                    <div class="form-group">
                        <label>Blood Pressure (mmHg)</label>
                        <input type="text" id="bloodPressure" placeholder="e.g. 120/80" maxlength="10" required>
                    </div>
                    <div class="form-group">
                        <label>Temperature (°C)</label>
                        <input type="number" id="temperature" placeholder="e.g. 37.2" step="0.1" min="30" max="45" required>
                    </div>
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" id="weight" placeholder="e.g. 70" min="1" max="300" required>
                    </div>
                    <div class="form-group">
                        <label>SpO2 (%)</label>
                        <input type="number" id="spo2" placeholder="e.g. 98" min="50" max="100" required>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:0.5rem;">
                    <button type="submit" class="btn btn-primary btn-log">
                        <i class="fa-solid fa-floppy-disk"></i> Log Vitals
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="section-title">
                <i class="fa-solid fa-chart-line"></i>
                <h3>Vitals History</h3>
            </div>

            <form method="GET" class="filter-bar" id="filterForm">
                <div class="filter-group">
                    <label><i class="fa-solid fa-user"></i> Patient Name</label>
                    <input type="text" name="filter_patient" placeholder="Search patient..." value="<?php echo htmlspecialchars($filter_patient); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-calendar"></i> Date</label>
                    <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-chart-simple"></i> Status</label>
                    <select name="filter_status">
                        <option value="">All</option>
                        <option value="normal" <?php echo $filter_status == 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="warning" <?php echo $filter_status == 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="critical" <?php echo $filter_status == 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
                </div>
                <div class="filter-group">
                    <a href="nurse-dashboard.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <div class="table-container" style="margin-top:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>HR (bpm)</th>
                            <th>BP (mmHg)</th>
                            <th>Temp (°C)</th>
                            <th>Weight (kg)</th>
                            <th>SpO2 (%)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vitalsTbody">
                    <?php foreach($vitals as $v):
                        $hr   = $v['heart_rate'];
                        $sp   = $v['spo2'];
                        $temp = $v['temperature'];

                        $isCritical = ($hr < 50 || $hr > 120 || $sp < 90 || $temp > 39.5);
                        $isWarning  = (!$isCritical) && ($hr < 60 || $hr > 100 || $sp < 95 || $temp > 38.5);

                        $rowClass = $isCritical ? 'row-critical' : ($isWarning ? 'row-warning' : 'row-normal');

                        $badge = $isCritical
                            ? '<span class="status-badge critical">Critical</span>'
                            : ($isWarning
                                ? '<span class="status-badge warning">Warning</span>'
                                : '<span class="status-badge normal">Normal</span>');

                        $hrClass   = ($hr < 50 || $hr > 120) ? 'abnormal' : '';
                        $spClass   = ($sp < 90) ? 'abnormal' : '';
                        $tempClass = ($temp > 39.5) ? 'abnormal' : '';
                    ?>
                    <tr class="<?php echo $rowClass; ?>" data-vital-id="<?php echo $v['id']; ?>">
                        <td><?php echo date('M d, H:i', strtotime($v['created_at'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($v['patient_name'] ?? 'Unknown'); ?></strong></td>
                        <td <?php if($hrClass) echo 'class="abnormal"'; ?>><?php echo $hr; ?></td>
                        <td><?php echo htmlspecialchars($v['blood_pressure']); ?></td>
                        <td <?php if($tempClass) echo 'class="abnormal"'; ?>><?php echo $temp; ?></td>
                        <td><?php echo $v['weight']; ?></td>
                        <td <?php if($spClass) echo 'class="abnormal"'; ?>><?php echo $sp; ?></td>
                        <td><?php echo $badge; ?></td>
                        <td>
                            <button class="btn-icon edit" title="Edit" onclick="editVital(<?php echo $v['id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn-icon delete" title="Delete" onclick="deleteVital(<?php echo $v['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                         </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($vitals)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:2rem; color:#9ca3af;">
                            No vitals records found.
                         </div>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<div class="modal-overlay" id="editVitalModal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeVitalModal()"><i class="fa-solid fa-xmark"></i></span>
        <h3><i class="fa-solid fa-pen text-primary"></i> Edit Vitals</h3>
        <input type="hidden" id="editVitalId">

        <div class="modal-grid">
            <div>
                <label>Heart Rate (bpm)</label>
                <input type="number" id="editHR" min="20" max="300" placeholder="e.g. 72">
            </div>
            <div>
                <label>Blood Pressure (mmHg)</label>
                <input type="text" id="editBP" maxlength="10" placeholder="e.g. 120/80">
            </div>
            <div>
                <label>Temperature (°C)</label>
                <input type="number" id="editTemp" step="0.1" min="30" max="45" placeholder="e.g. 37.2">
            </div>
            <div>
                <label>Weight (kg)</label>
                <input type="number" id="editWeight" min="1" max="300" placeholder="e.g. 70">
            </div>
            <div>
                <label>SpO2 (%)</label>
                <input type="number" id="editSpo2" min="50" max="100" placeholder="e.g. 98">
            </div>
        </div>

        <div style="display:flex; gap:1rem;">
            <button class="btn btn-secondary" onclick="closeVitalModal()" style="flex:1;">Cancel</button>
            <button class="btn btn-primary" onclick="saveVitalEdit()" style="flex:1;">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<script> 
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
});

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast-message toast-' + type;
    toast.innerHTML = '<i class="fa-solid ' + (type === 'success' ? 'fa-circle-check' : (type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation')) + '"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function assignToMe(appointmentId, buttonElement) {
    fetch('backend/assign_nurse.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'appointment_id=' + appointmentId + '&nurse_id=<?php echo $nurse_id; ?>'
    })
    .then(r => r.text())
    .then(res => {
        if (res === 'success') {
            const card = buttonElement.closest('.appointment-card');
            const actionsDiv = card.querySelector('.appointment-actions');
            const patientName = card.querySelector('.appointment-patient strong').innerText;
            actionsDiv.innerHTML = '<button class="btn-complete" onclick="completeVitals(\'' + patientName + '\')"><i class="fa-solid fa-stethoscope"></i> Record Vitals</button>';
            showToast('Patient assigned to you successfully!', 'success');
        } else {
            showToast('Error: ' + res, 'error');
        }
    })
    .catch(err => showToast('Network error: ' + err, 'error'));
}

function completeVitals(patientName) {
    document.getElementById('patientInput').value = patientName;
    document.getElementById('patientInput').focus();
    document.getElementById('vitalsForm').scrollIntoView({ behavior: 'smooth' });
}

function loadTodayAppointments() {
    fetch('backend/get_today_appointments.php?nurse_id=<?php echo $nurse_id; ?>')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('appointmentsList');
            if (data.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-calendar-check" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>No consultation appointments scheduled for today.</div>';
                return;
            }
            container.innerHTML = data.map(appt => `
                <div class="appointment-card" data-appointment-id="${appt.id}">
                    <div class="appointment-time">
                        <i class="fa-regular fa-clock"></i> ${appt.appointment_time.substring(0,5)}
                    </div>
                    <div class="appointment-patient">
                        <strong>${escapeHtml(appt.patient_name)}</strong>
                        <div class="appointment-doctor">
                            <i class="fa-solid fa-user-md"></i> Dr. ${escapeHtml(appt.doctor_name)}
                        </div>
                    </div>
                    <div class="appointment-actions">
                        ${appt.nurse_id != <?php echo $nurse_id; ?> ? 
                            `<button class="btn-assign" onclick="assignToMe(${appt.id}, this)">
                                <i class="fa-solid fa-hand-peace"></i> Take this patient
                            </button>` :
                            `<button class="btn-complete" onclick="completeVitals('${escapeHtml(appt.patient_name)}')">
                                <i class="fa-solid fa-stethoscope"></i> Record Vitals
                            </button>`
                        }
                    </div>
                </div>
            `).join('');
        })
        .catch(err => console.error('Error loading appointments:', err));
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

document.getElementById('vitalsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const patientName = document.getElementById('patientInput').value.trim();
    const heartRate   = document.getElementById('heartRate').value;
    const bp          = document.getElementById('bloodPressure').value.trim();
    const temp        = document.getElementById('temperature').value;
    const weight      = document.getElementById('weight').value;
    const spo2        = document.getElementById('spo2').value;

    if (!patientName) { showToast('Please select a patient.', 'error'); return; }
    if (!/^\d{2,3}\/\d{2,3}$/.test(bp)) {
        showToast('Blood pressure must be in format: 120/80', 'error'); return;
    }

    fetch('backend/save_vitals.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            patient_name: patientName, heart_rate: heartRate,
            blood_pressure: bp, temperature: temp, weight: weight, spo2: spo2
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            showToast(res.message, res.alert_type === 'Critical' ? 'error' : (res.alert_type === 'Warning' ? 'warning' : 'success'));
            document.getElementById('vitalsForm').reset();
            loadVitalsTable();
            loadTodayAppointments();
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => showToast('Network error: ' + err, 'error'));
});

function loadVitalsTable() {
    const filterPatient = document.querySelector('input[name="filter_patient"]')?.value || '';
    const filterDate = document.querySelector('input[name="filter_date"]')?.value || '';
    const filterStatus = document.querySelector('select[name="filter_status"]')?.value || '';
    
    fetch('backend/get_vitals_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            filter_patient: filterPatient,
            filter_date: filterDate,
            filter_status: filterStatus
        })
    })
    .then(r => r.json())
    .then(data => {
        const tbody = document.getElementById('vitalsTbody');
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:2rem; color:#9ca3af;">No vitals records found.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(v => {
            const isCritical = (v.heart_rate < 50 || v.heart_rate > 120 || v.spo2 < 90 || v.temperature > 39.5);
            const isWarning = (!isCritical) && (v.heart_rate < 60 || v.heart_rate > 100 || v.spo2 < 95 || v.temperature > 38.5);
            const rowClass = isCritical ? 'row-critical' : (isWarning ? 'row-warning' : 'row-normal');
            const badge = isCritical ? '<span class="status-badge critical">Critical</span>' : (isWarning ? '<span class="status-badge warning">Warning</span>' : '<span class="status-badge normal">Normal</span>');
            const hrClass = (v.heart_rate < 50 || v.heart_rate > 120) ? 'abnormal' : '';
            const spClass = (v.spo2 < 90) ? 'abnormal' : '';
            const tempClass = (v.temperature > 39.5) ? 'abnormal' : '';
            return `<tr class="${rowClass}" data-vital-id="${v.id}">
                <td>${new Date(v.created_at).toLocaleDateString('en-GB', {month:'short', day:'numeric'})} ${v.created_at.substring(11,16)}</div>
                <td><strong>${v.patient_name || 'Unknown'}</strong></div>
                <td class="${hrClass}">${v.heart_rate}</div>
                <td>${v.blood_pressure}</div>
                <td class="${tempClass}">${v.temperature}</div>
                <td>${v.weight}</div>
                <td class="${spClass}">${v.spo2}</div>
                <td>${badge}</div>
                <td>
                    <button class="btn-icon edit" title="Edit" onclick="editVital(${v.id})"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn-icon delete" title="Delete" onclick="deleteVital(${v.id})"><i class="fa-solid fa-trash"></i></button>
                 </div>
            </tr>`;
        }).join('');
    })
    .catch(err => console.error('Error loading vitals:', err));
}

function deleteVital(id) {
    if (!confirm('Delete this vital record?')) return;
    fetch('backend/vitals_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + id
    })
    .then(r => r.text())
    .then(res => {
        if (res === 'success') {
            showToast('Vital record deleted successfully', 'success');
            loadVitalsTable();
        } else {
            showToast('Error deleting: ' + res, 'error');
        }
    });
}

function editVital(id) {
    fetch('backend/vitals_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get&id=' + id
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('editVitalId').value = data.id;
        document.getElementById('editHR').value      = data.heart_rate;
        document.getElementById('editBP').value      = data.blood_pressure;
        document.getElementById('editTemp').value    = data.temperature;
        document.getElementById('editWeight').value  = data.weight;
        document.getElementById('editSpo2').value    = data.spo2;
        document.getElementById('editVitalModal').style.display = 'block';
    });
}

function closeVitalModal() {
    document.getElementById('editVitalModal').style.display = 'none';
}

function saveVitalEdit() {
    const bp = document.getElementById('editBP').value.trim();
    if (!/^\d{2,3}\/\d{2,3}$/.test(bp)) {
        showToast('Blood pressure must be in format: 120/80', 'error');
        return;
    }
    fetch('backend/vitals_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action:         'update',
            id:             document.getElementById('editVitalId').value,
            heart_rate:     document.getElementById('editHR').value,
            blood_pressure: bp,
            temperature:    document.getElementById('editTemp').value,
            weight:         document.getElementById('editWeight').value,
            spo2:           document.getElementById('editSpo2').value
        })
    })
    .then(r => r.text())
    .then(res => {
        if (res === 'success') {
            closeVitalModal();
            showToast('Vitals updated successfully', 'success');
            loadVitalsTable();
        } else {
            showToast('Error saving: ' + res, 'error');
        }
    });
}

window.onclick = function(e) {
    if (e.target === document.getElementById('editVitalModal')) closeVitalModal();
}

document.getElementById('filterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    loadVitalsTable();
});
</script>
</body>
</html>