<?php
session_start();
include '../Config/database.php';

// 假设当前用户 ID
$user_id = $_SESSION['user_id'] ?? 1;

// 获取通知列表，按时间倒序
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="../../CSS/Admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<style>
    .icon-wrapper {
        position: relative;
        display: inline-block;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -10px;
        background-color: #EF4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 999px;
        border: 2px solid #1E293B;
        line-height: 1;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .btn-danger-outline {
        background: transparent;
        color: #EF4444;
        border: 1px solid #EF4444;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-danger-outline:hover:not(:disabled) {
        background: #EF4444;
        color: white;
    }

    .btn-danger-outline:disabled {
        border-color: #D1D5DB;
        color: #9CA3AF;
        cursor: not-allowed;
    }

    .notification-list-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #E5E7EB;
        overflow: hidden;
    }

    .notification-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .notification-item {
        display: flex;
        align-items: flex-start;
        padding: 16px 24px;
        border-bottom: 1px solid #E5E7EB;
        transition: background-color 0.2s ease;
        position: relative;
        cursor: pointer;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background-color: #F9FAFB;
    }

    .notification-item.unread {
        background-color: #F0F9FF;
    }

    .notification-item.unread:hover {
        background-color: #E0F2FE;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #EEF2FF;
        color: #4F46E5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-right: 16px;
        flex-shrink: 0;
    }

    .notification-content {
        flex: 1;
    }

    .notification-content .message {
        margin: 0 0 4px 0;
        font-size: 14px;
        color: #111827;
    }

    .notification-item.unread .message {
        font-weight: 600;
        /* 未读加粗 */
    }

    .notification-content .time {
        font-size: 12px;
        color: #6B7280;
    }

    .unread-indicator {
        width: 10px;
        height: 10px;
        background-color: #3B82F6;
        border-radius: 50%;
        margin-top: 6px;
    }

    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #9CA3AF;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        color: #D1D5DB;
    }
</style>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar notification-header">
            <div class="page-title">
                <h1>Notifications</h1>
            </div>
            <button id="clearAllBtn" class="btn-danger-outline" <?= empty($notifications) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-trash-can"></i> Clear All
            </button>
        </header>

        <div class="notification-list-container">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash"></i>
                    <p>No new notifications right now.</p>
                </div>
            <?php else: ?>
                <ul class="notification-list">
                    <?php foreach ($notifications as $note): ?>
                        <?php
                        $is_unread = ($note['notification_status'] === 'unread');
                        $status_class = $is_unread ? 'unread' : 'read';
                        ?>
                        <li class="notification-item <?= $status_class ?>" data-id="<?= $note['notification_id'] ?>">
                            <div class="notification-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="notification-content">
                                <p class="message"><?= htmlspecialchars($note['notification_message']) ?></p>
                                <span
                                    class="time"><?= date('M j, Y, g:i a', strtotime($note['notification_created_at'])) ?></span>
                            </div>
                            <?php if ($is_unread): ?>
                                <div class="unread-indicator"></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../JAVA SCRIPT/notifications.js"></script>
</body>

</html>