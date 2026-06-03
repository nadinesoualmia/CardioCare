<?php
session_start();
include 'backend/connection.php';

if (!isset($_SESSION['name'])) {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['name'];
$userRole = $_SESSION['role'] ?? 'Laboratory';
$userAvatar = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";

$successMessage = null;
$errorMessage   = null;

/* Submit lab result nb3t b mthod POST l backend w n3mlo update 3la request w insert f exam_results*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');
    $keepOpen   = isset($_POST['keep_open']);

    if ($request_id <= 0) {
        $errorMessage = "No request selected.";
    } else {
        $chk = $conn->prepare("SELECT status, patient_id FROM exam_requests WHERE id = ?");
        $chk->execute([$request_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $errorMessage = "Request #$request_id not found.";
        } elseif (!in_array($row['status'], ['scheduled','in_progress'])) {
            $errorMessage = "Request #$request_id cannot be processed — status: " . htmlspecialchars($row['status']);
        } else {
            try {
                $filePath = null;
                if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $dir = "uploads/lab_results/";
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['file']['name']));
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $safeName)) {
                        $filePath = $dir . $safeName;
                    }
                }

                $newStatus = $keepOpen ? 'in_progress' : 'completed';

                $conn->prepare("UPDATE exam_requests SET status = ? WHERE id = ?")
                     ->execute([$newStatus, $request_id]);

                $conn->prepare("
                    INSERT INTO exam_results (request_id, result, file_path, is_critical)
                    VALUES (?, ?, ?, 0)
                ")->execute([$request_id, $notes, $filePath]);

                if ($newStatus === 'completed') {
                    $conn->prepare("
                        UPDATE appointments SET status = 'Completed'
                        WHERE request_id = ? AND status IN ('Scheduled', 'Waiting', 'In Progress')
                    ")->execute([$request_id]);
                }

                $successMessage = "Result for Request #$request_id submitted and " .
                                  ($keepOpen ? 'marked as In Progress' : 'completed') . ".";

            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        }
    }
}

/* ════════════════════════════════════════════════════
   LOAD: Lab requests with filters
════════════════════════════════════════════════════ */
$today = date('Y-m-d');
$dateFilter = $_GET['date'] ?? 'today';
$statusFilter = $_GET['status'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

$sqlCalendar = "
    SELECT er.id, er.urgency, er.exam_type, er.status, er.created_at,
           p.full_name AS patient_name,
           u.full_name AS doctor_name,
           a.payment_status,
           a.queue_number,
           a.appointment_date,
           a.appointment_time,
           eres.result, eres.file_path, eres.is_critical, eres.created_at as result_date
    FROM exam_requests er
    JOIN patients p ON p.id = er.patient_id
    LEFT JOIN users u ON u.id = er.doctor_id
    JOIN appointments a ON a.request_id = er.id
    LEFT JOIN exam_results eres ON eres.request_id = er.id
    WHERE er.department = 'laboratory'
      AND a.payment_status = 'Paid'
      AND er.status != 'completed'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
";

$stmtCalendar = $conn->prepare($sqlCalendar);
$stmtCalendar->execute();
$allRequestsForCalendar = $stmtCalendar->fetchAll(PDO::FETCH_ASSOC);
$appointmentsByDate = [];
foreach ($allRequestsForCalendar as $req) {
    $date = $req['appointment_date'];
    if (!isset($appointmentsByDate[$date])) {
        $appointmentsByDate[$date] = [];
    }
    $appointmentsByDate[$date][] = $req;
}

// ============================================================
// 2. QUERY FOR TABLE 
// ============================================================
$sqlTable = "
    SELECT er.id, er.urgency, er.exam_type, er.status, er.created_at,
           p.full_name AS patient_name,
           u.full_name AS doctor_name,
           a.payment_status,
           a.queue_number,
           a.appointment_date,
           a.appointment_time,
           eres.result, eres.file_path, eres.is_critical, eres.created_at as result_date
    FROM exam_requests er
    JOIN patients p ON p.id = er.patient_id
    LEFT JOIN users u ON u.id = er.doctor_id
    JOIN appointments a ON a.request_id = er.id
    LEFT JOIN exam_results eres ON eres.request_id = er.id
    WHERE er.department = 'laboratory'
      AND a.payment_status = 'Paid'
";

$params = [];

// Date filter
if ($dateFilter === 'today') {
    $sqlTable .= " AND DATE(a.appointment_date) = CURDATE()";
} elseif ($dateFilter === 'tomorrow') {
    $sqlTable .= " AND DATE(a.appointment_date) = CURDATE() + INTERVAL 1 DAY";
} elseif ($dateFilter === 'week') {
    $sqlTable .= " AND YEARWEEK(a.appointment_date) = YEARWEEK(CURDATE())";
} elseif ($dateFilter && $dateFilter !== 'all' && $dateFilter !== 'today' && $dateFilter !== 'tomorrow' && $dateFilter !== 'week') {
    $sqlTable .= " AND DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
}

// Status filter 
if (empty($statusFilter) || $statusFilter === 'all') {
    $sqlTable .= " AND er.status != 'completed'";
} else {
    // (scheduled, in_progress, completed)
    $sqlTable .= " AND er.status = ?";
    $params[] = $statusFilter;
}

// Search filter
if ($searchTerm) {
    $sqlTable .= " AND (p.full_name LIKE ? OR a.queue_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$sqlTable .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmtTable = $conn->prepare($sqlTable);
$stmtTable->execute($params);
$labRequests = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

/* Stats - TODAY only */
$pendingToday = $conn->query("
    SELECT COUNT(*) FROM exam_requests er
    JOIN appointments a ON a.request_id = er.id
    WHERE er.department='laboratory' 
      AND er.status='scheduled' 
      AND a.payment_status='Paid'
      AND DATE(a.appointment_date) = CURDATE()
")->fetchColumn();

$inProgressToday = $conn->query("
    SELECT COUNT(*) FROM exam_requests er
    JOIN appointments a ON a.request_id = er.id
    WHERE er.department='laboratory' 
      AND er.status='in_progress' 
      AND a.payment_status='Paid'
      AND DATE(a.appointment_date) = CURDATE()
")->fetchColumn();

$completedToday = $conn->query("
    SELECT COUNT(*) FROM exam_requests er
    JOIN appointments a ON a.request_id = er.id
    WHERE er.department='laboratory' 
      AND er.status='completed'
      AND a.payment_status='Paid'
      AND DATE(er.created_at)=CURDATE()
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Dashboard - CardioCare</title>
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
        
        .alert {
            padding: .9rem 1.1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: .875rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            transition: opacity 0.5s ease;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.25rem; margin-bottom: 1.75rem; }
        .stat-card { background:#fff; border-radius:12px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .stat-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
        .stat-icon.blue  { background:#dbeafe; color:#1d4ed8; }
        .stat-icon.amber { background:#fef3c7; color:#b45309; }
        .stat-icon.green { background:#dcfce7; color:#15803d; }
        .stat-details h3 { font-size:1.6rem; font-weight:700; margin:0; }
        .stat-details p  { font-size:.78rem; color:#6b7280; margin:0; }

        .calendar-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 0;
        }
        .calendar-header {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .calendar-header h3 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .calendar-nav {
            display: flex;
            gap: 0.3rem;
            align-items: center;
        }
        .calendar-nav button {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.2rem 0.5rem;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.2s;
        }
        .calendar-nav button:hover {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        .current-month {
            font-weight: 600;
            font-size: 0.8rem;
            min-width: 120px;
            text-align: center;
        }
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .calendar-weekday {
            padding: 0.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.7rem;
            color: #6b7280;
        }
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .calendar-day {
            min-height: 90px;
            border-right: 1px solid #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
            padding: 0.3rem;
            background: white;
        }
        .calendar-day:hover {
            background: #fafafa;
        }
        .calendar-day.empty {
            background: #f9fafb;
        }
        .day-number {
            font-size: 0.7rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.3rem;
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            border-radius: 50%;
        }
        .day-number.today {
            background: #10b981;
            color: white;
        }
        .day-request {
            background: #dbeafe;
            border-left: 3px solid #3b82f6;
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 3px;
            font-size: 0.6rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .day-request:hover {
            background: #bfdbfe;
        }
        .request-time {
            font-weight: 700;
            color: #1e40af;
        }
        .request-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 0.55rem;
            font-weight: 600;
            margin-left: 4px;
        }
        .badge-stat { background:#fee2e2; color:#dc2626; }
        .badge-urgent { background:#fef3c7; color:#b45309; }
        .badge-routine { background:#dcfce7; color:#16a34a; }

        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.6rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        .form-card h3 { 
            margin: 0 0 0.6rem; 
            font-size: 0.85rem; 
            font-weight: 600; 
        }
        .fg { margin-bottom: 1.2rem; }
        .fg label { 
            display: block; 
            font-size: 0.8rem; 
            font-weight: 600; 
            margin-bottom: 0.35rem; 
            color: #374151;
        }
        .fg select, .fg textarea { 
            width: 100%; 
            padding: 0.75rem 0.85rem; 
            border: 1px solid #d1d5db; 
            border-radius: 10px; 
            font-family: inherit; 
            font-size: 0.9rem;
        }
        .fg textarea { 
            resize: vertical;
            font-size: 0.9rem;
            height: 130px;
            line-height: 1.4;
        }
        .file-wrap { 
            border: 1px dashed #d1d5db; 
            border-radius: 10px; 
            padding: 1rem; 
            text-align: center; 
            cursor: pointer; 
            transition: all .2s; 
        }
        .file-wrap:hover { 
            border-color: #2563eb; 
            background: #f8fafc; 
        }
        .file-wrap input[type=file] { display: none; }
        .file-name { font-size: 0.6rem; color: #6b7280; margin-top: 0.15rem; }
        .sel-info { 
            background: #eff6ff; 
            border: 1px solid #bfdbfe; 
            border-radius: 5px; 
            padding: 0.3rem 0.5rem; 
            font-size: 0.65rem; 
            color: #1d4ed8; 
            margin-bottom: 0.5rem; 
            display: none; 
        }
        .sel-info.show { display: block; }
        .chk-row { 
            display: flex; 
            align-items: center; 
            gap: 0.3rem; 
            cursor: pointer; 
            font-size: 0.75rem;
            margin-top: 0.2rem;
        }
        .btn-submit { 
            width: 100%; 
            padding: 0.75rem; 
            background: #2563eb; 
            color: #fff; 
            border: none; 
            border-radius: 10px; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { background: #1d4ed8; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 1rem;
            border-radius: 12px;
            width: 350px;
            max-width: 90%;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .modal-header h4 {
            margin: 0;
            font-size: 0.85rem;
        }
        .close-modal {
            cursor: pointer;
            font-size: 0.9rem;
            color: #9ca3af;
        }
        .close-modal:hover {
            color: #ef4444;
        }
        .modal-detail {
            margin-bottom: 0.4rem;
            font-size: 0.75rem;
        }
        .modal-detail strong {
            display: inline-block;
            width: 70px;
            color: #6b7280;
        }
        
        .page-header { margin-bottom:1rem; }
        .page-header h2 { margin:0 0 0.2rem; font-size:1.2rem; }
        .page-header p { font-size:0.75rem; color:#6b7280; margin:0; }
        
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: 1fr 350px; 
            gap: 1rem; 
        }
        
        .requests-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            margin-top: 1.5rem;
        }
        .table-header {
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .table-header h3 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .table-filters {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.2rem 0.5rem;
        }
        .filter-item i {
            color: #9ca3af;
            font-size: 0.7rem;
        }
        .filter-item select, .filter-item input {
            border: none;
            outline: none;
            padding: 0.3rem;
            font-size: 0.7rem;
            background: transparent;
            width: 110px;
        }
        .filter-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-btn:hover {
            background: #059669;
        }
        .reset-filters {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .reset-filters:hover {
            background: #4b5563;
        }
        .table-search {
            position: relative;
        }
        .table-search input {
            padding: 0.3rem 0.3rem 0.3rem 2rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.7rem;
            width: 180px;
            background: white;
        }
        .table-search input:focus {
            outline: none;
            border-color: #10b981;
        }
        .table-search i {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.7rem;
            pointer-events: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b7280;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        td {
            padding: 0.6rem 0.8rem;
            font-size: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .queue-badge {
            background: #eef2ff;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-inprogress { background: #fef3c7; color: #92400e; }
        .status-completed { background: #dcfce7; color: #166534; }
        .action-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            cursor: pointer;
        }
        .action-btn:hover {
            background: #2563eb;
        }
        .file-link {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.65rem;
            color: #3b82f6;
            text-decoration: none;
            margin-top: 3px;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .result-preview {
            font-size: 0.65rem;
            color: #4b5563;
            margin-top: 3px;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (max-width: 900px) {
            .dashboard-grid { 
                grid-template-columns: 1fr; 
            }
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .table-filters {
                width: 100%;
                justify-content: flex-start;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
    <div class="sidebar-header"><div class="logo"><i class="fa-solid fa-heart-pulse"></i><span>CardioCare</span></div></div>
    <ul class="sidebar-nav">
        <li><a href="laboratory-dashboard.php" class="nav-item active"><i class="fa-solid fa-flask-vial"></i> Lab Requests</a></li>
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
            <img src="<?= htmlspecialchars($userAvatar) ?>" class="avatar">
        </div>
    </header>

    <div class="dashboard-content">
        <div class="page-header">
            <h2>Laboratory Operations</h2>
            <p>Process lab requests and upload results.</p>
        </div>

        <!-- MESSAGES WITH AUTO-HIDE (FIXED) -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fa-solid fa-circle-check"></i> 
                <?= htmlspecialchars($successMessage) ?>
                <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#166534; cursor:pointer; margin-left:auto; font-size:1.2rem;">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fa-solid fa-circle-xmark"></i> 
                <?= htmlspecialchars($errorMessage) ?>
                <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#991b1b; cursor:pointer; margin-left:auto; font-size:1.2rem;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- STATS CARDS - Today only -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="stat-details">
                    <h3><?= $pendingToday ?></h3>
                    <p>Pending Tests Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-details">
                    <h3><?= $inProgressToday ?></h3>
                    <p>In Progress Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
                <div class="stat-details">
                    <h3><?= $completedToday ?></h3>
                    <p>Completed Today</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- CALENDAR VIEW -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3><i class="fa-solid fa-calendar-alt"></i> Lab Schedule</h3>
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

            <!-- SUBMIT FORM -->
            <div class="form-card">
                <h3><i class="fa-solid fa-flask-vial"></i> Submit Lab Result</h3>
                <div class="sel-info" id="selInfo">Click on a lab request in the calendar or table</div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="fg">
                        <label>Select Request</label>
                        <select name="request_id" id="reqSelect" required>
                            <option value="" disabled selected>Select a request…</option>
                            <?php foreach ($labRequests as $q): ?>
                                <option value="<?= $q['id'] ?>">
                                    <?= date('M d', strtotime($q['appointment_date'])) ?> - <?= htmlspecialchars($q['patient_name']) ?> (<?= $q['exam_type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Attach File</label>
                        <div class="file-wrap" onclick="document.getElementById('labFile').click()">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p style="margin:0; font-size:0.6rem;">Click to browse</p>
                            <input type="file" id="labFile" name="file"
                                   accept=".pdf,.jpg,.jpeg,.png,.xlsx,.csv"
                                   onchange="showFn(this)">
                            <div class="file-name" id="labFn">No file chosen</div>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Notes / Results</label>
                        <textarea name="notes" rows="2" 
                            placeholder="Enter lab results, values, or notes…"></textarea>
                    </div>
                    <div class="fg">
                        <label class="chk-row">
                            <input type="checkbox" name="keep_open">
                            <span>Keep as In Progress (don't complete)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit Result
                    </button>
                </form>
            </div>
        </div>

        <!-- REQUESTS TABLE -->
        <div class="requests-table">
            <div class="table-header">
                <h3><i class="fa-solid fa-list"></i> Lab Requests</h3>
                <div class="table-filters">
                    <div class="filter-item">
                        <i class="fa-solid fa-calendar"></i>
                        <select id="filterDate">
                            <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <option value="tomorrow" <?= $dateFilter === 'tomorrow' ? 'selected' : '' ?>>Tomorrow</option>
                            <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <i class="fa-solid fa-chart-simple"></i>
                        <select id="filterStatus">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Patient or Queue..." value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <button class="filter-btn" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a href="laboratory-dashboard.php" class="reset-filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
                <div class="table-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="tableSearch" placeholder="Search in table...">
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Patient</th>
                            <th>Test Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Result / File</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <?php if (empty($labRequests)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:1.5rem; color:#9ca3af;">
                                    <i class="fa-solid fa-flask" style="font-size:1.5rem; display:block; margin-bottom:0.5rem;"></i>
                                    No lab requests found
                                  </div>
                            </tr>
                        <?php else: foreach ($labRequests as $req): ?>
                            <tr>
                                <td><span class="queue-badge"><?= htmlspecialchars($req['queue_number']) ?></span></td>
                                <td><strong><?= htmlspecialchars($req['patient_name']) ?></strong></td>
                                <td><?= htmlspecialchars($req['exam_type']) ?></div>
                                <td><?= date('M d, Y', strtotime($req['appointment_date'])) ?></div>
                                <td><?= date('h:i A', strtotime($req['appointment_time'])) ?></div>
                                <td>
                                    <?php if ($req['urgency'] === 'stat'): ?>
                                        <span class="badge-stat">STAT</span>
                                    <?php elseif ($req['urgency'] === 'urgent'): ?>
                                        <span class="badge-urgent">Urgent</span>
                                    <?php else: ?>
                                        <span class="badge-routine">Routine</span>
                                    <?php endif; ?>
                                  </div>
                                <td>
                                    <?php if ($req['status'] === 'scheduled'): ?>
                                        <span class="status-badge status-scheduled">Pending</span>
                                    <?php elseif ($req['status'] === 'in_progress'): ?>
                                        <span class="status-badge status-inprogress">In Progress</span>
                                    <?php else: ?>
                                        <span class="status-badge status-completed">Completed</span>
                                    <?php endif; ?>
                                  </div>
                                <td>
                                    <?php if ($req['result']): ?>
                                        <div class="result-preview" title="<?= htmlspecialchars($req['result']) ?>">
                                            <?= htmlspecialchars(substr($req['result'], 0, 40)) ?>...
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($req['file_path']): ?>
                                        <a href="<?= htmlspecialchars($req['file_path']) ?>" target="_blank" class="file-link">
                                            <i class="fa-solid fa-file-pdf"></i> View File
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$req['result'] && !$req['file_path']): ?>
                                        <span style="color:#9ca3af; font-size:0.65rem;">No result yet</span>
                                    <?php endif; ?>
                                  </div>
                                <td>
                                    <?php if ($req['status'] !== 'completed'): ?>
                                        <button class="action-btn" onclick="selectRequest(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['patient_name'])) ?>', '<?= htmlspecialchars(addslashes($req['exam_type'])) ?>', '<?= $req['queue_number'] ?>')">
                                            <i class="fa-solid fa-flask"></i> Process
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#10b981; font-size:0.65rem;"><i class="fa-solid fa-check-circle"></i> Done</span>
                                    <?php endif; ?>
                                  </div>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Request Detail Modal -->
<div id="requestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fa-solid fa-flask"></i> Lab Request Details</h4>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalDetails"></div>
        <div style="margin-top: 0.75rem; text-align: center;">
            <button onclick="processFromModal()" id="modalProcessBtn" class="btn-submit" style="background:#10b981;">Process Request</button>
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

const requestsByDate = <?php echo json_encode($appointmentsByDate); ?>;
let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let selectedRequest = null;

function renderCalendar() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    let startingDay = firstDay.getDay();
    const totalDays = lastDay.getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('currentMonthDisplay').innerText = monthNames[currentMonth] + ' ' + currentYear;
    
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    for (let i = 0; i < startingDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarDays.appendChild(emptyDay);
    }
    
    const today = new Date();
    const todayDate = today.getDate();
    const todayMonth = today.getMonth();
    const todayYear = today.getFullYear();
    
    for (let day = 1; day <= totalDays; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        
        const dateStr = currentYear + '-' + String(currentMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.innerText = day;
        if (todayYear === currentYear && todayMonth === currentMonth && todayDate === day) {
            dayNumber.classList.add('today');
        }
        dayCell.appendChild(dayNumber);
        
        if (requestsByDate[dateStr] && requestsByDate[dateStr].length > 0) {
            requestsByDate[dateStr].forEach(req => {
                const reqDiv = document.createElement('div');
                reqDiv.className = 'day-request';
                
                let badgeClass = '';
                let badgeText = '';
                if (req.urgency === 'stat') {
                    badgeClass = 'badge-stat';
                    badgeText = 'STAT';
                } else if (req.urgency === 'urgent') {
                    badgeClass = 'badge-urgent';
                    badgeText = 'Urgent';
                } else {
                    badgeClass = 'badge-routine';
                    badgeText = 'Routine';
                }
                
                const timeDisplay = req.appointment_time ? req.appointment_time.substring(0,5) : 'TBD';
                
                reqDiv.innerHTML = `
                    <span class="request-time">${timeDisplay}</span>
                    <span class="request-badge ${badgeClass}">${badgeText}</span>
                `;
                
                reqDiv.title = req.patient_name + ' - ' + req.exam_type;
                reqDiv.onclick = (function(r) { return function() { showRequestDetails(r); }; })(req);
                dayCell.appendChild(reqDiv);
            });
        }
        calendarDays.appendChild(dayCell);
    }
    
    const totalCells = calendarDays.children.length;
    const remainingCells = 42 - totalCells;
    for (let i = 0; i < remainingCells; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarDays.appendChild(emptyDay);
    }
}

function showRequestDetails(req) {
    selectedRequest = req;
    const modal = document.getElementById('requestModal');
    const modalDetails = document.getElementById('modalDetails');
    const processBtn = document.getElementById('modalProcessBtn');
    
    const timeDisplay = req.appointment_time ? req.appointment_time.substring(0,5) : 'TBD';
    const isInProgress = req.status === 'in_progress';
    
    modalDetails.innerHTML = `
        <div class="modal-detail"><strong>Patient:</strong> ${escapeHtml(req.patient_name)}</div>
        <div class="modal-detail"><strong>Test:</strong> ${escapeHtml(req.exam_type)}</div>
        <div class="modal-detail"><strong>Date:</strong> ${req.appointment_date}</div>
        <div class="modal-detail"><strong>Time:</strong> ${timeDisplay}</div>
        <div class="modal-detail"><strong>Queue:</strong> ${req.queue_number || 'N/A'}</div>
        <div class="modal-detail"><strong>Doctor:</strong> Dr. ${escapeHtml(req.doctor_name || 'N/A')}</div>
        <div class="modal-detail"><strong>Urgency:</strong> ${req.urgency.toUpperCase()}</div>
        <div class="modal-detail"><strong>Status:</strong> <span style="color:${isInProgress ? '#f59e0b' : '#10b981'}">${req.status === 'in_progress' ? 'In Progress' : 'Ready'}</span></div>
    `;
    
    processBtn.innerText = isInProgress ? 'Continue Processing' : 'Process Request';
    processBtn.style.background = isInProgress ? '#f59e0b' : '#10b981';
    modal.style.display = 'block';
}

function processFromModal() {
    if (selectedRequest) {
        selectRequest(selectedRequest.id, selectedRequest.patient_name, selectedRequest.exam_type, selectedRequest.queue_number);
        closeModal();
    }
}

function selectRequest(id, name, test, queue) {
    const sel = document.getElementById('reqSelect');
    sel.value = id;
    document.getElementById('selInfo').innerHTML = `<i class="fa-solid fa-info-circle"></i> Queue: ${queue} | Patient: ${name} | Test: ${test}`;
    document.getElementById('selInfo').classList.add('show');
    document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeModal() {
    document.getElementById('requestModal').style.display = 'none';
    selectedRequest = null;
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    } else if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    renderCalendar();
}

function goToToday() {
    currentDate = new Date();
    currentMonth = currentDate.getMonth();
    currentYear = currentDate.getFullYear();
    renderCalendar();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showFn(input) {
    document.getElementById('labFn').textContent = input.files.length ? input.files[0].name : 'No file chosen';
}

function applyFilters() {
    const date = document.getElementById('filterDate').value;
    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('searchInput').value;
    let url = 'laboratory-dashboard.php?';
    if (date) url += 'date=' + date + '&';
    if (status && status !== 'all') url += 'status=' + status + '&';
    if (search) url += 'search=' + encodeURIComponent(search);
    window.location.href = url;
}

function resetFilters() {
    window.location.href = 'laboratory-dashboard.php';
}

// Table search (live filtering)
document.getElementById('tableSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    const rows = document.querySelectorAll('#requestsTableBody tr');
    rows.forEach(row => {
        if (row.cells && row.cells.length > 1) {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        }
    });
});

// Enter key on search input
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

window.onclick = function(event) {
    const modal = document.getElementById('requestModal');
    if (event.target === modal) closeModal();
}

document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});

// ============================================
//Auto-hide BOTH success AND error messages
// ============================================
function autoHideMessages() {
    // Success message - hides after 5 seconds
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => {
                if (successAlert) successAlert.style.display = 'none';
            }, 500);
        }, 5000);
    }
    
    // Error message - hides after 8 seconds
    const errorAlert = document.getElementById('errorAlert');
    if (errorAlert) {
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 0.5s';
            errorAlert.style.opacity = '0';
            setTimeout(() => {
                if (errorAlert) errorAlert.style.display = 'none';
            }, 500);
        }, 8000);
    }
}

// Call the function when page loads
autoHideMessages();
</script>
</body>
</html>