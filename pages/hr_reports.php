<?php
// hr_reports.php
require_once  __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'hr') { header('Location: employee_dashboard.php'); exit; }
require_once  __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stats = [];
if ($pdo) {
    $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $stats['active_employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
    $stats['pending_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
    $stats['approved_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='approved'")->fetchColumn();
}
$page_title = 'HR Reports';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HR Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include  __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $stats['total_employees'] ?></h3><p>Total Employees</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3><?= $stats['active_employees'] ?></h3><p>Active</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $stats['pending_leaves'] ?></h3><p>Pending Leaves</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $stats['approved_leaves'] ?></h3><p>Approved Leaves</p></div></div>
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