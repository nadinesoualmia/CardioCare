<?php
session_start();

include_once 'backend/connection.php';

if (!isset($conn)) {
    die("Database connection failed. Check backend/connection.php path.");
}

$error   = "";
$success = "";

if (isset($_GET['deactivate_id'])) {
    $targetId = (int)$_GET['deactivate_id'];
    if (isset($_SESSION['id']) && $_SESSION['id'] === $targetId) {
        $error = "You cannot deactivate your own account!";
    } else {
        
        $stmt = $conn->prepare("UPDATE users SET isActive = 0 WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        $success = "User deactivated successfully.";
    }
}

if (isset($_GET['activate_id'])) {
    $targetId = (int)$_GET['activate_id'];
    $stmt = $conn->prepare("UPDATE users SET isActive = 1 WHERE id = :id");
    $stmt->execute(['id' => $targetId]);
    $success = "User reactivated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_form'])) {
    $fullName = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $role     = $_POST['role'];

    if (!empty($_POST['edit_id'])) {
        $editId = (int)$_POST['edit_id'];
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=:full, username=:user, email=:email, phone=:phone, role=:role, password=:pass WHERE id=:id");
            $stmt->execute(['full' => $fullName, 'user' => $username, 'email' => $email, 'phone' => $phone, 'role' => $role, 'pass' => $password, 'id' => $editId]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=:full, username=:user, email=:email, phone=:phone, role=:role WHERE id=:id");
            $stmt->execute(['full' => $fullName, 'user' => $username, 'email' => $email, 'phone' => $phone, 'role' => $role, 'id' => $editId]);
        }
        $success = "User updated successfully.";
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone, role, password, isActive) VALUES (:full, :user, :email, :phone, :role, :pass, 1)");
        $stmt->execute(['full' => $fullName, 'user' => $username, 'email' => $email, 'phone' => $phone, 'role' => $role, 'pass' => $password]);
        $success = "User added successfully.";
    }
    header("Location: admin-users.php?msg=" . urlencode($success));
    exit;
}

/* 
   ADD  lkan ma kanch id / UPDATE / DELETE STAFF SCHEDULE 1 m3naha mawjod,invital 3andha nafess khadma t3 int
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_form'])) {
    $staff_id = intval($_POST['staff_id']);
    $day_of_week = intval($_POST['day_of_week']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    if ($staff_id && $day_of_week !== '' && $start_time && $end_time) {
        $check = $conn->prepare("SELECT COUNT(*) FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
        $check->execute([$staff_id, $day_of_week]);
        
        if ($check->fetchColumn() > 0) {
            $stmt = $conn->prepare("UPDATE doctor_schedules SET start_time = ?, end_time = ? WHERE doctor_id = ? AND day_of_week = ?");
            $stmt->execute([$start_time, $end_time, $staff_id, $day_of_week]);
            $success = "Schedule updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$staff_id, $day_of_week, $start_time, $end_time]);
            $success = "Schedule added successfully.";
        }
    } else {
        $error = "Please fill all schedule fields.";
    }
    header("Location: admin-users.php?msg=" . urlencode($success) . ($error ? "&err=" . urlencode($error) : ""));
    exit;
}

if (isset($_GET['delete_schedule_id'])) {
    $scheduleId = intval($_GET['delete_schedule_id']);
    $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
    $stmt->execute([$scheduleId]);
    header("Location: admin-users.php?msg=Schedule deleted");
    exit;
}

if (isset($_GET['delete_schedule_day'])) {
    $staff_id = intval($_GET['staff_id']);
    $day_of_week = intval($_GET['day']);
    $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
    $stmt->execute([$staff_id, $day_of_week]);
    header("Location: admin-users.php?msg=Schedule removed for this day");
    exit;
}

/* ─────────────────────────────────────────────
   ADD / EDIT / DELETE STAFF TIME OFF
───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_timeoff_form'])) {
    $timeoff_id = intval($_POST['timeoff_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    if ($timeoff_id && $start_date && $end_date) {
        $stmt = $conn->prepare("UPDATE doctor_time_off SET off_date = ?, end_date = ?, reason = ? WHERE id = ?");
        $stmt->execute([$start_date, $end_date, $reason, $timeoff_id]);
        $success = "Time off updated successfully.";
    } else {
        $error = "Please fill all fields.";
    }
    header("Location: admin-users.php?msg=" . urlencode($success) . ($error ? "&err=" . urlencode($error) : ""));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeoff_form'])) {
    $staff_id = intval($_POST['staff_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    if ($staff_id && $start_date && $end_date) {
        $stmt = $conn->prepare("INSERT INTO doctor_time_off (doctor_id, off_date, end_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$staff_id, $start_date, $end_date, $reason]);
        $success = "Time off added successfully.";
    } else {
        $error = "Please fill all fields.";
    }
    header("Location: admin-users.php?msg=" . urlencode($success));
    exit;
}

if (isset($_GET['delete_timeoff_id'])) {
    $timeoffId = intval($_GET['delete_timeoff_id']);
    $stmt = $conn->prepare("DELETE FROM doctor_time_off WHERE id = ?");
    $stmt->execute([$timeoffId]);
    header("Location: admin-users.php?msg=Time off deleted");
    exit;
}

/* ─────────────────────────────────────────────
   FETCH DATA
───────────────────────────────────────────── */
$users = $conn->query("SELECT * FROM users ORDER BY isActive DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

$schedules = $conn->query("
    SELECT ds.*, u.full_name, u.role
    FROM doctor_schedules ds 
    JOIN users u ON ds.doctor_id = u.id
    ORDER BY u.role, u.full_name, ds.day_of_week
")->fetchAll(PDO::FETCH_ASSOC);

$timeOffs = $conn->query("
    SELECT t.*, u.full_name, u.role
    FROM doctor_time_off t 
    JOIN users u ON t.doctor_id = u.id
    ORDER BY t.off_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$staffList = $conn->query("
    SELECT id, full_name, role 
    FROM users 
    WHERE role IN ('Doctor', 'Laboratory', 'Radiology') 
    AND isActive = 1 
    ORDER BY role, full_name
")->fetchAll(PDO::FETCH_ASSOC);

$userName   = $_SESSION['name']   ?? 'Admin';
$userRole   = $_SESSION['role']   ?? 'Administrator';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=Admin&background=2563eb&color=fff';
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['err'] ?? '';
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$scheduleArray = [];
foreach ($schedules as $s) {
    $scheduleArray[] = [
        'staff_id' => $s['doctor_id'],
        'staff_name' => $s['full_name'],
        'role' => $s['role'],
        'day' => $s['day_of_week'],
        'start' => substr($s['start_time'], 0, 5),
        'end' => substr($s['end_time'], 0, 5)
    ];
}

$staffArray = [];
foreach ($staffList as $staff) {
    $staffArray[] = [
        'id' => $staff['id'],
        'name' => $staff['full_name'],
        'role' => $staff['role']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - CardioCare</title>
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

.nav-item:hover {
    background: #eff6ff;
    color: #2563eb;
}

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

/* MAIN CONTENT */
.main-content {
    margin-left: 260px;
    min-height: 100vh;
    background: #f1f5f9;
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

/* ALERTS */
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

/* CARDS */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.card-title {
    padding: 1rem 1.5rem;
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

/* FORM GRID */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    font-family: 'Inter', sans-serif;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-wrapper input {
    width: 100%;
    padding-right: 35px;
}

.password-toggle {
    position: absolute;
    right: 10px;
    cursor: pointer;
    color: #9ca3af;
    background: none;
    border: none;
    font-size: 1rem;
}

.password-note {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 4px;
}

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: #9ca3af;
    color: white;
}

.btn-secondary:hover {
    background: #6b7280;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    border: none;
    background: transparent;
}

.btn-icon.edit { color: #3b82f6; }
.btn-icon.edit:hover { background: #eff6ff; }
.btn-icon.deactivate { color: #dc2626; }
.btn-icon.deactivate:hover { background: #fef2f2; }
.btn-icon.activate { color: #16a34a; }
.btn-icon.activate:hover { background: #f0fdf4; }
.btn-icon.delete-sched { color: #ef4444; }
.btn-icon.delete-sched:hover { background: #fef2f2; }

/* BADGES */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-active { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #fee2e2; color: #991b1b; }

.role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}
.role-Doctor { background: #dbeafe; color: #1e40af; }
.role-Laboratory { background: #dcfce7; color: #166534; }
.role-Radiology { background: #fef3c7; color: #92400e; }

/* TABLES */
.table-container {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    margin-bottom: 2rem;
}

.table-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.table-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.table-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.35rem 0.7rem;
}

.table-search input {
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.84rem;
    width: 200px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    font-weight: 600;
    color: #64748b;
    font-size: 0.85rem;
    text-transform: uppercase;
    background: #f8fafc;
}

td {
    font-size: 0.95rem;
    color: #1e293b;
}

tbody tr:hover {
    background: #f8fafc;
}

tr.inactive-row td {
    opacity: 0.55;
}

/* FILTER BAR */
.filter-bar {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    padding: 1rem 1.25rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
}

.filter-group select {
    padding: 0.4rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.8rem;
    background: white;
    cursor: pointer;
}

.reset-filter {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    font-weight: 600;
}

.reset-filter:hover {
    background: #dc2626;
}

/* SCHEDULE SECTION */
.schedule-section, .timeoff-section {
    margin-top: 2rem;
}

.staff-search-section, .timeoff-search-section {
    margin-bottom: 1.5rem;
    padding: 0 1rem;
}

.staff-results, .timeoff-results {
    margin-top: 1rem;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    display: none;
}

.staff-result-item, .timeoff-result-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
}

.staff-result-item:hover, .timeoff-result-item:hover {
    background: #f3f4f6;
}

.staff-result-name, .timeoff-result-name {
    font-weight: 600;
}

.staff-result-role, .timeoff-result-role {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
    padding: 1rem;
}

.day-card {
    background: #f9fafb;
    border-radius: 10px;
    padding: 0.75rem;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.day-name {
    font-weight: 700;
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
    color: #374151;
}

.day-time {
    font-size: 0.7rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.edit-schedule-btn, .delete-schedule-btn {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    margin: 0 2px;
}

.edit-schedule-btn {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
}

.edit-schedule-btn:hover {
    background: #3b82f6;
    color: white;
}

.delete-schedule-btn {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #dc2626;
}

.delete-schedule-btn:hover {
    background: #dc2626;
    color: white;
}

.timeoff-form, .edit-timeoff-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: end;
    padding: 1rem;
}

.edit-timeoff-panel {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 12px;
    padding: 1.25rem;
    margin: 1rem;
}

/* TABS */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 0;
}

.tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: none;
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: #2563eb;
    background: #eff6ff;
}

.tab-btn.active {
    color: #2563eb;
    background: #eff6ff;
    border-bottom: 2px solid #2563eb;
}

.tab-panel {
    display: none;
}

.tab-panel.active {
    display: block;
}

/* MODAL */
.modal-overlay-schedule {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content-schedule {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    width: 400px;
    max-width: 90%;
}

.modal-buttons {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

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
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-search {
        width: 100%;
    }
    
    .table-search input {
        width: 100%;
    }
    
    .days-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .days-grid {
        grid-template-columns: 1fr;
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
        <li><a href="admin-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
        <li><a href="admin-users.php" class="nav-item active"><i class="fa-solid fa-users"></i> Manage Users</a></li>
    </ul>

    <div class="sidebar-footer">
        <a href="backend/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- NAVBAR -->
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

    <!-- CONTENT -->
    <div class="dashboard-content">

        <!-- Flash messages with auto-hide -->
        <?php if (!empty($flashMsg)): ?>
            <div class="alert alert-success" id="flashMessage">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($flashMsg); ?>
            </div>
            <script>
                setTimeout(function() {
                    const msg = document.getElementById('flashMessage');
                    if (msg) {
                        msg.style.animation = 'slideOut 0.3s ease';
                        setTimeout(function() { msg.style.display = 'none'; }, 300);
                    }
                }, 3000);
            </script>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" id="errorMessage">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
            <script>
                setTimeout(function() {
                    const msg = document.getElementById('errorMessage');
                    if (msg) {
                        msg.style.animation = 'slideOut 0.3s ease';
                        setTimeout(function() { msg.style.display = 'none'; }, 300);
                    }
                }, 4000);
            </script>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="users"><i class="fa-solid fa-users"></i> Users</button>
            <button class="tab-btn" data-tab="hours"><i class="fa-solid fa-clock"></i> Working Hours</button>
            <button class="tab-btn" data-tab="timeoff"><i class="fa-solid fa-calendar-xmark"></i> Time Off</button>
        </div>

        <!-- TAB 1: USERS -->
        <div id="tab-users" class="tab-panel active">
            <!-- ADD / EDIT USER FORM -->
            <div class="card">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-plus"></i>
                    <span id="formTitle">Add New User</span>
                </h3>

                <form id="userForm" method="POST" autocomplete="off">
                    <input type="hidden" name="user_form" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" id="name" required>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" id="username" required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email" required>
                        </div>

                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" id="phone" required>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" id="role" required>
                                <option value="" disabled selected hidden>Choose role</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Nurse">Nurse</option>
                                <option value="Receptionist">Receptionist</option>
                                <option value="Laboratory">Laboratory</option>
                                <option value="Radiology">Radiology</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password">
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="fa-solid fa-eye" id="passwordIcon"></i>
                                </button>
                            </div>
                            <p class="password-note" id="passwordNote"></p>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; padding:0 1.5rem 1.5rem 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check"></i> Save User
                        </button>
                        <button type="button" onclick="resetForm()" class="btn btn-secondary">
                            <i class="fa-solid fa-rotate-left"></i> Reset                        </button>
                    </div>
                </form>
            </div>

            <!-- USERS TABLE WITH FILTERS -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fa-solid fa-users"></i> All Users</h3>
                    <div class="table-search">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search users...">
                    </div>
                </div>
                
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>Filter by Role:</label>
                        <select id="filterRole">
                            <option value="all">All Roles</option>
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Receptionist">Receptionist</option>
                            <option value="Laboratory">Laboratory</option>
                            <option value="Radiology">Radiology</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Filter by Status:</label>
                        <select id="filterStatus">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <button class="reset-filter" onclick="resetFilters()">Reset Filters</button>
                </div>
                
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTable">
                            <?php foreach ($users as $user): ?>
                            <?php $isActive = (int)$user['isActive']; ?>
                            <tr class="<?php echo $isActive ? '' : 'inactive-row'; ?>" data-role="<?php echo htmlspecialchars($user['role']); ?>" data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>">
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-active"><i class="fa-solid fa-circle-check"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
                                    <?php endif; ?>
                                  </div>
                                <td>
                                    <button class="btn-icon edit" onclick="editUser(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($user['phone'], ENT_QUOTES); ?>','<?php echo $user['role']; ?>')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($isActive): ?>
                                        <a href="admin-users.php?deactivate_id=<?php echo $user['id']; ?>" class="btn-icon deactivate" onclick="return confirm('Deactivate this user?')">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="admin-users.php?activate_id=<?php echo $user['id']; ?>" class="btn-icon activate" onclick="return confirm('Reactivate this user?')">
                                            <i class="fa-solid fa-user-check"></i>
                                        </a>
                                    <?php endif; ?>
                                  </div>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: WORKING HOURS -->
        <div id="tab-hours" class="tab-panel">
            <div class="card schedule-section">
                <h3 class="card-title">
                    <i class="fa-solid fa-clock"></i>
                    Staff Working Hours (Doctor / Laboratory / Radiology)
                </h3>

                <div class="staff-search-section">
                    <div class="staff-search-box">
                        <div class="form-group">
                            <label><i class="fa-solid fa-search"></i> Search Staff by Name</label>
                            <input type="text" id="staffSearchInput" placeholder="Type staff name to search..." style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid #e5e7eb;">
                        </div>
                    </div>

                    <div id="staffSearchResults" class="staff-results"></div>
                    <div id="selectedStaffBadge" style="display: none;"></div>

                    <div id="weeklyScheduleContainer" style="display: none;">
                        <div class="weekly-schedule-grid">
                            <div class="days-grid" id="daysGrid"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: TIME OFF -->
        <div id="tab-timeoff" class="tab-panel">
            <div class="card timeoff-section">
                <h3 class="card-title">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    Staff Time Off / Vacations
                </h3>

                <div class="timeoff-search-section">
                    <div class="timeoff-search-box">
                        <div class="form-group">
                            <label><i class="fa-solid fa-search"></i> Search Staff for Time Off</label>
                            <input type="text" id="timeoffStaffSearchInput" placeholder="Type staff name to search..." style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid #e5e7eb;">
                        </div>
                    </div>

                    <div id="timeoffStaffSearchResults" class="timeoff-results"></div>
                    <div id="selectedTimeoffStaffBadge" style="display: none;"></div>

                    <div id="timeoffFormContainer" style="display: none; margin-top: 1.5rem;">
                        <form method="POST" class="timeoff-form">
                            <input type="hidden" name="timeoff_form" value="1">
                            <input type="hidden" name="staff_id" id="timeoff_staff_id">
                            
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" id="timeoff_start_date" required>
                            </div>

                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" id="timeoff_end_date" required>
                            </div>

                            <div class="form-group">
                                <label>Reason</label>
                                <input type="text" name="reason" id="timeoff_reason" placeholder="Vacation, Sick Leave, etc.">
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
                                    <i class="fa-solid fa-plus"></i> Add Time Off
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="editTimeOffPanel" style="display: none;" class="edit-timeoff-panel">
                    <h4>
                        <i class="fa-solid fa-pen-to-square"></i> Edit Time Off
                        <button type="button" class="close-edit" onclick="closeEditPanel()">&times;</button>
                    </h4>
                    <form method="POST" class="edit-timeoff-form">
                        <input type="hidden" name="edit_timeoff_form" value="1">
                        <input type="hidden" name="timeoff_id" id="edit_timeoff_id">
                        
                        <div class="form-group">
                            <label>Staff</label>
                            <input type="text" id="edit_staff_name" readonly style="background:#f3f4f6;">
                        </div>

                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" required>
                        </div>

                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" required>
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <input type="text" name="reason" id="edit_reason" placeholder="Vacation, Sick Leave, etc.">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
                                <i class="fa-solid fa-save"></i> Update Time Off
                            </button>
                        </div>
                    </form>
                </div>

                <!-- UPCOMING TIME OFF TABLE -->
                <div class="table-container" style="margin-top: 1.5rem;">
                    <div class="table-header">
                        <h3><i class="fa-solid fa-list"></i> Upcoming Time Off</h3>
                        <div class="table-search">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="timeoffSearchInput" placeholder="Search time off records...">
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="timeoff-table" id="timeoffTable">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($timeOffs)): ?>
                                    <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:1.5rem;">No time off records. Add one above.<?php echo "\n"; ?>NonNull
                                <?php else: ?>
                                    <?php foreach ($timeOffs as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                                            <td><span class="role-badge role-<?php echo $t['role']; ?>"><?php echo $t['role']; ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($t['off_date'])); ?></td>
                                            <td><?php echo $t['end_date'] ? date('M d, Y', strtotime($t['end_date'])) : date('M d, Y', strtotime($t['off_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($t['reason'] ?? '—'); ?></td>
                                            <td style="display:flex; gap:6px; align-items:center;">
                                                <button class="btn-icon edit" onclick="editTimeOff(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['full_name'], ENT_QUOTES); ?>', '<?php echo $t['off_date']; ?>', '<?php echo $t['end_date'] ?? $t['off_date']; ?>', '<?php echo htmlspecialchars($t['reason'] ?? '', ENT_QUOTES); ?>')">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <a href="admin-users.php?delete_timeoff_id=<?php echo $t['id']; ?>" class="btn-icon delete-sched" onclick="return confirm('Delete this time off?')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                             </div>
                                            </tr>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Schedule Edit Modal -->
<div id="scheduleEditModal" class="modal-overlay-schedule">
    <div class="modal-content-schedule">
        <h4><i class="fa-solid fa-pen"></i> Edit Schedule</h4>
        <form method="POST" id="scheduleEditForm">
            <input type="hidden" name="schedule_form" value="1">
            <input type="hidden" name="staff_id" id="edit_staff_id">
            <input type="hidden" name="day_of_week" id="edit_day_of_week">
            
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" name="start_time" id="edit_start_time" required>
            </div>
            
            <div class="form-group">
                <label>End Time</label>
                <input type="time" name="end_time" id="edit_end_time" required>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" class="btn-primary">Save</button>
                <button type="button" onclick="closeScheduleModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
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

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Pass PHP data to JavaScript
const schedulesData = <?php echo json_encode($scheduleArray); ?>;
const staffListData = <?php echo json_encode($staffArray); ?>;
const daysOfWeek = [
    { value: 1, name: 'Monday' },
    { value: 2, name: 'Tuesday' },
    { value: 3, name: 'Wednesday' },
    { value: 4, name: 'Thursday' },
    { value: 5, name: 'Friday' },
    { value: 6, name: 'Saturday' },
    { value: 0, name: 'Sunday' }
];

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.getElementById('passwordIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function resetForm() {
    document.getElementById('userForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('formTitle').innerText = 'Add New User';
    document.getElementById('password').required = true;
    document.getElementById('passwordNote').innerText = '';
}

function editUser(id, name, username, email, phone, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('username').value = username;
    document.getElementById('email').value = email;
    document.getElementById('phone').value = phone;
    document.getElementById('role').value = role;
    document.getElementById('formTitle').innerText = 'Edit User';
    document.getElementById('password').required = false;
    document.getElementById('passwordNote').innerText = 'Leave blank to keep current password';
}

function editTimeOff(id, name, startDate, endDate, reason) {
    document.getElementById('edit_timeoff_id').value = id;
    document.getElementById('edit_staff_name').value = name;
    document.getElementById('edit_start_date').value = startDate;
    document.getElementById('edit_end_date').value = endDate;
    document.getElementById('edit_reason').value = reason;
    document.getElementById('editTimeOffPanel').style.display = 'block';
}

function closeEditPanel() {
    document.getElementById('editTimeOffPanel').style.display = 'none';
}

function closeScheduleModal() {
    document.getElementById('scheduleEditModal').style.display = 'none';
}

function resetFilters() {
    document.getElementById('filterRole').value = 'all';
    document.getElementById('filterStatus').value = 'all';
    filterTable();
}

function filterTable() {
    const roleFilter = document.getElementById('filterRole').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#usersTable tr');
    
    rows.forEach(row => {
        const role = row.getAttribute('data-role');
        const status = row.getAttribute('data-status');
        let show = true;
        
        if (roleFilter !== 'all' && role !== roleFilter) show = false;
        if (statusFilter !== 'all' && status !== statusFilter) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

document.getElementById('filterRole').addEventListener('change', filterTable);
document.getElementById('filterStatus').addEventListener('change', filterTable);

document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
<script src="js/admin-users.js"></script>
</body>
</html>