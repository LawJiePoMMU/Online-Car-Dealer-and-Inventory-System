<?php
session_start();
include '../database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$search_condition = "";
if (!empty($search)) {
    $search_condition = " AND (u.user_name LIKE '%$search%' OR c.car_plate LIKE '%$search%' OR c.car_model LIKE '%$search%') ";
}

if ($status_filter !== 'all') {
    $search_condition .= " AND r.reservation_status = '$status_filter' ";
}

$tab_condition = " AND r.reservation_status IN ('Pending Viewing', 'Loan Processing') ";

$query = "SELECT r.*, u.user_name, u.user_ic, u.user_phone, u.user_email, u.user_avatar,
          c.car_brand, c.car_model, c.car_year, c.car_plate, c.car_origin,
          p.payment_amount, p.payment_status, p.payment_method
          FROM reservations r
          LEFT JOIN users u ON r.user_id = u.user_id
          LEFT JOIN cars c ON r.car_id = c.car_id
          LEFT JOIN payments p ON r.reservation_id = p.reservation_id
          WHERE 1=1 $search_condition $tab_condition
          ORDER BY r.reservation_created_at DESC";

$result = mysqli_query($conn, $query);

$count_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations r WHERE r.reservation_status IN ('Pending Viewing', 'Loan Processing')"))['c'];
$count_history = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations r WHERE r.reservation_status IN ('Sold', 'Cancelled', 'Refunded')"))['c'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations</title>
    <link rel="stylesheet" href="../../CSS/Admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        split-modal {
            display: flex;
            height: 65vh;
            gap: 0;
        }

        .split-left {
            flex: 1;
            border: 3px solid #3b82f6;
            border-radius: 8px 0 0 8px;
            padding: 24px;
            overflow-y: auto;
            background: #eff6ff;
        }

        .split-right {
            flex: 1;
            border: 3px solid #10b981;
            border-left: none;
            border-radius: 0 8px 8px 0;
            padding: 24px;
            overflow-y: auto;
            background: #ecfdf5;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(17, 24, 39, 0.7);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-container {
            background: white;
            width: 950px;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6b7280;
        }

        .modal-body {
            padding: 24px;
        }

        .detail-group {
            margin-bottom: 16px;
        }

        .detail-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .detail-group p {
            font-size: 15px;
            color: #111827;
            font-weight: 500;
        }

        .plate-edit-wrap {
            display: flex;
            gap: 8px;
            margin-top: 24px;
        }

        .plate-edit-wrap input {
            flex: 1;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .btn-update {
            background: #10b981;
            color: white;
            border: none;
            padding: 0 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f9fafb;
            border-radius: 0 0 12px 12px;
        }

        .btn-process {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-refund {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-cancel {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .left-title {
            color: #1d4ed8;
        }

        .right-title {
            color: #047857;
        }

        .data-row {
            cursor: pointer;
        }

        .data-row:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">Reservation</h1>
            </div>
        </header>
        <div class="page-tabs">
            <a href="reservation.php" class="tab-item active">Active Reservations (<?= $count_active ?>)</a>
            <a href="reservations history.php" class="tab-item">Reservations History (<?= $count_history ?>)</a>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
            <form method="GET" style="display: flex; gap: 16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 14px; top: 11px; color: #9ca3af;"></i>
                    <input type="text" name="search" class="form-control" style="padding-left: 38px; width: 280px;"
                        placeholder="Search Name, Plate, Model..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="form-control" style="width: 150px;" onchange="this.form.submit()">
                    <option value="all">All Status</option>
                    <option value="Pending Viewing" <?= $status_filter == 'Pending Viewing' ? 'selected' : '' ?>>Pending
                        Viewing</option>
                    <option value="Loan Processing" <?= $status_filter == 'Loan Processing' ? 'selected' : '' ?>>Loan
                        Processing</option>
                </select>
            </form>
            <button class="btn-export"><i class="fas fa-print"></i> Export List</button>
        </div>

        <div class="table-card" style="padding: 0;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;"><input type="checkbox"></th>
                        <th style="width: 12%;">RES ID</th>
                        <th style="width: 25%;">CUSTOMER</th>
                        <th style="width: 20%;">CAR MODEL</th>
                        <th style="width: 15%;">DOWN PAYMENT</th>
                        <th style="width: 10%;">DATE</th>
                        <th style="width: 13%;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $status = $row['reservation_status'];
                            $dot = 'dot-pending';
                            if ($status == 'Sold')
                                $dot = 'dot-active';
                            if ($status == 'Refunded' || $status == 'Cancelled')
                                $dot = 'dot-inactive';
                            if ($status == 'Loan Processing')
                                $dot = 'dot-new';
                            ?>
                            <tr class="data-row" data-json='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                <td style="text-align: center;" onclick="event.stopPropagation();"><input type="checkbox"></td>
                                <td style="font-family: monospace; font-weight: bold; color: #4b5563;">
                                    #RES-<?= str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div
                                            style="width: 35px; height: 35px; border-radius: 50%; background: #e0e7ff; color: #4f46e5; display: flex; justify-content: center; align-items: center; font-weight: bold;">
                                            <?= strtoupper(substr($row['user_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #111827;">
                                                <?= htmlspecialchars($row['user_name']) ?>
                                            </div>
                                            <div style="font-size: 12px; color: #6b7280;">
                                                <?= htmlspecialchars($row['user_phone']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #111827;">
                                        <?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?= htmlspecialchars($row['car_plate'] ?: 'No Plate') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">RM <?= number_format($row['payment_amount'], 2) ?></div>
                                    <div style="font-size: 11px; color: #059669;">
                                        <?= htmlspecialchars($row['payment_status']) ?>
                                    </div>
                                </td>
                                <td><?= date('d M Y', strtotime($row['reservation_date'] ?? $row['reservation_created_at'])) ?>
                                </td>
                                <td>
                                    <div class="status-cell">
                                        <span class="dot <?= $dot ?>"
                                            style="background-color: <?= $status == 'Sold' ? '#10b981' : ($status == 'Refunded' ? '#ef4444' : ($status == 'Loan Processing' ? '#3b82f6' : '#f59e0b')) ?>;"></span>
                                        <span
                                            style="color: <?= $status == 'Sold' ? '#10b981' : ($status == 'Refunded' ? '#ef4444' : ($status == 'Loan Processing' ? '#3b82f6' : '#f59e0b')) ?>; font-weight: 600;"><?= $status ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">No records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </main>

        <div id="splitModal" class="modal-overlay hidden">
            <div class="modal-container">
                <div class="modal-header">
                    <h2 style="margin:0; font-size: 20px;">Reservation Record</h2>
                    <button id="closeModalBtn" class="close-btn"><i class="fas fa-times"></i></button>
                </div>

                <div class="modal-body" style="padding: 24px;">
                    <div class="split-modal">
                        <div class="split-left">
                            <div class="section-title left-title"><i class="fas fa-user-circle"></i> Customer &
                                Reservation</div>

                            <div class="detail-group">
                                <label>Customer Full Name</label>
                                <p id="detName"></p>
                            </div>
                            <div class="detail-group">
                                <label>IC Number</label>
                                <p id="detIC"></p>
                            </div>
                            <div class="detail-group">
                                <label>Contact Info</label>
                                <p id="detContact"></p>
                            </div>

                            <hr
                                style="border-top: 1px dashed #93c5fd; margin: 20px 0; border-bottom: none; border-left: none; border-right: none;">

                            <div class="detail-group">
                                <label>Reservation ID</label>
                                <p id="detResID"
                                    style="font-family: monospace; font-size: 18px; font-weight: bold; color: #1d4ed8;">
                                </p>
                            </div>
                            <div class="detail-group">
                                <label>Reservation Date</label>
                                <p id="detDate"></p>
                            </div>
                            <div class="detail-group">
                                <label>Down Payment Paid</label>
                                <p id="detDP"></p>
                            </div>
                            <div class="detail-group">
                                <label>Status</label>
                                <p id="detStatusBadge" style="font-weight:bold;"></p>
                            </div>
                        </div>

                        <div class="split-right">
                            <div class="section-title right-title"><i class="fas fa-car"></i> Vehicle Details</div>

                            <div
                                style="background: #d1fae5; border-radius: 8px; height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                                <i class="fas fa-car-side" style="font-size: 40px; color: #047857;"></i>
                            </div>

                            <div class="detail-group">
                                <label>Vehicle Make & Model</label>
                                <p id="detCarModel" style="font-size: 18px; font-weight: bold; color: #047857;"></p>
                            </div>
                            <div class="detail-group">
                                <label>Manufacture Year</label>
                                <p id="detCarYear"></p>
                            </div>
                            <div class="detail-group">
                                <label>Condition</label>
                                <p id="detCarOrigin"></p>
                            </div>

                            <div
                                style="margin-top: 30px; background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <label
                                    style="display: block; font-size: 12px; font-weight: 700; color: #047857; text-transform: uppercase; margin-bottom: 8px;">Registration
                                    Plate</label>
                                <div class="plate-edit-wrap">
                                    <input type="hidden" id="editCarId">
                                    <input type="text" id="editPlateInput" placeholder="Enter Plate No">
                                    <button id="savePlateBtn" class="btn-update">Update</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="modalActions"></div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="../../JAVA SCRIPT/reservations.js?v=<?= time() ?>"></script>
</body>

</html>