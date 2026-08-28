<?php
// hr_dashboard.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'hr') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stats = ['employees'=>0, 'pending_leaves'=>0, 'payrolls'=>0];
if ($pdo) {
    $stats['employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
    $stats['pending_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
    $stats['payrolls'] = $pdo->query("SELECT COUNT(*) FROM payroll")->fetchColumn();
}
$page_title = 'HR Dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $stats['employees'] ?></h3><p>Active Employees</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $stats['pending_leaves'] ?></h3><p>Pending Leaves</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-money-bill-wave"></i></div><div class="stat-info"><h3><?= $stats['payrolls'] ?></h3><p>Payrolls</p></div></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <a href="hr_employees.php" class="btn btn-primary" style="text-align:center;">Manage Employees</a>
                <a href="hr_leave_requests.php" class="btn btn-primary" style="text-align:center;">Leave Approvals</a>
                <a href="hr_payrolls.php" class="btn btn-primary" style="text-align:center;">Payrolls</a>
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