<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
$unread_count = 0;
$chat_unread_count = 0;

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

        .nav-chat-link {
            position: relative;
        }

        .nav-chat-link.has-unread,
        .nav-chat-link.has-unread span {
            color: #ef4444 !important;
            font-weight: 700;
        }

        .nav-chat-link.has-unread::after {
            background: #ef4444;
            width: 100%;
        }
    </style>

<body>

    <header class="navbar">
        <div class="nav-container">

            <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php" class="nav-logo">🚗
                CarDealer</a>

            <ul class="nav-links">
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/cars.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'cars.php' ? 'active' : '' ?>">Cars</a></li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/booking.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'booking.php' ? 'active' : '' ?>">Status</a></li>
                <li>
                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/chat.php" class="nav-chat-link 
               <?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>
               <?= $chat_unread_count > 0 ? 'has-unread' : '' ?>">
                        <span>Chat</span>
                    </a>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>

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