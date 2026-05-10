<?php
$current_page = basename($_SERVER['PHP_SELF']);
$show_booking_alert = false;
$show_doc_alert = false;
if (isset($conn)) {
    $alert_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('new_booking_alert', 'document_verification')");
    $alerts = [];
    if ($alert_query) {
        while ($row = mysqli_fetch_assoc($alert_query)) {
            $alerts[$row['setting_key']] = $row['setting_value'];
        }
    }

    if (($alerts['new_booking_alert'] ?? '0') === '1') {
        $q_new = mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations WHERE reservation_status = 'Pending Viewing'");
        if ($q_new && mysqli_fetch_assoc($q_new)['c'] > 0) {
            $show_booking_alert = true;
        }
    }

    if (($alerts['document_verification'] ?? '0') === '1') {
        $q_doc = mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations WHERE reservation_status = 'Loan Processing' AND (ic_pdf_url IS NOT NULL OR driving_licence_url IS NOT NULL OR bank_statement_url IS NOT NULL OR salary_slip_url IS NOT NULL)");
        if ($q_doc && mysqli_fetch_assoc($q_doc)['c'] > 0) {
            $show_doc_alert = true;
        }
    }
}
$badge_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND notification_status = 'unread'");
$badge_stmt->bind_param("i", $current_user_id);
$badge_stmt->execute();
$badge_stmt->bind_result($unread_count);
$badge_stmt->fetch();
$badge_stmt->close();
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
            <a href="orders.php" class="<?= ($current_page == 'orders.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> <span>Orders</span>
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
            <a href="notifications.php" class="nav-item">
                <div class="icon-wrapper">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge" id="sidebar-badge">
                            <?= $unread_count > 99 ? '99+' : $unread_count ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span>Notifications</span>
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
        </a>
    </div>
</aside>