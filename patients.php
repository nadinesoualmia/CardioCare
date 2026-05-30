<?php
session_start();
include 'backend/connection.php';

// ✅ Role guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Receptionist') {
    header("Location: login.php");
    exit();
}

$userName   = $_SESSION['name']   ?? 'Receptionist';
$userRole   = $_SESSION['role']   ?? 'Receptionist';
$userAvatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=R&background=10b981&color=fff';

// ─── EDIT SUBMIT ────────────────────────────────────────────────────────────
// NIN is NOT included in update - it's set once during registration only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("
        UPDATE patients
        SET full_name=?, phone=?, emergency_contact=?, gender=?, dob=?, email=?, address=?
        WHERE id=?
    ");
    $stmt->execute([
        trim($_POST['full_name']),
        trim($_POST['phone']),
        trim($_POST['emergency_contact']),
        $_POST['gender'],
        $_POST['dob'],
        trim($_POST['email']),
        trim($_POST['address']),
        $_POST['edit_id']
    ]);
    header("Location: patients.php?msg=updated");
    exit();
}

// ─── SOFT DELETE ─────────────────────────────────────────────────────────────
if (isset($_GET['deactivate'])) {
    $pid = (int)$_GET['deactivate'];
    $check = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $check->execute([$pid]);
    $hasHistory = (int)$check->fetchColumn() > 0;

    if ($hasHistory) {
        $stmt = $conn->prepare("UPDATE patients SET isActive = 0 WHERE id = ?");
        $stmt->execute([$pid]);
        header("Location: patients.php?msg=deactivated");
    } else {
        $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$pid]);
        header("Location: patients.php?msg=deleted");
    }
    exit();
}

// ─── REACTIVATE ───────────────────────────────────────────────────────────────
if (isset($_GET['activate'])) {
    $stmt = $conn->prepare("UPDATE patients SET isActive = 1 WHERE id = ?");
    $stmt->execute([(int)$_GET['activate']]);
    header("Location: patients.php?msg=activated");
    exit();
}

// ─── FETCH ALL PATIENTS ───────────────────────────────────────────────────────
$patients = $conn->query("
    SELECT * FROM patients ORDER BY COALESCE(isActive,1) DESC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$flashMap = [
    'updated'     => ['success', 'Patient updated successfully.'],
    'deactivated' => ['warning', 'Patient deactivated (has history). They can be reactivated later.'],
    'deleted'     => ['success', 'Patient deleted successfully.'],
    'activated'   => ['success', 'Patient reactivated successfully.'],
];
$flash = $flashMap[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patients - CardioCare</title>
 <link rel="icon" type="image/png" href="heart.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/tables.css">
<link rel="stylesheet" href="css/forms.css">
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

.sidebar-footer .logout-btn,
.sidebar-footer .logout-btn:link,
.sidebar-footer .logout-btn:visited,
.sidebar-footer .logout-btn:hover,
.sidebar-footer .logout-btn:active,
.sidebar-footer .logout-btn:focus {
    background-color: #ef4444 !important;
    color: white !important;
    text-decoration: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    padding: 10px 16px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    transition: background-color 0.2s !important;
}

.sidebar-footer .logout-btn:hover {
    background-color: #dc2626 !important;
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

/* Table styles */
.table-container {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
    gap: .4rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: .35rem .7rem;
}

.table-search input {
    border: none;
    background: transparent;
    outline: none;
    font-size: .84rem;
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

/* Badges */
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

/* Buttons */
.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    margin: 0 3px;
    font-size: 1rem;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    transition: all 0.2s;
}
.btn-icon.edit { color: #3b82f6; }
.btn-icon.edit:hover { background: #eff6ff; }
.btn-icon.deactivate { color: #dc2626; }
.btn-icon.deactivate:hover { background: #fef2f2; }
.btn-icon.activate { color: #16a34a; }
.btn-icon.activate:hover { background: #f0fdf4; }

/* Alerts */
.alert {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.alert-warning { background: #fef9c3; border: 1px solid #fde047; color: #854d0e; }

/* Modal */
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
    margin: 5% auto;
    padding: 2rem;
    border-radius: 12px;
    width: 600px;
    max-width: 95%;
    position: relative;
}
.close-modal {
    position: absolute;
    top: 1rem;
    right: 1rem;
    cursor: pointer;
    font-size: 1.2rem;
    color: #64748b;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
}
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
.text-danger { color: #dc2626; }

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
    
    .modal-box {
        margin: 10% auto;
        padding: 1.5rem;
    }
    
    .form-grid-2 {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
    <div class="sidebar-header"><div class="logo"><i class="fa-solid fa-heart-pulse"></i><span>CardioCare</span></div></div>
    <ul class="sidebar-nav">
        <li><a href="receptionist-dashboard.php" class="nav-item"><i class="fa-solid fa-user-plus"></i> Add Patient</a></li>
        <li><a href="patients.php"               class="nav-item active"><i class="fa-solid fa-users"></i> Patients</a></li>
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

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash[0]; ?>">
                <i class="fa-solid fa-circle-<?php echo $flash[0] === 'success' ? 'check' : 'info'; ?>"></i>
                <?php echo htmlspecialchars($flash[1]); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fa-solid fa-users"></i> All Patients</h3>
                <div class="table-search">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search patients...">
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Emergency</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="patientTable">
                <?php foreach ($patients as $i => $p):
                    $isActive = isset($p['isActive']) ? (int)$p['isActive'] : 1;
                ?>
                <tr class="<?php echo $isActive ? '' : 'inactive-row'; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($p['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['phone'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['emergency_contact'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['gender'] ?? '-'); ?></td>
                    <td><?php echo !empty($p['dob']) ? date('M d, Y', strtotime($p['dob'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($p['email'] ?? '-'); ?></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="badge badge-active"><i class="fa-solid fa-circle-check"></i> Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
                        <?php endif; ?>
                     </div>
                    <td>
                        <button class="btn-icon edit" title="Edit" onclick='openEdit(<?php echo json_encode($p); ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <?php if ($isActive): ?>
                            <a href="patients.php?deactivate=<?php echo $p['id']; ?>"
                               class="btn-icon deactivate" title="Deactivate / Delete"
                               onclick="return confirm('Remove this patient? If they have history, they will be deactivated instead of deleted.')">
                                <i class="fa-solid fa-user-slash"></i>
                            </a>
                        <?php else: ?>
                            <a href="patients.php?activate=<?php echo $p['id']; ?>"
                               class="btn-icon activate" title="Reactivate patient"
                               onclick="return confirm('Reactivate this patient?')">
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
</main>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></span>
        <h3><i class="fa-solid fa-user-pen text-primary"></i> Edit Patient</h3>
        <form method="POST" onsubmit="return validateEditForm()">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-grid-2">
                <div class="form-group" style="grid-column:span 2;">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact" id="edit_emergency" 
                           placeholder="05/06/07... or +213..."
                           pattern="^(05|06|07)\d{8}$|^(\+213)(5|6|7)\d{8}$">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="edit_gender">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" id="edit_dob" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" rows="2"></textarea>
                </div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex:1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
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

document.getElementById('searchInput').addEventListener('input', function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#patientTable tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
});

function openEdit(p) {
    document.getElementById('edit_id').value        = p.id;
    document.getElementById('edit_full_name').value = p.full_name;
    document.getElementById('edit_phone').value     = p.phone    || '';
    document.getElementById('edit_emergency').value = p.emergency_contact || '';
    document.getElementById('edit_gender').value    = p.gender   || 'Male';
    document.getElementById('edit_dob').value       = p.dob      || '';
    document.getElementById('edit_email').value     = p.email    || '';
    document.getElementById('edit_address').value   = p.address  || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function validateEditForm() {
    const dob = document.getElementById('edit_dob').value;
    if (!dob) {
        alert('Date of Birth is required');
        return false;
    }
    return true;
}

window.onclick = function (e) {
    if (e.target === document.getElementById('editModal')) closeModal();
};
</script>
</body>
</html>