<?php
// employee_payroll.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'employee') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$payrolls = [];
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT period_month, period_year, basic_salary, total_allowances,
               gross_pay, total_deductions, net_pay, status
        FROM payroll
        WHERE employee_id = :id
        ORDER BY period_year DESC, period_month DESC
    ");
    $stmt->execute(['id' => $employeeId]);
    $payrolls = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'My Payslips';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Payslips</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>Payslips</h2></div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Period</th><th>Basic</th><th>Allowances</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>View</th></tr></thead>
                        <tbody>
                            <?php foreach ($payrolls as $p): ?>
                            <tr>
                                <td><?= $p->period_month.'/'.$p->period_year ?></td>
                                <td><?= number_format($p->basic_salary,2) ?></td>
                                <td><?= number_format($p->total_allowances,2) ?></td>
                                <td><?= number_format($p->gross_pay,2) ?></td>
                                <td><?= number_format($p->total_deductions,2) ?></td>
                                <td><strong><?= number_format($p->net_pay,2) ?></strong></td>
                                <td><span class="status <?= $p->status ?>"><?= ucfirst($p->status) ?></span></td>
                                <td><a href="payslip_viewer.php?employee_id=<?= $employeeId ?>&period=<?= $p->period_month.'-'.$p->period_year ?>" class="btn btn-outline btn-sm">View</a></td>
                                <td>
                                <a href="payslip_viewer.php?employee_id=<?= $employeeId ?>&period=<?= $p->period_month . '-' . $p->period_year ?>" class="btn btn-outline btn-sm">View</a>
                                <?php if ($p->status === 'finalized' || $p->status === 'paid'): ?>
                                <a href="download_payslip.php?employee_id=<?= $employeeId ?>&period=<?= $p->period_month . '-' . $p->period_year ?>" class="btn btn-primary btn-sm">Download</a>
                                <?php endif; ?>
</td>
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