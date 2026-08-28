<?php
// department_employees.php – Admin view of employees by department
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$error = '';
$departments = [];
$totalEmployees = 0;

try {
    // Fetch all active departments with their employees
    $stmt = $pdo->query("
        SELECT 
            d.id AS dept_id, d.name AS dept_name,
            e.id AS emp_id, e.employee_number, e.first_name, e.last_name, 
            e.hire_date, e.employment_status,
            p.title AS position_title
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.employment_status = 'active'
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE d.is_active = 1
        ORDER BY d.name, e.first_name
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Group by department
    $deptMap = [];
    foreach ($rows as $row) {
        if (!isset($deptMap[$row->dept_id])) {
            $deptMap[$row->dept_id] = [
                'name' => $row->dept_name,
                'employees' => []
            ];
        }
        if ($row->emp_id) { // only if employee exists
            $deptMap[$row->dept_id]['employees'][] = $row;
            $totalEmployees++;
        }
    }

    // Convert to indexed array for display
    $departments = array_values($deptMap);

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$page_title = 'Department Employees';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Departments – Employees</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional custom styles for this page */
        .dept-card {
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f7fafc;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #e2e8f0;
        }
        .dept-header:hover {
            background: #edf2f7;
        }
        .dept-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1a365d;
        }
        .dept-header .badge {
            background: #1a365d;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        .dept-body {
            padding: 15px 20px;
            display: none; /* hidden by default; toggled by JS */
        }
        .dept-body.open {
            display: block;
        }
        .dept-body table {
            width: 100%;
            border-collapse: collapse;
        }
        .dept-body table th {
            text-align: left;
            padding: 10px 12px;
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        .dept-body table td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
        }
        .dept-body table tr:hover td {
            background: #f7fafc;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status.active { background: #c6f6d5; color: #22543d; }
        .status.inactive { background: #fed7d7; color: #c53030; }
        .status.terminated { background: #e2e8f0; color: #4a5568; }
        .status.on_leave { background: #fefcbf; color: #975a16; }
        .empty-message {
            padding: 20px;
            text-align: center;
            color: #718096;
        }
        .summary-stats {
            display: flex;
            gap: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .summary-stats div {
            font-size: 16px;
        }
        .summary-stats strong {
            font-size: 22px;
            color: #1a365d;
        }
        .summary-stats span {
            color: #4a5568;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-box input:focus {
            outline: none;
            border-color: #1a365d;
        }
    </style>
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?>
                <div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="summary-stats">
                <div><strong><?= count($departments) ?></strong> <span>Departments</span></div>
                <div><strong><?= $totalEmployees ?></strong> <span>Active Employees</span></div>
            </div>

            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Filter employees by name or number..." onkeyup="filterEmployees()">
            </div>

            <?php if (empty($departments)): ?>
                <div class="card" style="padding:30px; text-align:center; color:#718096;">
                    No departments found or no active employees assigned.
                </div>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card" data-dept-name="<?= strtolower($dept['name']) ?>">
                        <div class="dept-header" onclick="toggleDept(this)">
                            <h3><i class="fas fa-chevron-right" style="margin-right:10px; transition:0.2s;"></i> <?= htmlspecialchars($dept['name']) ?></h3>
                            <span class="badge"><?= count($dept['employees']) ?> employee<?= count($dept['employees']) !== 1 ? 's' : '' ?></span>
                        </div>
                        <div class="dept-body open">
                            <?php if (empty($dept['employees'])): ?>
                                <div class="empty-message">No active employees in this department.</div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee No.</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Hire Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dept['employees'] as $emp): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($emp->employee_number) ?></td>
                                                <td><?= htmlspecialchars($emp->first_name . ' ' . $emp->last_name) ?></td>
                                                <td><?= htmlspecialchars($emp->position_title ?? 'N/A') ?></td>
                                                <td><?= $emp->hire_date ? date('d M Y', strtotime($emp->hire_date)) : 'N/A' ?></td>
                                                <td><span class="status <?= $emp->employment_status ?>"><?= ucfirst($emp->employment_status) ?></span></td>
                                                <td>
                                                    <a href="edit_employee.php?id=<?= $emp->emp_id ?>" class="btn btn-outline btn-sm">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="overlay"></div>

    <script>
        // Toggle department body visibility
        function toggleDept(headerElement) {
            const body = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i.fa-chevron-right');
            if (body.classList.contains('open')) {
                body.classList.remove('open');
                icon.style.transform = 'rotate(0deg)';
            } else {
                body.classList.add('open');
                icon.style.transform = 'rotate(90deg)';
            }
        }

        // Filter employees by name or number (client-side)
        function filterEmployees() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const cards = document.querySelectorAll('.dept-card');

            cards.forEach(card => {
                const rows = card.querySelectorAll('table tbody tr');
                let visibleRows = 0;
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                // Show/hide the department card if no matching rows
                const deptBody = card.querySelector('.dept-body');
                const emptyMessage = card.querySelector('.empty-message');
                if (rows.length === 0) {
                    // No employees at all; keep visible
                    card.style.display = '';
                } else if (visibleRows === 0) {
                    // If filter applied and no rows match, hide the card? 
                    // For better UX, we can keep it but show "No match"
                    // We'll just keep it visible but show a message.
                    if (!emptyMessage) {
                        // Add a message if not exists
                        const msg = document.createElement('div');
                        msg.className = 'empty-message';
                        msg.textContent = 'No employees match your search.';
                        deptBody.appendChild(msg);
                    } else {
                        emptyMessage.style.display = 'block';
                    }
                    // Hide the table
                    const table = deptBody.querySelector('table');
                    if (table) table.style.display = 'none';
                } else {
                    // Show table, hide empty message
                    const table = deptBody.querySelector('table');
                    if (table) table.style.display = '';
                    const msg = deptBody.querySelector('.empty-message');
                    if (msg) msg.style.display = 'none';
                }
            });
        }

        // Mobile toggle (same as other pages)
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.overlay').classList.toggle('active');
        });
        document.querySelector('.overlay')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('active');
            this.classList.remove('active');
        });
    </script>
</body>
</html>