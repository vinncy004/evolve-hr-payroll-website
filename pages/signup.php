<?php
// signup.php – Admin Registration
session_start();
require_once __DIR__.'/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
if (!$pdo) {
    die("Database connection failed. Please check your configuration.");
}

$org_id = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$error = '';
$success = '';
$org_name = '';

// Verify organization exists
if ($org_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, organization_name FROM organizations WHERE id = ? AND status = 'active'");
        $stmt->execute([$org_id]);
        if ($stmt->rowCount() === 0) {
            $error = 'Invalid organization. Please register again.';
            $org_id = 0;
        } else {
            $org_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $org_name = $org_data['organization_name'];
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        $org_id = 0;
    }
} else {
    $error = 'Organization ID missing. Please register your organization first.';
}

// Process signup form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $org_id) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already registered. Please login.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (organization_id, first_name, last_name, email, password, user_type, role, status) VALUES (?, ?, ?, ?, ?, 'employer', 'admin', 'active')");
                $stmt->execute([$org_id, $first_name, $last_name, $email, $hashed]);

                $_SESSION['signup_success'] = 'Account created! Please login.';
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account – Evolve HR</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f0f4f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .auth-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            padding: 40px 48px;
            max-width: 520px;
            width: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }
        .auth-container .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-container .logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary, #0f1b33);
            letter-spacing: -0.5px;
        }
        .auth-container .logo h1 span {
            color: var(--secondary, #d4af37);
        }
        .auth-container .sub {
            text-align: center;
            color: var(--gray, #718096);
            margin-bottom: 24px;
            font-size: 15px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            color: var(--accent, #2d3748);
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #f7fafc;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--secondary, #d4af37);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
            background: #ffffff;
        }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary, #0f1b33), #1a2d4d);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 24px rgba(15, 27, 51, 0.2);
        }
        .btn-primary:active {
            transform: scale(0.98);
        }
        .error-msg {
            background: #fff5f5;
            border-left: 4px solid #fc8181;
            padding: 12px 16px;
            border-radius: 6px;
            color: #c53030;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success-msg {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 12px 16px;
            border-radius: 6px;
            color: #276749;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .org-info {
            background: #f7fafc;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
            color: var(--accent);
        }
        .org-info strong {
            color: var(--primary);
        }
        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--gray, #718096);
        }
        .footer-text a {
            color: var(--primary, #0f1b33);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-text a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .auth-container {
                padding: 24px 20px;
                margin: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="logo">
            <h1>Evolve<span>HR</span></h1>
        </div>
        <p class="sub">Create your admin account</p>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($org_id && !empty($org_name)): ?>
            <div class="org-info">
                <strong>Organization:</strong> <?= htmlspecialchars($org_name) ?>
                <span style="color: var(--gray); font-size: 12px; margin-left: 12px;">ID: <?= $org_id ?></span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small style="color: var(--gray); font-size: 12px;">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-primary">Create Admin Account</button>
            </form>
        <?php else: ?>
            <p style="color: #e53e3e; text-align: center;"><?= htmlspecialchars($error) ?></p>
            <p style="text-align: center; margin-top: 20px;"><a href="index.php" class="btn-primary" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 30px;">Register Organization</a></p>
        <?php endif; ?>

        <p class="footer-text">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>
</body>
</html>