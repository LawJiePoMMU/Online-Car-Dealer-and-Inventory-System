<?php
session_name("AdminSession");
session_start();
include '../Config/database.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}
$current_admin_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'clear_all') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $current_admin_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($action === 'mark_read' && isset($input['id'])) {
        $nid = (int) $input['id'];
        $stmt = $conn->prepare("UPDATE notifications SET notification_status = 'read' WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $nid, $current_admin_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_created_at DESC");
$stmt->bind_param("i", $current_admin_id);
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
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .btn-danger-outline {
            background: transparent;
            border: 1.5px solid #ef4444;
            color: #ef4444;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-danger-outline:hover {
            background: #ef4444;
            color: white;
        }

        .btn-danger-outline:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .notification-list-container {
            max-width: 100%;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.2s, border-color 0.2s, background 0.2s;
        }

        .notification-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
            background: #fafafa;
        }

        .notification-card.unread {
            background: #fff7f7;
            border-left: 4px solid #ef4444;
        }

        .notification-card.unread:hover {
            background: #fff1f1;
        }

        .noti-icon-wrapper {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .noti-content {
            flex: 1;
            min-width: 0;
        }

        .noti-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }

        .noti-module {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .noti-time {
            font-size: 11px;
            color: #9ca3af;
        }

        .noti-message {
            font-size: 13.5px;
            color: #111827;
            font-weight: 500;
            margin: 0;
            line-height: 1.5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .noti-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .unread-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #ef4444;
            display: inline-block;
            flex-shrink: 0;
        }

        .action-arrow {
            color: #9ca3af;
            font-size: 13px;
        }

        .notification-card:hover .action-arrow {
            color: #374151;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .empty-state p {
            font-size: 15px;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
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
                <div class="notification-list">
                    <?php foreach ($notifications as $note):
                        $msg = $note['notification_message'];
                        $is_unread = ($note['notification_status'] === 'unread');

                        $icon = 'fa-bell';
                        $icon_bg = '#f3f4f6';
                        $icon_color = '#6b7280';
                        $module_name = 'System Alert';
                        $target_url = '#';

                        if (strpos($msg, 'New Booking') !== false) {
                            $icon = 'fa-shopping-cart';
                            $icon_bg = '#eff6ff';
                            $icon_color = '#3b82f6';
                            $module_name = 'Orders Module';
                            $target_url = 'orders.php?tab=booking';
                            if (preg_match('/\[booking_id:(\d+)\]/', $msg, $m)) {
                                $target_url = 'orders.php?tab=booking&highlight=' . $m[1];
                            }
                        } elseif (strpos($msg, 'New Reservation') !== false) {
                            $icon = 'fa-calendar-check';
                            $icon_bg = '#fdf4ff';
                            $icon_color = '#c026d3';
                            $module_name = 'Reservations Module';
                            $target_url = 'reservations.php?tab=reservations';
                            if (preg_match('/\[reservation_id:(\d+)\]/', $msg, $m)) {
                                $target_url = 'reservations.php?tab=reservations&highlight=' . $m[1];
                            }
                        } elseif (strpos($msg, 'New Down Payment') !== false) {
                            $icon = 'fa-file-invoice-dollar';
                            $icon_bg = '#ecfccb';
                            $icon_color = '#65a30d';
                            $module_name = 'Payments Module';
                            $target_url = 'orders.php?tab=down_payment';
                            if (preg_match('/\[dp_booking_id:(\d+)\]/', $msg, $m)) {
                                $target_url = 'orders.php?tab=down_payment&highlight=' . $m[1];
                            }
                        } elseif (strpos($msg, 'Stock Alert') !== false) {
                            $icon = 'fa-car-side';
                            $icon_bg = '#fef2f2';
                            $icon_color = '#ef4444';
                            $module_name = 'Inventory Module';
                            $target_url = 'manage cars.php';
                            if (preg_match('/\[car_id:(\d+)\]/', $msg, $matches)) {
                                $target_url = 'manage cars.php?highlight=' . $matches[1];
                            }
                        }
                        ?>

                        <a href="<?= htmlspecialchars($target_url) ?>"
                            class="notification-card <?= $is_unread ? 'unread' : '' ?>"
                            data-id="<?= $note['notification_id'] ?>">
                            <div class="noti-icon-wrapper"
                                style="background-color: <?= $icon_bg ?>; color: <?= $icon_color ?>;">
                                <i class="fas <?= $icon ?>"></i>
                            </div>

                            <div class="noti-content">
                                <div class="noti-header">
                                    <span class="noti-module"><?= htmlspecialchars($module_name) ?></span>
                                    <span
                                        class="noti-time"><?= date('M j, Y, g:i a', strtotime($note['notification_created_at'])) ?></span>
                                </div>
                                <p class="noti-message"><?= htmlspecialchars(preg_replace('/\[(car_id|booking_id|reservation_id|dp_booking_id):\d+\]/', '', $msg)) ?></p>
                            </div>

                            <div class="noti-actions">
                                <?php if ($is_unread): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-right action-arrow"></i>
                            </div>
                        </a>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JAVA SCRIPT/notifications.js"></script>
</body>

</html>