<?php
// view_payslip.php – Fixed: no redirects on errors, just display messages.
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/database.php';

$payslip_id = (int) ($_GET['id'] ?? 0);
if (!$payslip_id) {
    die('<h2>Invalid Payslip ID</h2><p><a href="login.php">Back to Dashboard</a></p>');
}

$db = new Database();
$pdo = $db->getConnection();

// Fetch payroll data
$stmt = $pdo->prepare("
    SELECT p.*, 
           e.employee_number, e.first_name, e.last_name, e.employment_status,
           d.name AS department, pos.title AS position,
           o.organization_name, o.email AS company_email, o.phone AS company_phone, o.address AS company_address
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN organizations o ON e.organization_id = o.id
    WHERE p.id = :id
");
$stmt->execute(['id' => $payslip_id]);
$payroll = $stmt->fetch(PDO::FETCH_OBJ);

if (!$payroll) {
    die('<h2>Payslip Not Found</h2><p><a href="dashboard.php">Back to Dashboard</a></p>');
}

// Permission check: employee can view only their own, HR/admin can view all
$isAdmin = in_array($_SESSION['role'] ?? '', ['admin', 'hr']);
if (!$isAdmin && ($_SESSION['employee_id'] ?? 0) != $payroll->employee_id) {
    die('<h2>Unauthorized</h2><p>You do not have permission to view this payslip.</p><p><a href="dashboard.php">Back to Dashboard</a></p>');
}

$period = date('F Y', mktime(0,0,0, $payroll->period_month, 1, $payroll->period_year));
$page_title = 'Payslip';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($payroll->employee_number) ?> - <?= $period ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (same styles as before) */
        .payslip-container { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px 50px; }
        .payslip-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1a365d; padding-bottom: 15px; margin-bottom: 20px; }
        .payslip-header .logo { display: flex; align-items: center; gap: 15px; }
        .payslip-header .logo img { max-height: 60px; }
        .payslip-header .title { text-align: right; }
        .payslip-header .title h1 { font-size: 28px; color: #1a365d; margin: 0; }
        .employee-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; background: #f7fafc; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; }
        .employee-info div { font-size: 14px; }
        .employee-info strong { color: #2d3748; }
        .status-warning { background: #fefcbf; color: #975a16; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #d69e2e; }
        .salary-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .salary-table th { text-align: left; padding: 8px 12px; background: #edf2f7; font-weight: 600; color: #2d3748; }
        .salary-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
        .salary-table .total-row { font-weight: 700; border-top: 2px solid #1a365d; }
        .net-pay { font-size: 24px; font-weight: 700; color: #1a365d; text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px solid #1a365d; }
        .payslip-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #718096; text-align: center; }
        .action-buttons { text-align: center; margin-top: 20px; display: flex; gap: 15px; justify-content: center; }
        .action-buttons .btn { padding: 10px 25px; }
        @media print { .action-buttons, .no-print { display: none !important; } .payslip-container { box-shadow: none; border: none; padding: 20px; margin: 0; } }
        @media (max-width: 600px) { .payslip-container { padding: 15px; } .employee-info { grid-template-columns: 1fr; } .payslip-header { flex-direction: column; align-items: start; gap: 10px; } .payslip-header .title { text-align: left; width: 100%; } }
    </style>
</head>
<body>
    <div class="payslip-container">
        <!-- Header -->
        <div class="payslip-header">
            <div class="logo">
                <img src="assets/images/lixnet2-BUcvBH34.png" alt="Company Logo">
                <div><strong><?= htmlspecialchars($payroll->organization_name ?? 'Evolve Payroll') ?></strong></div>
            </div>
            <div class="title">
                <h1>PAYSLIP</h1>
                <small>Period: <?= $period ?></small>
            </div>
        </div>

        <?php if ($payroll->status === 'draft'): ?>
            <div class="status-warning"><i class="fas fa-exclamation-triangle"></i> This payslip is in <strong>Draft</strong> status and may not be final.</div>
        <?php endif; ?>

        <div class="employee-info">
            <div><strong>Employee Number:</strong> <?= htmlspecialchars($payroll->employee_number) ?></div>
            <div><strong>Name:</strong> <?= htmlspecialchars($payroll->first_name . ' ' . $payroll->last_name) ?></div>
            <div><strong>Department:</strong> <?= htmlspecialchars($payroll->department ?? 'N/A') ?></div>
            <div><strong>Position:</strong> <?= htmlspecialchars($payroll->position ?? 'N/A') ?></div>
            <div><strong>Status:</strong> <?= ucfirst($payroll->employment_status) ?></div>
            <div><strong>Period:</strong> <?= $period ?></div>
        </div>

        <h3 style="margin-top: 20px; color: #1a365d;">Earnings</h3>
        <table class="salary-table">
            <thead><tr><th>Description</th><th style="text-align:right;">Amount (KES)</th></tr></thead>
            <tbody>
                <tr><td>Basic Salary</td><td style="text-align:right;"><?= number_format($payroll->basic_salary, 2) ?></td></tr>
                <?php if ($payroll->housing_allowance > 0): ?>
                <tr><td>Housing Allowance</td><td style="text-align:right;"><?= number_format($payroll->housing_allowance, 2) ?></td></tr>
                <?php endif; ?>
                <?php if ($payroll->transport_allowance > 0): ?>
                <tr><td>Transport Allowance</td><td style="text-align:right;"><?= number_format($payroll->transport_allowance, 2) ?></td></tr>
                <?php endif; ?>
                <?php if ($payroll->medical_allowance > 0): ?>
                <tr><td>Medical Allowance</td><td style="text-align:right;"><?= number_format($payroll->medical_allowance, 2) ?></td></tr>
                <?php endif; ?>
                <?php if ($payroll->overtime_pay > 0): ?>
                <tr><td>Overtime Pay (<?= $payroll->overtime_hours ?> hrs)</td><td style="text-align:right;"><?= number_format($payroll->overtime_pay, 2) ?></td></tr>
                <?php endif; ?>
                <tr class="total-row"><td><strong>Total Gross Pay</strong></td><td style="text-align:right;"><strong><?= number_format($payroll->gross_pay, 2) ?></strong></td></tr>
            </tbody>
        </table>

        <h3 style="margin-top: 20px; color: #1a365d;">Deductions</h3>
        <table class="salary-table">
            <thead><tr><th>Description</th><th style="text-align:right;">Amount (KES)</th></tr></thead>
            <tbody>
                <tr><td>PAYE (Income Tax)</td><td style="text-align:right;"><?= number_format($payroll->paye, 2) ?></td></tr>
                <tr><td>NSSF (Employee)</td><td style="text-align:right;"><?= number_format($payroll->nssf_employee, 2) ?></td></tr>
                <tr><td>SHIF (NHIF)</td><td style="text-align:right;"><?= number_format($payroll->shif, 2) ?></td></tr>
                <tr><td>Housing Levy</td><td style="text-align:right;"><?= number_format($payroll->housing_levy, 2) ?></td></tr>
                <?php if ($payroll->absence_deduction > 0): ?>
                <tr><td>Absence Deduction (<?= $payroll->absent_days ?> days)</td><td style="text-align:right;"><?= number_format($payroll->absence_deduction, 2) ?></td></tr>
                <?php endif; ?>
                <tr class="total-row"><td><strong>Total Deductions</strong></td><td style="text-align:right;"><strong><?= number_format($payroll->total_deductions, 2) ?></strong></td></tr>
            </tbody>
        </table>

        <div class="net-pay">
            Net Pay (Take‑Home): KES <?= number_format($payroll->net_pay, 2) ?>
        </div>

        <div class="payslip-footer">
            This is a computer‑generated payslip. No signature required.<br>
            <?= htmlspecialchars($payroll->company_name ?? 'Evolve Payroll') ?> | 
            <?= htmlspecialchars($payroll->company_address ?? 'Nairobi, Kenya') ?> | 
            <?= htmlspecialchars($payroll->company_email ?? 'info@evolve.com') ?> | 
            <?= htmlspecialchars($payroll->company_phone ?? '+254 700 000 000') ?>
        </div>

        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
            <a href="download_payslip.php?id=<?= $payslip_id ?>" class="btn btn-secondary"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <a href="<?= $isAdmin ? 'hr_payslips.php' : 'my_payslips.php' ?>" class="btn btn-outline">Back</a>
        </div>
    </div>
</body>
</html>