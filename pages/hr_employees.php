<?php
// hr_employees.php – only HR can manage employees
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'hr') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$error = $success = '';
$employees = [];
$departments = [];
$positions = [];
$managers = [];

/**
 * Validate and sanitize a Kenyan phone number.
 */
function validatePhone($phone) {
    $phone = trim($phone);
    if (empty($phone)) return '';
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
    if (preg_match('/^\+254\d{9}$/', $cleaned)) return $cleaned;
    if (preg_match('/^0\d{9}$/', $cleaned)) return $cleaned;
    if (preg_match('/^[7-9]\d{8}$/', $cleaned)) return '0' . $cleaned;
    return false;
}

// Check if net_salary column exists
$netSalaryExists = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'net_salary'");
    $netSalaryExists = $stmt->fetch() !== false;
} catch (PDOException $e) {}

if ($pdo) {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $positions = $pdo->query("SELECT id, title FROM positions ORDER BY title")->fetchAll(PDO::FETCH_OBJ);
    
    // Fetch managers with their department(s)
    $stmt = $pdo->query("
        SELECT e.id, CONCAT(e.first_name,' ',e.last_name) AS full_name, d.id AS dept_id
        FROM employees e
        JOIN employee_users eu ON e.id = eu.employee_id
        LEFT JOIN departments d ON d.manager_id = e.id
        WHERE eu.role = 'manager' AND e.employment_status = 'active'
        ORDER BY e.first_name
    ");
    $managerRows = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    // Group managers by ID and collect dept_ids
    $managerMap = [];
    foreach ($managerRows as $row) {
        if (!isset($managerMap[$row->id])) {
            $managerMap[$row->id] = [
                'full_name' => $row->full_name,
                'dept_ids' => []
            ];
        }
        if ($row->dept_id) {
            $managerMap[$row->id]['dept_ids'][] = $row->dept_id;
        }
    }
    foreach ($managerMap as $id => $data) {
        $obj = new stdClass();
        $obj->id = $id;
        $obj->full_name = $data['full_name'];
        $obj->dept_ids = $data['dept_ids'];
        $managers[] = $obj;
    }
    
    if (empty($managers)) {
        $error = 'No managers found. Please assign managers first using the Admin panel.';
    }
}

// --------------------- POST Handling ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $employee_number = trim($_POST['employee_number'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['work_email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department_id = (int) ($_POST['department_id'] ?? 0);
        $position_id = (int) ($_POST['position_id'] ?? 0);
        $manager_id = (int) ($_POST['manager_id'] ?? 0);
        $hire_date = $_POST['hire_date'] ?? null;
        $basic_salary = (float) ($_POST['basic_salary'] ?? 0);
        $net_salary = (float) ($_POST['net_salary'] ?? 0);
        $employment_status = $_POST['employment_status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        $errors = [];
        if (empty($first_name)) $errors[] = 'First Name is required.';
        if (empty($last_name)) $errors[] = 'Last Name is required.';
        if (empty($email)) $errors[] = 'Work Email is required.';
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

        if (!empty($phone)) {
            $validatedPhone = validatePhone($phone);
            if ($validatedPhone === false) {
                $errors[] = 'Phone number must be 10 digits starting with 0 (e.g., 0712345678) or +254 followed by 9 digits.';
            } else {
                $phone = $validatedPhone;
            }
        }

        if ($action === 'edit' && !empty($employee_number)) {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_number = :no AND id != :id");
            $stmt->execute(['no' => $employee_number, 'id' => (int)$_POST['id']]);
            if ($stmt->fetch()) $errors[] = 'Employee Number already exists.';
        }

        if (!$netSalaryExists && $action === 'add') {
            $errors[] = 'The "net_salary" column is missing. Please run: ALTER TABLE employees ADD COLUMN net_salary DECIMAL(12,2) DEFAULT 0.00 AFTER basic_salary;';
        }

        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            try {
                if ($action === 'add') {
                    $temp_no = 'TEMP-' . uniqid();
                    $columns = "organization_id, employee_number, first_name, last_name, work_email, phone, department_id, position_id, manager_id, hire_date, basic_salary, employment_status";
                    $values = ":org, :emp_no, :first, :last, :email, :phone, :dept, :pos, :mgr, :hire, :salary, :status";
                    $params = [
                        'org' => 1,
                        'emp_no' => $temp_no,
                        'first' => $first_name,
                        'last' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'dept' => $department_id ?: null,
                        'pos' => $position_id ?: null,
                        'mgr' => $manager_id ?: null,
                        'hire' => $hire_date,
                        'salary' => $basic_salary,
                        'status' => $employment_status
                    ];
                    if ($netSalaryExists) {
                        $columns .= ", net_salary";
                        $values .= ", :net";
                        $params['net'] = $net_salary;
                    }
                    $sql = "INSERT INTO employees ($columns) VALUES ($values)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $employee_id = $pdo->lastInsertId();

                    // Generate employee number using department name (first 3 letters) or 'EMP'
                    $prefix = 'EMP';
                    if ($department_id) {
                        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = :id");
                        $stmt->execute(['id' => $department_id]);
                        $dept = $stmt->fetch(PDO::FETCH_OBJ);
                        if ($dept) {
                            $prefix = strtoupper(substr($dept->name, 0, 3));
                        }
                    }
                    $generated_no = $prefix . '-' . $employee_id;
                    $stmt = $pdo->prepare("UPDATE employees SET employee_number = :no WHERE id = :id");
                    $stmt->execute(['no' => $generated_no, 'id' => $employee_id]);

                    $hash = password_hash($password ?: 'P@ssword1', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_users (employee_id, username, email, password_hash, role, is_active, force_password_change)
                        VALUES (:emp, :username, :email, :hash, 'employee', 1, 1)
                    ");
                    $stmt->execute([
                        'emp' => $employee_id,
                        'username' => $generated_no,
                        'email' => $email,
                        'hash' => $hash
                    ]);
                    $success = "Employee added successfully. Employee Number: $generated_no";
                } else { // edit
                    $id = (int) $_POST['id'];
                    $sql = "UPDATE employees SET 
                                employee_number = :emp_no,
                                first_name = :first,
                                last_name = :last,
                                work_email = :email,
                                phone = :phone,
                                department_id = :dept,
                                position_id = :pos,
                                manager_id = :mgr,
                                hire_date = :hire,
                                basic_salary = :salary,
                                employment_status = :status";
                    $params = [
                        'id' => $id,
                        'emp_no' => $employee_number,
                        'first' => $first_name,
                        'last' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'dept' => $department_id ?: null,
                        'pos' => $position_id ?: null,
                        'mgr' => $manager_id ?: null,
                        'hire' => $hire_date,
                        'salary' => $basic_salary,
                        'status' => $employment_status
                    ];
                    if ($netSalaryExists) {
                        $sql .= ", net_salary = :net";
                        $params['net'] = $net_salary;
                    }
                    $sql .= " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($password) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE employee_users SET password_hash = :hash, force_password_change = 1 WHERE employee_id = :emp");
                        $stmt->execute(['hash' => $hash, 'emp' => $id]);
                    }
                    $success = 'Employee updated successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM employee_users WHERE employee_id = :id");
            $stmt->execute(['id' => $id]);
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $pdo->commit();
            $success = 'Employee deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Delete failed: ' . $e->getMessage();
        }
    }
}

// Fetch employees
if ($pdo) {
    $stmt = $pdo->query("
        SELECT e.*, d.name AS department_name, p.title AS position_title,
               CONCAT(m.first_name,' ',m.last_name) AS manager_name,
               eu.role AS user_role
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN employees m ON e.manager_id = m.id
        LEFT JOIN employee_users eu ON e.id = eu.employee_id
        ORDER BY e.first_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Manage Employees';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employees</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?><div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <div class="card">
                <div class="card-header">
                    <h2>Employees</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'"><i class="fas fa-plus"></i> Add Employee</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Position</th><th>Manager</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($emp->employee_number) ?></td>
                                    <td><?= htmlspecialchars($emp->first_name.' '.$emp->last_name) ?></td>
                                    <td><?= htmlspecialchars($emp->work_email) ?></td>
                                    <td><?= htmlspecialchars($emp->department_name ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($emp->position_title ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($emp->manager_name ?? 'None') ?></td>
                                    <td><span class="status <?= $emp->employment_status ?>"><?= ucfirst($emp->employment_status) ?></span></td>
                                    <td>
                                        <button class="btn btn-outline btn-sm" onclick="editEmployee(<?= htmlspecialchars(json_encode($emp)) ?>)">Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this employee?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $emp->id ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Form -->
            <div id="addForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Add New Employee</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div style="grid-column:1/3; background:#f7fafc; padding:10px; border-radius:8px; color:#4a5568;">
                                <i class="fas fa-info-circle"></i> Employee Number will be auto‑generated (e.g., FIN‑7) after saving.
                            </div>
                            <div><label>First Name *</label><input type="text" name="first_name" required style="width:100%; padding:8px;"></div>
                            <div><label>Last Name *</label><input type="text" name="last_name" required style="width:100%; padding:8px;"></div>
                            <div><label>Work Email *</label><input type="email" name="work_email" required style="width:100%; padding:8px;"></div>
                            <div><label>Phone</label><input type="text" name="phone" placeholder="e.g., 0712345678 or +254712345678" style="width:100%; padding:8px;"></div>
                            <div><label>Department</label>
                                <select name="department_id" id="add_department" style="width:100%; padding:8px;">
                                    <option value="">None</option>
                                    <?php foreach($departments as $d): ?>
                                        <option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Position</label><select name="position_id" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= $p->id ?>"><?= htmlspecialchars($p->title) ?></option><?php endforeach; ?></select></div>
                            <div><label>Manager</label>
                                <select name="manager_id" id="add_manager" style="width:100%; padding:8px;">
                                    <option value="">None</option>
                                    <?php foreach($managers as $m): ?>
                                        <option value="<?= $m->id ?>" data-dept="<?= implode(',', $m->dept_ids) ?>">
                                            <?= htmlspecialchars($m->full_name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Hire Date</label><input type="date" name="hire_date" style="width:100%; padding:8px;"></div>
                            <div><label>Gross Salary (Basic)</label><input type="number" step="0.01" name="basic_salary" value="0" style="width:100%; padding:8px;"></div>
                            <div><label>Net Salary</label><input type="number" step="0.01" name="net_salary" value="0" style="width:100%; padding:8px;"></div>
                            <div><label>Employment Status</label><select name="employment_status" style="width:100%; padding:8px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option><option value="on_leave">On Leave</option></select></div>
                            <div><label>Password</label><input type="text" name="password" placeholder="Leave blank for default (P@ssword1)" style="width:100%; padding:8px;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:15px;">Save Employee</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('addForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Edit Form -->
            <div id="editForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Edit Employee</h2></div>
                    <form method="POST" id="editFormInner">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div><label>Employee Number *</label><input type="text" name="employee_number" id="edit_emp_no" required style="width:100%; padding:8px;"></div>
                            <div><label>First Name *</label><input type="text" name="first_name" id="edit_first" required style="width:100%; padding:8px;"></div>
                            <div><label>Last Name *</label><input type="text" name="last_name" id="edit_last" required style="width:100%; padding:8px;"></div>
                            <div><label>Work Email *</label><input type="email" name="work_email" id="edit_email" required style="width:100%; padding:8px;"></div>
                            <div><label>Phone</label><input type="text" name="phone" id="edit_phone" placeholder="e.g., 0712345678 or +254712345678" style="width:100%; padding:8px;"></div>
                            <div><label>Department</label>
                                <select name="department_id" id="edit_department" style="width:100%; padding:8px;">
                                    <option value="">None</option>
                                    <?php foreach($departments as $d): ?>
                                        <option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Position</label><select name="position_id" id="edit_pos" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= $p->id ?>"><?= htmlspecialchars($p->title) ?></option><?php endforeach; ?></select></div>
                            <div><label>Manager</label>
                                <select name="manager_id" id="edit_manager" style="width:100%; padding:8px;">
                                    <option value="">None</option>
                                    <?php foreach($managers as $m): ?>
                                        <option value="<?= $m->id ?>" data-dept="<?= implode(',', $m->dept_ids) ?>">
                                            <?= htmlspecialchars($m->full_name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Hire Date</label><input type="date" name="hire_date" id="edit_hire" style="width:100%; padding:8px;"></div>
                            <div><label>Gross Salary (Basic)</label><input type="number" step="0.01" name="basic_salary" id="edit_salary" style="width:100%; padding:8px;"></div>
                            <div><label>Net Salary</label><input type="number" step="0.01" name="net_salary" id="edit_net" style="width:100%; padding:8px;"></div>
                            <div><label>Employment Status</label><select name="employment_status" id="edit_status" style="width:100%; padding:8px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option><option value="on_leave">On Leave</option></select></div>
                            <div><label>New Password (optional)</label><input type="text" name="password" placeholder="Leave blank to keep current" style="width:100%; padding:8px;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:15px;">Update Employee</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('editForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay"></div>
    <script>
        // Manager dropdown filtering
        function filterManagers(departmentSelectId, managerSelectId) {
            const deptSelect = document.getElementById(departmentSelectId);
            const mgrSelect = document.getElementById(managerSelectId);
            if (!deptSelect || !mgrSelect) return;
            
            const selectedDept = deptSelect.value;
            const options = mgrSelect.options;
            
            for (let i = 0; i < options.length; i++) {
                const opt = options[i];
                if (opt.value === '') {
                    opt.style.display = '';
                    continue;
                }
                const deptIds = (opt.getAttribute('data-dept') || '').split(',').filter(id => id !== '');
                if (!selectedDept) {
                    opt.style.display = '';
                } else {
                    opt.style.display = deptIds.includes(selectedDept) ? '' : 'none';
                }
            }
            
            // If selected manager is hidden, reset to "None"
            const selectedOption = mgrSelect.options[mgrSelect.selectedIndex];
            if (selectedOption && selectedOption.style.display === 'none') {
                mgrSelect.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const addDept = document.getElementById('add_department');
            const editDept = document.getElementById('edit_department');
            if (addDept) {
                addDept.addEventListener('change', function() {
                    filterManagers('add_department', 'add_manager');
                });
                filterManagers('add_department', 'add_manager');
            }
            if (editDept) {
                editDept.addEventListener('change', function() {
                    filterManagers('edit_department', 'edit_manager');
                });
                filterManagers('edit_department', 'edit_manager');
            }
        });

        function editEmployee(emp) {
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('edit_id').value = emp.id;
            document.getElementById('edit_emp_no').value = emp.employee_number;
            document.getElementById('edit_first').value = emp.first_name;
            document.getElementById('edit_last').value = emp.last_name;
            document.getElementById('edit_email').value = emp.work_email;
            document.getElementById('edit_phone').value = emp.phone || '';
            document.getElementById('edit_department').value = emp.department_id || '';
            document.getElementById('edit_pos').value = emp.position_id || '';
            document.getElementById('edit_manager').value = emp.manager_id || '';
            document.getElementById('edit_hire').value = emp.hire_date || '';
            document.getElementById('edit_salary').value = emp.basic_salary || 0;
            document.getElementById('edit_net').value = emp.net_salary || 0;
            document.getElementById('edit_status').value = emp.employment_status;
            filterManagers('edit_department', 'edit_manager');
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }

        // Mobile toggle
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