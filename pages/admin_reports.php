<?php
// admin_reports.php – basic stats
require_once  __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stats = [];
if ($pdo) {
    $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $stats['active_employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
    $stats['departments'] = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $stats['leave_types'] = $pdo->query("SELECT COUNT(*) FROM leave_types WHERE is_active=1")->fetchColumn();
    $stats['pending_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
    $stats['payrolls'] = $pdo->query("SELECT COUNT(*) FROM payroll")->fetchColumn();
}
$page_title = 'Reports';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__. '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__. '/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $stats['total_employees'] ?? 0 ?></h3><p>Total Employees</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3><?= $stats['active_employees'] ?? 0 ?></h3><p>Active Employees</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-building"></i></div><div class="stat-info"><h3><?= $stats['departments'] ?? 0 ?></h3><p>Departments</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><h3><?= $stats['pending_leaves'] ?? 0 ?></h3><p>Pending Leaves</p></div></div>
            </div>
            <!-- You can add charts here later -->
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