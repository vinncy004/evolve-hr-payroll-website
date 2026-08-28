<?php
// admin_dashboard.php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: employee_dashboard.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stats = ['employees'=>0, 'departments'=>0, 'positions'=>0, 'leave_types'=>0, 'payrolls'=>0];
if ($pdo) {
    $stats['employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
    $stats['departments'] = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $stats['positions'] = $pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();
    $stats['leave_types'] = $pdo->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
    $stats['payrolls'] = $pdo->query("SELECT COUNT(*) FROM payroll")->fetchColumn();
}
$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__. '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__. '/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['employees'] ?></h3>
                        <p>Active Employees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary"><i class="fas fa-building"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['departments'] ?></h3>
                        <p>Departments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon accent"><i class="fas fa-briefcase"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['positions'] ?></h3>
                        <p>Positions</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['leave_types'] ?></h3>
                        <p>Leave Types</p>
                    </div>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:15px;">
                <a href="admin_employees.php" class="btn btn-primary" style="text-align:center;">Manage Employees</a>
                <a href="admin_departments.php" class="btn btn-primary" style="text-align:center;">Departments</a>
                <a href="admin_positions.php" class="btn btn-primary" style="text-align:center;">Positions</a>
                <a href="admin_leave_types.php" class="btn btn-primary" style="text-align:center;">Leave Types</a>
                <a href="admin_payrolls.php" class="btn btn-primary" style="text-align:center;">Payrolls</a>
                <a href="admin_reports.php" class="btn btn-primary" style="text-align:center;">Reports</a>
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