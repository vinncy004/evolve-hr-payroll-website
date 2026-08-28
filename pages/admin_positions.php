<?php
// admin_positions.php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$error = $success = '';
$positions = [];
$departments = [];
$employees = []; // for manager assignment

if ($pdo) {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $positions = $pdo->query("SELECT p.*, d.name AS dept_name FROM positions p LEFT JOIN departments d ON p.department_id = d.id ORDER BY p.title")->fetchAll(PDO::FETCH_OBJ);
    
    // Fetch employees that are NOT already managers (role != 'manager' and role is 'employee')
    // Also exclude those who might be admin/hr (we only want to promote regular employees)
    $stmt = $pdo->query("
        SELECT e.id, e.first_name, e.last_name, e.employee_number
        FROM employees e
        LEFT JOIN employee_users eu ON e.id = eu.employee_id
        WHERE eu.role IS NULL OR eu.role NOT IN ('manager', 'admin', 'hr')
        ORDER BY e.first_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);
}

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ---------- Position CRUD ----------
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $department_id = (int) ($_POST['department_id'] ?? 0);
        if (!$title) { 
            $error = 'Title is required.'; 
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO positions (organization_id, title, description, department_id) VALUES (1, :title, :desc, :dept)");
                    $stmt->execute(['title' => $title, 'desc' => $description, 'dept' => $department_id ?: null]);
                    $success = 'Position added.';
                } else {
                    $id = (int) $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE positions SET title = :title, description = :desc, department_id = :dept WHERE id = :id");
                    $stmt->execute(['title' => $title, 'desc' => $description, 'dept' => $department_id ?: null, 'id' => $id]);
                    $success = 'Position updated.';
                }
            } catch (PDOException $e) { 
                $error = 'Error: ' . $e->getMessage(); 
            }
        }
    } 
    elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM positions WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = 'Position deleted.';
        } catch (PDOException $e) { 
            $error = 'Cannot delete: ' . $e->getMessage(); 
        }
    }
    // ---------- Manager Assignment ----------
    elseif ($action === 'assign_manager') {
        $employee_id = (int) ($_POST['employee_id'] ?? 0);
        $department_id = (int) ($_POST['department_id'] ?? 0);
        
        if (!$employee_id || !$department_id) {
            $error = 'Please select both an employee and a department.';
        } else {
            try {
                // Check if the employee already has a role that is not 'employee' (e.g., admin/hr)
                $stmt = $pdo->prepare("SELECT role FROM employee_users WHERE employee_id = :id");
                $stmt->execute(['id' => $employee_id]);
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                if ($row && in_array($row->role, ['admin', 'hr'])) {
                    $error = 'This employee has an admin/HR role and cannot be assigned as a manager.';
                } else {
                    $pdo->beginTransaction();
                    
                    // Update employee_users role to 'manager'
                    if ($row) {
                        $stmt = $pdo->prepare("UPDATE employee_users SET role = 'manager' WHERE employee_id = :id");
                    } else {
                        // If no record exists, create one (default password? but we assume they have one)
                        // For safety, we'll update or insert; we'll use INSERT ... ON DUPLICATE KEY UPDATE
                        // But since employee_id is unique, we can do:
                        $stmt = $pdo->prepare("
                            INSERT INTO employee_users (employee_id, username, email, password_hash, role, is_active, force_password_change)
                            SELECT :id, employee_number, work_email, 'temp_hash', 'manager', 1, 1
                            FROM employees WHERE id = :id
                            ON DUPLICATE KEY UPDATE role = 'manager'
                        ");
                        $stmt->execute(['id' => $employee_id]);
                    }
                    if (isset($row) && $row) {
                        $stmt->execute(['id' => $employee_id]);
                    }
                    
                    // Update department manager
                    $stmt = $pdo->prepare("UPDATE departments SET manager_id = :mgr WHERE id = :dept");
                    $stmt->execute(['mgr' => $employee_id, 'dept' => $department_id]);
                    
                    $pdo->commit();
                    $success = "Employee assigned as manager for department successfully.";
                    
                    // Refresh the employee list (exclude the newly assigned manager)
                    $stmt = $pdo->query("
                        SELECT e.id, e.first_name, e.last_name, e.employee_number
                        FROM employees e
                        LEFT JOIN employee_users eu ON e.id = eu.employee_id
                        WHERE eu.role IS NULL OR eu.role NOT IN ('manager', 'admin', 'hr')
                        ORDER BY e.first_name
                    ");
                    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Assignment failed: ' . $e->getMessage();
            }
        }
    }
    
    // Refresh positions list after any operation
    if ($pdo) {
        $positions = $pdo->query("SELECT p.*, d.name AS dept_name FROM positions p LEFT JOIN departments d ON p.department_id = d.id ORDER BY p.title")->fetchAll(PDO::FETCH_OBJ);
    }
}

$page_title = 'Manage Positions';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Positions</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?><div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            
            <!-- Positions Management Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Positions</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'"><i class="fas fa-plus"></i> Add Position</button>
                </div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Title</th><th>Department</th><th>Description</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($positions as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p->title) ?></td>
                                <td><?= htmlspecialchars($p->dept_name ?? 'None') ?></td>
                                <td><?= htmlspecialchars($p->description) ?></td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="editPos(<?= htmlspecialchars(json_encode($p)) ?>)">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this position?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p->id ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Assign Managers Card -->
            <div class="card" style="margin-top:30px;">
                <div class="card-header">
                    <h2>Assign Department Managers</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_manager">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div>
                                <label>Select Employee (not already a manager)</label>
                                <select name="employee_id" required style="width:100%; padding:8px;">
                                    <option value="">-- Choose Employee --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp->id ?>"><?= htmlspecialchars($emp->employee_number . ' - ' . $emp->first_name . ' ' . $emp->last_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Assign to Department</label>
                                <select name="department_id" required style="width:100%; padding:8px;">
                                    <option value="">-- Choose Department --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:15px;"><i class="fas fa-user-tag"></i> Assign as Manager</button>
                    </form>
                    <?php if (empty($employees)): ?>
                        <p style="margin-top:10px; color:var(--gray);">All active employees are already managers or have admin/HR roles.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Position Form (hidden) -->
            <div id="addForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Add Position</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div><label>Title *</label><input type="text" name="title" required style="width:100%; padding:8px;"></div>
                            <div><label>Department</label><select name="department_id" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($departments as $d): ?><option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option><?php endforeach; ?></select></div>
                            <div style="grid-column:1/3;"><label>Description</label><textarea name="description" style="width:100%; padding:8px;"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('addForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Edit Position Form (hidden) -->
            <div id="editForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Edit Position</h2></div>
                    <form method="POST" id="editFormInner">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div><label>Title *</label><input type="text" name="title" id="edit_title" required style="width:100%; padding:8px;"></div>
                            <div><label>Department</label><select name="department_id" id="edit_dept" style="width:100%; padding:8px;"><option value="">None</option><?php foreach($departments as $d): ?><option value="<?= $d->id ?>"><?= htmlspecialchars($d->name) ?></option><?php endforeach; ?></select></div>
                            <div style="grid-column:1/3;"><label>Description</label><textarea name="description" id="edit_desc" style="width:100%; padding:8px;"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Update</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('editForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay"></div>
    <script>
        function editPos(pos) {
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('edit_id').value = pos.id;
            document.getElementById('edit_title').value = pos.title;
            document.getElementById('edit_dept').value = pos.department_id || '';
            document.getElementById('edit_desc').value = pos.description || '';
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