<?php
// login.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } elseif (!$pdo) {
        $error = 'System error!';
    } else {
        try {
            // Check employer_users
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, role, is_active,
                       first_name, last_name, 'employer' AS user_type
                FROM employer_users
                WHERE (username = :username OR email = :username) AND is_active = 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$user) {
                // Check employee_users
                $stmt = $pdo->prepare("
                    SELECT eu.id, eu.username, eu.email, eu.password_hash, eu.role, eu.is_active,
                           e.first_name, e.last_name, e.id AS employee_id, 'employee' AS user_type,
                           e.employment_status
                    FROM employee_users eu
                    JOIN employees e ON eu.employee_id = e.id
                    WHERE (eu.username = :username OR eu.email = :username)
                      AND eu.is_active = 1
                      AND e.employment_status = 'active'
                ");
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch(PDO::FETCH_OBJ);
            }

            if ($user && password_verify($password, $user->password_hash)) {
                // Set session
                $_SESSION['user_id'] = $user->id;
                $_SESSION['user_type'] = $user->user_type;
                $_SESSION['role'] = $user->role;
                $_SESSION['username'] = $user->username;
                $_SESSION['first_name'] = $user->first_name;
                $_SESSION['last_name'] = $user->last_name;
                if (isset($user->employee_id)) {
                    $_SESSION['employee_id'] = (int) $user->employee_id;
                }

                // Redirect based on role and user_type
                $redirect = 'employee_dashboard.php';
                if ($user->user_type === 'employer') {
                    if ($user->role === 'admin') {
                        $redirect = 'admin_dashboard.php';
                    } elseif ($user->role === 'hr') {
                        $redirect = 'hr_dashboard.php';
                    } else {
                        $redirect = 'employee_dashboard.php';
                    }
                } else { // employee
                    if ($user->role === 'admin') {
                        $redirect = 'admin_dashboard.php';
                    } elseif ($user->role === 'hr') {
                        $redirect = 'hr_dashboard.php';
                    } elseif ($user->role === 'manager') {
                        $redirect = 'manager_dashboard.php';
                    } else {
                        $redirect = 'employee_dashboard.php';
                    }
                }
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Invalid credentials.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Database error.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evolve Payroll - Login</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon-PvIL13JH.ico" type="image/x-icon">
    <link rel="shortcut icon" href="assets/images/favicon-PvIL13JH.ico" type="image/x-icon">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px 35px;
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-area img {
            max-width: 180px;
            height: auto;
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a365d;
            margin-top: 10px;
            letter-spacing: 1px;
        }
        .login-subtitle {
            color: #718096;
            font-size: 14px;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group .input-group {
            position: relative;
        }
        .form-group .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        .form-control {
            width: 100%;
            padding: 12px 12px 12px 44px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #1a365d;
            box-shadow: 0 0 0 3px rgba(26,54,93,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #1a365d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
            margin-top: 5px;
        }
        .btn-login:hover {
            background: #152642;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26,54,93,0.3);
        }
        .btn-login i {
            margin-right: 8px;
        }
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #fc8181;
        }
        .forgot-link {
            text-align: right;
            margin-top: 10px;
        }
        .forgot-link a {
            color: #718096;
            font-size: 13px;
            text-decoration: none;
            transition: 0.2s;
        }
        .forgot-link a:hover {
            color: #1a365d;
            text-decoration: underline;
        }
        .footer-text {
            text-align: center;
            color: #a0aec0;
            font-size: 13px;
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
        .footer-text span {
            color: #1a365d;
            font-weight: 600;
        }
        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
            .logo-area img { max-width: 140px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-area">
            <!-- Your logo image -->
            <img src="assets/images/lixnet2-BUcvBH34.png" alt="Evolve Payroll Logo">
            <div class="login-title">Evolve Hr & Payroll</div>
            <div class="login-subtitle"> Self-Service Portal</div>
        </div>

        <?php if ($error): ?>
            <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username or email" required autofocus value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</button>
            <div class="forgot-link">
                <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
            </div>
        </form>
        <div class="footer-text">
            &copy; <?= date('Y') ?> <span>Evolve</span> Hr & Payroll System.
        </div>
    </div>
</body>
</html>
