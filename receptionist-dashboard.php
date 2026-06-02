<?php
session_start();
include 'backend/connection.php';

//  Role guard — redirect to login if session is missing or wrong role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Receptionist') {
    header("Location: login.php");
    exit();
}

$userName   = $_SESSION['name']   ?? 'Receptionist';
$userRole   = $_SESSION['role']   ?? 'Receptionist';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=R&background=10b981&color=fff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - CardioCare</title>
     <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/receptionist.css">
    
    <style>
       
        
        /*  push right */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        /*  ensure fixed position */
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
        
        /* Alert styles with auto-hide animation */
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
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CardioCare</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li><a href="receptionist-dashboard.php" class="nav-item active"><i class="fa-solid fa-user-plus"></i> Add Patient</a></li>
        <li><a href="patients.php"               class="nav-item"><i class="fa-solid fa-users"></i> Patients</a></li>
        <li><a href="book-appointment.php"        class="nav-item"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a></li>
        <li><a href="appointments.php"            class="nav-item"><i class="fa-solid fa-calendar-check"></i> Appointments</a></li>
        <li><a href="billing.php"                 class="nav-item"><i class="fa-solid fa-receipt"></i> Billing</a></li>
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
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" class="avatar" alt="avatar">
        </div>
    </header>

    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h2>Reception Desk</h2>
                <p>Manage appointments and patient intake.</p>
            </div>
        </div>

        <!-- Flash message from register -->
        <div id="flashMsg" style="display:none;" class="alert"></div>

        <div class="registration-container">
            <div class="card">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-plus text-primary"></i> Patient Registration
                </h3>

                <form id="receptionForm">
                    <h4 class="section-subtitle">Patient Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="pFullName" name="fullName" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" id="pPhone" name="phone"
                                   placeholder="05/06/07... or +213..."
                                   pattern="^(05|06|07)\d{8}$|^(\+213)(5|6|7)\d{8}$"
                                   title="Must be a valid Algerian number" required>
                        </div>
                        <div class="form-group">
                            <label>Emergency Contact <span class="optional">(Optional)</span></label>
                            <input type="tel" id="pEmergency" name="emergency"
                                   placeholder="05/06/07... or +213..."
                                   pattern="^(05|06|07)\d{8}$|^(\+213)(5|6|7)\d{8}$"
                                   title="Must be a valid Algerian number">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select id="pGender" name="gender" required>
                                <option value="" disabled selected hidden>Select Gender...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="optional">(Optional)</span></label>
                            <input type="email" id="pEmail" name="email">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Algerian National ID (NIN) <span class="optional">(Optional)</span></label>
                            <input type="text" id="pCN" name="nin" placeholder="18 digits..." pattern="\d{18}">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" id="pDOB" name="dob" required>
                        </div>
                        <div class="form-group">
                            <label>Address <span class="optional">(Optional)</span></label>
                            <textarea id="pAddress" name="address" rows="1"></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-user-check"></i> Register Patient
                        </button>
                    </div>
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

// Function to show flash message with auto-hide
function showFlashMessage(message, type = 'success') {
    const flashDiv = document.getElementById('flashMsg');
    if (flashDiv) {
        flashDiv.className = 'alert alert-' + type;
        flashDiv.innerHTML = '<i class="fa-solid fa-circle-' + (type === 'success' ? 'check' : 'exclamation') + '"></i> ' + message;
        flashDiv.style.display = 'flex';
        
        // Auto hide after 4 seconds
        setTimeout(function() {
            flashDiv.style.animation = 'slideOut 0.3s ease';
            setTimeout(function() {
                flashDiv.style.display = 'none';
                flashDiv.style.animation = '';
            }, 300);
        }, 4000);
    }
}
</script>

<script src="js/receptionist.js"></script>
</body>
</html>