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

/* ════════════════════════════════════════════════════════════════
   POST: Process payment
════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'pay') {

    $appointment_id = intval($_POST['appointment_id']);

    try {
        $conn->beginTransaction();

        /* ── Load appointment ── */
        $stmtA = $conn->prepare("
            SELECT a.*, p.full_name AS patient_name
            FROM appointments a
            JOIN patients p ON p.id = a.patient_id
            WHERE a.id = ?
        ");
        $stmtA->execute([$appointment_id]);
        $appt = $stmtA->fetch(PDO::FETCH_ASSOC);

        if (!$appt) {
            throw new Exception("Appointment #$appointment_id not found.");
        }
        if ($appt['payment_status'] === 'Paid') {
            throw new Exception("Payment already processed for this appointment.");
        }

        /* ── 1. Mark appointment paid ── */
        $conn->prepare("
            UPDATE appointments
            SET payment_status = 'Paid',
                status = 'Scheduled'
            WHERE id = ?
        ")->execute([$appointment_id]);

        /* ── 2. Insert payment record (without processed_by) ── */
        $conn->prepare("
            INSERT INTO payments (appointment_id, amount, method, status)
            VALUES (?, ?, 'Cash', 'Paid')
        ")->execute([$appointment_id, $appt['price']]);

        /* ── 3. Route to department ── */
        $deptMap = ['Laboratory' => 'laboratory', 'Radiology' => 'radiology'];

        if (isset($deptMap[$appt['service']])) {
            $dept = $deptMap[$appt['service']];

            if (!empty($appt['request_id'])) {
                $conn->prepare("
                    UPDATE exam_requests
                    SET status = 'scheduled'
                    WHERE id = ?
                ")->execute([$appt['request_id']]);

            } else {
                $defaultExam = ($dept === 'laboratory') ? 'Blood Test' : 'Chest X-Ray';

                $conn->prepare("
                    INSERT INTO exam_requests
                        (patient_id, doctor_id, department, exam_type, urgency, notes, status)
                    VALUES (?, ?, ?, ?, 'routine', ?, 'scheduled')
                ")->execute([
                    $appt['patient_id'],
                    $appt['doctor_id'],
                    $dept,
                    $defaultExam,
                    'Walk-in appointment #' . $appointment_id
                ]);

                $newRequestId = $conn->lastInsertId();

                $conn->prepare("
                    UPDATE appointments SET request_id = ? WHERE id = ?
                ")->execute([$newRequestId, $appointment_id]);
            }
        }

        $conn->commit();
        echo 'success';

    } catch (Exception $e) {
        $conn->rollBack();
        echo 'error: ' . $e->getMessage();
    }
    exit();
}

/* ── Load ONLY pending (unpaid) appointments ── */
$stmt = $conn->prepare("
    SELECT a.*,
           p.full_name AS patient_name,
           u.full_name AS doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users    u ON a.doctor_id  = u.id
    WHERE a.payment_status = 'Pending'
    ORDER BY a.id DESC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$preSelected = intval($_GET['appt_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing - CardioCare</title>
 <link rel="icon" type="image/png" href="heart.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/tables.css">
<link rel="stylesheet" href="css/billing.css">

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
    
    /* Queue badge styles - same as appointments page */
    .queue-badge {
        display: inline-block;
        background: #e0e7ff;
        color: #3730a3;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
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
        <li><a href="book-appointment.php" class="nav-item"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a></li>
        <li><a href="appointments.php" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Appointments</a></li>
        <li><a href="billing.php" class="nav-item active"><i class="fa-solid fa-receipt"></i> Billing</a></li>
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
        <div class="table-container">
            <div class="table-header">
                <h3>Payment Queue — Pending Payments</h3>
                <div class="table-search">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchBilling" placeholder="Search...">
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="billingTable">
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fa-solid fa-circle-check"></i>
                            No pending payments! All appointments are paid.
                         </div>
                    </tr>
                <?php else: foreach ($appointments as $row): ?>
                    <tr id="appt-row-<?= $row['id'] ?>">
                        <td><span class="queue-badge"><?= htmlspecialchars($row['queue_number']) ?></span></td>
                        <td><strong><?= htmlspecialchars($row['patient_name'] ?? 'Unknown') ?></strong></td>
                        <td><?= htmlspecialchars($row['service']) ?></td>
                        <td><strong><?= number_format($row['price'], 2) ?> DA</strong></td>
                        <td><span class="badge-pending"><i class="fa-solid fa-clock"></i> PENDING</span></td>
                        <td>
                            <button class="pay-btn" id="paybtn-<?= $row['id'] ?>"
                                data-id="<?= $row['id'] ?>"
                                data-patient="<?= htmlspecialchars($row['patient_name'] ?? 'Unknown', ENT_QUOTES) ?>"
                                data-service="<?= htmlspecialchars($row['service'], ENT_QUOTES) ?>"
                                data-amount="<?= number_format($row['price'], 2) ?>">
                                <i class="fa-solid fa-money-bill"></i> Process Payment
                            </button>
                         </div>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-money-bill-wave"></i> Confirm Payment</h3>
        <div class="patient-info" id="modalPatientInfo"></div>
        <div class="amount" id="modalAmount"></div>
        <div class="method-box"><i class="fa-solid fa-money-bill"></i> Cash Payment</div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closePayModal()">Cancel</button>
            <button class="btn-confirm" onclick="confirmPay()">
                <i class="fa-solid fa-check"></i> Confirm Payment
            </button>
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

const preSelectedId = <?= $preSelected ?>;
</script>
<script src="js/billing.js"></script>
</body>
</html>