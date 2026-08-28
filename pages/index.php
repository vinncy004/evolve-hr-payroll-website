<?php
// index.php – Organization Registration
session_start();
require_once __DIR__.'/../config/database.php'; // includes the Database class

$db = new Database();
$pdo = $db->getConnection();
if (!$pdo) {
    die("Database connection failed. Please check your configuration.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_name = trim($_POST['organization_name'] ?? '');
    $org_code = trim($_POST['organization_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = 'active';

    if (empty($org_name) || empty($org_code)) {
        $error = 'Organization Name and Code are required.';
    } else {
        try {
            // Check if organization code exists
            $stmt = $pdo->prepare("SELECT id FROM organizations WHERE organization_code = ?");
            $stmt->execute([$org_code]);
            if ($stmt->rowCount() > 0) {
                $error = 'Organization Code already exists. Please choose another.';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO organizations (organization_name, organization_code, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$org_name, $org_code, $email, $phone, $address, $status]);
                $org_id = $pdo->lastInsertId();

                // Store in session
                $_SESSION['org_id'] = $org_id;
                $_SESSION['org_name'] = $org_name;

                // Redirect to signup
                header("Location: signup.php?org_id=" . $org_id);
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
    <title>Register Organization – Evolve HR</title>
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
            margin-bottom: 32px;
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
            margin-bottom: 28px;
            font-size: 15px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            color: var(--accent, #2d3748);
            margin-bottom: 6px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #f7fafc;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary, #d4af37);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
            background: #ffffff;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        <p class="sub">Start your journey – register your organization</p>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="organization_name">Organization Name *</label>
                <input type="text" id="organization_name" name="organization_name" value="<?= htmlspecialchars($_POST['organization_name'] ?? '') ?>" required placeholder="e.g. Acme Corp">
            </div>
            <div class="form-group">
                <label for="organization_code">Organization Code *</label>
                <input type="text" id="organization_code" name="organization_code" value="<?= htmlspecialchars($_POST['organization_code'] ?? '') ?>" required placeholder="Unique code, e.g. ACME">
                <small style="color: var(--gray); font-size: 12px;">This will be used as a unique identifier.</small>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@company.com">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+1 234 567 890">
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" placeholder="Street, City, State, Zip"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-primary">Register Organization</button>
        </form>

        <p class="footer-text">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>
</body>
</html>