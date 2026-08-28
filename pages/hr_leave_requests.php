<?php
// hr_leave_requests.php – approve/reject all leaves
require_once __DIR__.'/../includes/auth.php';
if ($_SESSION['role'] !== 'hr') { header('Location: employee_dashboard.php'); exit; }
require_once __DIR__.'/../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $action = $_GET['action'];
    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        try {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $status, 'id' => $id]);
            header('Location: hr_leave_requests.php?msg=updated');
            exit;
        } catch (PDOException $e) { /* ignore */ }
    }
}

$leaveRequests = [];
if ($pdo) {
    $stmt = $pdo->query("
        SELECT lr.*, e.first_name, e.last_name, e.employee_number, lt.name AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        ORDER BY lr.status = 'pending' DESC, lr.created_at DESC
    ");
    $leaveRequests = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$page_title = 'Leave Approvals';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leave Approvals</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__.'/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__.'/../includes/header.php'; ?>
        <div class="dashboard">
            <?php if (isset($_GET['msg'])): ?>
                <div style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:15px;">Leave request updated.</div>
            <?php endif; ?>
            <div class="card">
                <div class="card-header"><h2>All Leave Requests</h2></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($leaveRequests as $lr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lr->first_name.' '.$lr->last_name) ?><br><small><?= htmlspecialchars($lr->employee_number) ?></small></td>
                                    <td><?= htmlspecialchars($lr->leave_type_name) ?></td>
                                    <td><?= $lr->start_date ?></td>
                                    <td><?= $lr->end_date ?></td>
                                    <td><?= $lr->days_requested ?></td>
                                    <td><?= htmlspecialchars($lr->reason) ?></td>
                                    <td><span class="status <?= $lr->status ?>"><?= ucfirst($lr->status) ?></span></td>
                                    <td>
                                        <?php if ($lr->status === 'pending'): ?>
                                            <a href="hr_leave_requests.php?action=approve&id=<?= $lr->id ?>" class="btn btn-success btn-sm">Approve</a>
                                            <a href="hr_leave_requests.php?action=reject&id=<?= $lr->id ?>" class="btn btn-danger btn-sm">Reject</a>
                                        <?php else: ?>
                                            <span class="status <?= $lr->status ?>">Done</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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