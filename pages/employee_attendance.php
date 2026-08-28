<?php
// employee_attendance.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'employee') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$attendances = [];
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT attendance_date, status, overtime_hours
        FROM attendance
        WHERE employee_id = :id
        ORDER BY attendance_date DESC LIMIT 30
    ");
    $stmt->execute(['id' => $employeeId]);
    $attendances = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'My Attendance';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>

        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>Recent Attendance</h2></div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Date</th><th>Status</th><th>Overtime (hrs)</th></tr></thead>
                        <tbody>
                            <?php foreach ($attendances as $a): ?>
                            <tr>
                                <td><?= $a->attendance_date ?></td>
                                <td><span class="status <?= $a->status ?>"><?= ucfirst($a->status) ?></span></td>
                                <td><?= $a->overtime_hours ?></td>
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