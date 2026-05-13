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
        if (strtotime($td_at) < time()) {
          echo json_encode(['success' => false, 'message' => 'Test drive time cannot be in the past.']);
          break;
        }
        mysqli_query($conn, "UPDATE reservations SET test_drive_at='$td_at' WHERE reservation_id=$res_id");
        echo json_encode(['success' => true, 'message' => 'Test drive scheduled.']);
        break;

      case 'mark_test_drive_done':
        $lic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT driving_licence_url FROM reservations WHERE reservation_id=$res_id"));
        if (empty($lic['driving_licence_url'])) {
          echo json_encode(['success' => false, 'message' => 'Driving Licence must be uploaded.']);
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
        if (strtotime($td_at) < time()) {
          echo json_encode(['success' => false, 'message' => 'Test drive time cannot be in the past.']);
          break;
        }
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

$sub_tab = $_GET['sub_tab'] ?? 'active';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search))
  $search_condition .= " AND (u.user_name LIKE '%$search%' OR c.car_model LIKE '%$search%' OR c.car_brand LIKE '%$search%') ";

$tab_condition = ($sub_tab === 'history') ? " AND r.reservation_status = 'Test Drive Done' " : " AND r.reservation_status = 'Test Drive Pending' ";

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
    .layout-grid {
      display: flex;
      gap: 18px;
      margin-bottom: 20px;
    }

    .info-card {
      flex: 1;
      background: #f9fafb;
      border-radius: 8px;
      padding: 16px;
      border: 1px solid #e5e7eb;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 10px;
    }

    .detail-item label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      margin-bottom: 4px;
    }

    .detail-item p {
      font-size: 13px;
      color: #111827;
      font-weight: 600;
      margin: 0;
    }

    .data-row {
      cursor: pointer;
      transition: background 0.15s;
    }

    .data-row:hover {
      background: #f9fafb;
    }
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

    <div class="page-tabs">
      <a href="test_drives.php?sub_tab=active" class="tab-item <?= $sub_tab == 'active' ? 'active' : '' ?>">Active
        (<?= $count_active ?>)</a>
      <a href="test_drives.php?sub_tab=history" class="tab-item <?= $sub_tab == 'history' ? 'active' : '' ?>">History
        (<?= $count_history ?>)</a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <form method="GET" style="display: flex; gap: 16px;">
        <input type="hidden" name="sub_tab" value="<?= htmlspecialchars($sub_tab) ?>">
        <div style="position: relative;">
          <i class="fas fa-search"
            style="position: absolute; left: 14px; top: 11px; color: #9ca3af; font-size: 14px;"></i>
          <input type="text" name="search" class="form-control" placeholder="Search customer, car..."
            style="padding-left: 38px; width: 280px; font-size: 13px;" value="<?= htmlspecialchars($search) ?>">
        </div>
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
              $dot_class = ($st == 'Test Drive Done') ? 'dot-active' : 'dot-inactive';
              $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
              ?>
              <tr class="data-row" onclick='openModal(<?= $row_json ?>)'>
                <td style="text-align: left; color: #6b7280; font-weight: 500;">
                  ORD<?= str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div style="font-weight: 500; color: #111827; font-size: 14px;"><?= htmlspecialchars($row['user_name']) ?>
                  </div>
                  <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($row['user_phone']) ?></div>
                </td>
                <td>
                  <div style="font-weight: 500; color: #111827; font-size: 14px;">
                    <?= htmlspecialchars($row['car_brand'] . ' ' . $row['car_model']) ?></div>
                  <div style="font-size: 12px; color: #6b7280;">
                    <?= htmlspecialchars($row['car_year'] . ' | ' . $row['car_origin']) ?></div>
                </td>
                <td style="text-align: left; color: #4b5563; font-weight: 500;">
                  <?php if (!empty($row['test_drive_at'])): ?>
                    <?= date('d M Y, g:ia', strtotime($row['test_drive_at'])) ?>
                  <?php else: ?>
                    <span style="color:#9ca3af;">Not scheduled</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: left;">
                  <div class="status-cell">
                    <span class="dot <?= $dot_class ?>"></span>
                    <span style="font-weight: 500; color: #374151;"><?= $st ?></span>
                  </div>
                </td>
              </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 48px 0; color: #6b7280; font-size: 14px;">No records
                found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="pagination-container"
        style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb;">
        <div class="page-info" style="color: #6b7280; font-size: 13px;">
          Showing Page <?= $page ?> of <?= ($total_pages > 0) ? $total_pages : 1 ?>
        </div>
        <?php if ($total_pages > 1): ?>
          <div class="page-controls" style="display: flex; gap: 8px;">
            <?php $base_url = "?sub_tab=" . urlencode($sub_tab) . "&search=" . urlencode($search); ?>
            <a href="test_drives.php<?= $base_url ?>&page=<?= max(1, $page - 1) ?>" class="page-btn"
              style="text-decoration:none;"><i class="fas fa-angle-left"></i> Prev</a>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="test_drives.php<?= $base_url ?>&page=<?= $i ?>" class="page-btn <?= ($page == $i) ? 'active' : '' ?>"
                style="text-decoration:none;"><?= $i ?></a>
            <?php endfor; ?>
            <a href="test_drives.php<?= $base_url ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn"
              style="text-decoration:none;">Next <i class="fas fa-angle-right"></i></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- ==================== users.php 風格的 Modal ==================== -->

  <!-- 1. 詳細資料 Modal -->
  <div id="splitModal" class="modal">
    <div class="modal-content" style="width: 800px;">
      <h2 id="modalTitle" style="font-size: 20px; margin-bottom: 20px;"><i class="fas fa-file-alt"
          style="margin-right:8px; color: #3b82f6;"></i> Order Details</h2>

      <div class="layout-grid">
        <div class="info-card">
          <h3 style="font-size:14px; margin-bottom:10px; color:#111827;"><i class="fas fa-user-circle"></i> Customer
          </h3>
          <div class="grid-2">
            <div class="detail-item"><label>Name</label>
              <p id="detName">-</p>
            </div>
            <div class="detail-item"><label>Phone</label>
              <p id="detContact">-</p>
            </div>
            <div class="detail-item"><label>Email</label>
              <p id="detEmail">-</p>
            </div>
            <div class="detail-item"><label>IC</label>
              <p id="detIC">-</p>
            </div>
          </div>
          <div class="detail-item" style="margin-top: 15px;">
            <label>Driving Licence (PDF)</label>
            <div style="display:flex; align-items:center; gap:12px; margin-top:5px;">
              <button type="button" id="btnViewLicence" onclick="togglePdf('frameLicence')" class="btn-export"
                style="padding: 6px 12px; display:none;"><i class="fas fa-eye"></i> View</button>
              <label class="btn-export" id="lblLicenceUpload" style="padding: 6px 12px; cursor:pointer; margin:0;">
                <span id="lblLicenceText"><i class="fas fa-upload"></i> Upload</span>
                <input type="file" accept=".pdf" style="display:none;" onchange="uploadLicence(this)">
              </label>
            </div>
            <iframe id="frameLicence" src=""
              style="width:100%; height:250px; display:none; border:1px solid #d1d5db; border-radius:6px; margin-top:10px;"></iframe>
          </div>
        </div>

        <div class="info-card">
          <h3 style="font-size:14px; margin-bottom:10px; color:#111827;"><i class="fas fa-car"></i> Vehicle</h3>
          <div style="display:flex; gap:12px; margin-bottom: 15px;">
            <img id="detCarImage" src="" alt="Car"
              style="width:100px; height:70px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">
            <div style="flex:1;">
              <p id="detCarBrand" style="font-weight:700; color:#111827; margin:0;">-</p>
              <p id="detCarModel" style="color:#6b7280; font-size:13px; margin:0;">-</p>
            </div>
          </div>
          <div class="grid-2">
            <div class="detail-item"><label>Variant</label>
              <p id="detCarVariant">-</p>
            </div>
            <div class="detail-item"><label>Colour</label>
              <p id="detCarColor">-</p>
            </div>
            <div class="detail-item"><label>Year</label>
              <p id="detCarYear">-</p>
            </div>
          </div>
        </div>
      </div>

      <div class="info-card" style="margin-bottom: 20px;">
        <h3 style="font-size:14px; margin-bottom:10px; color:#111827;"><i class="fas fa-calendar-alt"></i> Test Drive
          Status</h3>
        <div class="grid-2">
          <div class="detail-item"><label>Scheduled At</label>
            <p id="detTDAt" style="color:#3b82f6;">-</p>
          </div>
          <div class="detail-item"><label>Completed At</label>
            <p id="detTDDone">-</p>
          </div>
        </div>
        <div id="tdScheduleWrap" style="margin-top:15px; display:none;">
          <label style="font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:6px;">Update
            Time</label>
          <div style="display:flex; gap:8px;">
            <input type="datetime-local" id="tdDateTime" class="form-control" style="width: 250px;">
            <button type="button" class="btn-add-blue" onclick="scheduleTestDrive()">Save</button>
          </div>
        </div>
      </div>

      <div
        style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <button type="button" class="btn-export" onclick="closeModal()">Close</button>
        <button type="button" id="btnCancelRes" class="btn-export" onclick="cancelReservation()"
          style="color: #ef4444; border-color: #ef4444; display:none;">Cancel Booking</button>
        <button type="button" id="btnMarkTDDone" class="btn-add-blue" onclick="markTestDriveDone()"
          style="display:none; background-color: #10b981;">Mark Done</button>
        <button type="button" id="btnConvertBook" class="btn-add-blue" onclick="openConvertModal()"
          style="display:none;">Convert to Booking</button>
      </div>
    </div>
  </div>

  <!-- 2. 新增 Test Drive Modal -->
  <div id="addTDModal" class="modal">
    <div class="modal-content" style="width: 500px;">
      <h2 style="font-size: 20px; margin-bottom: 20px;">Add Test Drive</h2>
      <form id="addTDForm">
        <div class="form-group">
          <label>Select Customer</label>
          <select name="user_id" class="form-control" required>
            <option value="">-- Choose Customer --</option>
            <?php mysqli_data_seek($users_query, 0);
            while ($u = mysqli_fetch_assoc($users_query)): ?>
              <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['user_name'] . ' (' . $u['user_ic'] . ')') ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Select Car</label>
          <select name="car_id" class="form-control" required>
            <option value="">-- Choose Car --</option>
            <?php mysqli_data_seek($cars_query, 0);
            while ($c = mysqli_fetch_assoc($cars_query)): ?>
              <option value="<?= $c['car_id'] ?>"><?= htmlspecialchars($c['car_brand'] . ' ' . $c['car_model']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date & Time</label>
          <input type="datetime-local" name="test_drive_at" class="form-control" required>
        </div>
        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
          <button type="button" class="btn-export"
            onclick="document.getElementById('addTDModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn-add-blue">Schedule</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 3. Convert to Booking Modal -->
  <div id="convertModal" class="modal">
    <div class="modal-content" style="width: 400px;">
      <h2 style="font-size: 20px; margin-bottom: 20px;">Convert to Booking</h2>
      <div class="form-group">
        <label>Booking Fee Amount (RM)</label>
        <input type="number" step="0.01" min="0" id="convertAmount" class="form-control" placeholder="e.g. 500.00">
      </div>
      <div class="form-group">
        <label>Payment Method</label>
        <select id="convertMethod" class="form-control">
          <option value="Online Transfer">Online Transfer</option>
          <option value="Credit Card">Credit Card</option>
          <option value="Cash">Cash</option>
        </select>
      </div>
      <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
        <button type="button" class="btn-export"
          onclick="document.getElementById('convertModal').style.display='none'">Cancel</button>
        <button type="button" class="btn-add-blue" onclick="confirmConvert()">Confirm Booking</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../JAVA SCRIPT/test_drives.js?v=<?= time() ?>"></script>
</body>

</html>