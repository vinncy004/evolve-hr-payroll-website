<?php
// employee_leave.php
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'employee') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
$employeeId = (int) $_SESSION['employee_id'];
$error = $success = '';
$leaveTypes = [];
$leaves = [];

if ($pdo) {
    // Fetch leave types with category, ordered by category and name
    $stmt = $pdo->prepare("SELECT id, name, category FROM leave_types WHERE is_active = 1 ORDER BY category, name");
    $stmt->execute();
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Fetch employee's leave requests
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name AS leave_type_name, lt.category AS leave_category
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = :id
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute(['id' => $employeeId]);
    $leaves = $stmt->fetchAll(PDO::FETCH_OBJ);
}

// Handle new leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $leave_type_id = (int) ($_POST['leave_type'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    if (!$leave_type_id || !$start_date || !$end_date) {
        $error = 'All fields are required.';
    } else {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $diff = $start->diff($end);
        $days = $diff->days + 1;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, days_requested, reason, status)
                VALUES (:emp, :type, :start, :end, :days, :reason, 'pending')
            ");
            $stmt->execute([
                'emp' => $employeeId,
                'type' => $leave_type_id,
                'start' => $start_date,
                'end' => $end_date,
                'days' => $days,
                'reason' => $reason
            ]);
            $success = 'Leave request submitted.';
            // Refresh leave list after submission
            $stmt = $pdo->prepare("
                SELECT lr.*, lt.name AS leave_type_name, lt.category AS leave_category
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = :id
                ORDER BY lr.created_at DESC
            ");
            $stmt->execute(['id' => $employeeId]);
            $leaves = $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// Build category‑grouped array for dropdown
$groupedTypes = [];
foreach ($leaveTypes as $lt) {
    $cat = $lt->category ?? 'Uncategorized';
    $groupedTypes[$cat][] = $lt;
}

$page_title = 'My Leave';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Leave</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if ($error): ?>
                <div style="background:#fed7d7; color:#c53030; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Apply Leave Card -->
            <div class="card">
                <div class="card-header"><h2>Apply for Leave</h2></div>
                <form method="POST">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div>
                            <label>Leave Type</label>
                            <select name="leave_type" style="width:100%; padding:8px;" required>
                                <option value="">Select</option>
                                <?php foreach ($groupedTypes as $category => $types): ?>
                                    <optgroup label="<?= htmlspecialchars($category) ?>">
                                        <?php foreach ($types as $lt): ?>
                                            <option value="<?= $lt->id ?>"><?= htmlspecialchars($lt->name) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label>Start Date</label><input type="date" name="start_date" style="width:100%; padding:8px;" required></div>
                        <div><label>End Date</label><input type="date" name="end_date" style="width:100%; padding:8px;" required></div>
                        <div style="grid-column:1/3;"><label>Reason</label><textarea name="reason" style="width:100%; padding:8px;"></textarea></div>
                    </div>
                    <button type="submit" name="apply" class="btn btn-primary" style="margin-top:10px;">Submit</button>
                </form>
            </div>

            <!-- My Requests Card -->
            <div class="card">
                <div class="card-header">
                    <h2>My Requests</h2>
                    <!-- Optional: category filter for the list -->
                    <div>
                        <label>Filter by Category:</label>
                        <select id="categoryFilter" style="padding:8px; border-radius:var(--radius); border:1px solid #ddd;">
                            <option value="all">All</option>
                            <?php foreach (array_keys($groupedTypes) as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <table class="employee-table" id="leaveTable">
                        <thead>
                            <tr><th>Type</th><th>Category</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $lv): ?>
                            <tr data-category="<?= htmlspecialchars($lv->leave_category ?? 'Uncategorized') ?>">
                                <td><?= htmlspecialchars($lv->leave_type_name) ?></td>
                                <td><?= htmlspecialchars($lv->leave_category ?? 'Uncategorized') ?></td>
                                <td><?= $lv->start_date ?></td>
                                <td><?= $lv->end_date ?></td>
                                <td><?= $lv->days_requested ?></td>
                                <td><span class="status <?= $lv->status ?>"><?= ucfirst($lv->status) ?></span></td>
                                <td><?= htmlspecialchars($lv->reason) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay"></div>
    <script>
        // Mobile toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.overlay').classList.toggle('active');
        });
        document.querySelector('.overlay').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('active');
            this.classList.remove('active');
        });

        // Category filter for the leave requests table
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const selected = this.value;
            const rows = document.querySelectorAll('#leaveTable tbody tr');
            rows.forEach(row => {
                const category = row.getAttribute('data-category');
                if (selected === 'all' || category === selected) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>