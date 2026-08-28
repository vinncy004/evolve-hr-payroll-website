<?php
$current_file = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? '';
$role = $_SESSION['role'] ?? '';

// Determine dashboard type for menu
if ($user_type === 'employer' && $role === 'admin') {
    $dash = 'admin';
} elseif ($user_type === 'employer' && $role === 'hr') {
    $dash = 'hr';
} elseif ($user_type === 'employee' && $role === 'manager') {
    $dash = 'manager';
} elseif ($user_type === 'employee' && $role === 'admin') {
    $dash = 'admin';
} elseif ($user_type === 'employee' && $role === 'hr') {
    $dash = 'hr';
} else {
    $dash = 'employee';
}

$menu = [];
switch ($dash) {
    case 'admin':
        $menu = [
            ['url' => 'admin_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['url' => 'admin_employees.php', 'icon' => 'fa-users', 'label' => 'Employees'],
            ['url' => 'admin_departments.php', 'icon' => 'fa-building', 'label' => 'Departments'],
            ['url' => 'admin_positions.php', 'icon' => 'fa-briefcase', 'label' => 'Positions'],
            ['url' => 'admin_leave_types.php', 'icon' => 'fa-calendar-alt', 'label' => 'Leave Types'],
            ['url' => 'admin_payrolls.php', 'icon' => 'fa-money-bill-wave', 'label' => 'Payrolls'],
            ['url' => 'admin_reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
        ];
        break;
    case 'hr':
        $menu = [
            ['url' => 'hr_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['url' => 'hr_employees.php', 'icon' => 'fa-users', 'label' => 'Employees'],
            ['url' => 'hr_leave_requests.php', 'icon' => 'fa-calendar-check', 'label' => 'Leave Approvals'],
            ['url' => 'hr_payrolls.php', 'icon' => 'fa-money-bill-wave', 'label' => 'Payrolls'],
            ['url' => 'hr_reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
        ];
        break;
    case 'manager':
        $menu = [
            ['url' => 'manager_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['url' => 'manager_team.php', 'icon' => 'fa-users', 'label' => 'My Team'],
            ['url' => 'manager_leave_requests.php', 'icon' => 'fa-calendar-alt', 'label' => 'Leave Approvals'],
            ['url' => 'manager_attendance.php', 'icon' => 'fa-clock', 'label' => 'Team Attendance'],
            ['url' => 'manager_reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
        ];
        break;
    default:
        $menu = [
            ['url' => 'employee_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['url' => 'employee_profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
            ['url' => 'employee_leave.php', 'icon' => 'fa-calendar-alt', 'label' => 'Leave'],
            ['url' => 'employee_attendance.php', 'icon' => 'fa-clock', 'label' => 'Attendance'],
            ['url' => 'employee_payroll.php', 'icon' => 'fa-wallet', 'label' => 'Payroll'],
        ];
        break;
}
?>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
        <i class="fas fa-times"></i>
    </button>

    <div class="logo">
        <div class="logo-icon">E</div>
        <div class="logo-text">Evolve</div>
    </div>
    <ul class="nav-links">
        <?php foreach ($menu as $item): ?>
            <li>
                <a href="<?= $item['url'] ?>" <?= ($current_file === $item['url']) ? 'class="active"' : '' ?>>
                    <i class="fas <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
    </ul>
</div>

<!-- OVERLAY -->
<div class="overlay" id="overlay"></div>

<!-- ===== FIXED TOGGLE SCRIPT ===== -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const closeBtn = document.getElementById('sidebarClose');

        function isMobile() {
            return window.innerWidth <= 576;
        }

        function openSidebar() {
            document.body.classList.add('sidebar-open');
            if (isMobile()) {
                overlay.classList.add('active');
                document.body.classList.add('no-overflow');
            }
        }

        function closeSidebar() {
            document.body.classList.remove('sidebar-open');
            overlay.classList.remove('active');
            document.body.classList.remove('no-overflow');
        }

        function toggleSidebar() {
            if (document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // ---- Toggle on header-left (entire area) ----
        const headerToggle = document.getElementById('headerToggle');
        if (headerToggle) {
            headerToggle.addEventListener('click', function(e) {
                // Prevent toggling if click is on a link inside (none) or a button that might have its own handler
                // We'll just toggle.
                toggleSidebar();
            });
        }

        // ---- Also attach to the menu-toggle button directly (in case the parent click fails) ----
        const menuBtn = document.querySelector('.menu-toggle');
        if (menuBtn) {
            menuBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // prevent double toggle from parent
                toggleSidebar();
            });
        }

        // ---- Close button ----
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        // ---- Overlay ----
        overlay.addEventListener('click', closeSidebar);

        // ---- Escape key ----
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // ---- Resize ----
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (!isMobile()) {
                    overlay.classList.remove('active');
                    document.body.classList.remove('no-overflow');
                } else {
                    if (document.body.classList.contains('sidebar-open')) {
                        overlay.classList.add('active');
                        document.body.classList.add('no-overflow');
                    }
                }
            }, 200);
        });

        // ---- Close on nav link click (mobile only) ----
        document.querySelectorAll('.nav-links a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        });

        // ---- Initial state: open on desktop, closed on mobile ----
        if (isMobile()) {
            closeSidebar();
        } else {
            openSidebar();
        }

        document.body.style.overflowX = 'hidden';
    });
</script>

<!-- STYLES FOR SIDEBAR CLOSE BUTTON -->
<style>
    .sidebar-close {
        display: block;
        position: absolute;
        top: 14px;
        right: 18px;
        background: none;
        border: none;
        font-size: 24px;
        color: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        z-index: 10;
        padding: 4px 8px;
        border-radius: var(--radius);
        transition: var(--ease);
    }
    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.08);
        color: white;
    }

    @media (max-width: 576px) {
        body.sidebar-open .main-content {
            margin-left: 0;
        }
    }
</style>