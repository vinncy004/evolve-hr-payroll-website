<?php
// manager_attendance.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'manager') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$attendanceData = [];
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_number,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) AS leave
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id
            AND a.attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
        WHERE e.manager_id = :mgr
        GROUP BY e.id
        ORDER BY e.first_name
    ");
    $stmt->execute(['mgr' => $employeeId]);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Team Attendance';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Team Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>Attendance Summary (This Month)</h2></div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Employee</th><th>Present</th><th>Absent</th><th>Leave</th></tr></thead>
                        <tbody>
                            <?php foreach ($attendanceData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row->first_name.' '.$row->last_name) ?><br><small><?= htmlspecialchars($row->employee_number) ?></small></td>
                                <td><?= $row->present ?? 0 ?></td>
                                <td><?= $row->absent ?? 0 ?></td>
                                <td><?= $row->leave ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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