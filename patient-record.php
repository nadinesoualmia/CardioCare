<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get doctor ID and user info for sidebar
$doctor_id = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Doctor';
$userRole = $_SESSION['role'] ?? 'Doctor';
$userAvatar = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";

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
$activeCountStmt->execute([$doctor_id]);
$activeCount = $activeCountStmt->fetchColumn();

$patient    = null;
$patient_id = null;

if (!empty($_GET['name'])) {
    $stmt = $conn->prepare("
        SELECT DISTINCT p.* 
        FROM patients p
        JOIN appointments a ON a.patient_id = p.id
        WHERE a.doctor_id = ?
        AND p.full_name LIKE ?
    ");
    $stmt->execute([$doctor_id, "%" . $_GET['name'] . "%"]);
    $patient    = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_id = $patient['id'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record - CardioCare</title>
     <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/patient-record.css">
    
    <style>
        /* ========================================
           SIDEBAR FIX - MATCHES CONSULTATION.PHP
           ======================================== */
        
        /* Fix for main content - push right */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        /* Fix for sidebar - ensure fixed position */
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
            padding: 12px 14px;
            padding-left: 13px;
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
        }
    </style>
</head>
<body class="dashboard-body">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo"><i class="fa-solid fa-heart-pulse"></i><span>CardioCare</span></div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="doctor-dashboard.php" class="nav-item"><i class="fa-solid fa-stethoscope"></i> Overview</a></li>
        <li><a href="patient-record.php" class="nav-item active"><i class="fa-solid fa-file-medical"></i> Medical Records</a></li>
        <li><a href="consultation.php" class="nav-item"><i class="fa-solid fa-user-doctor"></i> Consultation</a></li>
        <li><a href="exam-request.php" class="nav-item"><i class="fa-solid fa-file-prescription"></i> Exam Requests</a></li>
        <li style="position: relative;">
            <a href="alerts.php" class="nav-item"><i class="fa-solid fa-bell"></i> Alerts</a>
            <?php if ($activeCount > 0): ?>
                <span class="alert-badge"><?= $activeCount ?></span>
            <?php endif; ?>
        </li>
    </ul>
    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    <header class="top-navbar">
        <button class="toggle-sidebar-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="user-profile">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <img src="<?= htmlspecialchars($userAvatar) ?>" class="avatar" alt="avatar">
        </div>
    </header>

    <div class="dashboard-content">

        <!-- Header -->
        <div class="page-header">
            <h2><i class="fa-solid fa-file-medical text-primary"></i> Comprehensive Medical Record</h2>
            <a href="doctor-dashboard.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

        <!-- Search -->
        <div class="card">
            <form method="GET" class="search-row">
                <input type="text" name="name"
                       placeholder="Type patient name and press Search… (only YOUR patients)"
                       value="<?= htmlspecialchars($_GET['name'] ?? '') ?>">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-search"></i> Search
                </button>
            </form>
            <?php if (!empty($_GET['name']) && !$patient): ?>
                <div style="margin-top:1rem; padding:0.75rem; background:#fee2e2; color:#991b1b; border-radius:8px;">
                    <i class="fa-solid fa-circle-exclamation"></i> No patient found. Make sure this patient is assigned to you.
                </div>
            <?php endif; ?>
        </div>

        <!-- Demographics -->
        <div class="card">
            <h3><i class="fa-solid fa-id-card text-primary"></i> Patient Demographics</h3>
            <div class="patient-info-grid">
                <div class="info-block"><label>Full Name</label>
                    <span><?= htmlspecialchars($patient['full_name'] ?? '—') ?></span></div>
                <div class="info-block"><label>Gender</label>
                    <span><?= htmlspecialchars($patient['gender'] ?? '—') ?></span></div>
                <div class="info-block"><label>Date of Birth</label>
                    <span><?= htmlspecialchars($patient['dob'] ?? '—') ?></span></div>
                <div class="info-block"><label>Phone</label>
                    <span><?= htmlspecialchars($patient['phone'] ?? '—') ?></span></div>
                <div class="info-block" style="grid-column:span 2"><label>Address</label>
                    <span><?= htmlspecialchars($patient['address'] ?? '—') ?></span></div>
            </div>
        </div>

        <?php if ($patient): $id = $patient['id']; ?>

        <!-- ALERTS -->
        <div class="section-title"><i class="fa-solid fa-bell"></i> Active Alerts</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Message</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $q = $conn->prepare("SELECT * FROM alerts WHERE patient_id=? ORDER BY created_at DESC");
                $q->execute([$id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows): foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td><?= htmlspecialchars($r['alert_type']) ?></td>
                        <td><?= htmlspecialchars($r['message']) ?></td>
                        <td><span class="badge-pill <?= strtolower($r['status']) === 'active' ? 'bp-active' : 'bp-resolved' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" class="empty-cell">No alerts on record.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- VITAL SIGNS HISTORY -->
        <div class="section-title"><i class="fa-solid fa-heart-pulse"></i> Vital Signs History</div>
        <div class="tbl-wrap">
            <table class="vitals-table">
                <thead>
                    <tr>
                        <th style="width: 20%">Date</th>
                        <th style="width: 16%">Heart Rate</th>
                        <th style="width: 16%">Blood Pressure</th>
                        <th style="width: 16%">Temp (°C)</th>
                        <th style="width: 16%">SpO₂ (%)</th>
                        <th style="width: 16%">Weight (kg)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $q = $conn->prepare("SELECT * FROM vitals WHERE patient_id=? ORDER BY created_at DESC");
                $q->execute([$id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows): foreach ($rows as $r): 
                    $hr = $r['heart_rate'];
                    $temp = $r['temperature'];
                    $spo2 = $r['spo2'];
                    ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        <td class="<?= ($hr < 60 || $hr > 100) ? 'vital-abnormal' : 'vital-normal' ?>"><?= htmlspecialchars($hr) ?> bpm</td>
                        <td class="bp-cell"><?= htmlspecialchars($r['blood_pressure'] ?? '—') ?></td>
                        <td class="<?= ($temp > 37.5) ? 'vital-abnormal' : 'vital-normal' ?>"><?= htmlspecialchars($temp) ?>°C</td>
                        <td class="<?= ($spo2 < 95) ? 'vital-abnormal' : 'vital-normal' ?>"><?= htmlspecialchars($spo2) ?>%</td>
                        <td class="weight-cell"><?= htmlspecialchars($r['weight'] ?? '—') ?> kg</td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="empty-cell">No vitals recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- CONSULTATIONS HISTORY -->
        <div class="section-title"><i class="fa-solid fa-stethoscope"></i> Consultations History</div>
        <div class="tbl-wrap">
            <table class="consultations-table">
                <thead>
                    <tr>
                        <th style="width: 20%">Date</th>
                        <th style="width: 40%">Diagnosis</th>
                        <th style="width: 40%">Treatment</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $q = $conn->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY created_at DESC");
                $q->execute([$id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows && count($rows) > 0): 
                    foreach ($rows as $r): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($r['diagnosis'] ?? '—') ?></strong></td>
                            <td><?= nl2br(htmlspecialchars($r['treatment'] ?? $r['notes'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; 
                else: ?>
                    <tr><td colspan="3" class="empty-cell">No consultations recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- RADIOLOGY / IMAGING RESULTS -->
        <div class="section-title"><i class="fa-solid fa-x-ray"></i> Radiology / Imaging Results</div>
        <div class="tbl-wrap">
            <table class="radiology-table">
                <thead>
                    <tr>
                        <th style="width: 18%">Date</th>
                        <th style="width: 20%">Exam Type</th>
                        <th style="width: 32%">Findings</th>
                        <th style="width: 12%">Critical</th>
                        <th style="width: 18%">Attached File</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $q = $conn->prepare("
                    SELECT er.created_at, er.result, er.file_path, er.is_critical, eq.exam_type
                    FROM exam_results er
                    JOIN exam_requests eq ON eq.id = er.request_id
                    WHERE eq.patient_id = ? AND eq.department = 'radiology'
                    ORDER BY er.created_at DESC
                ");
                $q->execute([$id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows): foreach ($rows as $r): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($r['exam_type']) ?></strong></td>
                        <td class="findings-text"><?= nl2br(htmlspecialchars($r['result'] ?? '—')) ?></td>
                        <td><?= $r['is_critical'] ? '<span class="badge-pill bp-critical">Critical</span>' : '<span class="badge-pill bp-normal">Normal</span>' ?></td>
                        <td>
                            <?php if ($r['file_path']): ?>
                                <a class="file-link" href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">
                                    <i class="fa-solid fa-file-image"></i> View Image
                                </a>
                            <?php else: ?>
                                <span style="color:#9ca3af;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="empty-cell">No radiology results on record.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- LABORATORY RESULTS -->
        <div class="section-title"><i class="fa-solid fa-flask-vial"></i> Laboratory Results</div>
        <div class="tbl-wrap">
            <table class="lab-table">
                <thead>
                    <tr>
                        <th style="width: 20%">Date</th>
                        <th style="width: 25%">Test Type</th>
                        <th style="width: 35%">Notes / Values</th>
                        <th style="width: 20%">Attached File</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $q = $conn->prepare("
                    SELECT er.created_at, er.result, er.file_path, eq.exam_type
                    FROM exam_results er
                    JOIN exam_requests eq ON eq.id = er.request_id
                    WHERE eq.patient_id = ? AND eq.department = 'laboratory'
                    ORDER BY er.created_at DESC
                ");
                $q->execute([$id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows && count($rows) > 0): 
                    foreach ($rows as $r): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($r['exam_type'] ?? '—') ?></strong></td>
                            <td><?= nl2br(htmlspecialchars($r['result'] ?? '—')) ?></td>
                            <td>
                                <?php if ($r['file_path']): ?>
                                    <a class="file-link" href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">
                                        <i class="fa-solid fa-file-pdf"></i> View File
                                    </a>
                                <?php else: ?>
                                    <span style="color:#9ca3af;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; 
                else: ?>
                    <tr><td colspan="4" class="empty-cell">No laboratory results on record.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- APPOINTMENTS -->
        <div class="section-title"><i class="fa-solid fa-calendar-days"></i> Appointments</div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Date</th><th>Time</th><th>Service</th><th>Type</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                <?php
                $q = $conn->prepare("SELECT * FROM appointments WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at DESC");
                $q->execute([$id, $doctor_id]); 
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows): foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($r['appointment_time']) ?></td>
                        <td><?= htmlspecialchars($r['service']) ?></td>
                        <td><?= htmlspecialchars($r['case_type']) ?></td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td><?= htmlspecialchars($r['payment_status']) ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="empty-cell">No appointments on record with you.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

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

<script src="js/patient-record.js"></script>
</body>
</html>