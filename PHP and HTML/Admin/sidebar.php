<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="mySidebar">
    <div class="sidebar-brand"
        style="display: flex; align-items: center; justify-content: center; position: relative; border-bottom: 1px solid #374151; padding-bottom: 20px; margin-bottom: 10px;">
        <h2 style="margin: 0;">ADMIN</h2>
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
            <a href="reservation.php" class="<?= ($current_page == 'reservation.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> <span>Reservations</span>
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
        <li>
            <a href="reservations history.php" class="<?= ($current_page == 'reservations history.php') ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span>Reservations History</span>
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