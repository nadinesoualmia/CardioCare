<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: index.php");
    exit();
}

$userName   = $_SESSION['name']   ?? 'Doctor';
$userRole   = $_SESSION['role']   ?? 'Doctor';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=Doctor&background=2563eb&color=fff';

// Get doctor ID from session name
$stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
$stmt->execute([$userName]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
$doctorId = $doctor['id'] ?? 0;

// Handle acknowledge action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_id'])) {
    $stmt = $conn->prepare("UPDATE alerts SET status='Acknowledged' WHERE id=?");
    $stmt->execute([$_POST['acknowledge_id']]);
    header("Location: alerts.php");
    exit();
}

// Fetch alerts ONLY for patients that belong to this doctor (via appointments)
// Only show Critical and Warning alerts (hide Normal)
$alerts = $conn->prepare("
    SELECT a.*,
           p.full_name AS patient_name,
           v.heart_rate, v.blood_pressure, v.temperature, v.spo2, v.weight
    FROM alerts a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN vitals v ON a.vital_id = v.id
    WHERE a.patient_id IN (
        SELECT DISTINCT patient_id 
        FROM appointments 
        WHERE doctor_id = ?
    )
    AND a.alert_type IN ('Critical', 'Warning')
    ORDER BY FIELD(a.alert_type, 'Critical', 'Warning'), a.created_at DESC
");
$alerts->execute([$doctorId]);
$alerts = $alerts->fetchAll(PDO::FETCH_ASSOC);

// Count active alerts for this doctor only (Critical + Warning)
$activeCount = $conn->prepare("
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
$activeCount->execute([$doctorId]);
$activeCount = $activeCount->fetchColumn();

// Prepare data for JavaScript
$criticalCount = count(array_filter($alerts, fn($a) => $a['alert_type'] === 'Critical'));
$warningCount = count(array_filter($alerts, fn($a) => $a['alert_type'] === 'Warning'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts - CardioCare</title>
    <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }

        /* SIDEBAR */
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
            padding: 24px 24px;
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
            margin-bottom: 15px;
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

        /* TOP NAVBAR */
        .top-navbar {
            display: flex;
            justify-content: space-between;
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

        /* DASHBOARD CONTENT */
        .dashboard-content {
            padding: 24px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* FILTER TABS */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: #f3f4f6;
        }

        .filter-tab.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .filter-tab.tab-critical {
            color: #dc2626;
            border-color: #fecaca;
        }

        .filter-tab.tab-critical.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .filter-tab.tab-warning {
            color: #d97706;
            border-color: #fed7aa;
        }

        .filter-tab.tab-warning.active {
            background: #d97706;
            color: white;
            border-color: #d97706;
        }

        /* ALERT CARDS */
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            border-left: 4px solid;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }

        .alert-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .alert-card.Critical {
            border-left-color: #dc2626;
            background: #fef2f2;
        }

        .alert-card.Warning {
            border-left-color: #d97706;
            background: #fffbeb;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .alert-card.Critical .alert-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-card.Warning .alert-icon {
            background: #fef3c7;
            color: #d97706;
        }

        .alert-body {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pill.Active {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pill.Acknowledged {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-msg {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .vitals-mini {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .vital-chip {
            background: #f3f4f6;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
            color: #374151;
        }

        .vital-chip.bad {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-meta {
            font-size: 12px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ack-form {
            margin-left: auto;
        }

        .ack-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .ack-btn:hover {
            background: #1d4ed8;
        }

        .done-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
        }

        .empty-state {
            text-align: center;
            padding: 48px;
            background: white;
            border-radius: 12px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
        }

        /* RESPONSIVE */
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

            .alert-card {
                flex-direction: column;
            }

            .ack-form, .done-badge {
                margin-left: 0;
                width: 100%;
            }

            .ack-btn {
                width: 100%;
                justify-content: center;
            }

            .done-badge {
                justify-content: center;
            }

            .filter-tabs {
                gap: 8px;
            }

            .filter-tab {
                font-size: 11px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CardioCare</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="doctor-dashboard.php" class="nav-item">
            <i class="fa-solid fa-stethoscope"></i> Overview
        </a></li>
        <li><a href="patient-record.php" class="nav-item">
            <i class="fa-solid fa-file-medical"></i> Medical Records
        </a></li>
        <li><a href="consultation.php" class="nav-item">
            <i class="fa-solid fa-user-doctor"></i> Consultation
        </a></li>
        <li><a href="exam-request.php" class="nav-item">
            <i class="fa-solid fa-file-prescription"></i> Exam Requests
        </a></li>
        <li style="position: relative;">
            <a href="alerts.php" class="nav-item active">
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

<!-- MAIN CONTENT -->
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
        <div class="page-header">
            <div>
                <h2>Clinical Alerts Center</h2>
                <p>Review and acknowledge critical or warning alerts for your patients.</p>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">All (<?php echo count($alerts); ?>)</button>
            <button class="filter-tab tab-critical" data-filter="Critical">
                🔴 Critical (<?php echo $criticalCount; ?>)
            </button>
            <button class="filter-tab tab-warning" data-filter="Warning">
                🟡 Warning (<?php echo $warningCount; ?>)
            </button>
            <button class="filter-tab" data-filter="Active">Active Only</button>
            <button class="filter-tab" data-filter="Acknowledged">Acknowledged</button>
        </div>

        <!-- Alerts list -->
        <div id="alertsList">
        <?php if (empty($alerts)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-bell-slash"></i>
                <p>No critical or warning alerts for your patients.</p>
                <small style="color:#9ca3af;">All vital signs are within normal range.</small>
            </div>
        <?php else: ?>
            <?php foreach ($alerts as $a):
                $type   = $a['alert_type'];
                $status = $a['status'];
                $icon   = $type === 'Critical' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation';
                $hr     = $a['heart_rate'];
                $sp     = $a['spo2'];
                $temp   = $a['temperature'];
            ?>
            <div class="alert-card <?php echo $type; ?>"
                 data-type="<?php echo $type; ?>"
                 data-status="<?php echo $status; ?>">

                <div class="alert-icon">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>

                <div class="alert-body">
                    <div class="alert-title">
                        <?php echo htmlspecialchars($a['patient_name'] ?? 'Unknown Patient'); ?>
                        <span class="status-pill <?php echo $status; ?>">
                            <?php echo $status; ?>
                        </span>
                    </div>

                    <div class="alert-msg"><?php echo htmlspecialchars($a['message']); ?></div>

                    <!-- Vitals mini display -->
                    <?php if ($hr || $sp || $temp): ?>
                    <div class="vitals-mini">
                        <?php if ($hr): ?>
                            <span class="vital-chip <?php echo ($hr < 50 || $hr > 120) ? 'bad' : ''; ?>">
                                HR: <?php echo $hr; ?> bpm
                            </span>
                        <?php endif; ?>
                        <?php if ($a['blood_pressure']): ?>
                            <span class="vital-chip">BP: <?php echo htmlspecialchars($a['blood_pressure']); ?></span>
                        <?php endif; ?>
                        <?php if ($temp): ?>
                            <span class="vital-chip <?php echo ($temp > 39.5) ? 'bad' : ''; ?>">
                                Temp: <?php echo $temp; ?>°C
                            </span>
                        <?php endif; ?>
                        <?php if ($sp): ?>
                            <span class="vital-chip <?php echo ($sp < 90) ? 'bad' : ''; ?>">
                                SpO2: <?php echo $sp; ?>%
                            </span>
                        <?php endif; ?>
                        <?php if ($a['weight']): ?>
                            <span class="vital-chip">Weight: <?php echo $a['weight']; ?> kg</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="alert-meta">
                        <i class="fa-regular fa-clock"></i>
                        <?php echo date('M d, Y — H:i', strtotime($a['created_at'])); ?>
                    </div>
                </div>

                <!-- Acknowledge button -->
                <?php if ($status === 'Active'): ?>
                <form method="POST" class="ack-form">
                    <input type="hidden" name="acknowledge_id" value="<?php echo $a['id']; ?>">
                    <button type="submit" class="ack-btn">
                        <i class="fa-solid fa-check"></i> Acknowledge
                    </button>
                </form>
                <?php else: ?>
                    <span class="done-badge">
                        <i class="fa-solid fa-circle-check"></i> Done
                    </span>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

// Filter tabs functionality
const filterTabs = document.querySelectorAll('.filter-tab');
const alertCards = document.querySelectorAll('.alert-card');

filterTabs.forEach(tab => {
    tab.addEventListener('click', function() {
        filterTabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filterValue = this.getAttribute('data-filter');

        alertCards.forEach(card => {
            const cardType   = card.getAttribute('data-type');
            const cardStatus = card.getAttribute('data-status');

            if (filterValue === 'all') {
                card.style.display = 'flex';
            } else if (filterValue === 'Active') {
                card.style.display = cardStatus === 'Active' ? 'flex' : 'none';
            } else if (filterValue === 'Acknowledged') {
                card.style.display = cardStatus === 'Acknowledged' ? 'flex' : 'none';
            } else {
                card.style.display = cardType === filterValue ? 'flex' : 'none';
            }
        });
    });
});
</script>

</body>
</html>