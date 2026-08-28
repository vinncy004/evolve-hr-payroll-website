<?php
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role_display = ucfirst($_SESSION['role'] ?? '');
$initials = strtoupper(substr($first_name,0,1) . substr($last_name,0,1));
?>
<div class="header">
    <div class="header-left" id="headerToggle">
        <button class="menu-toggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
        <h1><?= $page_title ?? 'Dashboard' ?></h1>
    </div>
    <div class="header-right">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search...">
        </div>
        <div class="user-profile">
            <div class="user-avatar"><?= $initials ?: 'U' ?></div>
            <div class="user-meta">
                <span class="user-name"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></span>
                <span class="user-role"><?= $role_display ?></span>
            </div>
        </div>
    </div>
</div>