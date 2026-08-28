<?php
// manager_reports.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'manager') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$stats = [];
if ($pdo) {
    $stats['team_size'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE manager_id = $employeeId AND employment_status='active'")->fetchColumn();
    $stats['pending_leaves'] = $pdo->query("
        SELECT COUNT(*) FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE e.manager_id = $employeeId AND lr.status='pending'
    ")->fetchColumn();
    $stats['avg_present'] = $pdo->query("
        SELECT AVG(present_days) FROM (
            SELECT e.id, SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_days
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id
                AND a.attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
            WHERE e.manager_id = $employeeId
            GROUP BY e.id
        ) t
    ")->fetchColumn();
}
$page_title = 'Team Reports';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Team Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $stats['team_size'] ?? 0 ?></h3><p>Team Members</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $stats['pending_leaves'] ?? 0 ?></h3><p>Pending Leaves</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><h3><?= round($stats['avg_present'] ?? 0, 1) ?></h3><p>Avg Present Days</p></div></div>
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