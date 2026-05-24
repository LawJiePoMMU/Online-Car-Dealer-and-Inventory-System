<?php
session_start();
include '../Config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$my_id = $_SESSION['user_id'];

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chat_groups` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `created_by` int(11) NOT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chat_group_members` (`group_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, PRIMARY KEY (`group_id`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `group_messages` (`id` int(11) NOT NULL AUTO_INCREMENT, `group_id` int(11) NOT NULL, `sender_id` int(11) NOT NULL, `message` text, `file_path` varchar(255), `created_at` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `messages` (`id` int(11) NOT NULL AUTO_INCREMENT, `sender_id` int(11) NOT NULL, `receiver_id` int(11) NOT NULL, `message` text DEFAULT NULL, `file_path` varchar(255) DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($conn, "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
mysqli_query($conn, "ALTER TABLE group_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `muted_chats` (
    `user_id` INT NOT NULL,
    `muted_target_id` INT NOT NULL,
    `chat_type` VARCHAR(10) NOT NULL,
    PRIMARY KEY (`user_id`, `muted_target_id`, `chat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chat_state` (
    `user_id` INT NOT NULL,
    `target_id` INT NOT NULL,
    `chat_type` VARCHAR(10) NOT NULL,
    `cleared_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`user_id`, `target_id`, `chat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = isset($_POST['action']) ? $_POST['action'] : '';
function safe_chat_type($t)
{
    return ($t === 'group') ? 'group' : 'user';
}

if ($action !== '') {

    if ($action == 'fetch_users') {
        $sql_users = "SELECT DISTINCT u.user_id, u.user_name, u.user_phone, u.user_avatar,
                (SELECT COUNT(*) FROM messages
                 WHERE sender_id = u.user_id
                   AND receiver_id = $my_id
                   AND (is_read = 0 OR is_read IS NULL)
                   AND created_at > IFNULL(
                        (SELECT cleared_at FROM chat_state
                         WHERE user_id = $my_id AND target_id = u.user_id AND chat_type = 'user'),
                        '1970-01-01 00:00:00'
                   )
                ) AS unread_count,
                CASE WHEN mc.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_muted
            FROM users u
            JOIN messages m ON (u.user_id = m.sender_id OR u.user_id = m.receiver_id)
            LEFT JOIN muted_chats mc
                ON mc.user_id = $my_id
               AND mc.muted_target_id = u.user_id
               AND mc.chat_type = 'user'
            LEFT JOIN chat_state cs
                ON cs.user_id = $my_id
               AND cs.target_id = u.user_id
               AND cs.chat_type = 'user'
            WHERE u.user_id != $my_id
              AND (m.sender_id = $my_id OR m.receiver_id = $my_id)
              AND (cs.deleted_at IS NULL OR m.created_at > cs.deleted_at)";
        $res_users = mysqli_query($conn, $sql_users);
        $users = [];
        if ($res_users)
            while ($row = mysqli_fetch_assoc($res_users))
                $users[] = $row;
$sql_groups = "SELECT g.id as group_id, g.name as group_name,

(
SELECT COUNT(*)

FROM group_messages gm2

WHERE gm2.group_id=g.id

AND gm2.sender_id!=$my_id

AND (
gm2.is_read=0
OR gm2.is_read IS NULL
)

AND gm2.created_at>

IFNULL(
(
SELECT cleared_at
FROM chat_state
WHERE user_id=$my_id
AND target_id=g.id
AND chat_type='group'
),
'1970-01-01'
)

)

as unread_count,

CASE WHEN mc.user_id IS NOT NULL THEN 1 ELSE 0 END as is_muted

FROM chat_groups g

JOIN chat_group_members gm
ON g.id=gm.group_id

LEFT JOIN muted_chats mc
ON mc.user_id=$my_id
AND mc.muted_target_id=g.id
AND mc.chat_type='group'

WHERE gm.user_id=$my_id";
        $res_groups = mysqli_query($conn, $sql_groups);
        $groups = [];
        if ($res_groups)
            while ($row = mysqli_fetch_assoc($res_groups))
                $groups[] = $row;

        echo json_encode(['users' => $users, 'groups' => $groups]);
        exit();
    }

    if ($action == 'fetch_directory') {
        $sql = "SELECT user_id, user_name, user_phone, user_avatar, user_role FROM users WHERE user_id != $my_id ORDER BY user_name ASC";
        $result = mysqli_query($conn, $sql);
        $users = [];
        if ($result)
            while ($row = mysqli_fetch_assoc($result))
                $users[] = $row;
        echo json_encode($users);
        exit();
    }

    if ($action == 'fetch_messages') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');

        if ($chat_type === 'user') {
            mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = $other_id AND receiver_id = $my_id AND is_read = 0");
        }

        $msgs = [];
        if ($chat_type === 'group') {
 mysqli_query(
        $conn,
        "UPDATE group_messages
        SET is_read = 1
        WHERE group_id = $other_id
        AND sender_id != $my_id
        AND (is_read = 0 OR is_read IS NULL)"
    );

    $sql = "SELECT  gm.*, u.user_name FROM group_messages gm LEFT JOIN users u ON gm.sender_id = u.user_id WHERE gm.group_id = $other_id AND gm.created_at > IFNULL((SELECT cleared_at FROM chat_state WHERE user_id=$my_id AND target_id=$other_id AND chat_type='group'),'1970-01-01 00:00:00') ORDER BY gm.created_at ASC";
        } else {
            $sql = "SELECT * FROM messages
                    WHERE ((sender_id = $my_id AND receiver_id = $other_id)
                        OR (sender_id = $other_id AND receiver_id = $my_id))
                      AND created_at > IFNULL(
                            (SELECT cleared_at FROM chat_state
                             WHERE user_id = $my_id AND target_id = $other_id AND chat_type = 'user'),
                            '1970-01-01 00:00:00'
                      )
                    ORDER BY created_at ASC";
        }

        $result = mysqli_query($conn, $sql);
        if ($result)
            while ($row = mysqli_fetch_assoc($result))
                $msgs[] = $row;
        echo json_encode(['messages' => $msgs]);
        exit();
    }

    if ($action == 'fetch_shared_media') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');

        if ($chat_type === 'group') {
            $sql = "SELECT file_path FROM group_messages
                    WHERE group_id = $other_id
                      AND file_path IS NOT NULL AND file_path != ''
                      AND created_at > IFNULL(
                            (SELECT cleared_at FROM chat_state
                             WHERE user_id = $my_id AND target_id = $other_id AND chat_type = 'group'),
                            '1970-01-01 00:00:00'
                      )
                    ORDER BY created_at DESC";
        } else {
            $sql = "SELECT file_path FROM messages
                    WHERE ((sender_id = $my_id AND receiver_id = $other_id)
                        OR (sender_id = $other_id AND receiver_id = $my_id))
                      AND file_path IS NOT NULL AND file_path != ''
                      AND created_at > IFNULL(
                            (SELECT cleared_at FROM chat_state
                             WHERE user_id = $my_id AND target_id = $other_id AND chat_type = 'user'),
                            '1970-01-01 00:00:00'
                      )
                    ORDER BY created_at DESC";
        }

        $result = mysqli_query($conn, $sql);
        $media = [];
        if ($result)
            while ($row = mysqli_fetch_assoc($result))
                $media[] = $row['file_path'];
        echo json_encode(['media' => $media]);
        exit();
    }

    if ($action == 'fetch_profile_details') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');

        if ($chat_type === 'user') {
            $sql = "SELECT g.name FROM chat_groups g JOIN chat_group_members m1 ON g.id = m1.group_id JOIN chat_group_members m2 ON g.id = m2.group_id WHERE m1.user_id = $my_id AND m2.user_id = $other_id";
            $res = mysqli_query($conn, $sql);
            $list = [];
            if ($res)
                while ($row = mysqli_fetch_assoc($res))
                    $list[] = $row['name'];
            echo json_encode(['type' => 'user', 'data' => $list]);
        } else {
            $sql = "SELECT u.user_name FROM users u JOIN chat_group_members gm ON u.user_id = gm.user_id WHERE gm.group_id = $other_id";
            $res = mysqli_query($conn, $sql);
            $list = [];
            if ($res)
                while ($row = mysqli_fetch_assoc($res))
                    $list[] = $row['user_name'];
            echo json_encode(['type' => 'group', 'data' => $list]);
        }
        exit();
    }

    if ($action == 'send_message') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');
        $msg = mysqli_real_escape_string($conn, $_POST['message']);

        $file_paths = [];
        if (!empty($_FILES['files']['name'][0])) {
            $target_dir = "../../uploads/chat/";
            if (!is_dir($target_dir))
                mkdir($target_dir, 0777, true);
            foreach ($_FILES['files']['name'] as $key => $name) {
                $safe_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($name));
                $new_name = uniqid() . "_" . $safe_name;
                $target_file = $target_dir . $new_name;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $target_file)) {
                    $file_paths[] = $target_file;
                }
            }
        }

        $success = true;
        $error_msg = "";

        if (!empty($msg) && empty($file_paths)) {
            if ($chat_type === 'group') {
                $sql = "INSERT INTO group_messages (group_id, sender_id, message, created_at) VALUES ($other_id, $my_id, '$msg', NOW())";
            } else {
                $sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES ($my_id, $other_id, '$msg', NOW())";
            }
            if (!mysqli_query($conn, $sql)) {
                $success = false;
                $error_msg = mysqli_error($conn);
            }
        }

        foreach ($file_paths as $path) {
            if ($chat_type === 'group') {
                $sql = "INSERT INTO group_messages (group_id, sender_id, message, file_path, created_at) VALUES ($other_id, $my_id, '$msg', '$path', NOW())";
            } else {
                $sql = "INSERT INTO messages (sender_id, receiver_id, message, file_path, created_at) VALUES ($my_id, $other_id, '$msg', '$path', NOW())";
            }
            if (!mysqli_query($conn, $sql)) {
                $success = false;
                $error_msg = mysqli_error($conn);
            }
        }

        if ($success) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $error_msg]);
        }
        exit();
    }

    if ($action == 'create_group') {
        $group_name = mysqli_real_escape_string($conn, $_POST['group_name']);
        $members = json_decode($_POST['members'], true);
        $members[] = $my_id;

        mysqli_query($conn, "INSERT INTO chat_groups (name, created_by) VALUES ('$group_name', $my_id)");
        $group_id = mysqli_insert_id($conn);

        foreach (array_unique($members) as $uid) {
            $uid = (int) $uid;
            mysqli_query($conn, "INSERT INTO chat_group_members (group_id, user_id) VALUES ($group_id, $uid)");
        }
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action == 'fetch_all_users') {
        $sql = "SELECT user_id, user_name FROM users WHERE user_id != $my_id";
        $result = mysqli_query($conn, $sql);
        $users = [];
        if ($result)
            while ($row = mysqli_fetch_assoc($result))
                $users[] = $row;
        echo json_encode($users);
        exit();
    }

    if ($action == 'toggle_mute') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');
        $is_muted = $_POST['is_muted']; 

        if ($is_muted === 'true') {
            mysqli_query($conn, "INSERT IGNORE INTO muted_chats (user_id, muted_target_id, chat_type) VALUES ($my_id, $other_id, '$chat_type')");
        } else {
            mysqli_query($conn, "DELETE FROM muted_chats WHERE user_id = $my_id AND muted_target_id = $other_id AND chat_type = '$chat_type'");
        }

        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action == 'clear_chat') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');

        mysqli_query($conn, "INSERT INTO chat_state (user_id, target_id, chat_type, cleared_at)
            VALUES ($my_id, $other_id, '$chat_type', NOW())
            ON DUPLICATE KEY UPDATE cleared_at = NOW()");

        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action == 'delete_chat') {
        $other_id = isset($_POST['other_id']) ? (int) $_POST['other_id'] : 0;
        $chat_type = safe_chat_type($_POST['chat_type'] ?? 'user');

        if ($chat_type === 'group') {
            mysqli_query($conn, "DELETE FROM chat_group_members WHERE group_id = $other_id AND user_id = $my_id");
            mysqli_query($conn, "DELETE FROM chat_state WHERE user_id = $my_id AND target_id = $other_id AND chat_type = 'group'");
        } else {
            mysqli_query($conn, "INSERT INTO chat_state (user_id, target_id, chat_type, cleared_at, deleted_at)
                VALUES ($my_id, $other_id, 'user', NOW(), NOW())
                ON DUPLICATE KEY UPDATE cleared_at = NOW(), deleted_at = NOW()");
        }

        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action == 'fetch_non_group_members') {
        $group_id = (int) $_POST['group_id'];
        $sql = "SELECT user_id, user_name, user_avatar, user_role FROM users WHERE user_id != $my_id AND user_id NOT IN (SELECT user_id FROM chat_group_members WHERE group_id = $group_id) ORDER BY user_name ASC";
        $result = mysqli_query($conn, $sql);
        $users = [];
        if ($result)
            while ($row = mysqli_fetch_assoc($result))
                $users[] = $row;
        echo json_encode($users);
        exit();
    }

    if ($action == 'add_group_members') {
        $group_id = (int) $_POST['group_id'];
        $members = json_decode($_POST['members'], true);
        foreach (array_unique($members) as $uid) {
            $uid = (int) $uid;
            mysqli_query($conn, "INSERT IGNORE INTO chat_group_members (group_id, user_id) VALUES ($group_id, $uid)");
        }
        echo json_encode(['status' => 'success']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Management</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/Admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .chat-container {
            display: flex;
            height: 75vh;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .chat-sidebar {
            width: 320px;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }

        .chat-search-wrap {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .search-bar-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }

        .chat-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            align-items: center;
        }

        .chat-tab-btn {
            padding: 6px 12px;
            border-radius: 16px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #f3f4f6;
            color: #4b5563;
            transition: 0.2s;
            white-space: nowrap;
        }

        .chat-tab-btn.active {
            background: #1e3a8a;
            color: #fff;
        }

        .chat-tab-btn:hover:not(.active) {
            background: #e5e7eb;
        }

        .contact-list {
            flex: 1;
            overflow-y: auto;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f9fafb;
            transition: 0.2s;
            position: relative;
        }

        .contact-item:hover {
            background: #f3f4f6;
        }

        .contact-item.active {
            background: #e0e7ff;
            border-left: 4px solid #1e3a8a;
        }

        .avatar-wrap {
            position: relative;
            margin-right: 12px;
        }

        .unread-badge {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: #10b981;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            position: relative;
        }

        .chat-header {
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .chat-header:hover {
            background: #f9fafb;
        }

        .chat-history {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            background-blend-mode: soft-light;
            background-color: #f8fafc;
            opacity: 0.95;
        }

        .message-row {
            display: flex;
            max-width: 75%;
        }

        .message-row.incoming {
            align-self: flex-start;
        }

        .message-row.outgoing {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-bubble {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .incoming .message-bubble {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-top-left-radius: 0;
        }

        .outgoing .message-bubble {
            background: #1e3a8a;
            color: #ffffff;
            border-top-right-radius: 0;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
            text-align: right;
            display: block;
        }

        .chat-input-area {
            padding: 16px 24px;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .chat-input-area input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 24px;
            outline: none;
            font-size: 14px;
            background: #f9fafb;
        }

        .btn-send {
            background: #1e3a8a;
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            flex-shrink: 0;
        }

        .btn-send:hover {
            background: #1e40af;
            transform: scale(1.05);
        }

        .profile-sidebar {
            width: 320px;
            background: #fff;
            border-left: 1px solid #e5e7eb;
            display: none;
            flex-direction: column;
        }

        .profile-sidebar.open {
            display: flex;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                margin-right: -320px;
            }

            to {
                margin-right: 0;
            }
        }

        .profile-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: bold;
        }

        .profile-content {
            padding: 24px;
            text-align: center;
            overflow-y: auto;
            flex: 1;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        .media-item {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }

        .file-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 10px;
            aspect-ratio: 1;
            color: #4b5563;
            text-decoration: none;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">Chats</h1>
            </div>
        </header>

        <div class="chat-container">
            <div class="chat-sidebar">
                <div class="chat-search-wrap">
                    <div class="search-bar-row">
                        <input type="text" id="searchContact" class="form-control" placeholder="Search contacts...">
                        <button class="btn-add-blue" onclick="openNewChatModal()"
                            style="padding: 10px; border-radius: 8px;" title="New Chat"><i
                                class="fas fa-plus"></i></button>
                    </div>
                    <div class="chat-tabs">
                        <button class="chat-tab-btn active" onclick="switchTab('all', this)">All</button>
                        <button class="chat-tab-btn" onclick="switchTab('unread', this)">Unread</button>
                        <button class="chat-tab-btn" onclick="switchTab('group', this)">Groups</button>
                        <button class="chat-tab-btn"
                            style="margin-left:auto; background:transparent; color:#1e3a8a; border: 1px solid #1e3a8a;"
                            onclick="openCreateGroupModal()"><i class="fas fa-users" style="margin-right:4px;"></i>+
                            Group</button>
                    </div>
                </div>
                <div class="contact-list" id="chatList"></div>
            </div>

            <div class="chat-main" id="mainChatArea" style="display:none;">
                <div class="chat-header" id="chatHeader" onclick="toggleProfileSidebar()">
                    <div class="customer-info" style="display: flex; align-items: center; gap: 12px;">
                        <div id="headerAvatarContainer"></div>
                        <div>
                            <strong id="activeName" style="font-size: 16px; color: #111827;">Name</strong>
                            <div id="activePhone" style="font-size: 12px; color: #6b7280;">Phone</div>
                        </div>
                    </div>
                    <div class="action-icons" style="display:flex; gap: 15px; align-items: center;">
                        <a href="javascript:void(0)" onclick="event.stopPropagation(); dismissNotification()"
                            title="Mute/Unmute Notifications"><i id="notifBellIcon" class="fas fa-bell"
                                style="color: #f59e0b; font-size: 16px;"></i></a>
                        <a href="javascript:void(0)" onclick="event.stopPropagation(); confirmAction('clear')"
                            title="Clear Messages"><i class="fas fa-eraser"
                                style="color: #f59e0b; font-size: 16px;"></i></a>
                        <a href="javascript:void(0)" onclick="event.stopPropagation(); confirmAction('delete')"
                            title="Delete/Leave Chat"><i class="fas fa-trash"
                                style="color: #ef4444; font-size: 16px;"></i></a>
                    </div>
                </div>

                <div class="chat-history" id="messagesArea"></div>
                <div id="filePreviewContainer"
                    style="display: none; padding: 12px 24px; background: #f3f4f6; border-top: 1px solid #e5e7eb; gap: 12px; overflow-x: auto;">
                </div>

                <div class="chat-input-area">
                    <button class="btn-outline"
                        style="border:none; padding: 8px; cursor: pointer; background:transparent;"
                        onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-paperclip" style="font-size: 20px; color: #9ca3af;"></i>
                    </button>
                    <input type="file" id="fileInput" multiple style="display:none;">
                    <input type="text" id="messageInput" placeholder="Type a message...">
                    <button class="btn-send" id="sendBtn"><i class="fas fa-paper-plane" id="sendIcon"></i></button>
                </div>
            </div>

            <div class="chat-main" id="emptyChatArea" style="align-items: center; justify-content: center;">
                <div style="text-align: center; color: #9ca3af;">
                    <i class="fas fa-comments" style="font-size: 60px; margin-bottom: 16px; opacity: 0.5;"></i>
                    <h3 style="color: #4b5563;">Select a contact or group to start messaging</h3>
                </div>
            </div>

            <div class="profile-sidebar" id="profileSidebar">
                <div class="profile-header">
                    <i class="fas fa-times" style="cursor: pointer; color: #6b7280;"
                        onclick="toggleProfileSidebar()"></i>
                    <span>Contact Info</span>
                </div>
                <div class="profile-content">
                    <div id="profileAvatarContainer" style="display:flex; justify-content:center; margin-bottom:16px;">
                    </div>
                    <h3 id="profileName">Name</h3>
                    <p id="profilePhone">Phone</p>
                    <div style="text-align: left; margin-top: 24px;">
                        <h4 style="font-size: 14px; color: #4b5563; margin-bottom: 10px;">Media</h4>
                        <div class="media-grid" id="profileMediaGrid"></div>
                        <h4 style="font-size: 14px; color: #4b5563; margin-top: 15px; margin-bottom: 10px;">Docs</h4>
                        <div id="profileDocsList" style="display: flex; flex-direction: column; gap: 8px;"></div>
                    </div>
                    <div style="text-align: left; margin-top: 24px;">
                        <h4 id="profileDynamicTitle" style="font-size: 14px; color: #4b5563; margin-bottom: 10px;">
                            Groups in common</h4>
                        <div id="profileDynamicList"
                            style="padding: 12px; background: #f3f4f6; border-radius: 8px; font-size: 13px; color: #6b7280;">
                            No data available.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="newChatModal" class="modal">
        <div class="modal-content"
            style="width: 450px; display: flex; flex-direction: column; max-height: 80vh; padding: 0; overflow: hidden;">
            <div style="padding: 20px 20px 10px 20px; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #111827;">Start New Chat</h3>
                    <i class="fas fa-times" style="cursor: pointer; color: #9ca3af; font-size: 18px;"
                        onclick="closeNewChatModal()"></i>
                </div>
                <input type="text" id="newContactSearch" class="form-control" placeholder="Search by name or phone..."
                    style="margin-bottom: 15px;">
                <div class="chat-tabs" style="gap: 15px;">
                    <button class="chat-tab-btn active" id="tabDirAdmin" onclick="switchDirectoryTab('Admin')"
                        style="flex:1; border-radius: 8px;">Admins</button>
                    <button class="chat-tab-btn" id="tabDirCustomer" onclick="switchDirectoryTab('Customer')"
                        style="flex:1; border-radius: 8px;">Customers</button>
                </div>
            </div>
            <div id="newContactList" style="overflow-y: auto; flex: 1; padding: 0; background: #fff;"></div>
        </div>
    </div>

    <div id="createGroupModal" class="modal">
        <div class="modal-content"
            style="width: 450px; display: flex; flex-direction: column; max-height: 80vh; padding: 0; overflow: hidden;">
            <div style="padding: 20px 20px 10px 20px; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="font-size: 18px; font-weight: 700;">Create Group</h3>
                    <i class="fas fa-times" style="cursor: pointer; color: #9ca3af; font-size: 18px;"
                        onclick="closeCreateGroupModal()"></i>
                </div>
                <input type="text" id="groupNameInput" class="form-control" placeholder="Group Name (e.g. Project Team)"
                    style="margin-bottom: 15px;">
                <div class="chat-tabs" style="gap: 15px;">
                    <button class="chat-tab-btn active" id="tabGroupAdmin" onclick="switchGroupTab('Admin')"
                        style="flex:1; border-radius: 8px;">Admins</button>
                    <button class="chat-tab-btn" id="tabGroupCustomer" onclick="switchGroupTab('Customer')"
                        style="flex:1; border-radius: 8px;">Customers</button>
                </div>
            </div>
            <div id="groupMembersList" style="overflow-y: auto; flex: 1; padding: 0; background: #fff;"></div>
            <div style="padding: 15px 20px; border-top: 1px solid #e5e7eb;">
                <button class="btn-add-blue" style="width: 100%; justify-content: center;"
                    onclick="submitCreateGroup()">Create Group</button>
            </div>
        </div>
    </div>
    <div id="addMemberModal" class="modal">
        <div class="modal-content"
            style="width: 450px; display: flex; flex-direction: column; max-height: 80vh; padding: 0; overflow: hidden;">
            <div style="padding: 20px 20px 10px 20px; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="font-size: 18px; font-weight: 700;">Add Members</h3>
                    <i class="fas fa-times" style="cursor: pointer; color: #9ca3af; font-size: 18px;"
                        onclick="closeAddMemberModal()"></i>
                </div>
                <div class="chat-tabs" style="gap: 15px;">
                    <button class="chat-tab-btn active" id="tabAddAdmin" onclick="switchAddMemberTab('Admin')"
                        style="flex:1; border-radius: 8px;">Admins</button>
                    <button class="chat-tab-btn" id="tabAddCustomer" onclick="switchAddMemberTab('Customer')"
                        style="flex:1; border-radius: 8px;">Customers</button>
                </div>
            </div>
            <div id="addMembersList" style="overflow-y: auto; flex: 1; padding: 0; background: #fff;"></div>
            <div style="padding: 15px 20px; border-top: 1px solid #e5e7eb;">
                <button class="btn-add-blue" style="width: 100%; justify-content: center;"
                    onclick="submitAddMembers()">Add to Group</button>
            </div>
        </div>
    </div>

    <input type="hidden" id="myUserId" value="<?php echo $my_id; ?>">
    <input type="hidden" id="currentChatId" value="">
    <input type="hidden" id="currentChatType" value="user">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JAVA SCRIPT/chat.js?v=<?php echo time(); ?>"></script>
    <div id="imageLightbox"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; flex-direction:column; backdrop-filter: blur(5px);">
        <span onclick="document.getElementById('imageLightbox').style.display='none'"
            style="position:absolute; top:25px; right:40px; color:white; font-size:35px; cursor:pointer; font-weight:bold;">&times;</span>
        <img id="lightboxImg" src=""
            style="max-width:90%; max-height:80%; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
        <a id="lightboxDownload" href="" download class="btn-add-blue" style="margin-top:20px; text-decoration:none;"><i
                class="fas fa-download" style="margin-right:6px;"></i> Download Image</a>
    </div>
</body>

</html>