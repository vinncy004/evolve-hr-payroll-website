<?php
// hr_payrolls.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'hr') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$payrolls = [];
if ($pdo) {
    $stmt = $pdo->query("
        SELECT p.*, e.first_name, e.last_name, e.employee_number
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        ORDER BY p.period_year DESC, p.period_month DESC, e.first_name
    ");
    $payrolls = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Payrolls';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payrolls</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>All Payrolls</h2></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead><tr><th>Employee</th><th>Period</th><th>Basic</th><th>Allowances</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($payrolls as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p->first_name.' '.$p->last_name) ?><br><small><?= htmlspecialchars($p->employee_number) ?></small></td>
                                    <td><?= $p->period_month.'/'.$p->period_year ?></td>
                                    <td><?= number_format($p->basic_salary,2) ?></td>
                                    <td><?= number_format($p->total_allowances,2) ?></td>
                                    <td><?= number_format($p->gross_pay,2) ?></td>
                                    <td><?= number_format($p->total_deductions,2) ?></td>
                                    <td><strong><?= number_format($p->net_pay,2) ?></strong></td>
                                    <td><span class="status <?= $p->status ?>"><?= ucfirst($p->status) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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