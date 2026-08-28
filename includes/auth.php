<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php');
    exit;
}
// Optional: function to require specific roles
function requireRole($allowed_roles = []) {
    if (empty($allowed_roles)) return true;
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Redirect based on user_type
        if ($_SESSION['user_type'] === 'employee') {
            if ($_SESSION['role'] === 'manager') {
                header('Location: manager_dashboard.php');
            } elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'hr') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: employee_dashboard.php');
            }
        } else {
            header('Location: admin_dashboard.php'); // employer fallback
        }
        exit;
    }
}
?>