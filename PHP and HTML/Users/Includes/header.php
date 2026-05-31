<?php
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== "CustomerSession") {
    session_write_close();
}

if (session_status() === PHP_SESSION_NONE) {
    session_name("CustomerSession");
    session_start();
}

$current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
$unread_count = 0;
$chat_unread_count = 0;
$status_alert_count = 0;
if (isset($conn) && $current_user_id > 0) {
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

    $group_unread_count = 0;
    $group_stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM group_messages gm
        JOIN chat_group_members cgm ON gm.group_id = cgm.group_id
        WHERE cgm.user_id = ?
          AND gm.sender_id != ?
          AND (gm.is_read = 0 OR gm.is_read IS NULL)
          AND gm.group_id NOT IN (
              SELECT muted_target_id FROM muted_chats 
              WHERE user_id = ? AND chat_type = 'group'
          )
          AND gm.created_at > IFNULL(
              (SELECT cleared_at FROM chat_state 
               WHERE user_id = ? AND target_id = gm.group_id AND chat_type = 'group'),
              '1970-01-01 00:00:00'
          )
    ");
    $group_stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
    $group_stmt->execute();
    $group_stmt->bind_result($group_unread_count);
    $group_stmt->fetch();
    $group_stmt->close();

    $chat_unread_count += $group_unread_count;
    $status_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT b.booking_id)
        FROM bookings b
        LEFT JOIN down_payments dp ON dp.booking_id = b.booking_id
        WHERE b.user_id = ?
          AND (
                (b.booking_status = 'Pending' AND b.booking_paid_at IS NULL)
             OR (b.booking_status = 'Approved'
                 AND (dp.dp_status IS NULL OR dp.dp_status = 'Pending')
                 AND (dp.insurance_pdf_url IS NULL OR dp.insurance_pdf_url = ''))
             OR EXISTS (SELECT 1 FROM monthly_installments mi
                        WHERE mi.booking_id = b.booking_id AND mi.payment_status = 'Overdue')
          )
    ");
    $status_stmt->bind_param("i", $current_user_id);
    $status_stmt->execute();
    $status_stmt->bind_result($status_alert_count);
    $status_stmt->fetch();
    $status_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Dealer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/cus.css?v=<?php echo time(); ?>">
    <style>
        .nav-links a {
            position: relative;
            display: inline-block;
            padding: 8px 4px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 2px;
            background: #111827;
            transition: width 0.25s ease;
        }

        .nav-links a:hover {
            color: #111827;
        }

        .nav-links a:hover::after,
        .nav-links a.active::after {
            width: 100%;
        }

        .nav-links a.active {
            color: #111827;
            font-weight: 600;
        }

        /* 新增：小红点专属样式 */
        .nav-chat-link {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .unread-dot {
            position: absolute;
            top: 6px;
            right: -6px;
            /* 根据你的间距可微调这个数值 */
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            /* 红色 */
            border-radius: 50%;
            box-shadow: 0 0 0 2px #ffffff;
            /* 给红点加个白边，防粘连更好看 */
        }
    </style>
</head>

<body>

    <header class="navbar">
        <div class="nav-container">

            <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php" class="nav-logo"
                style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;">
                <img src="/Online-Car-Dealer-and-Inventory-System/Images/Uploads/company_logo.png" alt="LCWcar"
                    style="height:38px;width:auto;display:block;">
                <span>LCWcar</span>
            </a>

            <ul class="nav-links">
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/cars.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'cars.php' ? 'active' : '' ?>">Cars</a></li>
                <li>
                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/view_status.php"
                        class="nav-chat-link <?= basename($_SERVER['PHP_SELF']) == 'view_status.php' ? 'active' : '' ?>">
                        Status
                        <?php if ($status_alert_count > 0): ?>
                            <span class="unread-dot"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/chat.php"
                        class="nav-chat-link <?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">
                        Chat
                        <?php if ($chat_unread_count > 0): ?>
                            <span class="unread-dot"></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (
                    isset($_SESSION["loggedin"])
                    && $_SESSION["loggedin"] === true
                    && isset($_SESSION["role"])
                    && $_SESSION["role"] === "Customer"
                ): ?>

                    <div class="user-menu">
                        <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/wishlist.php"
                            class="icon-link" title="My Wishlist">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                                </path>
                            </svg>
                        </a>

                        <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/profile.php"
                            class="icon-link" title="My Profile">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </a>
                    </div>

                <?php else: ?>

                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/Auth/login.php"
                        class="btn-primary">Login / Register</a>

                <?php endif; ?>
            </div>

        </div>
    </header>