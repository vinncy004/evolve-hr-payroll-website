<?php
// manager_team.php
require_once  __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'manager') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$teamMembers = [];
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT e.*, d.name AS department_name, p.title AS position_title,
               CONCAT(m.first_name,' ',m.last_name) AS manager_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN employees m ON e.manager_id = m.id
        WHERE e.manager_id = :id
        ORDER BY e.first_name
    ");
    $stmt->execute(['id' => $employeeId]);
    $teamMembers = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'My Team';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Team</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include  __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>Full Team List</h2></div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Position</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($teamMembers as $tm): ?>
                            <tr>
                                <td><?= htmlspecialchars($tm->employee_number) ?></td>
                                <td><?= htmlspecialchars($tm->first_name.' '.$tm->last_name) ?></td>
                                <td><?= htmlspecialchars($tm->work_email) ?></td>
                                <td><?= htmlspecialchars($tm->department_name ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tm->position_title ?? 'N/A') ?></td>
                                <td><span class="status <?= $tm->employment_status ?>"><?= ucfirst($tm->employment_status) ?></span></td>
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