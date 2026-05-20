<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$unread_count = 0;
$chat_unread_count = 0;

if (isset($conn) && $current_user_id > 0) {
    $badge_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND notification_status = 'unread'");
    $badge_stmt->bind_param("i", $current_user_id);
    $badge_stmt->execute();
    $badge_stmt->bind_result($unread_count);
    $badge_stmt->fetch();
    $badge_stmt->close();
    $chat_stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM messages m 
        WHERE m.receiver_id = ? 
        AND m.is_read = 0 
        AND m.sender_id NOT IN (
            SELECT muted_target_id FROM muted_chats WHERE user_id = ? AND chat_type = 'user'
        )
    ");
    $chat_stmt->bind_param("ii", $current_user_id, $current_user_id);
    $chat_stmt->execute();
    $chat_stmt->bind_result($chat_unread_count);
    $chat_stmt->fetch();
    $chat_stmt->close();
}

$is_collapsed = isset($_COOKIE['sidebarCollapsed']) && $_COOKIE['sidebarCollapsed'] === 'true';
?>

<style>
    :root {
        --sidebar-width:
            <?= $is_collapsed ? '68px' : '250px' ?>
        ;
    }

    .sidebar {
        width: 250px;
        min-width: 250px;
        overflow: hidden;
        transition: width 0.25s ease, min-width 0.25s ease;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
        display: flex;
        flex-direction: column;
        background-color: #111827;
    }

    .sidebar.collapsed {
        width: 68px;
        min-width: 68px;
    }

    .sb-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 14px;
        border-bottom: 1px solid #374151;
        flex-shrink: 0;
        min-height: 64px;
    }

    .sidebar.collapsed .sb-brand {
        justify-content: center;
        padding: 16px 0;
    }

.sb-brand-text {
    font-size: 22px;
    font-weight: 800;
    margin: 0;
    white-space: nowrap;
    letter-spacing: 0.5px;
    transition: opacity 0.15s ease, width 0.15s ease;
    background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

    .sidebar.collapsed .sb-brand-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    .sb-toggle {
        width: 30px;
        height: 30px;
        min-width: 30px;
        background: #1f2937;
        border: none;
        border-radius: 8px;
        color: #9ca3af;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: background 0.2s, color 0.2s;
        flex-shrink: 0;
    }

    .sb-toggle:hover {
        background: #374151;
        color: white;
    }

    .sb-section {
        font-size: 10px;
        font-weight: 700;
        color: #6b7280;
        letter-spacing: 1px;
        padding: 14px 16px 6px 16px;
        white-space: nowrap;
        overflow: hidden;
        transition: opacity 0.15s ease;
        text-transform: uppercase;
    }

    .sidebar.collapsed .sb-section {
        opacity: 0;
    }

    #mySidebar .sidebar-menu {
        list-style: none;
        margin: 0;
        padding: 0 8px;
    }

    #mySidebar .sidebar-menu li {
        margin-bottom: 2px;
    }

    #mySidebar .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 14px;
        margin: 0;
        height: auto;
        color: #9ca3af;
        text-decoration: none;
        border-radius: 8px;
        border: none !important;
        font-size: 15px;
        font-weight: 500;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s;
        background: transparent;
        box-shadow: none !important;
    }

    #mySidebar .sidebar-menu a:hover {
        background: rgba(255, 255, 255, 0.06) !important;
        color: #ffffff !important;
        border: none !important;
    }

    #mySidebar .sidebar-menu a.active {
        background: #ffffff !important;
        color: #111827 !important;
        border: none !important;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15) !important;
    }

    #mySidebar .sidebar-menu a.active i {
        color: #111827 !important;
    }

    #mySidebar .sidebar-menu a i {
        width: 22px;
        text-align: center;
        flex-shrink: 0;
        font-size: 18px;
        margin-right: 0;
    }

    #mySidebar .sidebar-menu a span {
        overflow: hidden;
        opacity: 1;
        transition: opacity 0.15s ease, width 0.15s ease;
        white-space: nowrap;
    }

    #mySidebar.collapsed .sidebar-menu a span {
        opacity: 0;
        width: 0;
    }

    #mySidebar.collapsed .sidebar-menu a {
        justify-content: center;
        padding: 12px 0;
        margin: 0 8px;
        width: calc(100% - 16px);
    }

    .sb-nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 14px;
        color: #9ca3af;
        text-decoration: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 500;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s;
    }

    .sb-nav-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .sb-nav-link.active {
        background: #ffffff !important;
        color: #111827 !important;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    #mySidebar.collapsed .sb-nav-link {
        justify-content: center;
        padding: 12px 0;
        margin: 0 8px;
        width: calc(100% - 16px);
    }

    .sb-label {
        opacity: 1;
        transition: opacity 0.15s ease, width 0.15s ease;
        white-space: nowrap;
        overflow: hidden;
    }

    #mySidebar.collapsed .sb-label {
        opacity: 0;
        width: 0;
    }

    .sb-icon-wrap {
        position: relative;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sb-red-dot {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 10px;
        height: 10px;
        background-color: #ef4444;
        border-radius: 50%;
        border: 2px solid #111827;
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 8px 8px 16px 8px;
        border-top: 1px solid #374151;
    }

    #mySidebar .logout-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        color: #9ca3af;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s;
    }

    #mySidebar .logout-btn:hover {
        background: rgba(239, 68, 68, 0.12);
        color: #ef4444;
    }

    #mySidebar .logout-btn i {
        width: 20px;
        text-align: center;
        flex-shrink: 0;
        font-size: 15px;
        margin-right: 0;
    }

    #mySidebar .logout-btn span {
        overflow: hidden;
        opacity: 1;
        transition: opacity 0.15s ease, width 0.15s ease;
        white-space: nowrap;
    }

    #mySidebar.collapsed .logout-btn span {
        opacity: 0;
        width: 0;
    }

    #mySidebar.collapsed .logout-btn {
        justify-content: center;
        padding: 12px 0;
        margin: 0 8px;
        width: calc(100% - 16px);
    }
</style>

<aside class="sidebar <?= $is_collapsed ? 'collapsed' : '' ?>" id="mySidebar">

    <div class="sb-brand">
        <h2 class="sb-brand-text">LCWcar</h2>
        <button class="sb-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sb-section">Navigation</div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="sales_report.php" class="<?= $current_page == 'sales_report.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Sales Report</span>
            </a>
        </li>

        <li>
            <a href="Reservations.php" class="<?= $current_page == 'Reservations.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Reservations</span>
            </a>
        </li>
        <li>
            <a href="orders.php" class="<?= $current_page == 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>
        </li>
        <li>
            <a href="manage cars.php" class="<?= $current_page == 'manage cars.php' ? 'active' : '' ?>">
                <i class="fas fa-car"></i>
                <span>Manage Cars</span>
            </a>
        </li>
        <li>
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li>
            <a href="chat.php" class="sb-nav-link <?= $current_page == 'chat.php' ? 'active' : '' ?>">
                <div class="sb-icon-wrap">
                    <i class="fas fa-comments"></i>
                    <?php if ($chat_unread_count > 0): ?>
                        <span class="sb-red-dot"></span>
                    <?php endif; ?>
                </div>
                <span class="sb-label">Chat</span>
            </a>
        </li>
    </ul>

    <div class="sb-section">General</div>
    <ul class="sidebar-menu">
        <li>
            <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <li>
            <a href="notifications.php" class="sb-nav-link <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
                <div class="sb-icon-wrap">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="sb-red-dot" id="sidebar-badge"></span>
                    <?php endif; ?>
                </div>
                <span class="sb-label">Notifications</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="Auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Log Out</span>
        </a>
    </div>

</aside>

<script>
    (function () {
        const sidebar = document.getElementById('mySidebar');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const collapsed = sidebar.classList.toggle('collapsed');
                document.documentElement.style.setProperty('--sidebar-width', collapsed ? '68px' : '250px');
                document.cookie = `sidebarCollapsed=${collapsed}; path=/; max-age=31536000`;
            });
        }
    })();
</script>