<?php
// admin_payrolls.php – view all payrolls with department filter and payslip actions
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Get department filter from GET
$selected_dept = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Fetch departments for dropdown
$departments = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_OBJ);
}

// Build query with optional department filter
$sql = "
    SELECT p.*, 
           e.first_name, e.last_name, e.employee_number,
           d.name AS department_name
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
";
$params = [];
if ($selected_dept > 0) {
    $sql .= " WHERE e.department_id = :dept";
    $params['dept'] = $selected_dept;
}
$sql .= " ORDER BY p.period_year DESC, p.period_month DESC, e.first_name";

$payrolls = [];
if ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payrolls = $stmt->fetchAll(PDO::FETCH_OBJ);
}

$page_title = 'All Payrolls';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payrolls</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-bar select, .filter-bar button {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: white;
        }
        .filter-bar button {
            background: #1a365d;
            color: white;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }
        .filter-bar button:hover {
            background: #152642;
        }
        .filter-bar .clear-filter {
            background: #718096;
        }
        .filter-bar .clear-filter:hover {
            background: #4a5568;
        }
        .action-btns {
            display: flex;
            gap: 5px;
        }
        .action-btns .btn {
            padding: 4px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="dashboard">
            <div class="card">
                <div class="card-header">
                    <h2>All Payrolls</h2>
                </div>
                <div class="card-body">
                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label for="department_id">Department:</label>
                            <select name="department_id" id="department_id">
                                <option value="0">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept->id ?>" <?= ($selected_dept == $dept->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                            <?php if ($selected_dept > 0): ?>
                                <a href="admin_payrolls.php" class="btn btn-outline clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Payroll Table -->
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Period</th>
                                    <th>Basic</th>
                                    <th>Allowances</th>
                                    <th>Gross</th>
                                    <th>Deductions</th>
                                    <th>Net</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payrolls)): ?>
                                    <tr>
                                        <td colspan="10" style="text-align:center; color:#718096;">No payroll records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payrolls as $p): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($p->first_name . ' ' . $p->last_name) ?><br>
                                                <small><?= htmlspecialchars($p->employee_number) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($p->department_name ?? 'N/A') ?></td>
                                            <td><?= date('M Y', mktime(0,0,0,$p->period_month,1,$p->period_year)) ?></td>
                                            <td><?= number_format($p->basic_salary, 2) ?></td>
                                            <td><?= number_format($p->total_allowances, 2) ?></td>
                                            <td><?= number_format($p->gross_pay, 2) ?></td>
                                            <td><?= number_format($p->total_deductions, 2) ?></td>
                                            <td><strong><?= number_format($p->net_pay, 2) ?></strong></td>
                                            <td><span class="status <?= $p->status ?>"><?= ucfirst($p->status) ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="view_payslip.php?id=<?= $p->id ?>" class="btn btn-outline btn-sm" title="View Payslip">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="download_payslip.php?id=<?= $p->id ?>" class="btn btn-secondary btn-sm" title="Download PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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