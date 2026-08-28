<?php
// admin_employees.php – full CRUD
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$error = $success = '';
$employees = [];
$departments = [];
$positions = [];
$managers = [];

if ($pdo) {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $positions = $pdo->query("SELECT id, title FROM positions ORDER BY title")->fetchAll(PDO::FETCH_OBJ);
    $managers = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM employees WHERE employment_status='active' ORDER BY first_name")->fetchAll(PDO::FETCH_OBJ);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $employee_number = trim($_POST['employee_number']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['work_email']);
        $phone = trim($_POST['phone']);
        $department_id = (int) ($_POST['department_id'] ?? 0);
        $position_id = (int) ($_POST['position_id'] ?? 0);
        $manager_id = (int) ($_POST['manager_id'] ?? 0);
        $hire_date = $_POST['hire_date'] ?? null;
        $basic_salary = (float) ($_POST['basic_salary'] ?? 0);
        $employment_status = $_POST['employment_status'] ?? 'active';
        $role = $_POST['role'] ?? 'employee';
        $password = $_POST['password'] ?? '';

        if (!$employee_number || !$first_name || !$last_name || !$email) {
            $error = 'Employee Number, Name, and Email are required.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (organization_id, employee_number, first_name, last_name, work_email, phone, department_id, position_id, manager_id, hire_date, basic_salary, employment_status)
                        VALUES (1, :emp_no, :first, :last, :email, :phone, :dept, :pos, :mgr, :hire, :salary, :status)
                    ");
                    $stmt->execute([
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
                    ]);
                    $employee_id = $pdo->lastInsertId();
                    if ($role && $password) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO employee_users (employee_id, username, email, password_hash, role, is_active, force_password_change)
                            VALUES (:emp, :username, :email, :hash, :role, 1, 1)
                        ");
                        $stmt->execute([
                            'emp' => $employee_id,
                            'username' => $employee_number,
                            'email' => $email,
                            'hash' => $hash,
                            'role' => $role
                        ]);
                    }
                    $success = 'Employee added successfully.';
                } else {
                    $id = (int) $_POST['id'];
                    $stmt = $pdo->prepare("
                        UPDATE employees SET
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
                            employment_status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
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
                    ]);
                    if ($role && $password) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE employee_users SET role = :role, password_hash = :hash, force_password_change = 1
                            WHERE employee_id = :emp
                        ");
                        $stmt->execute(['role' => $role, 'hash' => $hash, 'emp' => $id]);
                    } elseif ($role) {
                        $stmt = $pdo->prepare("UPDATE employee_users SET role = :role WHERE employee_id = :emp");
                        $stmt->execute(['role' => $role, 'emp' => $id]);
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
    <?php include __DIR__. '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__. '/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?>
                <div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-header">
                    <h2>Employees</h2>
                    <div>
                        <!-- NEW: Departments button -->
                        <a href="department_employees.php" class="btn btn-secondary" style="margin-right:10px;">
                            <i class="fas fa-building"></i> Departments
                        </a>
                        <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'">
                            <i class="fas fa-plus"></i> Add Employee
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Position</th><th>Manager</th><th>Status</th><th>Role</th><th>Actions</th></tr></thead>
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
                                    <td><?= htmlspecialchars($emp->user_role ?? 'No User') ?></td>
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
                            <div><label>Employee Number *</label><input type="text" name="employee_number" required style="width:100%; padding:8px;"></div>
                            <div><label>First Name *</label><input type="text" name="first_name" required style="width:100%; padding:8px;"></div>
                            <div><label>Last Name *</label><input type="text" name="last_name" required style="width:100%; padding:8px;"></div>
                            <div><label>Work Email *</label><input type="email" name="work_email" required style="width:100%; padding:8px;"></div>
                            <div><label>Phone</label><input type="text" name="phone" style="width:100%; padding:8px;"></div>
                            <div><label>Department</label><select name="department_id" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($departments as $d): ?><option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option><?php endforeach; ?></select></div>
                            <div><label>Position</label><select name="position_id" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= $p->id ?>"><?= htmlspecialchars($p->title) ?></option><?php endforeach; ?></select></div>
                            <div><label>Manager</label><select name="manager_id" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($managers as $m): ?><option value="<?= $m->id ?>"><?= htmlspecialchars($m->full_name) ?></option><?php endforeach; ?></select></div>
                            <div><label>Hire Date</label><input type="date" name="hire_date" style="width:100%; padding:8px;"></div>
                            <div><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" value="0" style="width:100%; padding:8px;"></div>
                            <div><label>Employment Status</label><select name="employment_status" style="width:100%; padding:8px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option><option value="on_leave">On Leave</option></select></div>
                            <div><label>Role</label><select name="role" style="width:100%; padding:8px;"><option value="employee">Employee</option><option value="manager">Manager</option><option value="hr">HR</option><option value="admin">Admin</option></select></div>
                            <div><label>Temporary Password</label><input type="text" name="password" placeholder="Leave blank to not create user" style="width:100%; padding:8px;"></div>
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
                            <div><label>Phone</label><input type="text" name="phone" id="edit_phone" style="width:100%; padding:8px;"></div>
                            <div><label>Department</label><select name="department_id" id="edit_dept" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($departments as $d): ?><option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option><?php endforeach; ?></select></div>
                            <div><label>Position</label><select name="position_id" id="edit_pos" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= $p->id ?>"><?= htmlspecialchars($p->title) ?></option><?php endforeach; ?></select></div>
                            <div><label>Manager</label><select name="manager_id" id="edit_mgr" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($managers as $m): ?><option value="<?= $m->id ?>"><?= htmlspecialchars($m->full_name) ?></option><?php endforeach; ?></select></div>
                            <div><label>Hire Date</label><input type="date" name="hire_date" id="edit_hire" style="width:100%; padding:8px;"></div>
                            <div><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" id="edit_salary" style="width:100%; padding:8px;"></div>
                            <div><label>Employment Status</label><select name="employment_status" id="edit_status" style="width:100%; padding:8px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option><option value="on_leave">On Leave</option></select></div>
                            <div><label>Role</label><select name="role" id="edit_role" style="width:100%; padding:8px;"><option value="employee">Employee</option><option value="manager">Manager</option><option value="hr">HR</option><option value="admin">Admin</option></select></div>
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
        function editEmployee(emp) {
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('edit_id').value = emp.id;
            document.getElementById('edit_emp_no').value = emp.employee_number;
            document.getElementById('edit_first').value = emp.first_name;
            document.getElementById('edit_last').value = emp.last_name;
            document.getElementById('edit_email').value = emp.work_email;
            document.getElementById('edit_phone').value = emp.phone || '';
            document.getElementById('edit_dept').value = emp.department_id || '';
            document.getElementById('edit_pos').value = emp.position_id || '';
            document.getElementById('edit_mgr').value = emp.manager_id || '';
            document.getElementById('edit_hire').value = emp.hire_date || '';
            document.getElementById('edit_salary').value = emp.basic_salary || 0;
            document.getElementById('edit_status').value = emp.employment_status;
            document.getElementById('edit_role').value = emp.user_role || 'employee';
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
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