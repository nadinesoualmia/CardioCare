<?php
//bah el admin ykhazen ma3lomato f session w ykhdem bihom f dashboard
session_start();
include 'backend/connection.php';

//lakan ma3andekch session wla ma3andekch name f session, raja3 login
if (!isset($_SESSION['name'])) {
    header("Location: login.php");
    exit();
}
//yakhzen ma3lomato li fi session f variables local bach yst3mlhom f dashboard
$userName   = $_SESSION['name'];
$userRole   = $_SESSION['role'];
$userAvatar = $_SESSION['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=2563eb&color=fff";
//lkan ma3andekch avatar f session, ydir default avatar m3a isem el user w background blue
//lkan drna try catch, w kan 3andna moshkla f connection.php, rah ydir default values 0 f dashboard w mayt3atich error
try {
    //lhna n7sbou ba3d statistiques li n7ebou n'affichiw f dashboard, kif total patients, active doctors, etc.
    $totalPatients      = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?? 0;
    $activeDoctors      = $conn->query("SELECT COUNT(*) FROM users WHERE role='Doctor' AND isActive=1")->fetchColumn() ?? 0;
    $inactiveUsers      = $conn->query("SELECT COUNT(*) FROM users WHERE isActive=0")->fetchColumn() ?? 0;
    $activeUsers        = $conn->query("SELECT COUNT(*) FROM users WHERE isActive=1")->fetchColumn() ?? 0;
    $totalStaff         = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('Nurse', 'Laboratory', 'Radiology', 'Receptionist') AND isActive=1")->fetchColumn() ?? 0;
} catch (PDOException $e) {
    $totalPatients = $activeDoctors = $inactiveUsers = $activeUsers = $totalStaff = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - CardioCare</title>
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


.main-content {
    margin-left: 260px;
    min-height: 100vh;
    background: #f1f5f9;
}


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


.dashboard-content {
    padding: 24px;
}

.page-header {
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
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

.btn-add-patient {
    background: #10b981;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-patient:hover {
    background: #059669;
    transform: translateY(-1px);
}


.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.primary { background: #dbeafe; color: #2563eb; }
.stat-icon.warning { background: #fef3c7; color: #d97706; }
.stat-icon.info { background: #dbeafe; color: #3b82f6; }
.stat-icon.success { background: #d1fae5; color: #059669; }
.stat-icon.danger { background: #fee2e2; color: #dc2626; }

.stat-details {
    flex: 1;
}

.stat-details h3 {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}

.stat-details p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    font-weight: 500;
}

.stat-details small {
    font-size: 11px;
    color: #9ca3af;
    display: block;
    margin-top: 4px;
}


@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
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
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .dashboard-content {
        padding: 16px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- lhna n7otou script li ykhdem toggle l sidebar f mobile view -->
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
</script>

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
        <li><a href="admin-dashboard.php" class="nav-item active">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a></li>
        <li><a href="admin-users.php" class="nav-item">
            <i class="fa-solid fa-users-cog"></i> Manage Users
        </a></li>
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
        <button class="toggle-sidebar-btn">
            <i class="fa-solid fa-bars"></i>
        </button>

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
            <div>
                <h2>Admin Overview</h2>
                <p>Welcome back, <?= htmlspecialchars($userName) ?></p>
            </div>
            <a href="admin-users.php" class="btn-add-patient">
                <i class="fa-solid fa-user-plus"></i> Add User
            </a>
        </div>

        <div class="stats-grid">
            <!-- Patients Card -->
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
                <div class="stat-details">
                    <h3><?= $totalPatients ?></h3>
                    <p>Patients</p>
                </div>
            </div>

            <!-- Active Doctors Card -->
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fa-solid fa-user-doctor"></i></div>
                <div class="stat-details">
                    <h3><?= $activeDoctors ?></h3>
                    <p>Active Doctors</p>
                </div>
            </div>

            <!-- Total Staff Card -->
            <div class="stat-card">
                <div class="stat-icon info"><i class="fa-solid fa-user-nurse"></i></div>
                <div class="stat-details">
                    <h3><?= $totalStaff ?></h3>
                    <p>Active Staff</p>
                    <small>Nurse, Lab, Radio, Receptionist</small>
                </div>
            </div>

            <!-- Active Users Card -->
            <div class="stat-card">
                <div class="stat-icon success"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-details">
                    <h3><?= $activeUsers ?></h3>
                    <p>Active Users</p>
                </div>
            </div>

            <!-- Inactive Users Card -->
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fa-solid fa-user-slash"></i></div>
                <div class="stat-details">
                    <h3><?= $inactiveUsers ?></h3>
                    <p>Inactive Users</p>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>