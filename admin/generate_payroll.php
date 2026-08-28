<?php
// generate_payroll.php – generate payroll for a specific employee (or all)
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: employee_dashboard.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php'; // load constants

$db = new Database();
$pdo = $db->getConnection();

// Get parameters
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// If no employee provided, show form to choose
if ($employee_id === 0) {
    $stmt = $pdo->query("SELECT id, employee_number, first_name, last_name FROM employees WHERE employment_status = 'active' ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Generate Payroll</title>
    <link rel="stylesheet" href="./pages/assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <div class="dashboard">
                <div class="card">
                    <div class="card-header"><h2>Generate Payroll</h2></div>
                    <div class="card-body">
                        <form method="GET">
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                                <div>
                                    <label>Employee</label>
                                    <select name="employee_id" required style="width:100%; padding:8px;">
                                        <option value="">-- Select Employee --</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp->id ?>"><?= htmlspecialchars($emp->employee_number . ' - ' . $emp->first_name . ' ' . $emp->last_name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Month</label>
                                    <select name="month" style="width:100%; padding:8px;">
                                        <?php for ($m=1; $m<=12; $m++): ?>
                                            <option value="<?= $m ?>" <?= $m==$month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Year</label>
                                    <select name="year" style="width:100%; padding:8px;">
                                        <?php for ($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                            <option value="<?= $y ?>" <?= $y==$year ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top:15px;">Generate Payroll</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -------------------------------------------------------------
// Generate payroll for the selected employee
// -------------------------------------------------------------

// Fetch employee data with salary and department
$stmt = $pdo->prepare("
    SELECT e.*, d.name AS department_name, p.title AS position_title
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.id = :id
");
$stmt->execute(['id' => $employee_id]);
$employee = $stmt->fetch(PDO::FETCH_OBJ);

if (!$employee) {
    die('Employee not found.');
}

// Basic salary from employee record
$basic_salary = (float) $employee->basic_salary;
$gross_pay = $basic_salary; // start with basic

// You can add allowances here – for now we assume they are 0
$housing_allowance = 0;
$transport_allowance = 0;
$medical_allowance = 0;
$total_allowances = 0;
$overtime_hours = 0;
$overtime_pay = 0;
$absent_days = 0;
$absence_deduction = 0;

$gross_pay += $total_allowances + $overtime_pay;

// Taxable income = gross_pay - any tax-exempt portions
$taxable_income = $gross_pay;

// ---------- Calculate PAYE ----------
$paye = 0;
$remaining = $taxable_income;
foreach (PAYE_BANDS as $band) {
    if ($remaining <= 0) break;
    $band_min = $band['min'];
    $band_max = $band['max'];
    $rate = $band['rate'];
    $band_width = $band_max - $band_min + 1;
    if ($remaining > $band_width) {
        $paye += $band_width * $rate;
        $remaining -= $band_width;
    } else {
        $paye += $remaining * $rate;
        $remaining = 0;
    }
}

// ---------- NSSF ----------
$nssf_employee = 0;
$nssf_employer = 0;
if ($basic_salary >= NSSF_LOWER_LIMIT) {
    $nssfable = min($basic_salary, NSSF_UPPER_LIMIT);
    $nssf_employee = $nssfable * NSSF_RATE;
    $nssf_employer = $nssf_employee; // employer matches employee
}

// ---------- SHIF ----------
$shif = $gross_pay * SHIF_RATE;

// ---------- Housing Levy ----------
$housing_levy = $gross_pay * HOUSING_LEVY_RATE;

// ---------- Total Deductions ----------
$total_deductions = $paye + $nssf_employee + $shif + $housing_levy + $absence_deduction;

// ---------- Net Pay ----------
$net_pay = $gross_pay - $total_deductions;

// ---------- Insert/Update Payroll ----------
$stmt = $pdo->prepare("
    INSERT INTO payroll (
        organization_id, employee_id, period_month, period_year,
        basic_salary, housing_allowance, transport_allowance, medical_allowance,
        total_allowances, overtime_hours, overtime_pay,
        absent_days, absence_deduction,
        gross_pay, taxable_income, paye,
        nssf_employee, nssf_employer, shif, housing_levy,
        personal_relief, total_deductions, net_pay, status
    ) VALUES (
        :org, :emp, :month, :year,
        :basic, :housing, :transport, :medical,
        :allowances, :ot_hours, :ot_pay,
        :absent, :absent_ded,
        :gross, :taxable, :paye,
        :nssf_emp, :nssf_er, :shif, :levy,
        0, :deductions, :net, 'finalized'
    )
    ON DUPLICATE KEY UPDATE
        basic_salary = VALUES(basic_salary),
        housing_allowance = VALUES(housing_allowance),
        transport_allowance = VALUES(transport_allowance),
        medical_allowance = VALUES(medical_allowance),
        total_allowances = VALUES(total_allowances),
        overtime_hours = VALUES(overtime_hours),
        overtime_pay = VALUES(overtime_pay),
        absent_days = VALUES(absent_days),
        absence_deduction = VALUES(absence_deduction),
        gross_pay = VALUES(gross_pay),
        taxable_income = VALUES(taxable_income),
        paye = VALUES(paye),
        nssf_employee = VALUES(nssf_employee),
        nssf_employer = VALUES(nssf_employer),
        shif = VALUES(shif),
        housing_levy = VALUES(housing_levy),
        total_deductions = VALUES(total_deductions),
        net_pay = VALUES(net_pay),
        status = 'finalized'
");

$result = $stmt->execute([
    'org' => $employee->organization_id ?? 1,
    'emp' => $employee_id,
    'month' => $month,
    'year' => $year,
    'basic' => $basic_salary,
    'housing' => $housing_allowance,
    'transport' => $transport_allowance,
    'medical' => $medical_allowance,
    'allowances' => $total_allowances,
    'ot_hours' => $overtime_hours,
    'ot_pay' => $overtime_pay,
    'absent' => $absent_days,
    'absent_ded' => $absence_deduction,
    'gross' => $gross_pay,
    'taxable' => $taxable_income,
    'paye' => $paye,
    'nssf_emp' => $nssf_employee,
    'nssf_er' => $nssf_employer,
    'shif' => $shif,
    'levy' => $housing_levy,
    'deductions' => $total_deductions,
    'net' => $net_pay
]);

if ($result) {
    $payroll_id = $pdo->lastInsertId();
    if (!$payroll_id) {
        // If update happened, fetch existing ID
        $stmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = :emp AND period_month = :month AND period_year = :year");
        $stmt->execute(['emp' => $employee_id, 'month' => $month, 'year' => $year]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $payroll_id = $row->id;
    }
    $message = "Payroll generated successfully for " . htmlspecialchars($employee->first_name . ' ' . $employee->last_name) . " for " . date('F Y', mktime(0,0,0,$month,1,$year));
    $view_link = "<a href='view_payslip.php?id=$payroll_id' class='btn btn-primary'>View Payslip</a>";
    $download_link = "<a href='download_payslip.php?id=$payroll_id' class='btn btn-secondary'>Download PDF</a>";
} else {
    $message = "Error: Could not generate payroll.";
    $view_link = '';
    $download_link = '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Generated</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>Payroll Generation Result</h2></div>
                <div class="card-body">
                    <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;">
                        <?= $message ?>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <?= $view_link ?>
                        <?= $download_link ?>
                        <a href="generate_payroll.php" class="btn btn-outline">Generate Another</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>