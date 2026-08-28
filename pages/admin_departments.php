<?php
// admin_departments.php
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$error = $success = '';
$departments = [];
if ($pdo) {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        if (!$name) { $error = 'Name is required.'; }
        else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO departments (organization_id, name) VALUES (1, :name)");
                    $stmt->execute(['name' => $name]);
                    $success = 'Department added.';
                } else {
                    $id = (int) $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE departments SET name = :name WHERE id = :id");
                    $stmt->execute(['name' => $name, 'id' => $id]);
                    $success = 'Department updated.';
                }
            } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = 'Department deleted.';
        } catch (PDOException $e) { $error = 'Cannot delete: ' . $e->getMessage(); }
    }
    // Refresh
    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Manage Departments';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Departments</title>
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
                    <h2>Departments</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'"><i class="fas fa-plus"></i> Add Department</button>
                </div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($departments as $d): ?>
                            <tr>
                                <td><?= $d->id ?></td>
                                <td><?= htmlspecialchars($d->name) ?></td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="editDept(<?= $d->id ?>, '<?= htmlspecialchars($d->name) ?>')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $d->id ?>">
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
                    <div class="card-header"><h2>Add Department</h2></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div><label>Department Name</label><input type="text" name="name" required style="width:100%; padding:8px;"></div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('addForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>
            <div id="editForm" style="display:none; margin-top:20px;">
                <div class="card">
                    <div class="card-header"><h2>Edit Department</h2></div>
                    <form method="POST" id="editFormInner">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div><label>Department Name</label><input type="text" name="name" id="edit_name" required style="width:100%; padding:8px;"></div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Update</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('editForm').style.display='none'">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay"></div>
    <script>
        function editDept(id, name) {
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
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