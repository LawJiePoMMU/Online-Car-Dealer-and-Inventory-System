<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-circle-notch logo-icon"></i>
        <h2>Admin</h2>
    </div>

    <div class="sidebar-section">NAVIGATION</div>

    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="manage cars.php" class="<?= ($current_page == 'manage cars.php') ? 'active' : ''; ?>">
                <i class="fas fa-car"></i> <span>Manage Cars</span>
            </a>
        </li>
        <li>
            <a href="specifications.php" class="<?= ($current_page == 'specifications.php') ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> <span>Specifications</span>
            </a>
        </li>
        <li>
            <a href="reservations.php" class="<?= ($current_page == 'reservations.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> <span>Reservations</span>
            </a>
        </li>
        <li>
            <a href="inventory.php" class="<?= ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> <span>Inventory</span>
            </a>
        </li>
        <li>
            <a href="users.php" class="<?= ($current_page == 'users.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> <span>Users</span>
            </a>
        </li>
        <li>
            <a href="chat.php" class="<?= ($current_page == 'chat.php') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> <span>Chat</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-section">GENERAL</div>
    <ul class="sidebar-menu">
        <li>
            <a href="settings.php" class="<?= ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </a>
        </li>
        <li>
            <a href="notifications.php" class="<?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <span>Notifications</span>
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
        </a>
    </div>
</aside>