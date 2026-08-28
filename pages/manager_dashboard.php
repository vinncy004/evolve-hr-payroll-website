<?php
// manager_dashboard.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'manager') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$teamSize = 0;
$pendingCount = 0;
$presentDays = 0;
$teamMembers = [];
if ($pdo) {
    // Team size
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE manager_id = :id AND employment_status='active'");
    $stmt->execute(['id' => $employeeId]);
    $teamSize = $stmt->fetchColumn();
    // Pending leaves
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE e.manager_id = :id AND lr.status='pending'
    ");
    $stmt->execute(['id' => $employeeId]);
    $pendingCount = $stmt->fetchColumn();
    // Present days this month for team
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        WHERE e.manager_id = :id
          AND a.attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
          AND a.status='present'
    ");
    $stmt->execute(['id' => $employeeId]);
    $presentDays = $stmt->fetchColumn();
    // Team members list (first 5 for quick view)
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_number, e.employment_status,
               d.name AS department_name, p.title AS position_title
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.manager_id = :id AND e.employment_status='active'
        LIMIT 5
    ");
    $stmt->execute(['id' => $employeeId]);
    $teamMembers = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Manager Dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-user-friends"></i></div><div class="stat-info"><h3><?= $teamSize ?></h3><p>Team Members</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $pendingCount ?></h3><p>Pending Leaves</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><h3><?= $presentDays ?></h3><p>Team Present Days</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Quick Team View</h2><a href="manager_team.php" class="btn btn-outline">View All</a></div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Name</th><th>Department</th><th>Position</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($teamMembers as $tm): ?>
                            <tr>
                                <td><?= htmlspecialchars($tm->first_name.' '.$tm->last_name) ?><br><small><?= htmlspecialchars($tm->employee_number) ?></small></td>
                                <td><?= htmlspecialchars($tm->department_name ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tm->position_title ?? 'N/A') ?></td>
                                <td><span class="status <?= $tm->employment_status ?>"><?= ucfirst($tm->employment_status) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <a href="manager_leave_requests.php" class="btn btn-primary" style="text-align:center;">Leave Approvals</a>
                <a href="manager_attendance.php" class="btn btn-primary" style="text-align:center;">Team Attendance</a>
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