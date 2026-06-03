<?php
session_start();
if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_start();
}

include_once 'backend/connection.php';
if (!isset($conn)) {
    die("Database connection failed. Check connection.php path.");
}

$error = '';

if (isset($_POST['login'])) {
    $usernameOrEmail = trim($_POST['username']);
    $password        = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE (username = :u OR email = :u) LIMIT 1");
    $stmt->execute(['u' => $usernameOrEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        if ((int)$user['isActive'] === 0) {
            $error = "Your account has been deactivated. Please contact an administrator.";
        } else {
           
            session_regenerate_id(true);
            $_SESSION['id']       = $user['id'];  
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['name']     = $user['full_name'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['avatar']   = "https://ui-avatars.com/api/?name=" .
                                   urlencode($user['full_name']) .
                                   "&background=2563eb&color=fff";

            switch ($user['role']) {
                case 'Admin':        header("Location: admin-dashboard.php");        exit();
                case 'Receptionist': header("Location: receptionist-dashboard.php"); exit();
                case 'Doctor':       header("Location: doctor-dashboard.php");       exit();
                case 'Nurse':        header("Location: nurse-dashboard.php");        exit();
                case 'Laboratory':   header("Location: laboratory-dashboard.php");   exit();
                case 'Radiology':    header("Location: radiology-dashboard.php");    exit();
                default:             $error = "No dashboard assigned to this role.";
            }
        }

    } else {
        $error = "Username/email or password incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CardioCare</title>
    <link rel="icon" type="image/png" href="heart.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forms.css">
    
    <style>
        
        .auth-header .logo i {
            color: #2563eb !important;
        }
    </style>
</head>
<body class="auth-body">

<div class="auth-container">
    <div class="auth-card card">
        <div class="auth-header">
            <div class="logo justify-center mb-4">
                <i class="fa-solid fa-heart-pulse"></i>
                <span>CardioCare</span>
            </div>
            <h2>Welcome Back</h2>
            <p>Please enter your credentials to login</p>
        </div>

        <form id="loginForm" method="POST" action="" class="auth-form">

            <div class="form-group">
                <label>Email or Username</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username"
                           placeholder="Enter username or email"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <i class="fa-solid fa-lock" style="position:absolute; left:1rem; color:#6b7280;"></i>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required
                           style="width:100%; padding:0.75rem 3rem 0.75rem 2.75rem;">
                    <i class="fa-solid fa-eye" id="togglePassword"
                       style="position:absolute; right:1rem; cursor:pointer; color:#6b7280;"></i>
                </div>
            </div>

            <?php if ($error): ?>
                <div style="background:#fee2e2; border:1px solid #fca5a5; color:#b91c1c;
                            padding:0.75rem 1rem; border-radius:8px; font-size:0.875rem;
                            display:flex; align-items:center; gap:0.5rem;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" id="rememberMe" name="rememberMe">
                    <span class="checkmark"></span>
                    Remember me
                </label>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" name="login" class="btn btn-primary btn-block">
                Login to Dashboard
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye-slash');
});
</script>
</body>
</html>