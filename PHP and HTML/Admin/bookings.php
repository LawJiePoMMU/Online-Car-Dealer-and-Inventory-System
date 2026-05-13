<?php
session_start();
include '../Config/database.php';
include '../Config/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$admin_id = $_SESSION['user_id'] ?? 1;

$sys_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('company_name','contact_phone','contact_email')");
$sys_settings = [];
while ($row = mysqli_fetch_assoc($sys_query))
    $sys_settings[$row['setting_key']] = $row['setting_value'];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $res_id = intval($_POST['reservation_id'] ?? 0);

    try {
        switch ($action) {
            case 'schedule_test_drive':
                $td_at = mysqli_real_escape_string($conn, $_POST['test_drive_at'] ?? '');
                if (!$td_at) {
                    echo json_encode(['success' => false, 'message' => 'Please select a date and time.']);
                    break;
                }
                mysqli_query($conn, "UPDATE reservations SET test_drive_at='$td_at' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Test drive scheduled.']);
                break;

            case 'mark_test_drive_done':
                $lic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT driving_licence_url FROM reservations WHERE reservation_id=$res_id"));
                if (empty($lic['driving_licence_url'])) {
                    echo json_encode(['success' => false, 'message' => 'Driving Licence must be uploaded before marking as done.']);
                    break;
                }
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Test Drive Done', test_drive_done_at=NOW() WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Test Drive marked as done.']);
                break;

            case 'convert_to_booking':
                $amount = floatval($_POST['payment_amount'] ?? 0);
                $method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');

                $td_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, car_id FROM reservations WHERE reservation_id=$res_id"));
                $car_id = $td_info['car_id'];

                $stock_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_status_stock_quantity FROM car_status WHERE car_id=$car_id"));
                if (!$stock_check || $stock_check['car_status_stock_quantity'] <= 0) {
                    echo json_encode(['success' => false, 'message' => 'This car is no longer available.']);
                    break;
                }

                mysqli_begin_transaction($conn);
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Booked', booked_at=NOW() WHERE reservation_id=$res_id");
                $pay_exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM payments WHERE reservation_id=$res_id"));
                if (!$pay_exists && $amount > 0) {
                    mysqli_query($conn, "INSERT INTO payments (reservation_id,payment_amount,payment_status,payment_method,payment_date) VALUES ($res_id,$amount,'Paid','$method',NOW())");
                }
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Converted to Booking successfully.']);
                break;

            case 'add_test_drive':
                $user_id = intval($_POST['user_id']);
                $car_id = intval($_POST['car_id']);
                $td_at = mysqli_real_escape_string($conn, $_POST['test_drive_at'] ?? '');
                mysqli_query($conn, "INSERT INTO reservations (user_id,car_id,reservation_status,reservation_created_at,test_drive_at) VALUES ($user_id,$car_id,'Test Drive Pending',NOW(),'$td_at')");
                echo json_encode(['success' => true, 'message' => 'Test Drive scheduled.']);
                break;

            case 'cancel_reservation':
                $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
                mysqli_query($conn, "UPDATE reservations SET reservation_status='Cancelled',reservation_cancel_reason='$reason' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Cancelled.']);
                break;

            case 'upload_licence':
                if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'Upload failed.']);
                    break;
                }
                if (strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                    echo json_encode(['success' => false, 'message' => 'Only PDF allowed.']);
                    break;
                }
                $target_dir = "../../uploads/documents/";
                if (!is_dir($target_dir))
                    mkdir($target_dir, 0777, true);
                $filename = 'licence_res' . $res_id . '_' . time() . '.pdf';
                move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_dir . $filename);
                mysqli_query($conn, "UPDATE reservations SET driving_licence_url='../../uploads/documents/$filename' WHERE reservation_id=$res_id");
                echo json_encode(['success' => true, 'message' => 'Driving licence uploaded.']);
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

// ── GET ───────────────────────────────────────────────────────────────────────
$sub_tab = $_GET['sub_tab'] ?? 'active';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$search_condition = '';
if (!empty($search))
    $search_condition .= " AND (u.user_name LIKE '%$search%' OR c.car_model LIKE '%$search%' OR c.car_brand LIKE '%$search%') ";

if ($sub_tab === 'history') {
    $tab_condition = " AND r.reservation_status = 'Test Drive Done' ";
} else {
    $tab_condition = " AND r.reservation_status = 'Test Drive Pending' ";
}

$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT r.reservation_id) as t FROM reservations r LEFT JOIN users u ON r.user_id=u.user_id LEFT JOIN cars c ON r.car_id=c.car_id WHERE 1=1 $search_condition $tab_condition"))['t'];
$total_pages = ceil($total_rows / $limit);

$query = "
    SELECT r.*, u.user_name, u.user_ic, u.user_phone, u.user_email, u.user_avatar,
           c.car_brand, c.car_model, c.car_year, c.car_origin, c.variant as car_variant,
           (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) as car_image
    FROM reservations r
    LEFT JOIN users u ON r.user_id=u.user_id
    LEFT JOIN cars c ON r.car_id=c.car_id
    WHERE 1=1 $search_condition $tab_condition
    ORDER BY r.reservation_created_at DESC LIMIT $limit OFFSET $offset
";
$result = mysqli_query($conn, $query);

$count_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT reservation_id) as c FROM reservations WHERE reservation_status = 'Test Drive Pending'"))['c'];
$count_history = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT reservation_id) as c FROM reservations WHERE reservation_status = 'Test Drive Done'"))['c'];

$users_query = mysqli_query($conn, "SELECT user_id,user_name,user_ic FROM users WHERE user_role='Customer' ORDER BY user_name");
$cars_query = mysqli_query($conn, "SELECT c.car_id,c.car_brand,c.car_model,cs.car_status_price FROM cars c JOIN car_status cs ON c.car_id=cs.car_id WHERE cs.car_status_stock_quantity>0 AND cs.car_status_status='Active' ORDER BY c.car_brand");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Drives</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .clean-tabs { display: flex; gap: 24px; border-bottom: 1px solid #e5e7eb; margin-bottom: 24px; }
        .clean-tab-item { text-decoration: none; color: #6b7280; font-size: 15px; font-weight: 600; padding-bottom: 12px; border-bottom: 2px solid transparent; transition: all 0.2s ease; }
        .clean-tab-item:hover { color: #111827; }
        .clean-tab-item.active { color: #111827; border-bottom-color: #f59e0b; }
        
        /* Modal 專屬 CSS (很重要，不能刪掉) */
        .modal-overlay { position:fixed; inset:0; background:rgba(17,24,39,.72); z-index:1000; display:flex; justify-content:center; align-items:center; }
        .modal-overlay.hidden { display:none; }
        .modal-container { background:#fff; width:1100px; max-height:92vh; border-radius:14px; box-shadow:0 25px 60px rgba(0,0,0,.22); display:flex; flex-direction:column; }
        .modal-container-sm { width:540px; }
        .modal-header { padding:18px 26px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border-radius:14px 14px 0 0; }
        .modal-header h2 { margin:0; font-size:17px; font-weight:700; color:#111827; }
        .close-btn { background:none; border:none; font-size:18px; cursor:pointer; color:#9ca3af; }
        .modal-body { padding:24px; overflow-y:auto; background:#f3f4f6; flex:1; }
        .modal-footer { padding:14px 24px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:10px; background:#fff; border-radius:0 0 14px 14px; }
        .layout-grid { display:flex; gap:18px; }
        .info-card { flex:1; background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-top:4px solid; }
        .user-card { border-top-color:#3b82f6; }
        .car-card  { border-top-color:#10b981; }
        .section-title { font-size:13px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:8px; padding-bottom:10px; border-bottom:2px solid #eff6ff; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .detail-item label { display:block; font-size:10px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:3px; letter-spacing:.6px; }
        .detail-item p { font-size:13px; color:#111827; font-weight:600; margin:0; }
        .td-panel { background:#fff; border-radius:10px; padding:18px 20px; margin-top:16px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #8b5cf6; }
        .td-panel h3 { margin:0 0 12px; font-size:13px; font-weight:700; color:#6d28d9; display:flex; align-items:center; gap:8px; }
        .bottom-card { background:#fff; border-radius:10px; padding:16px 20px; margin-top:16px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #f59e0b; display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; }
        .btn-modal { padding:9px 18px; border-radius:7px; font-weight:600; cursor:pointer; border:none; font-size:13px; transition:opacity .2s; }
        .btn-modal-cancel  { background:#fff; color:#374151; border:1px solid #d1d5db; }
        .btn-modal-primary { background:#1e3a8a; color:#fff; }
        .btn-modal-green   { background:#10b981; color:#fff; }
        .btn-modal-danger  { background:#ef4444; color:#fff; }
        .btn-modal-sm { padding:5px 12px; font-size:12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#374151; }
        .data-row { cursor: pointer; transition: background 0.15s; }
        .data-row:hover { background: #f9fafb; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">Test Drives</h1>
            </div>
        </header>

        <div class="clean-tabs">
            <a href="test_drives.php?sub_tab=active" class="clean-tab-item <?= $sub_tab == 'active' ? 'active' : '' ?>">Active (<?= $count_active ?>)</a>
            <a href="test_drives.php?sub_tab=history" class="clean-tab-item <?= $sub_tab == 'history' ? 'active' : '' ?>">History (<?= $count_history ?>)</a>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 16px;">
                <input type="hidden" name="sub_tab" value="<?= htmlspecialchars($sub_tab) ?>">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 14px; top: 11px; color: #9ca3af; font-size: 14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search customer, car..." style="padding-left: 38px; width: 280px; font-size: 13px;" value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-modal btn-modal-primary" style="padding:8px 16px;">Search</button>
            </form>
            
            <?php if ($sub_tab === 'active'): ?>
                    <button onclick="openAddTDModal()" class="btn-add-blue"><i class="fas fa-plus"></i> Add Test Drive</button>
            <?php endif; ?>
        </div>

        <div class="table-card" style="padding: 0; border: none;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Order ID</th>
                        <th style="text-align: left;">Customer</th>
                        <th style="text-align: left;">Car</th>
                        <th style="text-align: left;">Test Drive Time</th>
                        <th style="text-align: left;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                        $st = $row['reservation_status'];
                        $dot_color = match ($st) {
                            'Test Drive Done' => '#10b981',
                            default => '#f59e0b'
                        };
                        $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr class="data-row" onclick='openModal(<?= $row_json ?>)'>
                                <td style="color:#6b7280; font-size:13px;">ORD<?= str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div style="font-weight: 500; color: #111827; font-size: 14px;"><?= htmlspecialchars($row['user_name']) ?></div>
                                    <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($row['user_phone']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #111827; font-size: 14px;"><?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?></div>
                                    <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($row['car_year'] . ' | ' . $row['car_origin']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($row['test_drive_at'])): ?>
                                            <span class="td-time-badge" style="background:#ede9fe; color:#6d28d9; font-size:12px; font-weight:700; padding:3px 10px; border-radius:20px;"><i class="fas fa-clock"></i> <?= date('d M Y, g:ia', strtotime($row['test_drive_at'])) ?></span>
                                    <?php else: ?>
                                            <span style="color:#9ca3af; font-size:12px;">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="status-cell">
                                        <span class="dot" style="background:<?= $dot_color ?>; display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px;"></span>
                                        <span style="color:<?= $dot_color ?>; font-weight: 600; font-size: 13px;"><?= $st ?></span>
                                    </div>
                                </td>
                            </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 48px 0; color: #6b7280; font-size: 14px;">No records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-container" style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb;">
                <div class="page-info" style="color: #6b7280; font-size: 13px;">
                    Showing Page <?= $page ?> of <?= ($total_pages > 0) ? $total_pages : 1 ?>
                </div>
                <?php if ($total_pages > 1): ?>
                        <div class="page-controls" style="display: flex; gap: 8px;">
                            <?php $base_url = "?sub_tab=" . urlencode($sub_tab) . "&search=" . urlencode($search); ?>
                            <a href="test_drives.php<?= $base_url ?>&page=<?= max(1, $page - 1) ?>" class="page-btn" style="text-decoration:none;"><i class="fas fa-angle-left"></i> Prev</a>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="test_drives.php<?= $base_url ?>&page=<?= $i ?>" class="page-btn <?= ($page == $i) ? 'active' : '' ?>" style="text-decoration:none;"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="test_drives.php<?= $base_url ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn" style="text-decoration:none;">Next <i class="fas fa-angle-right"></i></a>
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- ==================== 彈出視窗 (MODALS) 區塊 ==================== -->

    <!-- 主資料視窗 -->
    <div id="splitModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-file-alt" style="color:#6366f1;margin-right:8px;"></i>Order Details</h2>
                <button onclick="closeModal()" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="layout-grid">
                    <div class="info-card user-card">
                        <div class="section-title"><i class="fas fa-user-circle"></i> Customer</div>
                        <div class="grid-2">
                            <div class="detail-item"><label>Name</label><p id="detName">-</p></div>
                            <div class="detail-item"><label>Email</label><p id="detEmail" style="word-break:break-all;">-</p></div>
                            <div class="detail-item"><label>IC</label><p id="detIC">-</p></div>
                            <div class="detail-item"><label>Phone</label><p id="detContact">-</p></div>
                        </div>
                        <hr style="border:0;border-top:1px dashed #e5e7eb;margin:14px 0;">
                        <div class="detail-item">
                            <label>Driving Licence (PDF)</label>
                            <div style="display:flex;align-items:center;gap:12px;margin-top:4px;">
                                <button type="button" id="btnViewLicence" onclick="togglePdf('frameLicence')" style="background:none;border:none;color:#3b82f6;font-size:12px;font-weight:600;cursor:pointer;display:none;"><i class="fas fa-eye"></i> View</button>
                                <label class="upload-btn-label" id="lblLicenceUpload" style="cursor:pointer;color:#3b82f6;font-size:12px;font-weight:600;">
                                    <i class="fas fa-upload"></i> Upload
                                    <input type="file" accept=".pdf" style="display:none;" onchange="uploadLicence(this)">
                                </label>
                            </div>
                            <iframe id="frameLicence" src="" style="width:100%;height:360px;display:none;border:1px solid #d1d5db;border-radius:6px;margin-top:8px;"></iframe>
                        </div>
                    </div>

                    <div class="info-card car-card">
                        <div class="section-title"><i class="fas fa-car"></i> Vehicle</div>
                        <div style="display:flex;gap:12px;">
                            <img id="detCarImage" src="" alt="Car" style="width:110px;height:80px;object-fit:cover;border-radius:8px;background:#e2e8f0;border:1px solid #e5e7eb;flex-shrink:0;">
                            <div class="grid-2" style="flex:1;">
                                <div class="detail-item"><label>Brand</label><p id="detCarBrand">-</p></div>
                                <div class="detail-item"><label>Model</label><p id="detCarModel">-</p></div>
                                <div class="detail-item"><label>Variant</label><p id="detCarVariant">-</p></div>
                                <div class="detail-item"><label>Colour</label><p id="detCarColor">-</p></div>
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top:12px;">
                            <div class="detail-item"><label>Year</label><p id="detCarYear">-</p></div>
                        </div>
                    </div>
                </div>

                <div id="tdPanel" class="td-panel" style="display:none;">
                    <h3><i class="fas fa-car-side"></i> Test Drive</h3>
                    <div class="grid-2">
                        <div class="detail-item"><label>Scheduled At</label><p id="detTDAt">-</p></div>
                        <div class="detail-item"><label>Completed At</label><p id="detTDDone">-</p></div>
                    </div>
                    <div id="tdScheduleWrap" style="margin-top:12px;display:none;">
                        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Set / Update Test Drive Time</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="datetime-local" id="tdDateTime" class="form-control" style="width:240px;">
                            <button class="btn-modal btn-modal-primary btn-modal-sm" onclick="scheduleTestDrive()"><i class="fas fa-calendar-check"></i> Save</button>
                        </div>
                    </div>
                </div>

                <div class="bottom-card">
                    <div><label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Order ID</label>
                        <div id="detResID" style="font-family:monospace;font-size:20px;font-weight:800;color:#d97706;">ORD000</div></div>
                    <div><label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Status</label>
                        <div id="detStatus" style="font-size:15px;font-weight:700;color:#111827;">-</div></div>
                    <div><label style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Created</label>
                        <div id="detCreatedAt" style="font-size:14px;font-weight:600;color:#374151;">-</div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn-modal btn-modal-cancel">Close</button>
                <button id="btnMarkTDDone" class="btn-modal btn-modal-green" onclick="markTestDriveDone()" style="display:none;"><i class="fas fa-check"></i> Mark Test Drive Done</button>
                <button id="btnConvertBook" class="btn-modal btn-modal-primary" onclick="openConvertModal()" style="display:none;"><i class="fas fa-arrow-right"></i> Convert to Booking</button>
                <button id="btnCancelRes" class="btn-modal btn-modal-danger" onclick="cancelReservation()" style="display:none;"><i class="fas fa-ban"></i> Cancel</button>
            </div>
        </div>
    </div>

    <!-- 轉 Booking 視窗 -->
    <div id="convertModal" class="modal-overlay hidden">
        <div class="modal-container modal-container-sm">
            <div class="modal-header">
                <h2><i class="fas fa-receipt" style="color:#10b981;margin-right:8px;"></i>Convert to Booking</h2>
                <button onclick="document.getElementById('convertModal').classList.add('hidden')" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="background:#fff;">
                <div class="form-group"><label>Booking Fee Amount (RM)</label><input type="number" step="0.01" min="0" id="convertAmount" class="form-control" placeholder="e.g. 500.00"></div>
                <div class="form-group"><label>Payment Method</label>
                    <select id="convertMethod" class="form-control">
                        <option value="Online Transfer">Online Transfer</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="document.getElementById('convertModal').classList.add('hidden')" class="btn-modal btn-modal-cancel">Cancel</button>
                <button onclick="confirmConvert()" class="btn-modal btn-modal-primary"><i class="fas fa-check"></i> Confirm Booking</button>
            </div>
        </div>
    </div>

    <!-- 新增 Test Drive 視窗 -->
    <div id="addTDModal" class="modal-overlay hidden">
        <div class="modal-container modal-container-sm">
            <div class="modal-header">
                <h2><i class="fas fa-car" style="color:#8b5cf6;margin-right:8px;"></i>Schedule Test Drive</h2>
                <button onclick="document.getElementById('addTDModal').classList.add('hidden')" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addTDForm">
                <div class="modal-body" style="background:#fff;">
                    <div class="form-group"><label>Select Customer</label>
                        <select name="user_id" class="form-control" required><option value="">-- Choose Customer --</option>
                            <?php mysqli_data_seek($users_query, 0);
                            while ($u = mysqli_fetch_assoc($users_query)): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['user_name'] . ' (' . $u['user_ic'] . ')') ?></option>
                            <?php endwhile; ?>
                        </select></div>
                    <div class="form-group"><label>Select Car</label>
                        <select name="car_id" class="form-control" required><option value="">-- Choose Car --</option>
                            <?php mysqli_data_seek($cars_query, 0);
                            while ($c = mysqli_fetch_assoc($cars_query)): ?>
                                <option value="<?= $c['car_id'] ?>"><?= htmlspecialchars($c['car_brand'] . ' ' . $c['car_model'] . ' — RM ' . number_format($c['car_status_price'], 2)) ?></option>
                            <?php endwhile; ?>
                        </select></div>
                    <div class="form-group"><label>Test Drive Date & Time</label><input type="datetime-local" name="test_drive_at" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('addTDModal').classList.add('hidden')" class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-add-blue" style="border:none;"><i class="fas fa-calendar-check"></i> Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JAVA SCRIPT/test_drives.js?v=<?= time() ?>"></script>
</body>
</html>