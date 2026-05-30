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

// Get filter parameters
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// Build query with filters
$sql = "
    SELECT a.*,
           p.full_name AS patient_name,
           u.full_name AS doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE 1=1
";

$params = [];

if ($dateFilter && $dateFilter !== 'all') {
    $sql .= " AND DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
}

if ($statusFilter && $statusFilter !== 'all') {
    $sql .= " AND a.payment_status = ?";
    $params[] = $statusFilter;
}

if ($searchTerm) {
    $sql .= " AND (p.full_name LIKE ? OR a.queue_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments for today
$today = date('Y-m-d');
$stmtToday = $conn->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE DATE(appointment_date) = ? AND payment_status = 'Paid'
");
$stmtToday->execute([$today]);
$todayCount = $stmtToday->fetchColumn();

// Get pending payments count
$stmtPending = $conn->prepare("
    SELECT COUNT(*) FROM appointments WHERE payment_status = 'Pending'
");
$stmtPending->execute();
$pendingCount = $stmtPending->fetchColumn();

$allStaff = $conn->query("
    SELECT id, full_name, role FROM users
    WHERE isActive = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - CardioCare</title>
     <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/appointments.css">
    
    <style>
      
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
        <li><a href="appointments.php" class="nav-item active"><i class="fa-solid fa-calendar-check"></i> Appointments</a></li>
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
        <div class="page-header">
            <h2>Appointments Management</h2>
            <p>View, manage, and track all patient appointments</p>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fa-solid fa-calendar"></i> Date</label>
                <input type="date" id="filterDate" value="<?= $dateFilter !== 'all' ? $dateFilter : '' ?>">
            </div>
            <div class="filter-group">
                <label><i class="fa-solid fa-credit-card"></i> Payment Status</label>
                <select id="filterStatus">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fa-solid fa-search"></i> Search</label>
                <input type="text" id="filterSearch" placeholder="Patient or Queue #" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <button class="filter-btn" onclick="applyFilters()">
                <i class="fa-solid fa-filter"></i> Apply Filters
            </button>
            <button class="reset-btn" onclick="resetFilters()">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </button>
            <div class="stats-badge">
                <i class="fa-solid fa-calendar-day"></i> Today: <span><?= $todayCount ?></span> | 
                <i class="fa-solid fa-clock"></i> Pending: <span><?= $pendingCount ?></span>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3>All Appointments</h3>
                <div class="table-search-wrapper">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search in table...">
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Patient Name</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="queueTable">
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:2rem; color:#9ca3af;">
                                <i class="fa-solid fa-calendar-xmark" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                                No appointments found for selected filters
                             </div>
                        </tr>
                    <?php else: foreach ($appointments as $row):
                        $status  = $row['status'] ?? 'Waiting';
                        $payment = $row['payment_status'] ?? 'Pending';

                        $badge = match($status) {
                            'Completed'   => '<span class="badge-success">Completed</span>',
                            'Cancelled'   => '<span class="badge-danger">Cancelled</span>',
                            'Scheduled'   => '<span class="badge-primary">Scheduled</span>',
                            'In Progress' => '<span class="badge-primary">In Progress</span>',
                            default       => '<span class="badge-warning">Waiting</span>'
                        };

                        $date = !empty($row['appointment_date']) ? date('M d, Y', strtotime($row['appointment_date'])) : '-';
                        $time = !empty($row['appointment_time']) ? date('h:i A', strtotime($row['appointment_time'])) : '-';
                        
                        $isToday = $row['appointment_date'] == date('Y-m-d');
                    ?>
                        <tr class="<?= $isToday ? 'today-row' : '' ?>" data-date="<?= $row['appointment_date'] ?>">
                            <td><span class="queue-badge"><?= htmlspecialchars($row['queue_number']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['patient_name'] ?? 'Unknown') ?></strong></td>
                            <td><?= htmlspecialchars($row['service']) ?></td>
                            <td class="date-cell"><?= $date ?> <?= $isToday ? '<span class="today-badge">Today</span>' : '' ?></td>
                            <td class="time-cell"><?= $time ?></td>
                            <td class="payment-cell">
                                <?php if ($payment === 'Paid'): ?>
                                    <span class="paid-text"><i class="fa-solid fa-circle-check"></i> Paid</span>
                                <?php else: ?>
                                    <span class="pending-text"><i class="fa-solid fa-clock"></i> Pending</span>
                                <?php endif; ?>
                             </div>
                            <td class="status-cell"><?= $badge ?> </div>
                            <td class="action-buttons">
                                <?php if ($payment !== 'Paid'): ?>
                                    <button class="btn-icon pay" title="Process Payment" onclick="goToPayment(<?= (int)$row['id'] ?>)">
                                        <i class="fa-solid fa-credit-card"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn-icon edit" title="Edit" onclick="editAppointment(<?= (int)$row['id'] ?>)">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn-icon delete" title="Delete" onclick="deleteAppointment(<?= (int)$row['id'] ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                             </div>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3>Edit Appointment</h3>
        <input type="hidden" id="editId">
        <div class="form-grid">
            <div class="full-width">
                <label>Patient Name</label>
                <input type="text" id="editPatient" readonly>
            </div>
            <div class="full-width">
                <label>Service</label>
                <select id="editService" onchange="onEditServiceChange()">
                    <option value="Consultation">Consultation</option>
                    <option value="Laboratory">Laboratory</option>
                    <option value="Radiology">Radiology</option>
                </select>
            </div>
            <div class="full-width">
                <label>Doctor / Staff</label>
                <select id="editDoctor"></select>
            </div>
            <div>
                <label>Case Type</label>
                <select id="editCase">
                    <option>New</option>
                    <option>Follow-up</option>
                    <option>Urgent</option>
                </select>
            </div>
            <div>
                <label>Price (DA)</label>
                <input type="number" id="editPrice">
            </div>
            <div>
                <label>Date</label>
                <input type="date" id="editDateOnly">
            </div>
            <div>
                <label>Time</label>
                <input type="time" id="editTimeOnly">
            </div>
        </div>
        <button class="save-btn" onclick="saveEdit()">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
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
const allStaffFromPHP = <?php echo json_encode($allStaff); ?>;
const prices = { Consultation: 3000, Laboratory: 5000, Radiology: 8000 };
</script>
<script src="js/appointments.js"></script>
</body>
</html>