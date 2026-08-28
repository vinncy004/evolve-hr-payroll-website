<?php
// employee_profile.php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'employee') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$error = $success = '';
$employee = null;

if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
    $stmt->execute(['id' => $employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$employee) { session_destroy(); header('Location: login.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $phone = trim($_POST['phone'] ?? '');
    $personal_email = trim($_POST['personal_email'] ?? '');
    $address = trim($_POST['residential_address'] ?? '');
    try {
        $stmt = $pdo->prepare("UPDATE employees SET phone = :phone, personal_email = :email, residential_address = :address WHERE id = :id");
        $stmt->execute(['phone' => $phone, 'email' => $personal_email, 'address' => $address, 'id' => $employeeId]);
        $success = 'Profile updated.';
        // Refresh
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
        $stmt->execute(['id' => $employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_OBJ);
    } catch (PDOException $e) { $error = 'Update failed: ' . $e->getMessage(); }
}
$page_title = 'My Profile';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?><div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <div class="card">
                <div class="card-header"><h2>Edit Profile</h2></div>
                <form method="POST">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div><label>Employee Number</label><input type="text" value="<?= htmlspecialchars($employee->employee_number) ?>" disabled style="width:100%; padding:8px;"></div>
                        <div><label>First Name</label><input type="text" value="<?= htmlspecialchars($employee->first_name) ?>" disabled style="width:100%; padding:8px;"></div>
                        <div><label>Last Name</label><input type="text" value="<?= htmlspecialchars($employee->last_name) ?>" disabled style="width:100%; padding:8px;"></div>
                        <div><label>Department</label><input type="text" value="<?= htmlspecialchars($employee->department_id ?? 'N/A') ?>" disabled style="width:100%; padding:8px;"></div>
                        <div><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($employee->phone ?? '') ?>" style="width:100%; padding:8px;"></div>
                        <div><label>Personal Email</label><input type="email" name="personal_email" value="<?= htmlspecialchars($employee->personal_email ?? '') ?>" style="width:100%; padding:8px;"></div>
                        <div style="grid-column:1/3;"><label>Residential Address</label><textarea name="residential_address" style="width:100%; padding:8px;"><?= htmlspecialchars($employee->residential_address ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" name="update" class="btn btn-primary" style="margin-top:15px;">Update</button>
                </form>
            </div>
        </div>
    </div>
    <div class="overlay"></div>
    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.overlay').classList.toggle('active');
        });
        document.querySelector('.overlay').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('active');
            this.classList.remove('active');
        });
    </script>
</body>
</html>