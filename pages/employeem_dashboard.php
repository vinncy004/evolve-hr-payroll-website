<?php
// employee_dashboard.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'employee') {
    // Redirect managers/admin/hr to their dashboards
    if ($_SESSION['role'] === 'manager') header('Location: manager_dashboard.php');
    elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'hr') header('Location: admin_dashboard.php');
    else header('Location: employee_dashboard.php');
    exit;
}
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$employee = null;
$leaveBalances = [];
$recentLeaves = [];
$pendingCount = 0;
$presentDays = 0;
$payroll = null;

if ($pdo) {
    // Employee info
    $stmt = $pdo->prepare("
        SELECT e.*, d.name AS department_name, p.title AS position_title,
               CONCAT(m.first_name,' ',m.last_name) AS manager_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN employees m ON e.manager_id = m.id
        WHERE e.id = :id
    ");
    $stmt->execute(['id' => $employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$employee) { session_destroy(); header('Location: login.php'); exit; }

    // Leave balances
    $stmt = $pdo->prepare("
        SELECT lt.name, lb.days_entitled, lb.days_used, lb.days_remaining
        FROM leave_balances lb
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.employee_id = :id AND lb.year = YEAR(CURDATE())
    ");
    $stmt->execute(['id' => $employeeId]);
    $leaveBalances = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Recent leaves
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name AS leave_type_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = :id
        ORDER BY lr.created_at DESC LIMIT 5
    ");
    $stmt->execute(['id' => $employeeId]);
    $recentLeaves = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Pending count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = :id AND status='pending'");
    $stmt->execute(['id' => $employeeId]);
    $pendingCount = $stmt->fetchColumn();

    // Attendance summary
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM attendance
        WHERE employee_id = :id
          AND attendance_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
        GROUP BY status
    ");
    $stmt->execute(['id' => $employeeId]);
    $attRows = $stmt->fetchAll(PDO::FETCH_OBJ);
    $summary = [];
    foreach ($attRows as $row) { $summary[$row->status] = $row->cnt; }
    $presentDays = $summary['present'] ?? 0;

    // Latest payroll
    $stmt = $pdo->prepare("
        SELECT period_month, period_year, basic_salary, total_allowances,
               gross_pay, total_deductions, net_pay, status
        FROM payroll
        WHERE employee_id = :id
        ORDER BY period_year DESC, period_month DESC LIMIT 1
    ");
    $stmt->execute(['id' => $employeeId]);
    $payroll = $stmt->fetch(PDO::FETCH_OBJ);
}

$totalLeave = 0;
foreach ($leaveBalances as $lb) { $totalLeave += $lb->days_remaining; }
$page_title = 'Employee Dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-icon primary"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><h3><?= $presentDays ?></h3><p>Present Days (This Month)</p></div></div>
                <div class="stat-card"><div class="stat-icon secondary"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $pendingCount ?></h3><p>Pending Leaves</p></div></div>
                <div class="stat-card"><div class="stat-icon accent"><i class="fas fa-umbrella-beach"></i></div><div class="stat-info"><h3><?= $totalLeave ?></h3><p>Leave Balance</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div><div class="stat-info"><h3><?= $payroll ? number_format($payroll->net_pay,2) : '0.00' ?></h3><p>Last Net Pay</p></div></div>
            </div>
            <div class="dashboard-content">
                <div class="left-column">
                    <div class="card">
                        <div class="card-header"><h2>My Profile</h2><a href="employee_profile.php" class="btn btn-outline">View Profile</a></div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item"><strong>Employee #</strong> <?= htmlspecialchars($employee->employee_number) ?></div>
                                <div class="info-item"><strong>Department</strong> <?= htmlspecialchars($employee->department_name ?? 'N/A') ?></div>
                                <div class="info-item"><strong>Position</strong> <?= htmlspecialchars($employee->position_title ?? 'N/A') ?></div>
                                <div class="info-item"><strong>Manager</strong> <?= htmlspecialchars($employee->manager_name ?? 'None') ?></div>
                                <div class="info-item"><strong>Status</strong> <span class="status <?= $employee->employment_status ?>"><?= ucfirst($employee->employment_status) ?></span></div>
                                <div class="info-item"><strong>Hire Date</strong> <?= $employee->hire_date ?? 'N/A' ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h2>Recent Leave Requests</h2><a href="employee_leave.php" class="btn btn-outline">View All</a></div>
                        <div class="card-body">
                            <ul class="activity-list">
                                <?php foreach ($recentLeaves as $leave): ?>
                                <li class="activity-item">
                                    <div class="activity-icon"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="activity-content">
                                        <h4><?= htmlspecialchars($leave->leave_type_name) ?></h4>
                                        <p><?= $leave->start_date ?> to <?= $leave->end_date ?> (<?= $leave->days_requested ?> days)</p>
                                        <span class="status <?= $leave->status ?>"><?= ucfirst($leave->status) ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="right-column">
                    <div class="card">
                        <div class="card-header"><h2>Leave Balances (<?= date('Y') ?>)</h2></div>
                        <div class="card-body">
                            <?php foreach ($leaveBalances as $lb): ?>
                            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee;">
                                <span><?= htmlspecialchars($lb->name) ?></span>
                                <span><strong><?= $lb->days_remaining ?></strong> / <?= $lb->days_entitled ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h2>Latest Payslip</h2><a href="employee_payroll.php" class="btn btn-outline">View All</a></div>
                        <div class="card-body">
                            <?php if ($payroll): ?>
                            <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>Period</span> <strong><?= $payroll->period_month.'/'.$payroll->period_year ?></strong></div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>Basic</span> <strong><?= number_format($payroll->basic_salary,2) ?></strong></div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>Allowances</span> <strong><?= number_format($payroll->total_allowances,2) ?></strong></div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>Gross</span> <strong><?= number_format($payroll->gross_pay,2) ?></strong></div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0;"><span>Deductions</span> <strong><?= number_format($payroll->total_deductions,2) ?></strong></div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; border-top:2px solid var(--secondary); margin-top:5px; font-size:1.1rem;">
                                <span><strong>Net Pay</strong></span> <span><strong><?= number_format($payroll->net_pay,2) ?></strong></span>
                            </div>
                            <?php else: ?>
                            <p>No payroll records.</p>
                            <?php endif; ?>
                        </div>
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