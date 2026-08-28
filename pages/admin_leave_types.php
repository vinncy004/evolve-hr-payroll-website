<?php
// admin_leave_types.php
require_once __DIR__ .'/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ .'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$error = $success = '';
$leaveTypes = [];
if ($pdo) {
    $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $days_per_year = (int) ($_POST['days_per_year'] ?? 0);
        $gender_specific = isset($_POST['gender_specific']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (!$code || !$name) { $error = 'Code and Name are required.'; }
        else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO leave_types (organization_id, code, name, days_per_year, gender_specific, is_active) VALUES (1, :code, :name, :days, :gender, :active)");
                    $stmt->execute(['code' => $code, 'name' => $name, 'days' => $days_per_year, 'gender' => $gender_specific, 'active' => $is_active]);
                    $success = 'Leave type added.';
                } else {
                    $id = (int) $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE leave_types SET code = :code, name = :name, days_per_year = :days, gender_specific = :gender, is_active = :active WHERE id = :id");
                    $stmt->execute(['code' => $code, 'name' => $name, 'days' => $days_per_year, 'gender' => $gender_specific, 'active' => $is_active, 'id' => $id]);
                    $success = 'Leave type updated.';
                }
            } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = 'Leave type deleted.';
        } catch (PDOException $e) { $error = 'Cannot delete: ' . $e->getMessage(); }
    }
    // Refresh
    $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Manage Leave Types';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leave Types</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__. '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__. '/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?><div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <div class="card">
                <div class="card-header">
                    <h2>Leave Types</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'"><i class="fas fa-plus"></i> Add Leave Type</button>
                </div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>Code</th><th>Name</th><th>Days/Year</th><th>Gender Specific</th><th>Active</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($leaveTypes as $lt): ?>
                            <tr>
                                <td><?= htmlspecialchars($lt->code) ?></td>
                                <td><?= htmlspecialchars($lt->name) ?></td>
                                <td><?= $lt->days_per_year ?></td>
                                <td><?= $lt->gender_specific ? 'Yes' : 'No' ?></td>
                                <td><?= $lt->is_active ? 'Yes' : 'No' ?></td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="editLeaveType(<?= htmlspecialchars(json_encode($lt)) ?>)">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $lt->id ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="addForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Add Leave Type</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div><label>Code *</label><input type="text" name="code" required style="width:100%; padding:8px;"></div>
                            <div><label>Name *</label><input type="text" name="name" required style="width:100%; padding:8px;"></div>
                            <div><label>Days Per Year</label><input type="number" name="days_per_year" value="0" style="width:100%; padding:8px;"></div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label>Gender Specific</label>
                                <input type="checkbox" name="gender_specific" value="1">
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label>Active</label>
                                <input type="checkbox" name="is_active" value="1" checked>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('addForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>
            <div id="editForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Edit Leave Type</h2></div>
                    <form method="POST" id="editFormInner">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div><label>Code *</label><input type="text" name="code" id="edit_code" required style="width:100%; padding:8px;"></div>
                            <div><label>Name *</label><input type="text" name="name" id="edit_name" required style="width:100%; padding:8px;"></div>
                            <div><label>Days Per Year</label><input type="number" name="days_per_year" id="edit_days" style="width:100%; padding:8px;"></div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label>Gender Specific</label>
                                <input type="checkbox" name="gender_specific" id="edit_gender" value="1">
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label>Active</label>
                                <input type="checkbox" name="is_active" id="edit_active" value="1">
                            </div>
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
        function editLeaveType(lt) {
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('edit_id').value = lt.id;
            document.getElementById('edit_code').value = lt.code;
            document.getElementById('edit_name').value = lt.name;
            document.getElementById('edit_days').value = lt.days_per_year;
            document.getElementById('edit_gender').checked = lt.gender_specific == 1;
            document.getElementById('edit_active').checked = lt.is_active == 1;
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