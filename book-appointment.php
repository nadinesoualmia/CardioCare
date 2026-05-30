<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Receptionist') {
    header("Location: login.php");
    exit();
}

$userName   = $_SESSION['name']   ?? 'Receptionist';
$userRole   = $_SESSION['role']   ?? 'Receptionist';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=R&background=10b981&color=fff';

/* ── Active staff ── */
$staff = $conn->query("
    SELECT id, full_name, role FROM users
    WHERE isActive = 1 ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Active patients ── */
$patients = $conn->query("
    SELECT id, full_name FROM patients
    WHERE COALESCE(isActive,1) = 1 ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Doctor exam requests that still need an appointment ── */
$pendingRequests = $conn->query("
    SELECT er.id, er.exam_type, er.department, er.urgency, er.notes, er.created_at,
           p.full_name AS patient_name, p.id AS patient_id,
           u.full_name AS doctor_name
    FROM exam_requests er
    JOIN patients p ON p.id = er.patient_id
    LEFT JOIN users u ON u.id = er.doctor_id
    WHERE er.status = 'requested'
    ORDER BY FIELD(er.urgency,'stat','urgent','routine'), er.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$successMessage = '';
$errorMessage   = '';

/* ════════════════════════════════════════════════════
   POST: Book appointment
════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mode       = $_POST['mode']       ?? 'walkin';
    $request_id = intval($_POST['request_id'] ?? 0);
    $patient_id = intval($_POST['patient_id']  ?? 0);
    $doctor_id  = intval($_POST['doctor_id']   ?? 0);
    $service    = trim($_POST['service']        ?? '');
    $date       = $_POST['date']   ?? '';
    $time       = $_POST['time']   ?? '';
    $caseType   = $_POST['caseType'] ?? 'New';
    $price      = $_POST['price']    ?? '0';
    $urgency    = $_POST['urgency']  ?? 'routine';

    // ✅ Get the logged-in receptionist's ID from session
    $created_by = $_SESSION['id'] ?? null;
    
    if (!$created_by) {
        error_log("WARNING: Session ID not set! Created_by will be NULL");
    }

    if (!$patient_id || !$service || !$date || !$time || !$doctor_id) {
        $errorMessage = "Please fill all required fields.";
    } else {
        // Check if doctor is available at this time
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE doctor_id = ? 
            AND appointment_date = ? 
            AND appointment_time = ?
            AND status NOT IN ('Cancelled', 'Completed')
        ");
        $checkStmt->execute([$doctor_id, $date, $time]);
        $existingAppointment = $checkStmt->fetchColumn();
        
        if ($existingAppointment > 0) {
            $errorMessage = "This doctor already has an appointment scheduled at $date at $time. Please select another time.";
        } else {
            try {
                $conn->beginTransaction();

                // For walk-in Lab/Radiology, create exam_request first
                $newRequestId = null;
                if ($mode === 'walkin' && ($service === 'Laboratory' || $service === 'Radiology')) {
                    $examType = ($service === 'Laboratory') ? 'Lab Analysis' : 'Imaging Scan';
                    $department = strtolower($service);
                    
                    $stmtER = $conn->prepare("
                        INSERT INTO exam_requests 
                            (patient_id, doctor_id, exam_type, department, urgency, notes, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                    ");
                    $stmtER->execute([
                        $patient_id,
                        $doctor_id,
                        $examType,
                        $department,
                        $urgency,
                        'Walk-in appointment - ' . $service . ' requested'
                    ]);
                    $newRequestId = $conn->lastInsertId();
                }

                /* Queue number */
                $pfxMap      = ['Consultation'=>'CON','Laboratory'=>'LAB','Radiology'=>'RAD'];
                $pfx         = $pfxMap[$service] ?? 'GEN';
                $cnt         = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE queue_number LIKE ?");
                $cnt->execute([$pfx . '-%']);
                $queueNumber = $pfx . '-' . str_pad($cnt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

                /* Use new request_id if created, otherwise use existing request_id */
                $finalRequestId = ($newRequestId) ? $newRequestId : ($request_id > 0 ? $request_id : null);

                /* ✅ Insert appointment with created_by */
                $stmtA = $conn->prepare("
                    INSERT INTO appointments
                        (patient_id, doctor_id, service, case_type, price,
                         appointment_date, appointment_time, queue_number,
                         request_id, payment_status, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtA->execute([
                    $patient_id,
                    $doctor_id,
                    $service,
                    $caseType,
                    $price,
                    $date,
                    $time,
                    $queueNumber,
                    $finalRequestId,
                    'Pending',
                    'Waiting',
                    $created_by
                ]);

                if ($request_id > 0 && !$newRequestId) {
                    $conn->prepare("
                        UPDATE exam_requests
                        SET status = 'scheduled'
                        WHERE id = ? AND status = 'requested'
                    ")->execute([$request_id]);
                }

                $conn->commit();
                $successMessage = "Appointment created! Queue: <strong>$queueNumber</strong>. Go to <a href='billing.php'>Billing</a> to process payment.";

                /* Reload pending list */
                $pendingRequests = $conn->query("
                    SELECT er.id, er.exam_type, er.department, er.urgency, er.notes, er.created_at,
                           p.full_name AS patient_name, p.id AS patient_id,
                           u.full_name AS doctor_name
                    FROM exam_requests er
                    JOIN patients p ON p.id = er.patient_id
                    LEFT JOIN users u ON u.id = er.doctor_id
                    WHERE er.status = 'requested'
                    ORDER BY FIELD(er.urgency,'stat','urgent','routine'), er.created_at ASC
                ")->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $conn->rollBack();
                $errorMessage = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - CardioCare</title>
     <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/book-appointment.css">
    
    <style>
        /* ========================================
           SIDEBAR FIX - NO DARK BLUE BORDER LINE
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
            /* NO border-right - REMOVED */
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
        
        /* Fix for user profile */
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
        
        /* Alert styles with animations */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }
        .alert-success { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
        .alert-danger  { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* Optional text style */
        .optional {
            font-weight: normal;
            font-size: 0.75rem;
            color: #9ca3af;
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

<aside class="sidebar">
    <div class="sidebar-header"><div class="logo"><i class="fa-solid fa-heart-pulse"></i><span>CardioCare</span></div></div>
    <ul class="sidebar-nav">
        <li><a href="receptionist-dashboard.php" class="nav-item"><i class="fa-solid fa-user-plus"></i> Add Patient</a></li>
        <li><a href="patients.php" class="nav-item"><i class="fa-solid fa-users"></i> Patients</a></li>
        <li><a href="book-appointment.php" class="nav-item active"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a></li>
        <li><a href="appointments.php" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Appointments</a></li>
        <li><a href="billing.php" class="nav-item"><i class="fa-solid fa-receipt"></i> Billing</a></li>
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
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <img src="<?= htmlspecialchars($userAvatar) ?>" class="avatar" alt="avatar">
        </div>
    </header>

    <div class="dashboard-content">
        <div class="page-title">
            <h2><i class="fa-solid fa-calendar-plus"></i> Book Appointment</h2>
            <p>Schedule doctor-requested exams or walk-in appointments</p>
        </div>

        <div class="payment-warning">
            <i class="fa-solid fa-credit-card"></i>
            <strong>Important:</strong> After booking, go to <strong>Billing</strong> to process payment.
            Services are only visible to staff <strong>after payment is confirmed</strong>.
        </div>

        <!-- Success Message with auto-hide -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success" id="successMessage">
                <i class="fa-solid fa-circle-check"></i> <?= $successMessage ?>
            </div>
            <script>
                setTimeout(function() {
                    const msg = document.getElementById('successMessage');
                    if (msg) {
                        msg.style.animation = 'slideOut 0.3s ease';
                        setTimeout(function() { msg.style.display = 'none'; }, 300);
                    }
                }, 5000);
            </script>
        <?php endif; ?>

        <!-- Error Message with auto-hide -->
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger" id="errorMessage">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
            <script>
                setTimeout(function() {
                    const msg = document.getElementById('errorMessage');
                    if (msg) {
                        msg.style.animation = 'slideOut 0.3s ease';
                        setTimeout(function() { msg.style.display = 'none'; }, 4000);
                    }
                }, 4000);
            </script>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="requests">
                <i class="fa-solid fa-file-prescription"></i>
                Doctor Exam Requests
                <?php if (count($pendingRequests) > 0): ?>
                    <span class="count-badge"><?= count($pendingRequests) ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" data-tab="walkin">
                <i class="fa-solid fa-user-plus"></i> Walk-in / Direct Appointment
            </button>
        </div>

        <!-- TAB A: Doctor Exam Requests -->
        <div id="tab-requests" class="tab-panel active">
            <div class="tbl-wrap">
                <div class="tbl-hdr">
                    <i class="fa-solid fa-hourglass-half"></i>
                    <h3>Pending Requests from Doctors</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Urgency</th>
                                <th>Patient</th>
                                <th>Dept.</th>
                                <th>Exam Type</th>
                                <th>Requested By</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-circle-check"></i>All requests have been scheduled!</div>NonNull
                        <?php else: foreach ($pendingRequests as $r):
                            $badge = match($r['urgency']) {
                                'stat'   => '<span class="badge b-stat">STAT</span>',
                                'urgent' => '<span class="badge b-urgent">Urgent</span>',
                                default  => '<span class="badge b-routine">Routine</span>'
                            };
                        ?>
                            <tr>
                                <td><?= $badge ?></td>
                                <td><strong><?= htmlspecialchars($r['patient_name']) ?></strong></td>
                                <td><?= ucfirst($r['department']) ?></td>
                                <td><?= htmlspecialchars($r['exam_type']) ?></td>
                                <td>Dr. <?= htmlspecialchars($r['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                                <td><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
                                <td><button class="sched-btn" data-req-id="<?= $r['id'] ?>" data-pat-id="<?= $r['patient_id'] ?>" data-pat-name="<?= htmlspecialchars($r['patient_name'], ENT_QUOTES) ?>" data-exam-type="<?= htmlspecialchars($r['exam_type'], ENT_QUOTES) ?>" data-dept="<?= $r['department'] ?>" data-dr-name="<?= htmlspecialchars($r['doctor_name'] ?? '', ENT_QUOTES) ?>" data-urgency="<?= $r['urgency'] ?>"><i class="fa-solid fa-calendar-plus"></i> Schedule</button></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="scheduleWrap" style="display:none; margin-top:1rem;">
                <div class="sched-card">
                    <h3><i class="fa-solid fa-calendar-check"></i> Schedule Appointment</h3>
                    <div class="info-box" id="schedInfoBox"></div>
                    <form method="POST" id="scheduleForm">
                        <input type="hidden" name="mode" value="request">
                        <input type="hidden" name="request_id" id="f_req_id">
                        <input type="hidden" name="patient_id" id="f_pat_id">
                        <input type="hidden" name="service" id="f_service">
                        <div class="form-row">
                            <div class="fg full-width"><label>Patient</label><input type="text" id="f_pat_name" readonly></div>
                        </div>
                        <div class="form-row">
                            <div class="fg"><label>Department / Exam</label><input type="text" id="f_svc_display" readonly></div>
                            <div class="fg"><label>Assign Staff</label><select name="doctor_id" id="f_staff_sel" required><option value="">Select staff…</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="fg"><label>Date</label><input type="date" name="date" id="f_date" required></div>
                            <div class="fg"><label>Selected Time</label><input type="time" name="time" id="f_time" readonly placeholder="Select a time slot"></div>
                        </div>
                        <div class="form-row">
                            <div class="fg full-width"><label>Available Time Slots</label><div id="timeSlotsContainer" class="time-slots"><div class="loading-text">Select a doctor and date first</div></div></div>
                        </div>
                        <div class="form-row">
                            <div class="fg"><label>Case Type <span class="optional">(Optional)</span></label><select name="caseType"><option>New</option><option>Follow-up</option><option>Urgent</option></select></div>
                            <div class="fg"><label>Price (DA)</label><input type="text" name="price" id="f_price" readonly></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Create Appointment</button>
                            <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB B: Walk-in / Direct Appointment -->
        <div id="tab-walkin" class="tab-panel">
            <div class="card">
                <h3><i class="fa-solid fa-user-plus"></i> Walk-in / Direct Appointment</h3>
                <form method="POST" id="walkinForm">
                    <input type="hidden" name="mode" value="walkin">
                    <input type="hidden" name="request_id" value="0">
                    <input type="hidden" name="patient_id" id="w_pat_id">
                    <div class="fg"><label>Patient Name</label><input type="text" name="patient_name_walkin" id="w_pat_name" list="walkinPatList" autocomplete="off" required placeholder="Type to search patient…"><datalist id="walkinPatList"><?php foreach ($patients as $p): ?><option value="<?= htmlspecialchars($p['full_name']) ?>"><?php endforeach; ?></datalist></div>
                    <div class="form-row">
                        <div class="fg"><label>Date</label><input type="date" name="date" id="w_date" required></div>
                        <div class="fg"><label>Selected Time</label><input type="time" name="time" id="w_time" readonly placeholder="Select a time slot"></div>
                    </div>
                    <div class="form-row">
                        <div class="fg full-width"><label>Available Time Slots</label><div id="walkinTimeSlotsContainer" class="time-slots"><div class="loading-text">Select a service, doctor and date first</div></div></div>
                    </div>
                    <div class="form-row">
                        <div class="fg"><label>Service</label><select name="service" id="w_service" required><option value="" disabled selected>Select service…</option><option>Consultation</option><option>Laboratory</option><option>Radiology</option></select></div>
                        <div class="fg"><label>Doctor / Staff</label><select name="doctor_id" id="w_staff" required><option value="">Select service first…</option></select></div>
                    </div>
                    <div class="form-row">
                        <div class="fg"><label>Urgency Level</label><select name="urgency" id="w_urgency" required><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="stat">STAT (Emergency)</option></select></div>
                        <div class="fg"><label>Case Type <span class="optional">(Optional)</span></label><select name="caseType"><option>New</option><option>Follow-up</option><option>Urgent</option></select></div>
                    </div>
                    <div class="form-row">
                        <div class="fg"><label>Price (DA)</label><input type="text" name="price" id="w_price" readonly placeholder="Auto-filled"></div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Create Appointment</button>
                </form>
            </div>
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

// Pass PHP data to JavaScript
const allStaff = <?= json_encode($staff) ?>;
const allPatients = <?= json_encode($patients) ?>;
const prices = { Consultation: 3000, Laboratory: 5000, Radiology: 8000 };
const roleMap = { Consultation: 'Doctor', Laboratory: 'Laboratory', Radiology: 'Radiology' };
</script>
<script src="js/book-appointment.js"></script>
</body>
</html>