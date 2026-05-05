<?php
session_start();
include '../Config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$admin_id = $_SESSION['user_id'] ?? 1;
$sys_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_dp_percent', 'default_loan_rate')");
$sys_settings = [];
while ($row = mysqli_fetch_assoc($sys_query)) {
    $sys_settings[$row['setting_key']] = $row['setting_value'];
}
$dp_percent = $sys_settings['default_dp_percent'] ?? 10;
$dp_rate = $dp_percent / 100;
$loan_rate = $sys_settings['default_loan_rate'] ?? 3.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $res_id = intval($_POST['reservation_id'] ?? 0);

    try {
        switch ($action) {

            case 'process_to_loan':
                $order_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_id FROM reservations WHERE reservation_id = $res_id"));
                $car_id = $order_info['car_id'];
                $check_car = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_status_status FROM car_status WHERE car_id = $car_id"));
                if ($check_car['car_status_status'] === 'Inactive') {
                    echo json_encode(['success' => false, 'message' => 'Cannot process. This car model is currently Inactive.']);
                    break;
                }
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Loan Processing' WHERE reservation_id=$res_id");
                $res = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT cs.car_status_price FROM reservations r
                     JOIN cars c ON r.car_id = c.car_id
                     JOIN car_status cs ON c.car_id = cs.car_id
                     WHERE r.reservation_id = $res_id"
                ));
                $dp = round($res['car_status_price'] * $dp_rate, 2);
                $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM down_payments WHERE reservation_id=$res_id"));
                if (!$exists) {
                    mysqli_query($conn, "INSERT INTO down_payments (reservation_id, dp_amount, dp_status, dp_created_at) VALUES ($res_id, $dp, 'Pending', NOW())");
                }
                echo json_encode(['success' => true, 'message' => 'Moved to Loan Processing. Down payment record created.']);
                break;

            case 'approve_dp':
                mysqli_query($conn, "UPDATE down_payments SET dp_status='Approved', dp_approved_at=NOW() WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Down payment approved.']);
                break;

            case 'reject_dp':
                $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Rejected by admin');
                mysqli_query($conn, "UPDATE down_payments SET dp_status='Cancelled', dp_reason='$reason' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Down payment rejected.']);
                break;

            case 'mark_sold':
                $dp_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dp_status FROM down_payments WHERE reservation_id=$res_id"));
                if (!$dp_row || $dp_row['dp_status'] !== 'Approved') {
                    echo json_encode(['success' => false, 'message' => 'Down payment must be Approved before marking as Sold.']);
                    break;
                }
                $order_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_id FROM reservations WHERE reservation_id = $res_id"));
                $car_id = $order_info['car_id'];
                $check_car = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_status_status FROM car_status WHERE car_id = $car_id"));
                if ($check_car['car_status_status'] !== 'Active') {
                    echo json_encode(['success' => false, 'message' => 'Cannot sell. This car model has been marked as Inactive.']);
                    break;
                }
                mysqli_query($conn, "UPDATE car_status cs JOIN reservations r ON r.car_id = cs.car_id SET cs.car_status_stock_quantity = cs.car_status_stock_quantity - 1 WHERE r.reservation_id = $res_id");
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Sold', reservation_sold_at=NOW() WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Marked as Sold. Stock deducted.']);
                break;

            case 'cancel_reservation':
                $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Cancelled', reservation_cancel_reason='$reason' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Reservation cancelled.']);
                break;

            case 'update_plate':
                $plate = mysqli_real_escape_string($conn, $_POST['plate'] ?? '');
                mysqli_query($conn, "UPDATE reservations SET assigned_plate='$plate' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Number plate assigned to this order.']);
                break;

            case 'update_address':
                $addr = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
                $city = mysqli_real_escape_string($conn, $_POST['city'] ?? '');
                $state = mysqli_real_escape_string($conn, $_POST['state'] ?? '');
                $post = mysqli_real_escape_string($conn, $_POST['postcode'] ?? '');
                $uid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM reservations WHERE reservation_id=$res_id"))['user_id'];
                mysqli_query($conn, "UPDATE users SET user_address='$addr', user_city='$city', user_state='$state', user_postcode='$post' WHERE user_id=$uid");
                echo json_encode(['success' => true, 'message' => 'Address updated.']);
                break;

            case 'upload_document':
                $doc_type = mysqli_real_escape_string($conn, $_POST['doc_type']);
                $allowed = ['driving_licence', 'bank_statement', 'salary_slip', 'ic_pdf'];
                if (!in_array($doc_type, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
                    break;
                }
                if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'Upload failed.']);
                    break;
                }
                $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
                    break;
                }
                $target_dir = "../../uploads/documents/";
                if (!is_dir($target_dir))
                    mkdir($target_dir, 0777, true);
                $filename = $doc_type . '_res' . $res_id . '_' . time() . '.pdf';
                $filepath = $target_dir . $filename;
                $web_url = '../../uploads/documents/' . $filename;
                move_uploaded_file($_FILES['doc_file']['tmp_name'], $filepath);
                mysqli_query($conn, "UPDATE reservations SET {$doc_type}_url='$web_url' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Document uploaded.', 'file_url' => $web_url]);
                break;

            case 'add_reservation':
                $user_id = intval($_POST['user_id']);
                $car_id = intval($_POST['car_id']);
                $amount = floatval($_POST['payment_amount']);
                $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
                mysqli_begin_transaction($conn);
                mysqli_query($conn, "INSERT INTO reservations (user_id, car_id, reservation_status, reservation_created_at) VALUES ($user_id, $car_id, 'Pending Viewing', NOW())");
                $new_res_id = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO payments (reservation_id, payment_amount, payment_status, payment_method, payment_date) VALUES ($new_res_id, $amount, 'Paid', '$method', NOW())");
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'ORD' . str_pad($new_res_id, 3, '0', STR_PAD_LEFT) . ' created successfully.']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$active_tab = $_GET['tab'] ?? 'bookings';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = $_GET['status'] ?? 'all';

$search_condition = "";
if (!empty($search)) {
    $search_condition .= " AND (u.user_name LIKE '%$search%' OR c.car_plate LIKE '%$search%' OR c.car_model LIKE '%$search%') ";
}
if ($status_filter !== 'all') {
    $esc = mysqli_real_escape_string($conn, $status_filter);
    $search_condition .= " AND r.reservation_status = '$esc' ";
}

if ($active_tab === 'transactions') {
    $tab_condition = " AND r.reservation_status = 'Loan Processing' ";
} elseif ($active_tab === 'history') {
    $tab_condition = " AND r.reservation_status IN ('Sold', 'Cancelled', 'Refunded') ";
} else {
    $tab_condition = " AND r.reservation_status = 'Pending Viewing' ";
}

$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(DISTINCT r.reservation_id) as t FROM reservations r LEFT JOIN users u ON r.user_id=u.user_id LEFT JOIN cars c ON r.car_id=c.car_id WHERE 1=1 $search_condition $tab_condition";
$total_rows = mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['t'];
$total_pages = max(1, ceil($total_rows / $limit));

$query = "
    SELECT r.*,
           u.user_name, u.user_ic, u.user_phone, u.user_email, u.user_avatar,
           COALESCE(u.user_address, '') as user_address,
           COALESCE(u.user_city,    '') as user_city,
           COALESCE(u.user_state,   '') as user_state,
           COALESCE(u.user_postcode,'') as user_postcode,
           c.car_brand, c.car_model, c.car_year, c.car_origin,
           c.variant as car_variant,
           COALESCE(r.assigned_plate, c.car_plate) as car_plate, 
           COALESCE(r.assigned_color, 'Pending Selection') as car_color,
           p.payment_amount, p.payment_status, p.payment_method, p.payment_date,
           cs.car_status_stock_quantity as stock,
           cs.car_status_price as price,
           dp.dp_amount, dp.dp_status, dp.dp_approved_at, dp.dp_reason,
           (SELECT COUNT(*) FROM reservations r2 WHERE r2.user_id=u.user_id AND r2.reservation_status='Sold') as total_sold,
           (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) as car_image
    FROM reservations r
    LEFT JOIN users u          ON r.user_id        = u.user_id
    LEFT JOIN cars c           ON r.car_id         = c.car_id
    LEFT JOIN payments p       ON r.reservation_id = p.reservation_id
    LEFT JOIN car_status cs    ON c.car_id         = cs.car_id
    LEFT JOIN down_payments dp ON r.reservation_id = dp.reservation_id
    WHERE 1=1 $search_condition $tab_condition
    GROUP BY r.reservation_id
    ORDER BY r.reservation_created_at DESC
    LIMIT $limit OFFSET $offset
";
$result = mysqli_query($conn, $query);

$count_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT reservation_id) as c FROM reservations WHERE reservation_status='Pending Viewing'"))['c'];
$count_transactions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT reservation_id) as c FROM reservations WHERE reservation_status='Loan Processing'"))['c'];
$count_history = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT reservation_id) as c FROM reservations WHERE reservation_status IN ('Sold','Cancelled','Refunded')"))['c'];

$users_query = mysqli_query($conn, "SELECT user_id, user_name, user_ic FROM users WHERE user_role='Customer' ORDER BY user_name");
$cars_query = mysqli_query($conn, "SELECT c.car_id, c.car_brand, c.car_model, cs.car_status_price FROM cars c JOIN car_status cs ON c.car_id=cs.car_id WHERE cs.car_status_stock_quantity>0 AND cs.car_status_status='Active' ORDER BY c.car_brand");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @media print {

            body * {
                visibility: hidden;
            }

            #splitModal,
            #splitModal * {
                visibility: visible;
            }

            #splitModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
            }

            .modal-container {
                width: 100%;
                box-shadow: none;
                border: none;
                max-height: none;
            }

            .modal-body {
                padding: 0;
                overflow: visible;
            }

            .modal-footer,
            .close-btn,
            .edit-inline-btn,
            .upload-btn-label,
            #dpActionsWrap {
                display: none !important;
            }

            .layout-grid {
                flex-direction: column;
                gap: 20px;
            }

            .info-card {
                border: 1px solid #000;
                box-shadow: none;
                break-inside: avoid;
            }

            .bottom-card,
            .dp-panel {
                border: 1px solid #000;
                box-shadow: none;
            }
        }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            flex-shrink: 0;
        }

        .status-cell {
            display: flex;
            align-items: center;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .72);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-container {
            background: #fff;
            width: 1140px;
            max-height: 92vh;
            border-radius: 14px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .22);
            display: flex;
            flex-direction: column;
        }

        .modal-container-sm {
            width: 580px;
        }

        .modal-header {
            padding: 18px 26px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border-radius: 14px 14px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #9ca3af;
            transition: color .15s;
        }

        .close-btn:hover {
            color: #111827;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            background: #f3f4f6;
            flex: 1;
        }

        .modal-footer {
            padding: 14px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #fff;
            border-radius: 0 0 14px 14px;
        }

        .layout-grid {
            display: flex;
            gap: 18px;
        }

        .info-card {
            flex: 1;
            background: #fff;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
            border-top: 4px solid;
        }

        .user-card {
            border-top-color: #3b82f6;
        }

        .car-card {
            border-top-color: #10b981;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
        }

        .user-card .section-title {
            color: #1e3a8a;
            border-bottom: 2px solid #eff6ff;
        }

        .car-card .section-title {
            color: #064e3b;
            border-bottom: 2px solid #ecfdf5;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .detail-item label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: .6px;
        }

        .detail-item p {
            font-size: 14px;
            color: #111827;
            font-weight: 600;
            margin: 0;
        }

        .doc-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 0;
            font-size: 13px;
        }

        .doc-link:hover {
            color: #1d4ed8;
        }

        .finance-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 18px;
        }

        .finance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .finance-row.total {
            border-top: 2px dashed #cbd5e1;
            padding-top: 12px;
            margin-top: 12px;
            margin-bottom: 0;
        }

        .bottom-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 22px;
            margin-top: 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
            border-left: 4px solid #f59e0b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dp-panel {
            background: #fff;
            border-radius: 10px;
            padding: 20px 22px;
            margin-top: 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
            border-left: 4px solid #6366f1;
        }

        .dp-panel h3 {
            margin: 0 0 14px;
            font-size: 14px;
            font-weight: 700;
            color: #4338ca;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dp-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .plate-tag {
            background: #111827;
            color: #fff;
            display: inline-block;
            padding: 3px 10px;
            border-radius: 5px;
            font-family: monospace;
            font-weight: 700;
        }

        .btn-modal {
            padding: 9px 18px;
            border-radius: 7px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 13px;
            transition: opacity .2s;
        }

        .btn-modal:hover {
            opacity: .85;
        }

        .btn-modal-cancel {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-modal-process {
            background: #10b981;
            color: #fff;
        }

        .btn-modal-approve {
            background: #3b82f6;
            color: #fff;
        }

        .btn-modal-reject {
            background: #ef4444;
            color: #fff;
        }

        .btn-modal-sold {
            background: #7c3aed;
            color: #fff;
        }

        .btn-modal-danger {
            background: #ef4444;
            color: #fff;
        }

        .btn-modal-sm {
            padding: 5px 12px;
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #374151;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 13px;
            box-sizing: border-box;
        }

        #rejectReasonWrap {
            margin-top: 12px;
            display: none;
        }

        #rejectReasonWrap textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            height: 70px;
            resize: vertical;
            box-sizing: border-box;
        }

        .data-row {
            cursor: pointer;
            transition: background .12s;
        }

        .data-row:hover {
            background: #f9fafb;
        }

        .edit-inline-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #3b82f6;
            font-size: 12px;
            margin-left: 6px;
            padding: 0;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar" style="margin-bottom:24px;">
            <div class="page-title">
                <h1 style="font-size:24px; font-weight:700; color:#111827;">Orders</h1>
            </div>
        </header>

        <div class="page-tabs">
            <a href="orders.php?tab=bookings"
                class="tab-item <?= $active_tab === 'bookings' ? 'active' : '' ?>">Bookings (<?= $count_bookings ?>)</a>
            <a href="orders.php?tab=transactions"
                class="tab-item <?= $active_tab === 'transactions' ? 'active' : '' ?>">Transactions
                (<?= $count_transactions ?>)</a>
            <a href="orders.php?tab=history" class="tab-item <?= $active_tab === 'history' ? 'active' : '' ?>">History
                (<?= $count_history ?>)</a>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <form method="GET" style="display:flex; gap:16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <div style="position:relative;">
                    <i class="fas fa-search"
                        style="position:absolute; left:14px; top:11px; color:#9ca3af; font-size:14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search name, plate, model…"
                        style="padding-left:38px; width:280px; font-size:13px;"
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <?php if ($active_tab === 'history'): ?>
                    <select name="status" class="form-control" style="width:150px; font-size:13px;"
                        onchange="this.form.submit()">
                        <option value="all">All Status</option>
                        <option value="Sold" <?= $status_filter === 'Sold' ? 'selected' : '' ?>>Sold</option>
                        <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                <?php endif; ?>
            </form>
            <div>
                <button onclick="openAddModal()" class="btn-add-blue"><i class="fas fa-plus"></i> Add
                    Reservation</button>
            </div>
        </div>

        <div class="table-card" style="padding:0; border:none;">
            <table class="admin-table" id="ordersTable">
                <thead>
                    <tr>
                        <th style="text-align:left; width:12%;">Order ID</th>
                        <th style="text-align:left; width:8%;">Avatar</th>
                        <th style="text-align:left; width:20%;">Customer</th>
                        <th style="text-align:left; width:20%;">Car Model</th>
                        <th style="text-align:left; width:12%;">Booking Fee</th>
                        <?php if (in_array($active_tab, ['transactions', 'history'])): ?>
                            <th style="text-align:left; width:13%;">Down Payment</th>
                        <?php endif; ?>
                        <th style="text-align:left; width:13%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $st = $row['reservation_status'];
                            $dot_color = match ($st) {
                                'Loan Processing' => '#3b82f6',
                                'Sold' => '#10b981',
                                'Cancelled' => '#ef4444',
                                'Refunded' => '#8b5cf6',
                                default => '#f59e0b'
                            };
                            $name_parts = explode(' ', trim($row['user_name'] ?? ''));
                            $initials = '';
                            foreach ($name_parts as $part) {
                                if (!empty($part))
                                    $initials .= strtoupper(mb_substr($part, 0, 1, 'UTF-8'));
                            }
                            $initials = mb_substr($initials, 0, 3, 'UTF-8');
                            $avatar_content = !empty($row['user_avatar'])
                                ? "<img src='" . htmlspecialchars($row['user_avatar']) . "' style='width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;'>"
                                : $initials;
                            $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            ?>
                            <tr class="data-row" onclick='openModal(<?= $row_json ?>)'>
                                <td style="text-align: left; color: #6b7280;">
                                    ORD<?= str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td style="text-align:left;">
                                    <div
                                        style="width:42px;height:42px;font-size:14px;font-weight:700;letter-spacing:0.5px;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;border-radius:50%;background-color:#e0e7ff;color:#6366f1;margin:0 auto;">
                                        <?= $avatar_content ?>
                                    </div>
                                </td>
                                <td style="text-align:left;">
                                    <div style="font-weight:500; color:#111827; font-size:14px;">
                                        <?= htmlspecialchars($row['user_name']) ?>
                                    </div>
                                    <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($row['user_phone']) ?>
                                    </div>
                                </td>
                                <td style="text-align:left;">
                                    <div style="font-weight:500; color:#111827; font-size:14px;">
                                        <?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?>
                                    </div>
                                    <div style="font-size:12px; color:#6b7280;">
                                        <?= htmlspecialchars($row['car_plate'] ?: 'No Plate') ?>
                                    </div>
                                </td>
                                <td style="text-align:left; font-weight:500; color:#111827; font-size:14px;">
                                    RM <?= number_format($row['payment_amount'], 2) ?>
                                </td>
                                <?php if (in_array($active_tab, ['transactions', 'history'])): ?>
                                    <td style="text-align:left;">
                                        <?php
                                        $ds = $row['dp_status'] ?? 'Pending';
                                        $dp_dot = match ($ds) { 'Approved' => '#10b981', 'Cancelled' => '#ef4444', default => '#f59e0b'};
                                        ?>
                                        <div class="status-cell">
                                            <span class="dot print-hide" style="background:<?= $dp_dot ?>;"></span>
                                            <span
                                                style="color:<?= $dp_dot ?>; font-weight:600; font-size:13px;"><?= htmlspecialchars($ds) ?></span>
                                        </div>
                                        <div style="font-size:11px; color:#6b7280; margin-top:2px; padding-left:14px;">RM
                                            <?= number_format($row['dp_amount'] ?? 0, 2) ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align:left;">
                                    <div class="status-cell" style="width:150px;">
                                        <span class="dot print-hide" style="background:<?= $dot_color ?>;"></span>
                                        <span
                                            style="color:<?= $dot_color ?>; font-weight:600; font-size:13px;"><?= $st ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $active_tab === 'transactions' ? 8 : 7 ?>"
                                style="text-align:center; padding:48px 0; color:#6b7280; font-size:14px;">No records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-container"
                style="padding:20px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e5e7eb;">
                <div class="page-info" style="color:#6b7280; font-size:13px;">Showing Page <?= $page ?> of
                    <?= $total_pages ?>
                </div>
                <?php if ($total_pages > 1):
                    $base = "?tab=" . urlencode($active_tab) . "&search=" . urlencode($search) . "&status=" . urlencode($status_filter);
                    ?>
                    <div class="page-controls" style="display:flex; gap:8px;">
                        <a href="orders.php<?= $base ?>&page=<?= max(1, $page - 1) ?>" class="page-btn"
                            style="text-decoration:none;"><i class="fas fa-angle-left"></i> Prev</a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="orders.php<?= $base ?>&page=<?= $i ?>" class="page-btn <?= $page == $i ? 'active' : '' ?>"
                                style="text-decoration:none;"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="orders.php<?= $base ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn"
                            style="text-decoration:none;">Next <i class="fas fa-angle-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="splitModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice" style="color:#6366f1; margin-right:8px;"></i>Reservation Dossier</h2>
                <button onclick="closeModal()" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="layout-grid">

                    <div class="info-card user-card">
                        <div class="section-title"><i class="fas fa-user-circle"></i> User Information</div>
                        <div class="grid-2">
                            <div class="detail-item"><label>Name</label>
                                <p id="detName">-</p>
                            </div>
                            <div class="detail-item"><label>Gmail</label>
                                <p id="detEmail" style="word-break:break-all;">-</p>
                            </div>
                            <div class="detail-item"><label>IC Number</label>
                                <p id="detIC">-</p>
                            </div>
                            <div class="detail-item"><label>Contact</label>
                                <p id="detContact">-</p>
                            </div>
                        </div>

                        <hr style="border:0; border-top:1px dashed #e5e7eb; margin:16px 0;">
                        <div id="pdfSectionWrap" style="display:none;">
                            <div class="detail-item">
                                <label>Documents (PDF)</label>

                                <div
                                    style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-weight:600; font-size:13px; color:#374151;"><i
                                            class="fas fa-id-card" style="color:#3b82f6; margin-right:6px;"></i>IC
                                        Document</span>
                                    <div style="display:flex; gap:14px; align-items:center;">
                                        <button type="button" id="btnView_ic_pdf" onclick="togglePdf('frameIcPdf')"
                                            style="background:none; border:none; color:#4b5563; font-size:12px; font-weight:600; cursor:pointer; display:none;"><i
                                                class="fas fa-eye"></i> View</button>
                                        <label class="upload-btn-label"
                                            style="cursor:pointer; color:#3b82f6; font-size:12px; font-weight:600; margin:0;">
                                            <span id="lblUpload_ic_pdf"><i class="fas fa-upload"></i> Upload</span>
                                            <input type="file" accept=".pdf" style="display:none;"
                                                onchange="uploadDoc(this,'ic_pdf')">
                                        </label>
                                    </div>
                                </div>
                                <iframe id="frameIcPdf" src=""
                                    style="width:100%; height:400px; display:none; border:1px solid #d1d5db; border-radius:6px; margin-top:8px;"></iframe>

                                <div
                                    style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-weight:600; font-size:13px; color:#374151;"><i
                                            class="fas fa-file-pdf" style="color:#ef4444; margin-right:6px;"></i>Driving
                                        Licence</span>
                                    <div style="display:flex; gap:14px; align-items:center;">
                                        <button type="button" id="btnView_driving_licence"
                                            onclick="togglePdf('frameDrivingLicence')"
                                            style="background:none; border:none; color:#4b5563; font-size:12px; font-weight:600; cursor:pointer; display:none;"><i
                                                class="fas fa-eye"></i> View</button>
                                        <label class="upload-btn-label"
                                            style="cursor:pointer; color:#3b82f6; font-size:12px; font-weight:600; margin:0;">
                                            <span id="lblUpload_driving_licence"><i class="fas fa-upload"></i>
                                                Upload</span>
                                            <input type="file" accept=".pdf" style="display:none;"
                                                onchange="uploadDoc(this,'driving_licence')">
                                        </label>
                                    </div>
                                </div>
                                <iframe id="frameDrivingLicence" src=""
                                    style="width:100%; height:400px; display:none; border:1px solid #d1d5db; border-radius:6px; margin-top:8px;"></iframe>

                                <div
                                    style="display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-weight:600; font-size:13px; color:#374151;"><i
                                            class="fas fa-file-pdf" style="color:#ef4444; margin-right:6px;"></i>Bank
                                        Statement</span>
                                    <div style="display:flex; gap:14px; align-items:center;">
                                        <button type="button" id="btnView_bank_statement"
                                            onclick="togglePdf('frameBankStatement')"
                                            style="background:none; border:none; color:#4b5563; font-size:12px; font-weight:600; cursor:pointer; display:none;"><i
                                                class="fas fa-eye"></i> View</button>
                                        <label class="upload-btn-label"
                                            style="cursor:pointer; color:#3b82f6; font-size:12px; font-weight:600; margin:0;">
                                            <span id="lblUpload_bank_statement"><i class="fas fa-upload"></i>
                                                Upload</span>
                                            <input type="file" accept=".pdf" style="display:none;"
                                                onchange="uploadDoc(this,'bank_statement')">
                                        </label>
                                    </div>
                                </div>
                                <iframe id="frameBankStatement" src=""
                                    style="width:100%; height:400px; display:none; border:1px solid #d1d5db; border-radius:6px; margin-top:8px;"></iframe>

                                <div
                                    style="display:flex; align-items:center; justify-content:space-between; padding:6px 0;">
                                    <span style="font-weight:600; font-size:13px; color:#374151;"><i
                                            class="fas fa-file-pdf" style="color:#ef4444; margin-right:6px;"></i>3 Month
                                        Salary</span>
                                    <div style="display:flex; gap:14px; align-items:center;">
                                        <button type="button" id="btnView_salary_slip"
                                            onclick="togglePdf('frameSalarySlip')"
                                            style="background:none; border:none; color:#4b5563; font-size:12px; font-weight:600; cursor:pointer; display:none;"><i
                                                class="fas fa-eye"></i> View</button>
                                        <label class="upload-btn-label"
                                            style="cursor:pointer; color:#3b82f6; font-size:12px; font-weight:600; margin:0;">
                                            <span id="lblUpload_salary_slip"><i class="fas fa-upload"></i> Upload</span>
                                            <input type="file" accept=".pdf" style="display:none;"
                                                onchange="uploadDoc(this,'salary_slip')">
                                        </label>
                                    </div>
                                </div>
                                <iframe id="frameSalarySlip" src=""
                                    style="width:100%; height:400px; display:none; border:1px solid #d1d5db; border-radius:6px; margin-top:8px;"></iframe>
                            </div>
                            <hr style="border:0; border-top:1px dashed #e5e7eb; margin:16px 0;">
                        </div>

                        <div class="detail-item">
                            <label>
                                Billing Address
                                <button id="editAddressBtn" class="edit-inline-btn" onclick="toggleEditAddress(true)"><i
                                        class="fas fa-pen"></i></button>
                            </label>

                            <div id="addressViewMode">
                                <p id="detAddress" style="font-weight:normal; margin-bottom:10px;">-</p>
                                <div class="grid-3">
                                    <div><label style="font-size:9px;">City</label>
                                        <p id="detCity" style="font-size:13px;">-</p>
                                    </div>
                                    <div><label style="font-size:9px;">State</label>
                                        <p id="detState" style="font-size:13px;">-</p>
                                    </div>
                                    <div><label style="font-size:9px;">Postcode</label>
                                        <p id="detPostcode" style="font-size:13px;">-</p>
                                    </div>
                                </div>
                            </div>

                            <div id="addressEditMode"
                                style="display:none; margin-top:8px; padding:12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">

                                <input type="text" id="inlineAddr" class="form-control"
                                    style="margin-bottom:8px; width:100%; box-sizing:border-box;" placeholder="Address">

                                <div style="position:relative; width:100%; height:36px; margin-bottom:8px;">
                                    <select id="inlineState" class="form-control"
                                        onmousedown="this.size=4; this.style.position='absolute'; this.style.zIndex='9999';"
                                        onchange="this.size=1; this.style.position='static'; inlinePopulateCities(); this.blur();"
                                        onblur="this.size=1; this.style.position='static';"
                                        style="width:100%; box-sizing:border-box; top:0; left:0;">
                                        <option value="">Select State</option>
                                    </select>
                                </div>

                                <div style="position:relative; width:100%; height:36px; margin-bottom:8px;">
                                    <select id="inlineCity" class="form-control"
                                        onmousedown="this.size=4; this.style.position='absolute'; this.style.zIndex='9999';"
                                        onchange="this.size=1; this.style.position='static'; this.blur();"
                                        onblur="this.size=1; this.style.position='static';"
                                        style="width:100%; box-sizing:border-box; top:0; left:0;">
                                        <option value="">Select City</option>
                                    </select>
                                </div>

                                <input type="text" id="inlinePost" class="form-control"
                                    style="margin-bottom:8px; width:100%; box-sizing:border-box;"
                                    placeholder="Postcode">

                                <div style="display:flex; gap:8px; margin-top:4px;">
                                    <button class="btn-modal btn-modal-approve" onclick="saveInlineAddress()"
                                        style="flex:1;">Save</button>
                                    <button class="btn-modal btn-modal-cancel" onclick="toggleEditAddress(false)"
                                        style="flex:1;">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card car-card">
                        <div class="section-title"><i class="fas fa-car"></i> Car Information</div>
                        <div style="display:flex; gap:14px;">
                            <img id="detCarImage" src="" alt="Car"
                                style="width:120px;height:88px;object-fit:cover;border-radius:8px;background:#e2e8f0;border:1px solid #e5e7eb;flex-shrink:0;">
                            <div class="grid-2" style="flex:1;">
                                <div class="detail-item"><label>Brand</label>
                                    <p id="detCarBrand">-</p>
                                </div>
                                <div class="detail-item"><label>Model</label>
                                    <p id="detCarModel">-</p>
                                </div>
                                <div class="detail-item"><label>Variant/Trim</label>
                                    <p id="detCarVariant">-</p>
                                </div>
                                <div class="detail-item"><label>Colour</label>
                                    <p id="detCarColor">-</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2" style="margin-top:14px;">
                            <div class="detail-item"><label>Condition / Origin</label>
                                <p id="detCarOrigin">-</p>
                            </div>

                            <div class="detail-item">
                                <label>
                                    Number Plate
                                    <button id="editPlateBtn" class="edit-inline-btn" onclick="toggleEditPlate(true)"><i
                                            class="fas fa-pen"></i></button>
                                </label>
                                <div id="plateViewMode">
                                    <p><span id="detCarPlate" class="plate-tag">-</span></p>
                                </div>
                                <div id="plateEditMode"
                                    style="display:none; margin-top:6px; gap:6px; align-items:center;">
                                    <input type="text" id="inlinePlate" class="form-control" style="width:120px;"
                                        placeholder="Plate No.">
                                    <button class="btn-modal btn-modal-approve btn-modal-sm"
                                        onclick="saveInlinePlate()"><i class="fas fa-check"></i></button>
                                    <button class="btn-modal btn-modal-cancel btn-modal-sm"
                                        onclick="toggleEditPlate(false)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>

                            <div class="detail-item"><label>Quantity</label>
                                <p id="detCarStock">-</p>
                            </div>
                            <div class="detail-item"><label>Year</label>
                                <p id="detCarYear">-</p>
                            </div>
                        </div>

                        <div class="finance-box">
                            <div class="finance-row">
                                <span style="font-size:13px;font-weight:600;color:#6b7280;">Price</span>
                                <span id="detPrice" style="font-size:16px;font-weight:700;color:#111827;">RM 0.00</span>
                            </div>
                            <div class="finance-row">
                                <span style="font-size:13px;font-weight:600;color:#6b7280;">Down Payment
                                    (<?= $sys_settings['default_dp_percent'] ?? 10 ?>%)</span>
                                <span id="detDP" style="font-size:14px;font-weight:600;color:#dc2626;">- RM 0.00</span>
                            </div>
                            <div class="finance-row">
                                <span style="font-size:13px;font-weight:600;color:#6b7280;">Booking Fee Paid</span>
                                <span id="detBookingFee" style="font-size:14px;font-weight:600;color:#10b981;">RM
                                    0.00</span>
                            </div>
                            <div class="finance-row total">
                                <div>
                                    <span style="display:block;font-size:14px;font-weight:700;color:#2563eb;">Est.
                                        Monthly Installment</span>
                                    <span style="font-size:11px;color:#9ca3af;">
                                        Loan
                                        <select id="loanYears" onchange="recalcMonthly()"
                                            style="border:1px solid #d1d5db;border-radius:4px;font-size:11px;padding:1px 4px;">
                                            <option value="5">5 Yrs</option>
                                            <option value="7">7 Yrs</option>
                                            <option value="9" selected>9 Yrs</option>
                                        </select>
                                        @ <?= $loan_rate ?>% P.A.
                                    </span>
                                </div>
                                <span id="detMonthly" style="font-size:20px;font-weight:800;color:#2563eb;">RM 0.00 /
                                    mo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="dpPanel" class="dp-panel" style="display:none;">
                    <h3><i class="fas fa-hand-holding-usd"></i> Down Payment Approval</h3>
                    <div class="grid-2">
                        <div class="detail-item"><label>DP Amount</label>
                            <p id="detDPAmount">-</p>
                        </div>
                        <div class="detail-item"><label>DP Status</label>
                            <p id="detDPStatus">-</p>
                        </div>
                        <div class="detail-item"><label>Approved At</label>
                            <p id="detDPApproved">-</p>
                        </div>
                        <div class="detail-item"><label>Reject Reason</label>
                            <p id="detDPReason" style="color:#dc2626;">-</p>
                        </div>
                    </div>
                    <div id="dpActionsWrap" class="dp-actions">
                        <button class="btn-modal btn-modal-approve" onclick="approveDP()"><i
                                class="fas fa-check-circle"></i> Approve DP</button>
                        <button class="btn-modal btn-modal-reject" onclick="toggleRejectReason()"><i
                                class="fas fa-times-circle"></i> Reject DP</button>
                    </div>
                    <div id="rejectReasonWrap">
                        <textarea id="rejectReasonText" placeholder="Enter reason for rejection…"></textarea>
                        <button class="btn-modal btn-modal-danger btn-modal-sm" style="margin-top:6px;"
                            onclick="rejectDP()">Confirm Reject</button>
                    </div>
                </div>

                <div class="bottom-card">
                    <div>
                        <label
                            style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Order
                            ID</label>
                        <div id="detResID" style="font-family:monospace;font-size:20px;font-weight:800;color:#d97706;">
                            ORD000</div>
                    </div>
                    <div>
                        <label
                            style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Status</label>
                        <div id="detStatus" style="font-size:15px;font-weight:700;color:#111827;">-</div>
                    </div>
                    <div>
                        <label
                            style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Payment
                            Method</label>
                        <div id="detPayMethod" style="font-size:15px;font-weight:600;color:#111827;">-</div>
                    </div>
                    <div>
                        <label
                            style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Reserved
                            On</label>
                        <div id="detCreatedAt" style="font-size:14px;font-weight:600;color:#374151;">-</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button onclick="closeModal()" class="btn-modal btn-modal-cancel">Close</button>

                <button id="btnPrintDossier" class="btn-modal" onclick="window.print()"
                    style="display:none; background:#f3f4f6; color:#374151; border:1px solid #d1d5db;">
                    <i class="fas fa-print"></i> Print Dossier
                </button>

                <button id="btnProcessLoan" class="btn-modal btn-modal-process" onclick="processToLoan()"
                    style="display:none;"><i class="fas fa-arrow-right"></i> Process to Loan</button>
                <button id="btnMarkSold" class="btn-modal btn-modal-sold" onclick="markSold()" style="display:none;"><i
                        class="fas fa-check-double"></i> Mark as Sold &amp; Deduct Stock</button>
                <button id="btnCancelRes" class="btn-modal btn-modal-danger" onclick="cancelReservation()"
                    style="display:none;"><i class="fas fa-ban"></i> Cancel Reservation</button>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay hidden">
        <div class="modal-container modal-container-sm">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle" style="color:#3b82f6;margin-right:8px;"></i>Create New Reservation
                </h2>
                <button onclick="closeAddModal()" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addReservationForm">
                <div class="modal-body" style="background:#fff;">
                    <div class="form-group">
                        <label>Select Customer</label>
                        <select name="user_id" required>
                            <option value="">-- Choose Customer --</option>
                            <?php while ($u = mysqli_fetch_assoc($users_query)): ?>
                                <option value="<?= $u['user_id'] ?>">
                                    <?= htmlspecialchars($u['user_name'] . ' (' . $u['user_ic'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Car</label>
                        <select name="car_id" required>
                            <option value="">-- Choose Car --</option>
                            <?php while ($c = mysqli_fetch_assoc($cars_query)): ?>
                                <option value="<?= $c['car_id'] ?>">
                                    <?= htmlspecialchars($c['car_brand'] . ' ' . $c['car_model'] . ' — RM ' . number_format($c['car_status_price'], 2)) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Booking Fee Amount (RM)</label>
                        <input type="number" step="0.01" min="0" name="payment_amount" placeholder="e.g. 500.00"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" required>
                            <option value="Online Transfer">Online Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-add-blue" style="border:none;"><i class="fas fa-save"></i> Save
                        Reservation</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.GLOBAL_DP_RATE = <?= isset($dp_rate) ? $dp_rate : 0.10 ?>;
        window.GLOBAL_LOAN_RATE = <?= isset($loan_rate) ? $loan_rate : 3.00 ?>;
    </script>
    <script src="../../JAVA SCRIPT/orders.js?v=<?= time() ?>"></script>
</body>

</html>